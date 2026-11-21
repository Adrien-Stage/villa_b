<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\NightAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Le reflet analytique : la classe 9, et pourquoi elle ne touche à rien.
 *
 * La comptabilité analytique répond à « où part l'argent », question que la
 * comptabilité générale ignore : elle classe les charges par nature (achats,
 * salaires, loyers), pas par destination. Savoir que l'établissement a dépensé
 * 4 millions en achats n'apprend rien sur la rentabilité du restaurant.
 *
 * SYSCOHADA résout cela par une comptabilité **autonome** : la classe 9
 * reprend les charges de la classe 6 dans des comptes miroirs — les comptes
 * réfléchis — puis les ventile par destination. Le mot « reflet » est à
 * prendre au pied de la lettre : rien n'est déplacé, la charge d'origine reste
 * intacte à sa place. La classe 9 se solde à zéro sur elle-même, donc **ni le
 * bilan ni le compte de résultat ne bougent d'un centime.**
 *
 * L'écriture produite, pour une période :
 *
 *   D 921000  coûts hébergement    ─┐
 *   D 922000  coûts restauration    │ charges portant un centre
 *   D 923000  coûts boutique        │
 *   D 924000  coûts économat       ─┘
 *   D 911000  charges en attente de destination   (celles sans centre)
 *     C 901000  charges réfléchies — total de la classe 6
 *
 * Le compte 911000 mérite un mot : il ne masque pas un problème, il le rend
 * visible. Une charge sans centre d'analyse s'y accumule et se voit, plutôt
 * que d'être répartie au prorata sur des centres qui ne l'ont pas engagée.
 *
 * Montants en centimes FCFA.
 */
class AnalyticPostingService
{
    public const SCHEMA_MIRROR = 'analytic_mirror';

    /** Compte de coût de chaque centre d'analyse. */
    public const COST_ACCOUNTS = [
        JournalEntryLine::CENTER_ACCOMMODATION => '921000',
        JournalEntryLine::CENTER_RESTAURANT    => '922000',
        JournalEntryLine::CENTER_SHOP          => '923000',
        JournalEntryLine::CENTER_STORE         => '924000',
    ];

    /** Charges réfléchies — la contrepartie unique du reflet. */
    public const REFLECTED_CHARGES = '901000';

    /** Charges reprises mais pas encore affectées à une destination. */
    public const PENDING_DESTINATION = '911000';

