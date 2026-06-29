<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use App\Models\Booking;
use App\Enums\BookingStatus;
use App\Models\FolioItem;
use App\Services\CheckOutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantBillingController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'room_charge', 'other'];

    public function index(Request $request): View
    {
        $query = RestaurantCustomerOrder::query()
            ->withCount('items')
            ->orderByDesc('id');

        if ($request->filled('payment_status')) {
            $query->where('payment_status', (string) $request->input('payment_status'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('table')) {
            $table = trim((string) $request->input('table'));
            $query->where('table_number', 'ilike', "%{$table}%");
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('restaurant.billing.index', [
            'orders' => $orders,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    public function show(RestaurantCustomerOrder $order): View
    {
        $order->load(['items', 'booking.room', 'booking.customer', 'booking.guests']);

        $checkedInBookings = Booking::query()
            ->where('status', BookingStatus::CHECKED_IN)
            ->with(['room', 'customer'])
            ->orderByDesc('id')
            ->take(50)
            ->get();

        return view('restaurant.billing.show', [
            'order' => $order,
            'paymentMethods' => self::PAYMENT_METHODS,
            'checkedInBookings' => $checkedInBookings,
        ]);
    }

    public function markPaid(Request $request, RestaurantCustomerOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'booking_id' => ['nullable', 'integer'],
        ]);

        if ($order->payment_status === 'paid') {
            return back()->with('success', 'Commande deja payee.');
        }

        $method = (string) $validated['payment_method'];

        if ($method === 'room_charge') {
            $bookingId = (int) ($validated['booking_id'] ?? 0);
            if ($bookingId <= 0) {
                return back()->withErrors(['booking_id' => 'Selectionne un resident (sejour en cours).']);
            }

            $booking = Booking::query()
                ->where('id', $bookingId)
                ->where('status', BookingStatus::CHECKED_IN)
                ->with('customer')
                ->first();

            if (!$booking) {
                return back()->withErrors(['booking_id' => 'Resident invalide (le sejour doit etre en cours).']);
            }

            DB::transaction(function () use ($order, $booking, $method) {
                // Idempotence: ne pas recreer 2 fois la ligne folio.
                if (!$order->folio_item_id) {
                    $folio = FolioItem::create([
                        'booking_id' => $booking->id,
                        'customer_id' => $booking->customer_id,
                        'type' => FolioItem::TYPE_RESTAURANT,
                        'description' => "Restaurant — Commande #{$order->id} (Table {$order->table_number})",
                        'quantity' => 1,
                        'unit_price' => (int) $order->total_amount,
                        'total_price' => (int) $order->total_amount,
                        'is_complimentary' => false,
                        'earns_points' => true,
                        'recorded_by' => Auth::id(),
                        'occurred_at' => now(),
                        'notes' => $order->notes,
                    ]);

                    $order->folio_item_id = $folio->id;
                    $order->booking_id = $booking->id;
                }

                $order->payment_status = 'paid';
                $order->payment_method = $method;
                $order->amount_paid = (int) $order->total_amount;
                $order->paid_at = now();
                $order->paid_by = Auth::id();
                $order->save();
            });

            // Met a jour les totaux du booking pour reflet immediat (extras, total, solde)
            app(CheckOutService::class)->recalculateTotals($booking->fresh());
        } else {
            $order->update([
                'payment_status' => 'paid',
                'payment_method' => $method,
                'amount_paid' => (int) $order->total_amount,
                'paid_at' => now(),
                'paid_by' => Auth::id(),
            ]);
        }

        \App\Models\AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Paiement de " . number_format($order->total_amount / 100, 0, ',', ' ') . " FCFA enregistré pour la commande restaurant #{$order->id} (Méthode: {$method})",
            'restaurant',
            ['order_id' => $order->id, 'amount' => $order->total_amount, 'method' => $method]
        );

        return redirect()
            ->route('restaurant.billing.show', $order)
            ->with('success', 'Paiement enregistre.');
    }

    public function markUnpaid(RestaurantCustomerOrder $order): RedirectResponse
    {
        $order->update([
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'amount_paid' => 0,
            'paid_at' => null,
            'paid_by' => null,
        ]);

        return redirect()
            ->route('restaurant.billing.show', $order)
            ->with('success', 'Commande repassee en impayee.');
    }

    public function receipt(RestaurantCustomerOrder $order): View
    {
        $order->load(['items', 'booking.room', 'booking.customer', 'booking.guests']);

        return view('restaurant.billing.receipt', [
            'order' => $order,
        ]);
    }
}
