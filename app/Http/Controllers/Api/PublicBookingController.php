<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Notifications\WebsiteBookingReceived;
use App\Services\Notifier;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API publique (site vitrine) : demandes de réservation.
 *
 * Un visiteur du site réserve une chambre : on crée une réservation au
 * statut PENDING (source "website") — à confirmer par la réception — puis on
 * notifie managers et réception (in-app + push système). Pas d'auth : c'est
 * un formulaire public, protégé par throttle contre le spam.
 */
class PublicBookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_id'     => ['required', 'integer', 'exists:rooms,id'],
            'check_in'    => ['required', 'date', 'after_or_equal:today'],
            'check_out'   => ['required', 'date', 'after:check_in'],
            'adults'      => ['required', 'integer', 'min:1', 'max:20'],
            'children'    => ['nullable', 'integer', 'min:0', 'max:20'],
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'email'       => ['nullable', 'email', 'max:255'],
            'phone'       => ['required', 'string', 'max:30'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        /** @var Room $room */
        $room = Room::with('roomType')->findOrFail($data['room_id']);

        if (!$room->roomType || !$room->roomType->is_active) {
            return response()->json(['ok' => false, 'message' => "Cette chambre n'est plus disponible à la réservation."], 409);
        }

        // Capacité
        $guests = $data['adults'] + ($data['children'] ?? 0);
        if ($room->roomType->max_capacity && $guests > $room->roomType->max_capacity) {
            return response()->json(['ok' => false, 'message' => "Le nombre de personnes dépasse la capacité de la chambre ({$room->roomType->max_capacity} max)."], 422);
        }

        // Disponibilité sur les dates demandées. Même autorité que la réception :
        // une chambre occupée aujourd'hui accepte une réservation pour plus tard,
        // et le tampon de ménage est appliqué à l'identique des deux côtés.
        $refus = app(RoomAvailabilityService::class)
            ->conflictReason($room, $data['check_in'], $data['check_out']);

        if ($refus !== null) {
            return response()->json(['ok' => false, 'message' => $refus], 409);
        }

        // Client : réutilise un client existant (email ou téléphone), sinon en crée un
        $customer = null;
        if (!empty($data['email'])) {
            $customer = Customer::where('email', $data['email'])->first();
        }
        if (!$customer) {
            $customer = Customer::where('phone', $data['phone'])->first();
        }
        if (!$customer) {
            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'] ?? null,
                'phone'      => $data['phone'],
                'tenant_id'  => $room->tenant_id,
            ]);
        }

        // Tarification à partir du type de chambre
        $checkIn  = \Carbon\Carbon::parse($data['check_in']);
        $checkOut = \Carbon\Carbon::parse($data['check_out']);
        $nights   = max(1, $checkIn->diffInDays($checkOut));
        $pricePerNight = (int) ($room->roomType->base_price ?? 0);
        $totalRoom = $nights * $pricePerNight;

        $booking = Booking::create([
            'room_id'           => $room->id,
            'customer_id'       => $customer->id,
            'status'            => BookingStatus::PENDING,
            'check_in'          => $checkIn->toDateString(),
            'check_out'         => $checkOut->toDateString(),
            'adults_count'      => $data['adults'],
            'children_count'    => $data['children'] ?? 0,
            'total_nights'      => $nights,
            'price_per_night'   => $pricePerNight,
            'total_room_amount' => $totalRoom,
            'extras_amount'     => 0,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'total_amount'      => $totalRoom,
            'deposit_amount'    => 0,
            'paid_amount'       => 0,
            'balance_due'       => $totalRoom,
            'source'            => 'website',
            'notes'             => $data['notes'] ?? null,
            'created_by'        => null,
            'tenant_id'         => $room->tenant_id,
        ]);

        // Notifier managers + réception (in-app + push système). Passe par le
        // Notifier : il résout aussi les rôles portés par le pivot, donc les
        // utilisateurs multi-modules ne sont pas oubliés.
        app(Notifier::class)->toRoles(['manager', 'reception'], new WebsiteBookingReceived($booking));

        return response()->json([
            'ok'             => true,
            'booking_number' => $booking->booking_number,
            'message'        => 'Votre demande de réservation a bien été enregistrée. La réception vous recontactera pour la confirmer.',
        ], 201);
    }
}
