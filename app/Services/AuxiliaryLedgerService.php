<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntryLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lecture de la comptabilité auxiliaire : qui doit quoi, et depuis quand.
 *
 * Le compte reste collectif — 411000 pour tous les clients — et c'est
 * l'auxiliaire porté par la ligne qui distingue les tiers. Ce service ne fait
 * que lire : il ne lettre rien et n'écrit aucune écriture.
 *
 * Montants en centimes FCFA.
 */
class AuxiliaryLedgerService
{
    /** Tranches d'ancienneté de la balance âgée, en jours. */
    public const BUCKETS = [
        'current' => 'Non échu (0-30 j)',
        'd30'     => '31 à 60 jours',
        'd60'     => '61 à 90 jours',
        'd90'     => 'Plus de 90 jours',
    ];

    /**
     * Soldes par tiers sur un compte collectif.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function balances(string $accountCode, bool $openOnly = true): Collection
    {
        $lignes = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->whereNotNull('auxiliary_type')
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at'))
            ->when($openOnly, fn ($q) => $q->whereNull('reconciliation_code'))
            ->with('entry')
            ->get();

        return $lignes
            ->groupBy(fn ($l) => $l->auxiliary_type . '#' . $l->auxiliary_id)
            ->map(function ($groupe) {
                $premiere = $groupe->first();
                $debit    = (int) $groupe->sum('debit');
                $credit   = (int) $groupe->sum('credit');

                return [
                    'auxiliary_type' => $premiere->auxiliary_type,
                    'auxiliary_id'   => (int) $premiere->auxiliary_id,
                    'auxiliary'      => $premiere->auxiliary,
                    'label'          => $this->nomTiers($premiere),
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'balance'        => $debit - $credit,
                    'lines'          => $groupe->count(),
                    'oldest'         => $groupe->min(fn ($l) => $l->entry?->entry_date),
                ];
            })
            // En vue « postes ouverts », un tiers soldé n'a rien à montrer.
            ->when($openOnly, fn ($tiers) => $tiers->filter(fn ($t) => $t['balance'] !== 0))
            ->sortByDesc(fn ($t) => abs($t['balance']))
            ->values();
    }

    /**
     * Balance âgée : ce qui reste dû, ventilé par ancienneté.
     *
     * Seules les lignes non lettrées entrent dans le calcul — une créance
     * réglée n'a pas d'âge. C'est précisément ce que la comptabilité de
     * caisse actuelle ne sait pas dire.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function agedBalance(string $accountCode, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();

        $lignes = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->whereNotNull('auxiliary_type')
            ->whereNull('reconciliation_code')
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at'))
            ->with('entry')
            ->get();

        $rows = $lignes
            ->groupBy(fn ($l) => $l->auxiliary_type . '#' . $l->auxiliary_id)
            ->map(function ($groupe) use ($asOf) {
                $premiere = $groupe->first();
                $tranches = array_fill_keys(array_keys(self::BUCKETS), 0);

                foreach ($groupe as $ligne) {
                    $montant = $ligne->debit - $ligne->credit;
                    $date = $ligne->entry?->entry_date;
                    $age = $date ? $date->diffInDays($asOf, false) : 0;

                    $tranches[$this->tranche($age)] += $montant;
                }

                return [
                    'auxiliary' => $premiere->auxiliary,
                    'label'     => $this->nomTiers($premiere),
                    'buckets'   => $tranches,
                    'total'     => array_sum($tranches),
                ];
            })
            // Un tiers à zéro n'a rien à faire dans un suivi d'impayés.
            ->filter(fn ($t) => $t['total'] !== 0)
            ->sortByDesc('total')
            ->values();

        $totals = array_fill_keys(array_keys(self::BUCKETS), 0);

        foreach ($rows as $row) {
            foreach ($row['buckets'] as $cle => $montant) {
                $totals[$cle] += $montant;
            }
        }

        $totals['total'] = array_sum($totals);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Grand livre d'un tiers : le détail de son compte, avec solde progressif
     * et état de lettrage.
     *
     * @return array{lines: Collection<int, array<string, mixed>>, debit: int, credit: int, balance: int, open: int}
     */
    public function ledger(string $accountCode, string $auxiliaryType, int $auxiliaryId): array
    {
        $lignes = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->where('auxiliary_type', $auxiliaryType)
            ->where('auxiliary_id', $auxiliaryId)
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at'))
            ->with('entry.journal')
            ->get()
            ->sortBy(fn ($l) => [$l->entry->entry_date->timestamp, $l->id])
            ->values();

        $cumul = 0;

        $rows = $lignes->map(function ($ligne) use (&$cumul) {
            $cumul += $ligne->signedAmount();

            return [
                'id'         => $ligne->id,
                'date'       => $ligne->entry->entry_date,
                'journal'    => $ligne->entry->journal?->code,
                'label'      => $ligne->label ?: $ligne->entry->label,
                'reference'  => $ligne->entry->reference,
                'debit'      => $ligne->debit,
                'credit'     => $ligne->credit,
                'balance'    => $cumul,
                'lettre'     => $ligne->reconciliation_code,
                'entry_id'   => $ligne->entry->id,
            ];
        });

        $ouvert = (int) $lignes->whereNull('reconciliation_code')->sum(fn ($l) => $l->signedAmount());

        return [
            'lines'   => $rows,
            'debit'   => (int) $lignes->sum('debit'),
            'credit'  => (int) $lignes->sum('credit'),
            'balance' => $cumul,
            'open'    => $ouvert,
        ];
    }

    /** Comptes collectifs disponibles. */
    public function collectiveAccounts(): Collection
    {
        return Account::query()->where('is_collective', true)->orderBy('code')->get();
    }

    /** Nom d'un tiers, résolu sur une seule ligne plutôt que sur tout le compte. */
    public function label(string $accountCode, string $auxiliaryType, int $auxiliaryId): string
    {
        $ligne = JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->where('auxiliary_type', $auxiliaryType)
            ->where('auxiliary_id', $auxiliaryId)
            ->with('auxiliary')
            ->first();

        return $ligne ? $this->nomTiers($ligne) : 'Tiers inconnu';
    }

    /** Nom lisible du tiers, quel que soit son modèle. */
    private function nomTiers(JournalEntryLine $ligne): string
    {
        $tiers = $ligne->auxiliary;

        if ($tiers === null) {
            return 'Tiers supprimé #' . $ligne->auxiliary_id;
        }

        return $tiers->full_name
            ?? $tiers->name
            ?? (class_basename($ligne->auxiliary_type) . ' #' . $ligne->auxiliary_id);
    }

    /** Tranche d'ancienneté correspondant à un âge en jours. */
    private function tranche(float $ageEnJours): string
    {
        return match (true) {
            $ageEnJours <= 30 => 'current',
            $ageEnJours <= 60 => 'd30',
            $ageEnJours <= 90 => 'd60',
            default           => 'd90',
        };
    }
}
