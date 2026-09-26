<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * BreakfastPricingService : Gestion et calcul de la tarification des petits-déjeuners.
 *
 * Tous les montants manipulés sont en FCFA entiers.
 * Les tranches d'âge enfants et le tarif adulte sont configurables dans les paramètres du Tenant.
 */
class BreakfastPricingService
{
    public const DEFAULT_ADULT_PRICE = 5000;

    /**
     * Tranches d'âge enfants par défaut.
     *
     * @return array<int, array{id: string, label: string, min_age: int, max_age: int, price: int}>
     */
    public static function defaultAgeBrackets(): array
    {
        return [
            [
                'id'      => '0_2',
                'label'   => 'Bébé (0 à 2 ans)',
                'min_age' => 0,
                'max_age' => 2,
                'price'   => 0,
            ],
            [
                'id'      => '2_10',
                'label'   => 'Enfant (2 à 10 ans)',
                'min_age' => 2,
                'max_age' => 10,
                'price'   => 2500,
            ],
            [
                'id'      => '11_15',
                'label'   => 'Adolescent (11 à 15 ans)',
                'min_age' => 11,
                'max_age' => 15,
                'price'   => 4000,
            ],
            [
                'id'      => '16_17',
                'label'   => 'Jeune (16 à 17 ans)',
                'min_age' => 16,
                'max_age' => 17,
                'price'   => 5000,
            ],
        ];
    }

    /**
     * Récupère les paramètres de petit-déjeuner du tenant.
     *
     * @return array<string, mixed>
     */
    public function getSettings(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? \Illuminate\Support\Facades\Auth::user()?->tenant_id ?? Tenant::first()?->id;
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();
        $settings = $tenant?->settings ?? [];

        return $settings['hebergement']['breakfast'] 
            ?? $settings['reception']['breakfast'] 
            ?? $settings['breakfast'] 
            ?? [];
    }

    /**
     * Récupère la liste des tranches d'âge configurées (ou les défauts).
     *
     * @return array<int, array{id: string, label: string, min_age: int, max_age: int, price: int}>
     */
    public function getAgeBrackets(?int $tenantId = null): array
    {
        $settings = $this->getSettings($tenantId);
        $brackets = $settings['age_brackets'] ?? null;

        if (!is_array($brackets) || empty($brackets)) {
            return self::defaultAgeBrackets();
        }

        // Nettoyage et typage strict des données
        $normalized = [];
        foreach ($brackets as $b) {
            if (!isset($b['label']) || trim((string)$b['label']) === '') {
                continue;
            }
            $id = !empty($b['id']) ? (string)$b['id'] : \Illuminate\Support\Str::slug($b['label'], '_');
            $normalized[] = [
                'id'      => $id,
                'label'   => trim((string)$b['label']),
                'min_age' => (int)($b['min_age'] ?? 0),
                'max_age' => (int)($b['max_age'] ?? 17),
                'price'   => max(0, (int)($b['price'] ?? 0)),
            ];
        }

        return !empty($normalized) ? $normalized : self::defaultAgeBrackets();
    }

    /**
     * Récupère le tarif standard adulte (FCFA / jour).
     */
    public function getAdultPrice(?int $tenantId = null): int
    {
        $settings = $this->getSettings($tenantId);
        return isset($settings['adult_price']) ? max(0, (int)$settings['adult_price']) : self::DEFAULT_ADULT_PRICE;
    }

    /**
     * Trouve une tranche d'âge par son identifiant.
     *
     * @return array{id: string, label: string, min_age: int, max_age: int, price: int}|null
     */
    public function findBracket(string $bracketId, ?int $tenantId = null): ?array
    {
        $brackets = $this->getAgeBrackets($tenantId);
        foreach ($brackets as $bracket) {
            if ($bracket['id'] === $bracketId) {
                return $bracket;
            }
        }

        return null;
    }

