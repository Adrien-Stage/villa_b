<?php

namespace App\Observers;

use App\Models\MinibarCheck;
use App\Models\ReceptionSale;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBuffetEntry;
use App\Models\RestaurantCustomerOrder;
use App\Models\ShopOrder;
use App\Services\TaxationService;
use Illuminate\Database\Eloquent\Model;

class SaleVatObserver
{
    public const SOURCES = [RestaurantCustomerOrder::class, ShopOrder::class, MinibarCheck::class,
        ReceptionSale::class, RestaurantBuffetEntry::class, RestaurantBanquet::class];

    public function saving(Model $sale): void
    {
        if ($sale->vat_snapshot !== null || ($sale->exists && !$sale->isDirty(['amount', 'total_amount', 'payment_status', 'status', 'billing']))) {
            return;
        }
        $confirmed = match (true) {
            $sale instanceof ReceptionSale => in_array($sale->payment_status, ['paid', 'charged_to_room'], true),
            $sale instanceof RestaurantBanquet => in_array($sale->status, [RestaurantBanquet::REALISE, RestaurantBanquet::SOLDE], true),
            $sale instanceof RestaurantBuffetEntry => true,
            $sale instanceof MinibarCheck => in_array($sale->billing, [MinibarCheck::BILLING_FOLIO, MinibarCheck::BILLING_A_FACTURER], true),
            default => $sale->payment_status === 'paid',
        };
        $total = (int) ($sale instanceof RestaurantBuffetEntry ? $sale->amount : $sale->total_amount);
        if ($confirmed && $total > 0) {
            $tax = app(TaxationService::class)->breakdown($total);
            $sale->setAttribute('vat_snapshot', json_encode([
                'total' => $total, 'rate' => $tax->rateCode, 'basis_points' => $tax->rateBasisPoints,
                'base' => $tax->ht, 'vat' => $tax->vatOnly, 'surtax' => $tax->surtax,
            ], JSON_THROW_ON_ERROR));
        }
    }
}
