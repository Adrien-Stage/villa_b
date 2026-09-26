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
}
