<?php

namespace App\Services;

use App\Models\PointOfSale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des encaissements : ce qui est entré, par point de vente et par
 * mode de règlement.
 *
 * C'est le document que l'établissement édite chaque jour, et il n'existait
 * pas : les recettes vivaient dans quatre tables sans rien pour les réunir,
 * et aucune ne savait de quel point de vente elle venait.
 *
 * Ce qui est compté, et une seule fois :
 *
 *   - les règlements enregistrés (payments), qui sont la pièce de référence ;
 *   - les ventes de réception qui n'en portent pas — reception_sales.payment_id
 *     nul — donc réglées sans passer par cette pièce ;
 *   - les commandes de restaurant et de boutique soldées sur place, qui ne
 *     créent pas de règlement séparé.
 *
 * Une vente de réception rattachée à un règlement n'est pas recomptée : c'est
 * le même argent, vu deux fois.
 */
class RevenueJournal
{
    /**
     * @return array{
     *     lignes: list<array{point_de_vente: string, code: string, mode: string, montant: int}>,
     *     par_point_de_vente: array<string, int>,
     *     par_mode: array<string, int>,
     *     total: int
     * }
     */
    public function forPeriod(Carbon $debut, Carbon $fin): array
    {
        $points = PointOfSale::orderBy('sort_order')->get()->keyBy('id');
        $brut   = [];

        foreach ($this->sources() as [$table, $colonneMontant, $colonneMode, $colonneDate, $filtre]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'point_of_sale_id')) {
                continue;
            }

            $requete = DB::table($table)
                ->selectRaw("point_of_sale_id, {$colonneMode} as mode, SUM({$colonneMontant}) as montant")
                ->whereBetween($colonneDate, [$debut, $fin])
                ->groupBy('point_of_sale_id', $colonneMode);

            if ($filtre !== null) {
                $filtre($requete);
            }

            foreach ($requete->get() as $ligne) {
                $cle = ($ligne->point_of_sale_id ?? 0) . '|' . ($ligne->mode ?? 'inconnu');
                $brut[$cle] = ($brut[$cle] ?? 0) + (int) $ligne->montant;
            }
        }

        $lignes = [];
        $parPoint = [];
        $parMode  = [];
        $total    = 0;

        foreach ($brut as $cle => $montant) {
            [$pointId, $mode] = explode('|', $cle, 2);
            $point = $points[(int) $pointId] ?? null;

            // Une recette sans point de vente n'est pas écartée : la taire
            // ferait un journal qui ne tombe pas juste.
            $nom  = $point?->name ?? 'Non rattaché';
            $code = $point?->code ?? '—';

            $lignes[] = ['point_de_vente' => $nom, 'code' => $code, 'mode' => $mode, 'montant' => $montant];

            $parPoint[$nom] = ($parPoint[$nom] ?? 0) + $montant;
            $parMode[$mode] = ($parMode[$mode] ?? 0) + $montant;
            $total += $montant;
        }

        usort($lignes, static fn (array $a, array $b): int => [$a['point_de_vente'], $a['mode']] <=> [$b['point_de_vente'], $b['mode']]);

        return [
            'lignes'             => $lignes,
            'par_point_de_vente' => $parPoint,
            'par_mode'           => $parMode,
            'total'              => $total,
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: ?callable}>
     */
    private function sources(): array
    {
        return [
            ['payments', 'amount', 'method', 'paid_at',
                static fn ($q) => $q->where('status', 'completed')],

            // Réglées sans pièce de règlement : les compter deux fois
            // gonflerait le journal du même argent.
            ['reception_sales', 'total_amount', 'payment_method', 'paid_at',
                static fn ($q) => $q->whereNull('payment_id')->where('payment_status', 'paid')],

            ['restaurant_customer_orders', 'amount_paid', 'payment_method', 'paid_at',
                static fn ($q) => $q->where('payment_status', 'paid')],

            ['shop_orders', 'total_amount', 'payment_method', 'paid_at',
                static fn ($q) => $q->where('payment_status', 'paid')],
        ];
    }
}
