<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use Illuminate\View\View;

/**
 * Tableau de bord cuisine (KDS) : la file des bons de commande transmis par la
 * salle. Les cuisiniers y prennent les plats en préparation et les signalent
 * prêts, sans jamais parler directement à la salle.
 */
class RestaurantKitchenController extends Controller
{
    public function index(): View
    {
        // Les bons en cours de traitement en cuisine, du plus ancien au plus récent
        // (premier arrivé, premier préparé).
        $orders = RestaurantCustomerOrder::query()
            ->whereIn('status', [
                RestaurantCustomerOrder::STATUS_CONFIRMED,
                RestaurantCustomerOrder::STATUS_PREPARING,
                RestaurantCustomerOrder::STATUS_READY,
            ])
            ->with(['items', 'assignedServer:id,name'])
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
}
