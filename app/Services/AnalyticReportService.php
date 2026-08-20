<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\JournalEntryLine;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecture analytique : la rentabilité par point de vente.
 *
 * Le grand livre sait ce qui a été gagné et ce qui a été dépensé. Il ne sait
 * pas si le restaurant gagne de l'argent — parce qu'il classe par nature, pas
 * par destination. C'est le centre d'analyse porté sur chaque ligne qui permet
 * de croiser les deux.
 *
 * Ce service ne fait que lire : il ne produit aucune écriture. Le reflet de
 * classe 9 relève d'AnalyticPostingService.
 *
 * Montants en centimes FCFA.
 */
class AnalyticReportService
{
    /** Compte de produit dominant de chaque centre, pour le RevPAR et les repères. */
    public const REVENUE_ACCOUNTS = [
        JournalEntryLine::CENTER_ACCOMMODATION => '706000',
        JournalEntryLine::CENTER_RESTAURANT    => '706100',
        JournalEntryLine::CENTER_SHOP          => '701000',
    ];

    public function __construct(
        private readonly RoomCostingService $costing,
    ) {
    }

    /**
     * Compte de résultat par centre : produits, charges, marge brute.
     *
     * La colonne « non ventilé » n'est pas un défaut d'affichage. Les charges
     * de structure — loyer, assurances, administration — ne se rattachent
     * honnêtement à aucun point de vente ; les répartir au prorata donnerait
     * une marge d'apparence précise et de fait arbitraire. Elles restent donc
     * à part, et se lisent comme ce qu'elles sont : le coût d'exister.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, unassigned: array<string, int>, totals: array<string, int>}
     */
    public function resultByCenter(CarbonInterface $from, CarbonInterface $to): array
    {
        $produits = $this->sumByCenter($from, $to, 7);
        $charges  = $this->sumByCenter($from, $to, 6);

        $rows = collect(JournalEntryLine::CENTERS)
            ->map(function (string $libelle, string $centre) use ($produits, $charges) {
                // Un produit est créditeur, une charge débitrice : on les
                // ramène tous deux à des montants positifs pour la lecture.
                $produit = -($produits[$centre] ?? 0);
                $charge  = $charges[$centre] ?? 0;
                $marge   = $produit - $charge;

                return [
                    'center'  => $centre,
                    'label'   => $libelle,
                    'revenue' => $produit,
                    'cost'    => $charge,
                    'margin'  => $marge,
                    // Un taux de marge sans produit ne veut rien dire : on
                    // préfère ne rien afficher plutôt qu'un pourcentage faux.
                    'margin_pct' => $produit > 0 ? round($marge * 100 / $produit, 1) : null,
                ];
            })
            ->values();

        $nonVentile = [
            'revenue' => -($produits[''] ?? 0),
            'cost'    => $charges[''] ?? 0,
        ];
        $nonVentile['margin'] = $nonVentile['revenue'] - $nonVentile['cost'];

        return [
            'rows'       => $rows,
            'unassigned' => $nonVentile,
            'totals'     => [
                'revenue' => (int) $rows->sum('revenue') + $nonVentile['revenue'],
                'cost'    => (int) $rows->sum('cost') + $nonVentile['cost'],
                'margin'  => (int) $rows->sum('margin') + $nonVentile['margin'],
            ],
        ];
    }

    /**
     * RevPAR — revenu par chambre disponible.
     *
     * L'indicateur central de l'hôtellerie, parce qu'il refuse le confort des
     * deux autres : un prix moyen élevé sur trois chambres vendues ne vaut pas
     * un prix modeste sur trente, et un taux d'occupation flatteur obtenu en
     * bradant ne paie pas les charges. Le RevPAR les réconcilie en rapportant
     * le produit d'hébergement à **toutes** les chambres, vendues ou non.
     *
     * Le dénominateur compte les chambres vendables, pas les chambres
     * existantes : une chambre en travaux n'aurait pas pu être vendue, la
     * compter punirait l'établissement d'un fait qu'il subit déjà.
     *
     * @return array{revenue: int, rooms: int, nights: int, available: int, revpar: int|null, adr: int|null, occupancy: float|null, sold: int}
     */
    public function revpar(CarbonInterface $from, CarbonInterface $to): array
    {
        $produit = -$this->sumForAccount($from, $to, self::REVENUE_ACCOUNTS[JournalEntryLine::CENTER_ACCOMMODATION]);

        $chambres = Room::query()->sellable()->count();
        // Bornes incluses : du 1er au 31, l'établissement a bien 31 nuits à vendre.
        $nuits = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $disponibles = $chambres * $nuits;
        $vendues     = $this->soldNights($from, $to);

        return [
            'revenue'   => $produit,
            'rooms'     => $chambres,
            'nights'    => $nuits,
            'available' => $disponibles,
            'sold'      => $vendues,
            'revpar'    => $disponibles > 0 ? intdiv($produit, $disponibles) : null,
            'adr'       => $vendues > 0 ? intdiv($produit, $vendues) : null,
            'occupancy' => $disponibles > 0 ? round($vendues * 100 / $disponibles, 1) : null,
        ];
    }

