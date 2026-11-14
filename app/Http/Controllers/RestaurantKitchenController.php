<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\ServiceStoreMovement;
use App\Models\User;
use App\Notifications\RestaurantOrderReady;
use App\Services\RestaurantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Tableau de bord cuisine (KDS) et bar : la file des bons transmis par la
 * salle. Chaque restaurant a sa cuisine et son bar : la cuisine voit les plats
 * de son restaurant, le bar ses boissons.
 */
class RestaurantKitchenController extends Controller
{
    public function index(): View
    {
        // Les bons en cours de traitement en cuisine, du plus ancien au plus récent
        // (premier arrivé, premier préparé). Un bon fait de boissons seules ne
        // passe pas par la cuisine.
        $orders = RestaurantCustomerOrder::query()
            ->visiblesPour(Auth::user())
            ->whereIn('status', [
                RestaurantCustomerOrder::STATUS_CONFIRMED,
                RestaurantCustomerOrder::STATUS_PREPARING,
                RestaurantCustomerOrder::STATUS_READY,
            ])
            ->whereHas('items', fn ($q) => $q->enCuisine())
            ->with(['items' => fn ($q) => $q->enCuisine(), 'assignedServer:id,name', 'pointOfSale:id,name'])
            ->orderBy('sent_to_kitchen_at')
            ->orderBy('id')
            ->get();

        $columns = [
            RestaurantCustomerOrder::STATUS_CONFIRMED => $orders->where('status', RestaurantCustomerOrder::STATUS_CONFIRMED)->values(),
            RestaurantCustomerOrder::STATUS_PREPARING => $orders->where('status', RestaurantCustomerOrder::STATUS_PREPARING)->values(),
            RestaurantCustomerOrder::STATUS_READY => $orders->where('status', RestaurantCustomerOrder::STATUS_READY)->values(),
        ];

        return view('restaurant.kitchen.index', [
            'columns' => $columns,
        ]);
    }

    /** Le bar : les boissons des bons en cours, à préparer puis à signaler prêtes. */
    public function bar(RestaurantContext $context): View
    {
        $orders = RestaurantCustomerOrder::query()
            ->visiblesPour(Auth::user())
            ->whereIn('status', [
                RestaurantCustomerOrder::STATUS_CONFIRMED,
                RestaurantCustomerOrder::STATUS_PREPARING,
                RestaurantCustomerOrder::STATUS_READY,
            ])
            ->whereIn('point_of_sale_id', $context->offrant(Auth::user(), PointOfSale::SERVICE_BAR)->pluck('id'))
            ->whereHas('items', fn ($q) => $q->auBar()->whereNull('ready_at'))
            ->with(['items' => fn ($q) => $q->auBar()->whereNull('ready_at'), 'assignedServer:id,name', 'pointOfSale:id,name'])
            ->orderBy('sent_to_kitchen_at')
            ->orderBy('id')
            ->get();

        $movements = ServiceStoreMovement::query()->with('item:id,name,unit')
            ->whereIn('source_type', [ServiceStoreMovement::SOURCE_BAR_SALE, ServiceStoreMovement::SOURCE_BAR_RETURN, ServiceStoreMovement::SOURCE_BAR_VARIANCE])
            ->whereIn('source_id', $orders->modelKeys())->orderBy('id')->get();
        $reversed = $movements->where('source_type', ServiceStoreMovement::SOURCE_BAR_RETURN)->pluck('details.reverses');
        $allocations = $movements->where('source_type', ServiceStoreMovement::SOURCE_BAR_VARIANCE)->groupBy('details.offsets');
        $preparations = $movements->where('source_type', ServiceStoreMovement::SOURCE_BAR_SALE)->whereNotIn('id', $reversed)
            ->groupBy('details.order_line_id');

        return view('restaurant.bar.index', compact('orders', 'preparations', 'allocations'));
    }

    /**
     * Le bar a servi les boissons d'un bon. Le serveur est prévenu ; un bon
     * fait de boissons seules est alors prêt.
     */
    public function barReady(RestaurantCustomerOrder $order): RedirectResponse
    {
        $context = app(RestaurantContext::class);
        abort_unless(in_array($order->point_of_sale_id, $context->idsVisibles(Auth::user()), true), 404);

        $marked = DB::transaction(function () use ($order) {
            $order = RestaurantCustomerOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($order->pointOfSale?->offre(PointOfSale::SERVICE_BAR) && $order->pointOfSale->is_active, 404);
            if (! in_array($order->status, [RestaurantCustomerOrder::STATUS_CONFIRMED, RestaurantCustomerOrder::STATUS_PREPARING, RestaurantCustomerOrder::STATUS_READY], true)) {
                return false;
            }

            $marked = $order->items()->auBar()->whereNull('ready_at')->update(['ready_at' => now()]);

            if ($marked && ! $order->items()->enCuisine()->exists() && $order->status !== RestaurantCustomerOrder::STATUS_READY) {
                $order->update(['status' => RestaurantCustomerOrder::STATUS_READY, 'ready_at' => now()]);
            }

            return $marked > 0;
        });

        if (! $marked) {
            return back();
        }

        if ($order->assigned_server_id) {
            try {
                User::find($order->assigned_server_id)?->notify(new RestaurantOrderReady($order->fresh(), fromBar: true));
            } catch (\Throwable $e) {
                Log::error("Notification bar, commande #{$order->id} : " . $e->getMessage());
            }
        }

        return back()->with('success', 'Boissons prêtes : le serveur est prévenu.');
    }
}
