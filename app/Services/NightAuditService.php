<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashRegisterSession;
use App\Models\JournalEntryLine;
use App\Models\NightAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * La clôture journalière.
 *
 * Elle fait trois choses en une opération, et l'ordre compte :
 *
 *  1. **Comptabiliser** — toutes les opérations du jour deviennent des
 *     écritures. C'est la dernière chance : après la clôture, la journée
 *     n'accepte plus rien.
 *  2. **Constater** — le chiffre d'affaires, la trésorerie encaissée et les
 *     écarts de caisse sont figés dans la table. Ce sont des faits datés,
 *     qu'une correction ultérieure du grand livre ne doit pas réécrire.
 *  3. **Figer** — la journée est close. Un mouvement découvert après coup ne
 *     s'y glisse plus : il passera par une écriture datée du jour de sa
 *     découverte, ce qui laisse une trace au lieu de la masquer.
 *
 * Montants en centimes FCFA.
 */
class NightAuditService
{
    /** Comptes de trésorerie : ce qui est réellement entré en caisse ou en banque. */
    private const TREASURY = [Account::CASH, Account::BANK, '531000'];

    public function __construct(private readonly LedgerPostingService $posting)
    {
    }

    /**
     * Ce que donnerait la clôture, sans rien figer.
     * Permet de vérifier l'état d'une journée avant de la fermer.
     *
     * @return array<string, mixed>
     */
    public function preview(CarbonInterface $date): array
    {
        $caisses = $this->caisses($date);

        return [
            'date'              => $date->copy()->startOfDay(),
            'already_closed'    => NightAudit::isClosed($date),
            'is_future'         => $date->copy()->startOfDay()->greaterThan(now()->startOfDay()),
            'revenue'           => $this->revenus($date),
            'treasury'          => $this->tresorerie($date),
            'registers_closed'  => $caisses['closed'],
            'registers_open'    => $caisses['open'],
            'cash_discrepancy'  => $caisses['discrepancy'],
        ];
    }

    /**
     * Clôture une journée.
     *
     * @throws RuntimeException si la journée est déjà close ou n'est pas terminée.
     */
    public function run(CarbonInterface $date, ?string $notes = null): NightAudit
    {
        $jour = $date->copy()->startOfDay();

        if (NightAudit::isClosed($jour)) {
            throw new RuntimeException(
                "La journée du {$jour->format('d/m/Y')} est déjà clôturée. "
                . "Une correction passe désormais par une écriture datée d'aujourd'hui."
            );
        }

        if ($jour->greaterThan(now()->startOfDay())) {
            throw new RuntimeException("Impossible de clôturer une journée à venir.");
        }

        return DB::transaction(function () use ($jour, $notes) {
            // 1. Comptabiliser — dernière passe avant le gel.
            $produites = array_sum($this->posting->postDay($jour));

            // 2. Constater, après comptabilisation : les totaux se lisent au
            //    grand livre, seule source qui fasse foi.
            $revenus = $this->revenus($jour);
            $caisses = $this->caisses($jour);

            // 3. Figer.
            return NightAudit::create([
                'audit_date'            => $jour->toDateString(),
                'closed_at'             => now(),
                'closed_by'             => Auth::id(),
                'revenue_accommodation' => $revenus['accommodation'],
                'revenue_restaurant'    => $revenus['restaurant'],
                'revenue_shop'          => $revenus['shop'],
                'revenue_total'         => $revenus['total'],
                'cash_collected'        => $this->tresorerie($jour),
                'cash_discrepancy'      => $caisses['discrepancy'],
                'registers_closed'      => $caisses['closed'],
                'registers_left_open'   => $caisses['open'],
                'entries_posted'        => $produites,
                'notes'                 => $notes,
            ]);
        });
    }

    /**
     * Journées non clôturées depuis la dernière clôture, hors aujourd'hui.
     *
     * Une journée oubliée fausse le suivi : on la signale plutôt que
     * d'attendre qu'un contrôle la découvre.
     *
     * @return array<int, \Illuminate\Support\Carbon>
     */
    public function pendingDays(int $lookbackDays = 30): array
    {
        $depuis = now()->copy()->subDays($lookbackDays)->startOfDay();
        $jusqua = now()->copy()->subDay()->startOfDay();

        $closes = NightAudit::query()
            ->whereDate('audit_date', '>=', $depuis->toDateString())
            ->pluck('audit_date')
            ->map(fn ($d) => $d->toDateString())
            ->flip();

        $manquantes = [];

        for ($jour = $depuis->copy(); $jour->lessThanOrEqualTo($jusqua); $jour->addDay()) {
            if (!$closes->has($jour->toDateString())) {
                $manquantes[] = $jour->copy();
            }
        }

        return $manquantes;
    }

    /**
     * Chiffre d'affaires du jour, lu au grand livre.
     *
     * @return array{accommodation:int, restaurant:int, shop:int, total:int}
     */
    private function revenus(CarbonInterface $date): array
    {
        $parCompte = $this->creditsParCompte($date, [
            Account::REVENUE_ACCOMMODATION,
            Account::REVENUE_RESTAURANT,
            Account::REVENUE_SHOP,
        ]);

        $hebergement = $parCompte[Account::REVENUE_ACCOMMODATION] ?? 0;
        $restaurant  = $parCompte[Account::REVENUE_RESTAURANT] ?? 0;
        $boutique    = $parCompte[Account::REVENUE_SHOP] ?? 0;

        return [
            'accommodation' => $hebergement,
            'restaurant'    => $restaurant,
            'shop'          => $boutique,
            'total'         => $hebergement + $restaurant + $boutique,
        ];
    }

    /** Trésorerie encaissée dans la journée (débits des comptes de trésorerie). */
    private function tresorerie(CarbonInterface $date): int
    {
        return (int) JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereDate('journal_entries.entry_date', $date->toDateString())
            ->whereNotNull('journal_entries.posted_at')
            ->whereIn('journal_entry_lines.account_code', self::TREASURY)
            ->sum('journal_entry_lines.debit');
    }

    /**
     * État des caisses du jour.
     *
     * @return array{closed:int, open:int, discrepancy:int}
     */
    private function caisses(CarbonInterface $date): array
    {
        $debut = $date->copy()->startOfDay();
        $fin = $date->copy()->endOfDay();

        $fermees = CashRegisterSession::query()
            ->whereBetween('closed_at', [$debut, $fin])
            ->get();

        // Une caisse ouverte ce jour-là et jamais fermée : l'écart du jour
        // reste inconnu tant qu'elle n'est pas clôturée.
        $ouvertes = CashRegisterSession::query()
            ->whereBetween('opened_at', [$debut, $fin])
            ->whereNull('closed_at')
            ->count();

        return [
            'closed'      => $fermees->count(),
            'open'        => $ouvertes,
            'discrepancy' => (int) $fermees->sum('discrepancy_amount'),
        ];
    }

    /**
     * Crédits du jour pour une liste de comptes.
     *
     * @param  array<int, string>  $comptes
     * @return array<string, int>
     */
    private function creditsParCompte(CarbonInterface $date, array $comptes): array
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereDate('journal_entries.entry_date', $date->toDateString())
            ->whereNotNull('journal_entries.posted_at')
            ->whereIn('journal_entry_lines.account_code', $comptes)
            ->groupBy('journal_entry_lines.account_code')
            ->selectRaw('journal_entry_lines.account_code, SUM(journal_entry_lines.credit) - SUM(journal_entry_lines.debit) as net')
            ->pluck('net', 'account_code')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
