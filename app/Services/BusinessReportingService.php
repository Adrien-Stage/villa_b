<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\ShopOrder;
use App\Models\User;
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

    // ── Résumé 360° (page d'accueil business) ─────────────────────────────────

    public function summary(string $period): array
    {
        [$start, $end] = $this->periodRange($period);

        $revenue = $this->revenue($start, $end);
        $cash    = $this->cashSummary($start, $end);

        return [
            'period'   => $period,
            'currency' => (string) env('TENANT_CURRENCY', 'XAF'),
            'revenue'  => $revenue,
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
