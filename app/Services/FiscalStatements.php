<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FiscalYear;
use App\Models\SupplierInvoice;
use App\Support\Document\Colonne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * États de travail de l'arrêté, exprimés en centimes et justifiés par compte.
 */
class FiscalStatements
{
    public const REGIMES = ['normal' => 'Système normal', 'smt' => 'Système minimal de trésorerie'];

    public function __construct(private readonly LedgerBooks $books)
    {
    }

    public function build(FiscalYear $year, string $regime, string $notes = '', array $flowMap = []): array
    {
        $from = $year->starts_on;
        $to = $year->ends_on;
        $previous = FiscalYear::query()->whereDate('ends_on', $from->copy()->subDay())->first();
        $currentRows = $this->books->balance($from, $to)->keyBy('code');
        $previousRows = $previous ? $this->books->balance($previous->starts_on, $previous->ends_on)->keyBy('code') : collect();
        $rows = $currentRows->keys()->merge($previousRows->keys())->unique()->sort(SORT_STRING)
            ->map(function ($code) use ($currentRows, $previousRows, $previous) {
                $row = $currentRows->get($code) ?? $previousRows->get($code);
                return [
                    'code' => $code, 'label' => $row['label'], 'class' => $row['class'],
                    'current' => $currentRows->get($code)['closing'] ?? 0,
                    'previous' => $previous ? ($previousRows->get($code)['closing'] ?? 0) : null,
                ];
            })->values();
        $management = $rows->whereIn('class', [6, 7, 8])->values();
        $result = -$management->sum('current');
        $balance = $this->balanceSheet($rows->whereBetween('class', [1, 5]));
        $balance->push([
            'code' => 'RN', 'label' => 'Résultat net de l’exercice',
            'assets' => 0, 'liabilities' => $result,
            'previous_assets' => $previous ? 0 : null,
            'previous_liabilities' => $previous ? -$management->sum('previous') : null,
        ]);

        $flows = $this->cashFlows($from, $to, $flowMap);
        $documents = [
            $this->books->document('Bilan par compte', $from, $to)->colonnes([
                Colonne::texte('code', 'Compte'), Colonne::texte('label', 'Intitulé'),
                Colonne::montant('assets', 'Actif N'), Colonne::montant('liabilities', 'Passif N'),
                Colonne::montant('previous_assets', 'Actif N-1'), Colonne::montant('previous_liabilities', 'Passif N-1'),
            ])->lignes($balance)->totaux([
                'assets' => $balance->sum('assets'), 'liabilities' => $balance->sum('liabilities'),
                'previous_assets' => $previous ? $balance->sum('previous_assets') : null,
                'previous_liabilities' => $previous ? $balance->sum('previous_liabilities') : null,
            ]),
            $this->books->document('Compte de résultat par compte', $from, $to)->colonnes([
                Colonne::texte('code', 'Compte'), Colonne::texte('label', 'Intitulé'),
                Colonne::montant('current', 'Contribution N'), Colonne::montant('previous', 'Contribution N-1'),
            ])->lignes($management->map(fn ($row) => array_replace($row, [
                'current' => -$row['current'], 'previous' => $row['previous'] === null ? null : -$row['previous'],
            ])))->totaux(['current' => $result, 'previous' => $previous ? -$management->sum('previous') : null]),
            $this->books->document('Livre d’inventaire', $from, $to)->colonnes([
                Colonne::texte('code', 'Compte'), Colonne::texte('label', 'Élément de patrimoine'),
                Colonne::montant('assets', 'Actif à la clôture'), Colonne::montant('liabilities', 'Passif à la clôture'),
            ])->lignes($balance)
                ->note('Soldes du grand livre à justifier par les pièces et relevés d’inventaire de l’exercice.'),
            $this->books->document('Notes annexes', $from, $to)->colonnes([
                Colonne::texte('label', 'Rubrique'), Colonne::texte('text', 'Information'),
            ])->lignes([
                ['label' => 'Référentiel déclaré', 'text' => self::REGIMES[$regime]],
                ['label' => 'Comparatif', 'text' => $previous?->label ?? 'Exercice précédent indisponible'],
                ['label' => 'Méthodes, engagements et justificatifs', 'text' => $notes],
            ]),
        ];
        if ($regime === 'normal') {
            $documents[] = $this->books->document('Flux de trésorerie', $from, $to)->colonnes([
                Colonne::texte('reference', 'Écriture'), Colonne::texte('type', 'Rubrique'),
                Colonne::montant('net', 'Flux net'),
            ])->lignes($flows)->totaux(['net' => $flows->sum('net')])
                ->note('Les à-nouveaux sont exclus des flux. Les écritures sans rubrique renseignée restent visibles à contrôler.');
        }

        return $documents;
    }

    private function balanceSheet(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            [$assets, $liabilities] = $this->allocation($row, $row['current']);
            [$previousAssets, $previousLiabilities] = $row['previous'] === null ? [null, null]
                : $this->allocation($row, $row['previous']);

            return $row + ['assets' => $assets, 'liabilities' => $liabilities,
                'previous_assets' => $previousAssets, 'previous_liabilities' => $previousLiabilities];
        })->values();
    }

    private function allocation(array $row, int $signed): array
    {
        return match ($row['class']) {
            1 => [0, -$signed],
            2, 3 => [$signed, 0],
            default => [max(0, $signed), max(0, -$signed)],
        };
    }

    private function cashFlows(Carbon $from, Carbon $to, array $map): Collection
    {
        return $this->books->entries($from, $to)->with('lines')->orderBy('entry_date')->orderBy('id')->get()
            ->reject(fn ($entry) => $entry->schema === 'opening_balance')
            ->map(function ($entry) use ($map) {
                [$treasury, $others] = $entry->lines->partition(fn ($line) => preg_match('/^(52|53|57)/', $line->account_code));
                $movement = $treasury->sum(fn ($line) => $line->signedAmount());
                $classes = $others->map(fn ($line) => (int) $line->account_code[0])->unique();
                $category = match (true) {
                    $entry->source_type === FixedAsset::class => 'Investissement',
                    $entry->source_type === SupplierInvoice::class => 'À ventiler',
                    $classes->isEmpty() => 'À ventiler',
                    $classes->every(fn ($class) => $class === 1) => 'Financement',
                    $classes->every(fn ($class) => $class === 2) => 'Investissement',
                    $classes->every(fn ($class) => in_array($class, [4, 6, 7], true)) => 'Exploitation',
                    default => 'À ventiler',
                };
                return ['reference' => $entry->number(), 'net' => $movement,
                    'type' => $map[$entry->number()] ?? $category];
            })->where('net', '!=', 0)->values();
    }
}
