<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\NightAudit;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * L'écrivain unique du grand livre.
 *
 * Toute écriture comptable passe par ici. C'est la seule façon de garantir
 * quatre invariants dont dépend la validité de toute la comptabilité :
 *
 *  - **L'équilibre.** Une écriture dont la somme des débits diffère de la
 *    somme des crédits est refusée. Il n'existe aucun chemin pour en insérer
 *    une déséquilibrée.
 *
 *  - **L'irréversibilité.** Une période verrouillée n'accepte plus rien
 *    (Article 22 de l'Acte Uniforme). La correction passe par contre-passation.
 *
 *  - **L'idempotence.** Le couple (source, schéma) est unique : un check-out
 *    rejoué ou un import relancé retrouve son écriture au lieu d'en créer une
 *    seconde. Sans cela, une double génération serait indétectable.
 *
 *  - **L'intégrité du plan.** On n'impute que sur un compte existant et
 *    imputable — jamais sur un compte de regroupement.
 *
 * Montants en centimes FCFA.
 */
class LedgerService
{
    /**
     * Enregistre une écriture équilibrée.
     *
     * Chaque ligne : ['account' => '411000', 'debit' => 1000, 'label' => …,
     * 'auxiliary' => $customer, 'center' => 'hebergement'].
     * Un montant se porte soit au débit, soit au crédit, jamais aux deux.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Model|null  $source   Opération à l'origine de l'écriture.
     * @param  string|null  $schema  Nom du schéma d'imputation appliqué.
     */
    public function post(
        string $journalCode,
        CarbonInterface $date,
        string $label,
        array $lines,
        ?Model $source = null,
        ?string $schema = null,
        ?string $reference = null,
        bool $autoPost = true,
        ?CarbonInterface $valueDate = null,
    ): JournalEntry {
        if ($valueDate && $valueDate->toDateString() > $date->toDateString()) {
            throw new RuntimeException('La date d’origine ne peut pas suivre la date comptable.');
        }
        // Idempotence : on rend l'écriture existante plutôt que d'en créer
        // une seconde. Vérifié avant tout travail, y compris en cas de rejeu.
        if ($source !== null && $schema !== null) {
            $existing = $this->findBySource($source, $schema);

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($journalCode, $date, $valueDate, $label, $lines, $source, $schema,
            $reference, $autoPost) {
            $period = $this->lockForDate($date);
            if ($source !== null && $schema !== null && ($existing = $this->findBySource($source, $schema))) {
                return $existing;
            }
            $this->requireOpenDate($period, $date);

            $journal = Journal::byCode($journalCode)
                ?? throw new RuntimeException("Journal « {$journalCode} » introuvable.");
            $normalized = $this->normalizeLines($lines);

            $attributes = [
                'journal_id'       => $journal->id,
                'fiscal_period_id' => $period->id,
                'entry_date'       => $date->toDateString(),
                'value_date'       => $valueDate?->toDateString(),
                'reference'        => $reference,
                'label'            => $label,
                'source_type'      => $source ? $source::class : null,
                'source_id'        => $source?->getKey(),
                'schema'           => $schema,
                'created_by'       => Auth::id(),
            ];
            $entry = $source !== null && $schema !== null
                ? JournalEntry::query()->createOrFirst([
                    'source_type' => $source::class, 'source_id' => $source->getKey(), 'schema' => $schema,
                ], $attributes)
                : JournalEntry::create($attributes);
            if (!$entry->wasRecentlyCreated) {
                return $entry;
            }

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            if ($autoPost) {
                $entry->update(['posted_at' => now(), 'validated_by' => Auth::id()]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Contre-passe une écriture : seule correction possible après validation.
     *
     * L'extourne est datée du jour de la correction, jamais de l'écriture
     * d'origine — antidater rouvrirait une période close.
     */
    public function reverse(JournalEntry $entry, ?CarbonInterface $date = null, ?string $reason = null): JournalEntry
    {
        $date ??= now();

        return DB::transaction(function () use ($entry, $date, $reason) {
            $period = $this->lockForDate($date);
            $this->requireOpenDate($period, $date);
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (!$entry->isPosted()) {
                throw new RuntimeException('Seule une écriture validée peut être contre-passée.');
            }
            if ($entry->isReversed() || JournalEntry::query()->where('reverses_id', $entry->id)->exists()) {
                throw new RuntimeException('Cette écriture a déjà été contre-passée.');
            }
            if ($entry->isReversal()) {
                throw new RuntimeException("Une contre-passation ne s'extourne pas à son tour.");
            }
            if ($date->toDateString() < $entry->entry_date->toDateString()) {
                throw new RuntimeException("La contre-passation doit suivre la date de l'écriture d'origine.");
            }
            $description = 'Extourne — ' . ($reason ? "{$reason} — " : '') . $entry->label;

            $reversal = JournalEntry::create([
                'journal_id'       => $entry->journal_id,
                'fiscal_period_id' => $period->id,
                'entry_date'       => $date->toDateString(),
                'value_date'       => ($entry->value_date ?? $entry->entry_date)->toDateString(),
                'reference'        => $entry->reference,
                'label'            => mb_substr($description, 0, 255),
                'reverses_id'      => $entry->id,
                'created_by'       => Auth::id(),
            ]);

            // Débits et crédits permutés : la somme des deux écritures est nulle.
            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_code'    => $line->account_code,
                    'label'           => $line->label,
                    'debit'           => $line->credit,
                    'credit'          => $line->debit,
                    'auxiliary_type'  => $line->auxiliary_type,
                    'auxiliary_id'    => $line->auxiliary_id,
                    'analytic_center' => $line->analytic_center,
                ]);
            }

            $reversal->update(['posted_at' => now(), 'validated_by' => Auth::id()]);
            $entry->update(['reversed_by_id' => $reversal->id]);

            return $reversal->load('lines');
        });
    }

    /** Écriture déjà produite pour cette opération et ce schéma, s'il y en a une. */
    public function findBySource(Model $source, string $schema): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->where('schema', $schema)
            ->first();
    }

    /**
     * Verrouille une période. Irréversible : c'est tout l'objet de l'Article 22.
     */
    public function lockPeriod(FiscalPeriod $period): FiscalPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (!$period->isLocked()) {
                if ($period->entries()->whereNull('posted_at')->exists()) {
                    throw new RuntimeException('La période contient des écritures à valider ou à retirer.');
                }
                $period->update(['locked_at' => now(), 'locked_by' => Auth::id()]);
            }

            return $period;
        });
    }

    /** Verrou commun aux écritures et à la clôture, à prendre dans une transaction. */
    public function lockForDate(CarbonInterface $date): FiscalPeriod
    {
        $period = $this->periodFor($date);

        return FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
    }

    public function requireOpenDate(FiscalPeriod $period, CarbonInterface $date): void
    {
        if ($period->isLocked()) {
            throw new RuntimeException("La période {$period->label()} est verrouillée. Choisissez une période ouverte.");
        }
        if (NightAudit::isClosed($date)) {
            throw new RuntimeException(
                "La journée du {$date->format('d/m/Y')} est clôturée. Portez l'opération à une date ouverte."
            );
        }
    }

    /**
     * Reprend les à-nouveaux : reporte les soldes de bilan de l'exercice
     * précédent en ouverture du nouveau.
     *
     * Seuls les comptes de bilan (classes 1 à 5) sont repris : les comptes de
     * gestion repartent à zéro chaque exercice. La reprise ne se fait qu'une
     * fois — le drapeau `opening_posted_at` empêche de doubler les soldes.
     *
     * @param  array<int, array{account: string, debit?: int, credit?: int, auxiliary?: Model|null}>  $balances
     */
    public function postOpeningBalance(FiscalYear $year, array $balances): JournalEntry
    {
        return DB::transaction(function () use ($year, $balances) {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);
            if ($year->hasOpeningBalance()) {
                throw new RuntimeException("Les à-nouveaux de l'exercice {$year->label} ont déjà été repris.");
            }

            foreach ($balances as $line) {
                $account = $this->account($line['account']);

                if (!$account->isBalanceSheet()) {
                    throw new RuntimeException(
                        "Le compte {$account->code} est un compte de gestion : il ne se reprend pas en à-nouveaux."
                    );
                }
            }

            $entry = $this->post(
                journalCode: Journal::MISC,
                date: $year->starts_on->copy(),
                label: "Reprise des à-nouveaux — {$year->label}",
                lines: $balances,
                source: $year,
                schema: 'opening_balance',
                reference: 'AN-' . $year->starts_on->year,
            );

            $year->update(['opening_posted_at' => now()]);

            return $entry;
        });
    }

    /**
     * Période couvrant une date, en ouvrant l'exercice au besoin.
     * Un établissement qui saisit sa première écriture ne doit pas avoir à
     * créer son exercice à la main.
     */
    public function periodFor(CarbonInterface $date): FiscalPeriod
    {
        $period = FiscalPeriod::forDate($date);

        if ($period !== null) {
            return $period;
        }

        FiscalYear::openYear((int) $date->year);

        return FiscalPeriod::forDate($date)
            ?? throw new RuntimeException("Aucune période comptable ne couvre le {$date->toDateString()}.");
    }

    /**
     * Valide et met en forme les lignes.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        if ($lines === []) {
            throw new RuntimeException('Une écriture comptable ne peut pas être vide.');
        }

        $normalized = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $index => $line) {
            $code = (string) ($line['account'] ?? '');
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new RuntimeException(
                    "Ligne {$index} : un montant comptable ne peut pas être négatif. Utilisez le sens opposé.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException(
                    "Ligne {$index} : une ligne se porte au débit ou au crédit, jamais aux deux.");
            }

            if ($debit === 0 && $credit === 0) {
                continue; // Ligne à zéro : sans effet, on ne l'enregistre pas.
            }

            $account = $this->account($code);

            if (!$account->is_postable) {
                throw new RuntimeException(
                    "Le compte {$code} est un compte de regroupement : il ne reçoit pas d'écriture directe.");
            }

            $auxiliary = $line['auxiliary'] ?? null;

            $normalized[] = [
                'account_code'    => $account->code,
                'label'           => $line['label'] ?? null,
                'debit'           => $debit,
                'credit'          => $credit,
                'auxiliary_type'  => $auxiliary instanceof Model ? $auxiliary::class : null,
                'auxiliary_id'    => $auxiliary instanceof Model ? $auxiliary->getKey() : null,
                'analytic_center' => $line['center'] ?? null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($normalized === []) {
            throw new RuntimeException('Une écriture comptable ne peut pas être intégralement à zéro.');
        }

        if ($totalDebit !== $totalCredit) {
            throw new RuntimeException(sprintf(
                'Écriture déséquilibrée : %s au débit contre %s au crédit (écart de %s).',
                number_format($totalDebit / 100, 0, ',', ' '),
                number_format($totalCredit / 100, 0, ',', ' '),
                number_format(abs($totalDebit - $totalCredit) / 100, 0, ',', ' ')
            ));
        }

        return $normalized;
    }

    private function account(string $code): Account
    {
        return Account::query()->where('code', $code)->where('is_active', true)->first()
            ?? throw new RuntimeException("Compte « {$code} » inconnu au plan de comptes.");
    }
}