    /**
     * Marges de contribution par type de chambre.
     *
     * Reprend les fiches de coût déjà tenues plutôt que d'en recalculer une
     * version parallèle : deux sources pour la même marge finiraient par
     * diverger, et personne ne saurait laquelle croire.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function contributionMargins(): Collection
    {
        return RoomType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (RoomType $type) => [
                'type'    => $type,
                'summary' => $this->costing->summaryFor($type),
            ])
            ->filter(fn ($l) => $l['summary']['is_configured'])
            ->values();
    }

    /**
     * Part des charges effectivement rattachées à un centre.
     *
     * Cet indicateur mesure la qualité de l'analytique elle-même : une marge
     * par point de vente calculée sur 30 % des charges ventilées n'est pas une
     * marge, c'est une impression. Le dire évite de bâtir une décision dessus.
     *
     * @return array{assigned: int, unassigned: int, total: int, rate: float|null}
     */
    public function ventilationRate(CarbonInterface $from, CarbonInterface $to): array
    {
        $charges = $this->sumByCenter($from, $to, 6);

        $nonVentile = $charges[''] ?? 0;
        $ventile    = array_sum($charges) - $nonVentile;
        $total      = $ventile + $nonVentile;

        return [
            'assigned'   => $ventile,
            'unassigned' => $nonVentile,
            'total'      => $total,
            'rate'       => $total > 0 ? round($ventile * 100 / $total, 1) : null,
        ];
    }

    // ── Rouages internes ────────────────────────────────────────────────────

    /**
     * Soldes signés d'une classe de comptes, groupés par centre.
     *
     * La clé '' porte ce qui n'a pas de centre — plus commode qu'un null dans
     * un tableau associatif.
     *
     * @return array<string, int>
     */
    private function sumByCenter(CarbonInterface $from, CarbonInterface $to, int $classe): array
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.code', '=', 'journal_entry_lines.account_code')
            ->where('accounts.account_class', $classe)
            ->whereNotNull('journal_entries.posted_at')
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->selectRaw('journal_entry_lines.analytic_center as centre')
            ->selectRaw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as montant')
            ->groupBy('journal_entry_lines.analytic_center')
            ->get()
            ->mapWithKeys(fn ($l) => [(string) ($l->centre ?? '') => (int) $l->montant])
            ->all();
    }

    /** Solde signé d'un compte sur la période. */
    private function sumForAccount(CarbonInterface $from, CarbonInterface $to, string $code): int
    {
        $ligne = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_code', $code)
            ->whereNotNull('journal_entries.posted_at')
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->selectRaw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as montant')
            ->first();

        return (int) ($ligne->montant ?? 0);
    }

    /**
     * Nuitées vendues sur la période.
     *
     * Comptées par intersection entre le séjour et la période : un séjour à
     * cheval sur deux mois ne doit peser que sur les nuits qu'il occupe
     * réellement dans chacun.
     */
    private function soldNights(CarbonInterface $from, CarbonInterface $to): int
    {
        $debut = $from->copy()->startOfDay();
        $fin   = $to->copy()->startOfDay()->addDay();

        return (int) Booking::query()
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->where('check_in', '<', $fin)
            ->where('check_out', '>', $debut)
            ->get()
            ->sum(function (Booking $booking) use ($debut, $fin) {
                $arrivee = $booking->check_in->copy()->startOfDay()->max($debut);
                $depart  = $booking->check_out->copy()->startOfDay()->min($fin);

                return max(0, (int) $arrivee->diffInDays($depart));
            });
    }
}
