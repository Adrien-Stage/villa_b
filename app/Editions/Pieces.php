<?php

namespace App\Editions;

use App\Models\Booking;
use App\Models\ExternalIssue;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\ReceptionSale;
use App\Models\RestaurantWasteLog;
use App\Models\ShopOrder;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\TenantModules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Retrouver une pièce par son numéro — facture, bon de commande, bon
 * d'entrée, réquisition, réservation, vente, procès-verbal de perte — et
 * l'imprimer.
 *
 * Chaque sorte de pièce mène à son document imprimable existant ; elle
 * n'apparaît qu'à qui peut ouvrir ce document.
 */
class Pieces
{
    /** Résultats par sorte de pièce, au plus. */
    private const LIMITE = 8;

    /**
     * @return Collection<int, array{sorte: string, numero: string, date: mixed, detail: string, url: string}>
     */
    public function chercher(string $recherche, User $user): Collection
    {
        $recherche = trim($recherche);

        if (mb_strlen($recherche) < 2) {
            return collect();
        }

        $motif = '%' . mb_strtolower($recherche) . '%';
        $resultats = collect();

        foreach ($this->sortes() as $sorte) {
            if (! Route::has($sorte['route']) || (isset($sorte['module']) && ! TenantModules::has($sorte['module']))
                || ! app(PermissionResolver::class)->allows($user, $sorte['droit'])) {
                continue;
            }

            $pieces = $sorte['modele']::query()
                // Une pièce d'un restaurant ne se montre qu'à qui voit ce restaurant.
                ->when(method_exists($sorte['modele'], 'scopeVisiblesPour'), fn ($q) => $q->visiblesPour($user))
                ->whereRaw('LOWER(' . $sorte['colonne'] . ') LIKE ?', [$motif])
                ->latest('id')
                ->limit(self::LIMITE)
                ->get();

            foreach ($pieces as $piece) {
                $resultats->push([
                    'sorte' => $sorte['sorte'],
                    'numero' => (string) $piece->{$sorte['colonne']},
                    'date' => $piece->{$sorte['date']},
                    'detail' => ($sorte['detail'])($piece),
                    'url' => route($sorte['route'], $piece),
                ]);
            }
        }

        return $resultats;
    }

    /** @return list<array<string, mixed>> */
    private function sortes(): array
    {
        return [
            ['sorte' => 'Facture', 'modele' => Invoice::class, 'colonne' => 'invoice_number', 'date' => 'invoice_date',
                'route' => 'invoices.show', 'droit' => 'invoices.voir',
                'detail' => fn (Invoice $f) => ($f->customer?->full_name ?? '') . ' — ' . number_format(((int) $f->total_amount) / 100, 0, ',', ' ') . ' FCFA'],
            ['sorte' => 'Réservation', 'modele' => Booking::class, 'colonne' => 'booking_number', 'date' => 'check_in',
                'route' => 'bookings.summary', 'droit' => 'bookings.voir',
                'detail' => fn (Booking $b) => ($b->customer?->full_name ?? '') . ' — ' . ($b->status?->label() ?? '')],
            ['sorte' => 'Bon de commande', 'modele' => PurchaseOrder::class, 'colonne' => 'number', 'date' => 'created_at',
                'route' => 'economat.orders.print', 'droit' => 'economat.orders.voir',
                'detail' => fn (PurchaseOrder $o) => ($o->supplier?->name ?? '') . ' — ' . $o->statusLabel()],
            ['sorte' => "Bon d'entrée", 'modele' => GoodsReceipt::class, 'colonne' => 'number', 'date' => 'received_at',
                'route' => 'economat.receipts.print', 'droit' => 'economat.receipts.voir',
                'detail' => fn (GoodsReceipt $r) => ($r->supplier?->name ?? '') . ' — ' . $r->statusLabel()],
            ['sorte' => 'Bon de réquisition', 'modele' => StockRequisition::class, 'colonne' => 'number', 'date' => 'created_at',
                'route' => 'economat.requisitions.print', 'droit' => 'economat.requisitions.voir',
                'detail' => fn (StockRequisition $r) => (StockRequisition::DEPARTMENTS[$r->department] ?? $r->department) . ' — ' . $r->statusLabel()],
            ['sorte' => 'Bon de sortie hors établissement', 'modele' => ExternalIssue::class, 'colonne' => 'number', 'date' => 'issued_at',
                'route' => 'economat.external_issues.print', 'droit' => 'economat.external_issues.voir',
                'detail' => fn (ExternalIssue $s) => $s->beneficiaire . ' — ' . $s->reasonLabel()],
            ['sorte' => 'Vente au comptoir', 'modele' => ReceptionSale::class, 'colonne' => 'sale_number', 'date' => 'created_at',
                'route' => 'reception.pos.receipt', 'droit' => 'reception.pos.receipt',
                'detail' => fn (ReceptionSale $v) => ($v->customer_name ?: '') . ' — ' . number_format(((int) $v->total_amount) / 100, 0, ',', ' ') . ' FCFA'],
            ['sorte' => 'Vente boutique', 'modele' => ShopOrder::class, 'colonne' => 'order_number', 'date' => 'created_at',
                'route' => 'shop.orders.show', 'droit' => 'shop.orders.voir', 'module' => 'shop',
                'detail' => fn (ShopOrder $o) => ($o->customer_name ?: '') . ' — ' . number_format(((int) $o->total_amount) / 100, 0, ',', ' ') . ' FCFA'],
            ['sorte' => 'Procès-verbal de perte', 'modele' => RestaurantWasteLog::class, 'colonne' => 'reference', 'date' => 'occurred_at',
                'route' => 'restaurant.waste.print', 'droit' => 'restaurant.waste.voir', 'module' => 'restaurant',
                'detail' => fn (RestaurantWasteLog $p) => ($p->item?->name ?? '') . ' — ' . ($p->reason ?? '')],
        ];
    }
}
