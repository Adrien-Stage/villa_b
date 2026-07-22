<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\ShopOrder;
use App\Models\User;
use App\Support\Countries;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Source unique de vérité des chiffres business/financiers d'un
 * établissement. Consommé à la fois par l'espace analytics du manager
 * (meka_template) et par l'API de reporting exploitée par la console
 * business de pms — pour que le propriétaire voie exactement les mêmes
 * montants que son directeur (indispensable pour la reddition de comptes).
 *
 * Tous les montants sont en centimes (comme en base) sauf mention contraire.
 */
class BusinessReportingService
{
    /** Seuils au-delà desquels une anomalie locale est signalée (centimes). */
    private const CASH_DISCREPANCY_ALERT = 100000;   // 1 000 FCFA d'écart de caisse
    private const DISBURSEMENT_ALERT     = 5000000;  // 50 000 FCFA de décaissement unique

    /** Profondeur des classements clients renvoyés au pms, qui re-classe après fusion. */
    private const CUSTOMER_LEADERBOARD_SIZE = 50;

    /**
     * Bornes [début, fin] pour une période nommée.
     */
    public function periodRange(string $period): array
    {
        $start = match ($period) {
            'today' => Carbon::today(),
            'week'  => Carbon::now()->startOfWeek(),
            'year'  => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        return [$start, Carbon::now()->endOfDay()];
    }

    /**
     * Période précédente comparable (même durée écoulée, décalée d'une
     * unité) — pour calculer les tendances « vs période précédente ».
     */
    public function previousRange(string $period): array
    {
        [$start, $end] = $this->periodRange($period);
        $elapsed = $start->diffInSeconds($end);

        $prevStart = match ($period) {
            'today' => $start->copy()->subDay(),
            'week'  => $start->copy()->subWeek(),
            'year'  => $start->copy()->subYear(),
            default => $start->copy()->subMonthNoOverflow(),
        };

        return [$prevStart, $prevStart->copy()->addSeconds($elapsed)];
    }

    // ── Revenus ───────────────────────────────────────────────────────────────

    /**
     * Revenu par pôle sur une période. Hôtel = paiements encaissés ;
     * restaurant / boutique = commandes payées.
     */
    public function revenue(Carbon $start, Carbon $end): array
    {
        $hotel = (int) Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $restaurant = (int) RestaurantCustomerOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount_paid');

        $shop = (int) ShopOrder::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total_amount');

        return [
            'hotel'      => $hotel,
            'restaurant' => $restaurant,
            'shop'       => $shop,
            'total'      => $hotel + $restaurant + $shop,
        ];
    }

    /**
     * Série d'évolution du revenu (pour les graphes) : par jour, ou par mois
     * si period=year. Montants renvoyés en unité monétaire (FCFA), pas en
     * centimes, pour l'affichage direct.
     */
    public function revenueSeries(string $period, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $hotel = [];
        $restaurant = [];
        $shop = [];

        if ($period === 'year') {
            $mHotel = $this->monthlySum(Payment::where('status', 'completed'), 'paid_at', 'amount', $start, $end);
            $mResto = $this->monthlySum(RestaurantCustomerOrder::where('payment_status', 'paid'), 'paid_at', 'amount_paid', $start, $end);
            $mShop  = $this->monthlySum(ShopOrder::where('payment_status', 'paid'), 'paid_at', 'total_amount', $start, $end);

            for ($m = 1; $m <= Carbon::now()->month; $m++) {
                $labels[] = Carbon::create()->month($m)->locale('fr')->shortMonthName;
                $hotel[]      = ($mHotel[$m] ?? 0) / 100;
                $restaurant[] = ($mResto[$m] ?? 0) / 100;
                $shop[]       = ($mShop[$m] ?? 0) / 100;
            }
        } else {
            $dHotel = $this->dailySum(Payment::where('status', 'completed'), 'paid_at', 'amount', $start, $end);
            $dResto = $this->dailySum(RestaurantCustomerOrder::where('payment_status', 'paid'), 'paid_at', 'amount_paid', $start, $end);
            $dShop  = $this->dailySum(ShopOrder::where('payment_status', 'paid'), 'paid_at', 'total_amount', $start, $end);

            $cursor = $start->copy();
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $hotel[]      = ($dHotel[$key] ?? 0) / 100;
                $restaurant[] = ($dResto[$key] ?? 0) / 100;
                $shop[]       = ($dShop[$key] ?? 0) / 100;
                $cursor->addDay();
            }
        }

        return compact('labels', 'hotel', 'restaurant', 'shop');
    }