    /**
     * Calcule le montant détaillé du petit-déjeuner pour une réservation donnée.
     *
     * @param int $nights Nombre de nuits
     * @param int $adults Nombre d'adultes
     * @param array<int, string> $childrenAgeBracketIds Identifiants des tranches choisies pour chaque enfant
     * @param int|null $tenantId
     * @return array{
     *   nights: int,
     *   adults_count: int,
     *   children_count: int,
     *   adult_unit_price: int,
     *   adult_daily_total: int,
     *   adult_stay_total: int,
     *   children_daily_total: int,
     *   children_stay_total: int,
     *   daily_total: int,
     *   stay_total: int,
     *   children_breakdown: array<string, array{id: string, label: string, unit_price: int, count: int, daily_subtotal: int, stay_subtotal: int}>,
     *   summary_label: string
     * }
     */
    public function calculateBreakfast(int $nights, int $adults, array $childrenAgeBracketIds = [], ?int $tenantId = null): array
    {
        $nights = max(1, $nights);
        $adults = max(0, $adults);
        $adultUnitPrice = $this->getAdultPrice($tenantId);

        $adultDailyTotal = $adults * $adultUnitPrice;
        $adultStayTotal = $adultDailyTotal * $nights;

        $brackets = $this->getAgeBrackets($tenantId);
        $bracketsMap = [];
        foreach ($brackets as $b) {
            $bracketsMap[$b['id']] = $b;
        }

        $childrenBreakdown = [];
        $childrenDailyTotal = 0;

        foreach ($childrenAgeBracketIds as $bracketId) {
            if (empty($bracketId)) {
                continue;
            }
            $bracket = $bracketsMap[$bracketId] ?? null;
            if (!$bracket) {
                // Repli sur le premier disponible
                $bracket = $brackets[0] ?? ['id' => $bracketId, 'label' => 'Enfant', 'price' => 0];
            }

            $id = $bracket['id'];
            if (!isset($childrenBreakdown[$id])) {
                $childrenBreakdown[$id] = [
                    'id'            => $id,
                    'label'         => $bracket['label'],
                    'unit_price'    => $bracket['price'],
                    'count'         => 0,
                    'daily_subtotal'=> 0,
                    'stay_subtotal' => 0,
                ];
            }

            $childrenBreakdown[$id]['count']++;
            $childrenBreakdown[$id]['daily_subtotal'] += $bracket['price'];
            $childrenBreakdown[$id]['total_price'] = $childrenBreakdown[$id]['daily_subtotal'];
            $childrenBreakdown[$id]['stay_subtotal'] += ($bracket['price'] * $nights);
            $childrenDailyTotal += $bracket['price'];
        }

        $childrenStayTotal = $childrenDailyTotal * $nights;
        $summaryLabel = $this->formatChildrenSummary($childrenAgeBracketIds, $tenantId);

        return [
            'nights'               => $nights,
            'adults_count'         => $adults,
            'children_count'       => count($childrenAgeBracketIds),
            'adult_unit_price'     => $adultUnitPrice,
            'adult_daily_total'    => $adultDailyTotal,
            'adult_stay_total'     => $adultStayTotal,
            'children_daily_total' => $childrenDailyTotal,
            'children_stay_total'  => $childrenStayTotal,
            'daily_total'          => $adultDailyTotal + $childrenDailyTotal,
            'stay_total'           => $adultStayTotal + $childrenStayTotal,
            'children_breakdown'   => array_values($childrenBreakdown),
            'summary_label'        => $summaryLabel,
        ];
    }

