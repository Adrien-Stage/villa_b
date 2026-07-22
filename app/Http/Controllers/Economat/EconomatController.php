<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use Illuminate\View\View;

/**
 * Tableau de bord de l'économat : état du stock, alertes de réapprovisionnement,
 * demandes en attente et derniers mouvements.
 */
class EconomatController extends Controller
{
    public function index(): View
    {
        $items = StockItem::active()->get();

        $stats = [
            'items_total'   => $items->count(),
            'out_of_stock'  => $items->filter->isOutOfStock()->count(),
            'below_min'     => $items->filter->isBelowThreshold()->count(),
            'stock_value'   => $items->sum(fn (StockItem $i) => $i->stockValue()),
            'pending_reqs'  => StockRequisition::pending()->count(),
            'open_orders'   => PurchaseOrder::open()->count(),
        ];

        // Articles à réapprovisionner en tête : ce que l'économe doit traiter.
        $alerts = StockItem::active()->belowThreshold()
            ->with('category', 'supplier')
            ->orderBy('current_stock')
            ->get();

        $pendingRequisitions = StockRequisition::pending()
            ->with('requestedBy', 'lines')
            ->latest()
            ->take(8)
            ->get();

        $recentMovements = StockMovement::with('item', 'user')
            ->latest('occurred_at')
            ->take(12)
            ->get();

        return view('economat.dashboard', compact('stats', 'alerts', 'pendingRequisitions', 'recentMovements'));
    }
}