    public function __construct(
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * Reflète les charges d'une période dans la classe 9.
     *
     * Idempotent par schéma daté, comme le coût matière : rejouer un mois ne
     * produit rien de nouveau. Utiliser `remirror()` pour tenir compte
     * d'écritures postérieures.
     */
    public function mirror(CarbonInterface $from, CarbonInterface $to): ?JournalEntry
    {
        return DB::transaction(function () use ($from, $to) {
            $this->ledger->lockForDate($to);
            if ($this->existingMirror($from, $to) !== null) {
                return null;
            }

            return $this->writeMirror($from, $to, $to, $this->schemaFor($from, $to));
        });
    }

    private function writeMirror(CarbonInterface $from, CarbonInterface $to, CarbonInterface $date, string $schema): ?JournalEntry
    {
        $ventilation = $this->chargesByCenter($from, $to);

        if ($ventilation['total'] === 0 && $ventilation['centers'] === []) {
            return null;
        }

        $lignes = [];

        $labels = JournalEntryLine::centers();
        foreach ($ventilation['centers'] as $centre => $montant) {
            $lignes[] = $this->ligneSignee(self::COST_ACCOUNTS[$centre] ?? '925000',
                'Charges ' . ($labels[$centre] ?? $centre), $montant, $centre);
        }

        if ($ventilation['unassigned'] !== 0) {
            $lignes[] = $this->ligneSignee(
                self::PENDING_DESTINATION,
                'Charges sans centre d’analyse',
                $ventilation['unassigned'],
            );
        }

        // Contrepartie unique, du côté opposé : le reflet se solde à zéro.
        $lignes[] = $this->ligneSignee(
            self::REFLECTED_CHARGES,
            'Charges réfléchies de la période',
            -$ventilation['total'],
        );

        return $this->ledger->post(
            journalCode: Journal::MISC,
            date: $date,
            label: 'Reflet analytique — ' . $this->periodLabel($from, $to),
            lines: $lignes,
            schema: $schema,
            valueDate: $date->isSameDay($to) ? null : $to,
        );
    }

    /**
     * Contre-passe le reflet existant et le reproduit.
     *
     * Un reflet est un constat daté : si des charges arrivent après coup, il
     * devient faux. On ne le modifie pas — on l'extourne et on en produit un
     * neuf, ce qui laisse la trace des deux au journal.
     */
    public function remirror(CarbonInterface $from, CarbonInterface $to,
        string $reason = 'Reflet analytique recalculé'): ?JournalEntry
    {
        return DB::transaction(function () use ($from, $to, $reason) {
            $period = $this->ledger->lockForDate($to);
            $existant = $this->existingMirror($from, $to);
            $date = $period->isLocked() || NightAudit::isClosed($to) ? now() : $to;
            $schema = $this->schemaFor($from, $to);
            if ($existant) {
                if ($date->isBefore($existant->entry_date)) {
                    $date = now();
                }
                $this->ledger->reverse($existant, $date, $reason);
                $schema .= ':' . $existant->id;
            }

            return $this->writeMirror($from, $to, $date, $schema);
        });
    }

    /**
     * État du reflet d'une période : fait ou non, et à jour ou non.
     *
     * L'écart entre ce qui a été reflété et ce que porte aujourd'hui la classe
     * 6 est l'information utile — c'est lui qui dit si le reflet est périmé.
     *
     * @return array{mirrored: bool, entry: JournalEntry|null, reflected: int, current: int, drift: int}
     */
    public function status(CarbonInterface $from, CarbonInterface $to): array
    {
        $existant = $this->existingMirror($from, $to);
        $actuel   = $this->chargesByCenter($from, $to)['total'];

        // Le compte réfléchi est normalement créditeur ; il peut être débiteur
        // si la période est nette d'avoirs. On lit donc le solde signé.
        $ligneReflet = $existant?->lines->firstWhere('account_code', self::REFLECTED_CHARGES);
        $reflete = $ligneReflet ? (int) $ligneReflet->credit - (int) $ligneReflet->debit : 0;

        return [
            'mirrored'  => $existant !== null,
            'entry'     => $existant,
            'reflected' => $reflete,
            'current'   => $actuel,
            'drift'     => $actuel - $reflete,
        ];
    }

    /**
     * Charges de la période, ventilées par centre.
     *
     * @return array{centers: array<string, int>, unassigned: int, total: int}
     */
    public function chargesByCenter(CarbonInterface $from, CarbonInterface $to): array
    {
        $lignes = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.code', '=', 'journal_entry_lines.account_code')
            ->where('accounts.account_class', 6)
            ->whereNotNull('journal_entries.posted_at')
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->selectRaw('journal_entry_lines.analytic_center as centre')
            ->selectRaw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as montant')
            ->groupBy('journal_entry_lines.analytic_center')
            ->get();

        $centres = [];
        $sansCentre = 0;

        foreach ($lignes as $ligne) {
            $montant = (int) $ligne->montant;

            if ($ligne->centre === null || $ligne->centre === '') {
                $sansCentre += $montant;
                continue;
            }

            $centres[$ligne->centre] = ($centres[$ligne->centre] ?? 0) + $montant;
        }

        // Montants signés : un centre peut être net créditeur si les avoirs de
        // la période dépassent ses charges. Le reflet le portera au crédit du
        // compte de coût plutôt que d'écraser l'information.
        $centres = array_filter($centres, fn (int $m) => $m !== 0);

        return [
            'centers'    => $centres,
            'unassigned' => $sansCentre,
            'total'      => array_sum($centres) + $sansCentre,
        ];
    }

    /** Porte un montant signé du bon côté : positif au débit, négatif au crédit. */
    private function ligneSignee(string $compte, string $libelle, int $montant, ?string $centre = null): array
    {
        $ligne = ['account' => $compte, 'label' => $libelle];

        if ($montant >= 0) {
            $ligne['debit'] = $montant;
        } else {
            $ligne['credit'] = -$montant;
        }

        if ($centre !== null) {
            $ligne['center'] = $centre;
        }

        return $ligne;
    }

    /** Écriture de reflet déjà produite pour cette période, s'il y en a une. */
    public function existingMirror(CarbonInterface $from, CarbonInterface $to): ?JournalEntry
    {
        $schema = $this->schemaFor($from, $to);
        return JournalEntry::query()
            ->where(fn ($q) => $q->where('schema', $schema)->orWhere('schema', 'like', $schema . ':%'))
            ->whereNull('reversed_by_id')
            ->whereNotNull('posted_at')
            ->latest('id')
            ->with('lines')
            ->first();
    }

    private function schemaFor(CarbonInterface $from, CarbonInterface $to): string
    {
        return self::SCHEMA_MIRROR . ':' . $from->toDateString() . ':' . $to->toDateString();
    }

    private function periodLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        return $from->format('d/m/Y') . ' au ' . $to->format('d/m/Y');
    }
}