    /**
     * Formate un libellé clair pour le récapitulatif des tranches d'enfants.
     * Exemple : "2 de 2 à 10 ans, 2 de 13 à 15 ans".
     *
     * @param array<int, string> $childrenAgeBracketIds
     */
    public function formatChildrenSummary(array $childrenAgeBracketIds, ?int $tenantId = null): string
    {
        if (empty($childrenAgeBracketIds)) {
            return '';
        }

        $brackets = $this->getAgeBrackets($tenantId);
        $bracketsMap = [];
        foreach ($brackets as $b) {
            $bracketsMap[$b['id']] = $b['label'];
        }

        $counts = [];
        foreach ($childrenAgeBracketIds as $id) {
            if (empty($id)) continue;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        if (empty($counts)) {
            return '';
        }

        $parts = [];
        foreach ($counts as $id => $cnt) {
            $label = $bracketsMap[$id] ?? $id;
            // Raccourcir le label si nécessaire pour la concision (ex: "Enfant (2 à 10 ans)" -> "2 de 2 à 10 ans")
            if (preg_match('/\((.*?)\)/', $label, $matches)) {
                $bracketShort = $matches[1];
                $parts[] = "{$cnt} de {$bracketShort}";
            } else {
                $parts[] = "{$cnt} {$label}";
            }
        }

        return implode(', ', $parts);
    }

    public const DEFAULT_SERVICE_START_TIME = '06:30';
    public const DEFAULT_SERVICE_END_TIME   = '10:30';
    public const DEFAULT_EXTRA_BED_PRICE    = 10000; // En FCFA

    /**
     * Récupère les horaires de service du petit-déjeuner.
     *
     * @return array{start: string, end: string}
     */
    public function getServiceHours(?int $tenantId = null): array
    {
        $settings = $this->getSettings($tenantId);

        return [
            'start' => $settings['service_start_time'] ?? self::DEFAULT_SERVICE_START_TIME,
            'end'   => $settings['service_end_time'] ?? self::DEFAULT_SERVICE_END_TIME,
        ];
    }

    /**
     * Récupère le tarif par défaut du lit d'appoint (en FCFA).
     */
    public function getDefaultExtraBedPrice(?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? \Illuminate\Support\Facades\Auth::user()?->tenant_id ?? Tenant::first()?->id;
        $tenant = $tenantId ? Tenant::find($tenantId) : Tenant::first();
        $settings = $tenant?->settings ?? [];

        return (int) (
            $settings['hebergement']['default_extra_bed_price'] 
            ?? $settings['reception']['default_extra_bed_price'] 
            ?? self::DEFAULT_EXTRA_BED_PRICE
        );
    }

    /**
     * Génère les droits journaliers au petit-déjeuner (BreakfastEntitlement) pour un séjour.
     * Crée une ligne pour chaque matinée du séjour (check_in + 1 jour jusqu'à check_out).
     */
    public function generateEntitlementsForBooking(\App\Models\Booking $booking): void
    {
        $room = $booking->room;
        $roomType = $room?->roomType;

        $checkIn = \Carbon\Carbon::parse($booking->check_in);
        $checkOut = \Carbon\Carbon::parse($booking->check_out);

        // Nombre de matinées = nombre de nuitées (ou au moins 1)
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $includesBreakfast = $roomType ? (bool)$roomType->includes_breakfast : true;

        // Quota adultes : capacité de base de la chambre si inclus, 0 sinon
        $adultsIncluded = 0;
        if ($includesBreakfast) {
            $baseCap = $roomType ? $roomType->base_capacity : 2;
            $adultsIncluded = min($booking->adults_count, $baseCap);
        }

        // Quota enfants : si prépayé à la réservation, les enfants sont inclus
        $childrenIncluded = $booking->prepaid_breakfast_children ? (int)$booking->children_count : 0;

        for ($i = 0; $i < $nights; $i++) {
            $morningDate = $checkIn->copy()->addDays($i + 1)->toDateString();

            \App\Models\BreakfastEntitlement::updateOrCreate(
                [
                    'booking_id'   => $booking->id,
                    'service_date' => $morningDate,
                ],
                [
                    'tenant_id'         => $booking->tenant_id,
                    'room_id'           => $booking->room_id,
                    'adults_included'   => $adultsIncluded,
                    'children_included' => $childrenIncluded,
                    'status'            => \App\Models\BreakfastEntitlement::STATUS_AVAILABLE,
                ]
            );
        }
    }

    /**
     * Enregistre le pointage du petit-déjeuner pour une chambre.
     * Déduit les inclus et facture le surplus soit par débit sur chambre (Room Charge),
     * soit par paiement direct au restaurant.
     *
     * @return array{
     *   success: bool,
     *   covered_adults: int,
     *   covered_children: int,
     *   extra_adults: int,
     *   extra_children: int,
     *   extra_amount_fcfa: int,
     *   settlement: string,
     *   folio_item_id: int|null
     * }
     */
    public function recordPointage(
        \App\Models\BreakfastEntitlement $entitlement,
        int $adultsServed,
        int $childrenServed,
        string $settlementMethod = 'room_charge',
        ?int $userId = null,
        ?string $notes = null
    ): array {
        return \Illuminate\Support\Facades\DB::transaction(function () use (
            $entitlement,
            $adultsServed,
            $childrenServed,
            $settlementMethod,
            $userId,
            $notes
        ) {
            $booking = $entitlement->booking;
            $room = $entitlement->room;
            $tenantId = $entitlement->tenant_id;

            // 1. Calcul des couverts vs extras
            $coveredAdults = min($adultsServed, $entitlement->adults_included);
            $extraAdults = max(0, $adultsServed - $entitlement->adults_included);

            $coveredChildren = min($childrenServed, $entitlement->children_included);
            $extraChildren = max(0, $childrenServed - $entitlement->children_included);

            // 2. Calcul du montant des extras en FCFA
            $adultUnitPrice = $this->getAdultPrice($tenantId);
            $extraAdultsTotal = $extraAdults * $adultUnitPrice;

            // Pour les enfants, on utilise les tranches d'âge de la réservation si disponibles
            $childrenAges = $booking->children_ages ?? [];
            $brackets = $this->getAgeBrackets($tenantId);
            $bracketsMap = [];
            foreach ($brackets as $b) {
                $bracketsMap[$b['id']] = $b['price'];
            }

            $extraChildrenTotal = 0;
            // On calcule pour les enfants en surplus
            for ($k = 0; $k < $extraChildren; $k++) {
                $bracketId = $childrenAges[$coveredChildren + $k] ?? null;
                $childPrice = ($bracketId && isset($bracketsMap[$bracketId]))
                    ? $bracketsMap[$bracketId]
                    : ($brackets[1]['price'] ?? 2500); // Repli standard
                $extraChildrenTotal += $childPrice;
            }

            $extraTotalFcfa = $extraAdultsTotal + $extraChildrenTotal;
            $extraTotalCentimes = $extraTotalFcfa * 100;

            $folioItemId = null;

            // 3. Traitement du règlement si extra
            if ($extraTotalCentimes > 0 && $settlementMethod === 'room_charge') {
                $descDetails = [];
                if ($extraAdults > 0) {
                    $descDetails[] = "{$extraAdults} adulte(s) extra (" . number_format($extraAdultsTotal, 0, ',', ' ') . " FCFA)";
                }
                if ($extraChildren > 0) {
                    $descDetails[] = "{$extraChildren} enfant(s) extra (" . number_format($extraChildrenTotal, 0, ',', ' ') . " FCFA)";
                }
                $descSuffix = !empty($descDetails) ? ' : ' . implode(', ', $descDetails) : '';

                $folioItem = \App\Models\FolioItem::create([
                    'booking_id'       => $booking->id,
                    'customer_id'      => $booking->customer_id,
                    'type'             => \App\Models\FolioItem::TYPE_RESTAURANT,
                    'description'      => "Petit-déjeuner extra — Ch. {$room->number}{$descSuffix}",
                    'quantity'         => 1,
                    'unit_price'       => $extraTotalCentimes,
                    'total_price'      => $extraTotalCentimes,
                    'is_complimentary' => false,
                    'earns_points'     => true,
                    'recorded_by'      => $userId,
                    'occurred_at'      => now(),
                    'notes'            => $notes,
                ]);

                $folioItemId = $folioItem->id;

                // Recalcul des totaux du séjour
                app(\App\Services\CheckOutService::class)->recalculateTotals($booking->fresh());
            }

            // 4. Mise à jour de l'entitlement
            $entitlement->update([
                'adults_consumed'   => $adultsServed,
                'children_consumed' => $childrenServed,
                'status'            => \App\Models\BreakfastEntitlement::STATUS_CONSUMED,
                'consumed_at'       => now(),
                'served_by'         => $userId,
                'notes'             => $notes,
            ]);

            \App\Models\AuditLog::record(
                $userId,
                'sensitive_action',
                "Pointage petit-déjeuner Ch. {$room->number} : {$adultsServed} adulte(s), {$childrenServed} enfant(s) servis. Couverts: {$coveredAdults}A / {$coveredChildren}E. Extra: {$extraTotalFcfa} FCFA ({$settlementMethod})."
            );

            return [
                'success'           => true,
                'covered_adults'    => $coveredAdults,
                'covered_children'  => $coveredChildren,
                'extra_adults'      => $extraAdults,
                'extra_children'    => $extraChildren,
                'extra_amount_fcfa' => $extraTotalFcfa,
                'settlement'        => $settlementMethod,
                'folio_item_id'     => $folioItemId,
            ];
        });
    }

    /**
     * Récupère la liste des petits-déjeuners du jour avec toutes les informations nécessaires.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\BreakfastEntitlement>
     */
    public function getDailyBreakfastList(?string $date = null, ?int $tenantId = null)
    {
        $date = $date ?? now()->toDateString();

        // 1. S'assurer que les réservations actives ont leur entitlement généré
        $activeBookings = \App\Models\Booking::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', [
                \App\Enums\BookingStatus::CHECKED_IN,
                \App\Enums\BookingStatus::CONFIRMED,
            ])
            ->whereDate('check_in', '<=', $date)
            ->whereDate('check_out', '>=', $date)
            ->with(['room.roomType', 'customer'])
            ->get();