    private function dailySum($query, string $dateCol, string $amountCol, Carbon $start, Carbon $end): array
    {
        return $query->whereBetween($dateCol, [$start, $end])
            ->selectRaw("DATE($dateCol) as d, SUM($amountCol) as total")
            ->groupByRaw("DATE($dateCol)")
            ->pluck('total', 'd')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    private function monthlySum($query, string $dateCol, string $amountCol, Carbon $start, Carbon $end): array
    {
        return $query->whereBetween($dateCol, [$start, $end])
            ->selectRaw("EXTRACT(MONTH FROM $dateCol) as m, SUM($amountCol) as total")
            ->groupByRaw("EXTRACT(MONTH FROM $dateCol)")
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->m => (int) $r->total])
            ->toArray();
    }

    /**
     * Répartition des encaissements par méthode de paiement (cash, Orange
     * Money, MTN, carte...) — d'où l'argent rentre physiquement.
     */
    public function paymentMethods(Carbon $start, Carbon $end): array
    {
        return Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('method, COUNT(*) as n, SUM(amount) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count'  => (int) $r->n,
                'total'  => (int) $r->total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    // ── Occupation ──────────────────────────────────────────────────────────

    public function occupancy(): array
    {
        $totalRooms = Room::where('is_active', true)->count();
        $occupied   = Room::where('is_active', true)->where('status', RoomStatus::OCCUPIED->value)->count();

        return [
            'rooms_total'    => $totalRooms,
            'rooms_occupied' => $occupied,
            'rate'           => $totalRooms > 0 ? round($occupied / $totalRooms * 100, 1) : 0.0,
        ];
    }

    // ── Caisse (audit écart déclaré vs théorique) ─────────────────────────────

    public function cashSummary(Carbon $start, Carbon $end): array
    {
        $closed = CashRegisterSession::whereNotNull('closed_at')
            ->whereBetween('closed_at', [$start, $end])
            ->get();

        $withDiscrepancy = $closed->filter(fn ($s) => (int) $s->discrepancy_amount !== 0);

        return [
            'sessions_closed'         => $closed->count(),
            'sessions_open'           => CashRegisterSession::whereNull('closed_at')->count(),
            'sessions_with_gap'       => $withDiscrepancy->count(),
            'total_discrepancy'       => (int) $closed->sum('discrepancy_amount'),
            'worst_discrepancy'       => (int) ($withDiscrepancy->min('discrepancy_amount') ?? 0),
        ];
    }

    /**
     * Détail des sessions de caisse (pour l'onglet audit financier).
     */
    public function cashSessions(Carbon $start, Carbon $end): Collection
    {
        return CashRegisterSession::with('user')
            ->whereBetween('opened_at', [$start, $end])
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn (CashRegisterSession $s) => [
                'id'          => $s->id,
                'module'      => $s->module,
                'user'        => $s->user?->name ?? '—',
                'status'      => $s->status,
                'opened_at'   => $s->opened_at?->format('d/m/Y H:i'),
                'closed_at'   => $s->closed_at?->format('d/m/Y H:i'),
                'opening'     => (int) $s->opening_amount,
                'theoretical' => (int) $s->theoretical_closing_amount,
                'declared'    => (int) $s->actual_closing_amount,
                'discrepancy' => (int) $s->discrepancy_amount,
            ]);
    }

    // ── Dépenses / décaissements ──────────────────────────────────────────────

    public function expenses(Carbon $start, Carbon $end): array
    {
        $items = CashRegisterDisbursement::with(['user', 'session'])
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get();

        return [
            'total' => (int) $items->sum('amount'),
            'count' => $items->count(),
            'items' => $items->map(fn (CashRegisterDisbursement $d) => [
                'amount' => (int) $d->amount,
                'reason' => $d->reason,
                'user'   => $d->user?->name ?? '—',
                'module' => $d->session?->module ?? '—',
                'at'     => $d->created_at?->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    // ── Personnel ─────────────────────────────────────────────────────────────

    public function staff(): array
    {
        $users = User::orderBy('name')->get();

        return [
            'total'  => $users->count(),
            'active' => $users->where('is_active', true)->count(),
            'by_role' => $users->groupBy('role')->map->count()->toArray(),
            'members' => $users->map(fn (User $u) => [
                'name'       => $u->name,
                'role'       => $u->role,
                'is_active'  => (bool) $u->is_active,
                'last_login' => $u->last_login_at?->diffForHumans(),
            ])->all(),
        ];
    }

    // ── Clientèle ─────────────────────────────────────────────────────────────

    /**
     * Analytique de la clientèle : indicateurs de valeur, classements
     * (chiffre d'affaires et rentabilité par nuitée), segmentation RFM et
     * répartition géographique des marchés émetteurs.
     *
     * Les classements portent l'email de chaque client : c'est la clé qui
     * permet à la console business de reconnaître un même client séjournant
     * dans plusieurs établissements. Un condensé md5 de tous les emails est
     * joint séparément pour que le pms puisse dédoublonner la base sans
     * transporter toute la clientèle.
     */
    public function customers(Carbon $start, Carbon $end): array
    {
        $excluded = [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value];
        $home     = Countries::normalize((string) config('app.home_country', 'CM')) ?? 'CM';

        // Un paiement se rattache soit directement au client (extra hors
        // réservation : restaurant, boutique), soit à sa réservation.
        $attribution = 'COALESCE(payments.customer_id, bookings.customer_id)';

        $revenueByCustomer = Payment::query()
            ->where('payments.status', 'completed')
            ->whereBetween('payments.paid_at', [$start, $end])
            ->leftJoin('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->selectRaw("{$attribution} as cid, SUM(payments.amount) as revenue")
            ->groupByRaw($attribution)
            ->pluck('revenue', 'cid');

        // Séjours de la période (nuitées vendues), hors annulations/no-show.
        $periodStays = Booking::query()
            ->whereBetween('check_in', [$start, $end])
            ->whereNotIn('status', $excluded)
            ->selectRaw('customer_id, SUM(total_nights) as nights, COUNT(*) as stays')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        // Historique complet : sert la récence et la fréquence du score RFM.
        $lifetime = Booking::query()
            ->whereNotIn('status', $excluded)
            ->selectRaw('customer_id, COUNT(*) as stays, MAX(check_in) as last_stay')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $customers = Customer::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'country', 'nationality',
                      'total_spent', 'total_nights_stayed', 'is_vip', 'created_at'])
            ->get();

        // Seuil « gros contributeur » = 80e centile des montants dépensés.
        // Un seuil relatif s'adapte à la taille de l'établissement, là où un
        // montant fixe classerait tout le monde pareil dans un petit hôtel.
        $spends = $customers->pluck('total_spent')->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)->sort()->values();
        $highSpend = $spends->isEmpty()
            ? 0
            : (int) $spends[min($spends->count() - 1, (int) floor($spends->count() * 0.8))];

        $rows          = [];
        $rfm           = ['champions' => 0, 'fideles' => 0, 'prometteurs' => 0,
                          'nouveaux' => 0, 'a_risque' => 0, 'endormis' => 0, 'occasionnels' => 0];
        $geo           = [];
        $keys          = [];
        $withoutEmail  = 0;
        $withCountry   = 0;
        $domestic      = 0;
        $repeatCount   = 0;
        $stayedCount   = 0;
        $newInPeriod   = 0;

        foreach ($customers as $c) {
            $periodRevenue = (int) ($revenueByCustomer[$c->id] ?? 0);
            $periodNights  = (int) ($periodStays[$c->id]->nights ?? 0);
            $periodBookings = (int) ($periodStays[$c->id]->stays ?? 0);
            $freq          = (int) ($lifetime[$c->id]->stays ?? 0);
            $lastStay      = ($lifetime[$c->id]->last_stay ?? null);
            $ltv           = (int) $c->total_spent;

            // Clé de rapprochement inter-établissements.
            $email = $c->email ? strtolower(trim($c->email)) : null;
            if ($email) {
                $keys[md5($email)] = true;
            } else {
                $withoutEmail++;
            }

            if ($c->created_at && $c->created_at->between($start, $end)) {
                $newInPeriod++;
            }
            if ($freq >= 1) {
                $stayedCount++;
                if ($freq >= 2) { $repeatCount++; }
            }

            // Géographie : le pays de résidence prime ; la nationalité ne sert
            // de repli que si elle a été saisie sous forme de code ISO.
            $iso = Countries::normalize($c->country) ?? Countries::normalize($c->nationality);
            if ($iso) {
                $withCountry++;
                if ($iso === $home) { $domestic++; }

                $geo[$iso] ??= ['customers' => 0, 'revenue' => 0];
                $geo[$iso]['customers']++;
                $geo[$iso]['revenue'] += $periodRevenue;
            }

            $rfm[$this->rfmSegment($freq, $lastStay, $ltv, $c->created_at, $highSpend)]++;

            // Seuls les clients ayant pesé sur la période alimentent les
            // classements — inutile de transporter une base dormante entière.
            if ($periodRevenue > 0 || $periodNights > 0) {
                $rows[] = [
                    'key'        => $email ? md5($email) : 'id:' . $c->id,
                    'name'       => trim($c->first_name . ' ' . $c->last_name),
                    'email'      => $email,
                    'country'    => $iso,
                    'continent'  => $iso ? Countries::continent($iso) : null,
                    'revenue'    => $periodRevenue,
                    'nights'     => $periodNights,
                    'bookings'   => $periodBookings,
                    'ltv'        => $ltv,
                    'is_vip'     => (bool) $c->is_vip,
                ];
            }
        }

        // Classements locaux tronqués : le pms fusionne puis re-classe, une
        // profondeur de 50 par établissement suffit à ne pas perdre un client
        // qui ne serait dominant qu'une fois les établissements consolidés.
        $byRevenue = collect($rows)->sortByDesc('revenue')
            ->take(self::CUSTOMER_LEADERBOARD_SIZE)->values()->all();

        $byProfit = collect($rows)->filter(fn ($r) => $r['nights'] > 0)
            ->sortByDesc(fn ($r) => intdiv($r['revenue'], max(1, $r['nights'])))
            ->take(self::CUSTOMER_LEADERBOARD_SIZE)->values()->all();

        $periodRevenueTotal = collect($rows)->sum('revenue');
        $periodStaysTotal   = collect($rows)->sum('bookings');

        $geoRows = [];
        foreach ($geo as $iso => $agg) {
            $geoRows[] = [
                'country'   => $iso,
                'name'      => Countries::name($iso),
                'continent' => Countries::continent($iso),
                'numeric'   => Countries::numeric($iso),
                'customers' => $agg['customers'],
                'revenue'   => $agg['revenue'],
            ];
        }
        usort($geoRows, fn ($a, $b) => $b['customers'] <=> $a['customers']);

        return [
            'currency' => 'XAF',
            'home_country' => $home,
            'totals' => [
                'customers'      => $customers->count(),
                'new'            => $newInPeriod,
                'without_email'  => $withoutEmail,
                'with_country'   => $withCountry,
                'domestic'       => $domestic,
                'stayed'         => $stayedCount,
                'repeat'         => $repeatCount,
                'revenue'        => $periodRevenueTotal,
                'stays'          => $periodStaysTotal,
                'ltv_sum'        => (int) $customers->sum('total_spent'),
            ],
            'customer_keys'   => array_keys($keys),
            'top_revenue'     => $byRevenue,
            'top_profitable'  => $byProfit,
            'rfm'             => $rfm,
            'geo'             => $geoRows,
        ];
    }

    /**
     * Classement RFM d'un client en un segment actionnable. La cascade est
     * ordonnée du signal le plus fort au plus faible pour que les segments
     * restent mutuellement exclusifs (un client ne tombe que dans un seul).
     */
    private function rfmSegment(int $frequency, $lastStay, int $monetary, ?Carbon $createdAt, int $highSpend): string
    {
        if ($frequency === 0) {
            // Jamais venu : soit une fiche récente à convertir, soit une fiche morte.
            return ($createdAt && $createdAt->diffInDays(now()) <= 90) ? 'nouveaux' : 'endormis';
        }

        $recency = Carbon::parse($lastStay)->diffInDays(now());

        return match (true) {
            $recency > 365                                                     => 'endormis',
            $recency <= 90 && $frequency >= 3 && $monetary >= $highSpend       => 'champions',
            $recency <= 180 && $frequency >= 3                                 => 'fideles',
            $recency <= 180 && $frequency === 2                                => 'prometteurs',
            $recency <= 90 && $frequency === 1                                 => 'nouveaux',
            $frequency >= 2                                                    => 'a_risque',
            default                                                            => 'occasionnels',
        };
    }

    // ── Alertes locales (anomalies détectables au niveau établissement) ───────

    public function localAlerts(Carbon $start, Carbon $end): array
    {
        $alerts = [];

        // Écarts de caisse significatifs
        CashRegisterSession::with('user')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$start, $end])
            ->get()
            ->filter(fn ($s) => abs((int) $s->discrepancy_amount) >= self::CASH_DISCREPANCY_ALERT)
            ->each(function ($s) use (&$alerts) {
                $gap = (int) $s->discrepancy_amount;
                $alerts[] = [
                    'type'     => 'cash_discrepancy',
                    'severity' => abs($gap) >= self::CASH_DISCREPANCY_ALERT * 5 ? 'high' : 'medium',
                    'title'    => 'Écart de caisse',
                    'message'  => "Caisse {$s->module} de " . ($s->user?->name ?? 'un employé') . " : écart de " . number_format(abs($gap) / 100, 0, ',', ' ') . ' FCFA ' . ($gap < 0 ? '(manquant)' : '(excédent)') . '.',
                    'at'       => $s->closed_at?->format('d/m/Y H:i'),
                ];
            });

        // Décaissements élevés
        CashRegisterDisbursement::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->where('amount', '>=', self::DISBURSEMENT_ALERT)
            ->get()
            ->each(function ($d) use (&$alerts) {
                $alerts[] = [
                    'type'     => 'large_disbursement',
                    'severity' => 'medium',
                    'title'    => 'Décaissement important',
                    'message'  => number_format((int) $d->amount / 100, 0, ',', ' ') . ' FCFA sortis par ' . ($d->user?->name ?? 'un employé') . " — motif : {$d->reason}.",
                    'at'       => $d->created_at?->format('d/m/Y H:i'),
                ];
            });

        return $alerts;
    }

    // ── Rapport financier complet (page Rapport / audit) ──────────────────────

    /**
     * Photographie financière complète d'un établissement sur une période :
     * revenus, méthodes de paiement, facturé/encaissé/dû, dépenses, audit de
     * caisse et résultat net. Consommé par la page Rapport de pms (affichage
     * + exports Excel/PDF).
     */
    public function financeReport(string $period): array
    {
        [$start, $end] = $this->periodRange($period);

        $revenue  = $this->revenue($start, $end);
        $expenses = $this->expenses($start, $end);

        $invoices = \App\Models\Invoice::whereBetween('invoice_date', [$start, $end])->get();
        $invoiceSummary = [
            'count'          => $invoices->count(),
            'total_invoiced' => (int) $invoices->sum('total_amount'),
            'total_paid'     => (int) $invoices->sum('paid_amount'),
            'total_due'      => (int) $invoices->sum('balance_due'),
        ];

        return [
            'period'          => $period,
            'currency'        => (string) env('TENANT_CURRENCY', 'XAF'),
            'revenue'         => $revenue,
            'payment_methods' => $this->paymentMethods($start, $end),
            'invoices'        => $invoiceSummary,
            'expenses'        => $expenses,
            'cash'            => $this->cashSummary($start, $end),
            'net'             => $revenue['total'] - (int) $expenses['total'],
        ];
    }

    // ── Résumé 360° (page d'accueil business) ─────────────────────────────────

    public function summary(string $period): array
    {
        [$start, $end] = $this->periodRange($period);
        [$prevStart, $prevEnd] = $this->previousRange($period);

        $revenue = $this->revenue($start, $end);
        $cash    = $this->cashSummary($start, $end);

        return [
            'period'   => $period,
            'currency' => (string) env('TENANT_CURRENCY', 'XAF'),
            'revenue'  => $revenue,
            'revenue_previous' => $this->revenue($prevStart, $prevEnd),
            'occupancy' => $this->occupancy(),
            'bookings' => [
                'total'     => Booking::whereBetween('created_at', [$start, $end])->count(),
                'pending'   => Booking::where('status', BookingStatus::PENDING->value)->count(),
                'confirmed' => Booking::where('status', BookingStatus::CONFIRMED->value)->count(),
                'checked_in' => Booking::where('status', BookingStatus::CHECKED_IN->value)->count(),
            ],
            'cash'        => $cash,
            'staff'       => ['total' => User::count(), 'active' => User::where('is_active', true)->count()],
            'alerts_count' => count($this->localAlerts($start, $end)),
        ];
    }
}