        foreach ($activeBookings as $b) {
            $this->generateEntitlementsForBooking($b);
        }

        // 2. Charger les entitlements de la date avec relations
        return \App\Models\BreakfastEntitlement::query()
            ->when($tenantId, fn($q) => $q->where('breakfast_entitlements.tenant_id', $tenantId))
            ->whereDate('breakfast_entitlements.service_date', $date)
            ->with([
                'booking.customer',
                'booking.room.roomType',
                'room.roomType',
                'server',
            ])
            ->join('rooms', 'breakfast_entitlements.room_id', '=', 'rooms.id')
            ->orderBy('rooms.number', 'asc')
            ->select('breakfast_entitlements.*')
            ->get();
    }

    /**
     * Calcule les statistiques résumées du service petit-déjeuner pour une date donnée.
     *
     * @return array{
     *   total_expected_adults: int,
     *   total_expected_children: int,
     *   total_expected: int,
     *   total_served_adults: int,
     *   total_served_children: int,
     *   total_served: int,
     *   total_pending: int,
     *   rooms_count: int,
     *   rooms_served_count: int
     * }
     */
    public function getDailyStats(?string $date = null, ?int $tenantId = null): array
    {
        $list = $this->getDailyBreakfastList($date, $tenantId);

        $expectedAdults = 0;
        $expectedChildren = 0;
        $servedAdults = 0;
        $servedChildren = 0;
        $roomsServed = 0;

        foreach ($list as $item) {
            $expectedAdults += $item->adults_included;
            $expectedChildren += $item->children_included;

            if ($item->isConsumed()) {
                $servedAdults += $item->adults_consumed;
                $servedChildren += $item->children_consumed;
                $roomsServed++;
            }
        }

        $totalExpected = $expectedAdults + $expectedChildren;
        $totalServed = $servedAdults + $servedChildren;
        $totalPending = max(0, $totalExpected - $totalServed);

        return [
            'total_expected_adults'   => $expectedAdults,
            'total_expected_children' => $expectedChildren,
            'total_expected'          => $totalExpected,
            'total_served_adults'     => $servedAdults,
            'total_served_children'   => $servedChildren,
            'total_served'            => $totalServed,
            'total_pending'           => $totalPending,
            'rooms_count'             => $list->count(),
            'rooms_served_count'      => $roomsServed,
        ];
    }
}
