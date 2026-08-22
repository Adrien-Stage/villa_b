<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur de gestion des brouillons de réservation.
 *
 * Responsabilités :
 *  - Lister les brouillons actifs de l'agent connecté.
 *  - Sauvegarder automatiquement les données partielles à chaque étape.
 *  - Reprendre directement la session à l'étape exacte enregistrée avec pré-remplissage complet.
 *  - Supprimer ou abandonner un brouillon.
 */
class BookingDraftController extends Controller
{
    // ── Liste des brouillons ──────────────────────────────────────────────────

    /**
     * Liste tous les brouillons actifs de l'agent connecté.
     */
    public function index()
    {
        $drafts = BookingDraft::active()
            ->where('created_by', Auth::id())
            ->with(['customer', 'room.roomType'])
            ->latest('last_activity_at')
            ->get();

        return view('bookings.drafts.index', compact('drafts'));
    }

    // ── Sauvegarde automatique (AJAX) ─────────────────────────────────────────

    /**
     * Crée ou met à jour un brouillon depuis le wizard.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'draft_token'    => ['nullable', 'string', 'max:64'],
            'current_step'   => ['required', 'integer', 'min:1', 'max:4'],
            'customer_id'    => ['nullable', 'exists:customers,id'],
            'booker_id'      => ['nullable', 'exists:customers,id'],
            'check_in'       => ['nullable', 'date'],
            'check_out'      => ['nullable', 'date', 'after:check_in'],
            'check_in_time'  => ['nullable', 'string', 'max:10'],
            'adults'         => ['nullable', 'integer', 'min:1'],
            'children'       => ['nullable', 'integer', 'min:0'],
            'source'         => ['nullable', 'string', 'max:30'],
            'room_id'        => ['nullable', 'exists:rooms,id'],
            'notes'          => ['nullable', 'string'],
        ]);

        $tenantId = Auth::user()->tenant_id;

        $draft = BookingDraft::upsertDraft($validated['draft_token'] ?? null, Auth::id(), [
            'tenant_id'     => $tenantId,
            'current_step'  => $validated['current_step'],
            'customer_id'   => $validated['customer_id'] ?? null,
            'booker_id'     => $validated['booker_id'] ?? null,
            'check_in'      => $validated['check_in'] ?? null,
            'check_out'     => $validated['check_out'] ?? null,
            'check_in_time' => $validated['check_in_time'] ?? null,
            'adults'        => $validated['adults'] ?? null,
            'children'      => $validated['children'] ?? 0,
            'source'        => $validated['source'] ?? 'direct',
            'room_id'       => $validated['room_id'] ?? null,
            'notes'         => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success'    => true,
            'token'      => $draft->token,
            'draft_id'   => $draft->id,
            'step_label' => $draft->stepLabel(),
            'expires_at' => $draft->expires_at?->toIso8601String(),
        ]);
    }

    // ── Vue récapitulative de reprise ─────────────────────────────────────────

    /**
     * Affiche l'écran récapitulatif du brouillon avant reprise.
     */
    public function resume(Request $request, string $token)
    {
        $draft = BookingDraft::active()
            ->where('token', $token)
            ->where('created_by', Auth::id())
            ->with(['customer', 'booker', 'room.roomType'])
            ->firstOrFail();

        // Si l'utilisateur clique directement sur reprendre sans passer par la prévisualisation
        if ($request->boolean('direct')) {
            return $this->continue($token);
        }

        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')
                ->with('warning', 'Vous devez ouvrir votre caisse avant de reprendre un brouillon.');
        }

        $customers = Customer::orderBy('last_name')->orderBy('first_name')->get();
        $partnerOrganizations = \App\Models\PartnerOrganization::validOn()->orderBy('name')->get();

        return view('bookings.drafts.resume', compact('draft', 'customers', 'partnerOrganizations'));
    }

    // ── Reprise directe et injection du state à l'étape active ────────────────

    /**
     * Restaure et ouvre directement le wizard à l'étape exacte où l'utilisateur s'est arrêté,
     * avec toutes les données déjà renseignées injectées et le stepper positionné sur l'étape courante.
     */
    public function continue(string $token)
    {
        $draft = BookingDraft::active()
            ->where('token', $token)
            ->where('created_by', Auth::id())
            ->with(['customer', 'booker', 'room.roomType'])
            ->firstOrFail();

        // 1. Vérifier la caisse
        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')
                ->with('warning', 'Vous devez ouvrir votre caisse avant de reprendre un brouillon.');
        }

        // Mettre à jour l'horodatage d'activité
        $draft->touch();

        // ── CAS 1 : Étape 1 (Sélection du client) ─────────────────────────────
        if ($draft->current_step <= 1 || empty($draft->customer_id)) {
            return redirect()->route('bookings.create', [
                'draft_token' => $draft->token,
                'booker_id'   => $draft->booker_id,
            ]);
        }

        // ── CAS 2 : Étape 2 (Dates et personnes) ──────────────────────────────
        if ($draft->current_step === 2 || empty($draft->check_in) || empty($draft->check_out)) {
            return redirect()->route('bookings.create', [
                'customer_id'   => $draft->customer_id,
                'booker_id'     => $draft->booker_id,
                'check_in'      => $draft->check_in?->format('Y-m-d'),
                'check_out'     => $draft->check_out?->format('Y-m-d'),
                'check_in_time' => $draft->check_in_time ?? '14:00',
                'adults'        => $draft->adults ?? 1,
                'children'      => $draft->children ?? 0,
                'source'        => $draft->source ?? 'direct',
                'draft_token'   => $draft->token,
            ]);
        }

        // ── CAS 3 : Étape 3 (Sélection de la chambre) ─────────────────────────
        if ($draft->current_step === 3 || empty($draft->room_id)) {
            return $this->renderSelectRoomStep($draft);
        }

        // ── CAS 4 : Étape 4 (Confirmation & paiement) ────────────────────────
        return $this->renderConfirmStep($draft);
    }

    /**
     * Restaure et affiche directement la vue Étape 3 (Sélection de la chambre)
     * avec toutes les chambres disponibles, indicateurs de ménage et détection de rotation.
     */
    private function renderSelectRoomStep(BookingDraft $draft)
    {
        $customer    = Customer::findOrFail($draft->customer_id);
        $bookerId    = $draft->booker_id;
        $checkIn     = $draft->check_in->format('Y-m-d');
        $checkOut    = $draft->check_out->format('Y-m-d');
        $checkInTime = $draft->check_in_time ?? '14:00';
        $adults      = $draft->adults ?? 1;
        $children    = $draft->children ?? 0;
        $source      = $draft->source ?? 'direct';
        $totalPeople = $adults + $children;
        $maxCapacityLimit = RoomType::max('max_capacity') ?? 4;
        $draftToken  = $draft->token;

        $availabilityService = app(\App\Services\RoomAvailabilityService::class);
        $standardCheckOutTime = $availabilityService->checkOutTime();

        $candidateRooms = Room::availableBetween($checkIn, $checkOut)
            ->with(['roomType', 'statusHistory'])
            ->whereHas('roomType', fn($q) => $q->where('max_capacity', '>=', $totalPeople))
            ->get();

        $roomIds = $candidateRooms->pluck('id');

        $currentBookings = Booking::query()
            ->whereIn('room_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>', today())
            ->get()
            ->keyBy('room_id');

        $sameDayPriorBookings = Booking::query()
            ->whereIn('room_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_out', $checkIn)
            ->get()
            ->keyBy('room_id');

        foreach ($candidateRooms as $room) {
            $delay = $availabilityService->delayMinutesFor($room->roomType);
            $readyTime = \Carbon\Carbon::parse($standardCheckOutTime)->addMinutes($delay)->format('H:i');

            // 1. Occupation actuelle (en temps réel)
            $currBooking = $currentBookings->get($room->id);
            $room->is_currently_occupied = ($room->status === \App\Enums\RoomStatus::OCCUPIED) || ($currBooking !== null);
            $room->current_checkout_date = $currBooking?->check_out?->format('Y-m-d');
            $room->current_checkout_formatted = $currBooking?->check_out?->locale('fr')->isoFormat('D MMM YYYY');
            $room->current_checkout_time = $standardCheckOutTime;
            $room->current_ready_time = $readyTime;
            $room->cleaning_delay_minutes = $delay;

            // 2. Conflit de rotation : départ précédent le jour de l'arrivée demandée
            $sameDayBooking = $sameDayPriorBookings->get($room->id);
            $room->has_same_day_departure = ($sameDayBooking !== null);
            $room->same_day_prior_booking = $sameDayBooking;
            $room->same_day_checkout_time = $standardCheckOutTime;
            $room->same_day_ready_time = $readyTime;
            $room->has_rotation_conflict = $room->has_same_day_departure && ($checkInTime < $readyTime);
        }

        $availableRooms = $candidateRooms->groupBy('room_type_id');
        $roomTypes = RoomType::whereIn('id', $availableRooms->keys())->get();

        return view('bookings.select-room', compact(
            'customer',
            'bookerId',
            'checkIn',
            'checkOut',
            'checkInTime',
            'adults',
            'children',
            'source',
            'availableRooms',
            'roomTypes',
            'maxCapacityLimit',
            'draftToken'
        ));
    }

    /**
     * Restaure et affiche directement la vue Étape 4 (Confirmation & Paiement d'acompte)
     * avec tarification, remises convention, packs et acompte calculés.
     */
    private function renderConfirmStep(BookingDraft $draft)
    {
        $room        = Room::with('roomType')->findOrFail($draft->room_id);
        $customer    = Customer::with('partnerOrganization')->findOrFail($draft->customer_id);
        $bookerId    = $draft->booker_id;
        $checkIn     = \Carbon\Carbon::parse($draft->check_in);
        $checkOut    = \Carbon\Carbon::parse($draft->check_out);
        $checkInTime = $draft->check_in_time ?? '14:00';
        $nights      = $checkIn->diffInDays($checkOut);
        $adultsCount = $draft->adults ?? 1;
        $childrenCount = $draft->children ?? 0;
        $source      = $draft->source ?? 'direct';
        $notes       = $draft->notes ?? '';
        $draftToken  = $draft->token;

        $pricePerNight = $room->roomType->getCalculatedPricePerNight($adultsCount, $childrenCount) / 100;
        $totalRoomAmount = $nights * $pricePerNight;

        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $tenantSettings = \App\Models\Tenant::where('id', $tenantId)->value('settings') ?? [];
        $minDepositPercentage = $tenantSettings['reception']['min_deposit_percentage'] ?? 30;
        $maxDiscountPercentage = $tenantSettings['reception']['max_discount_percentage'] ?? 10;

        $partnerOrganization = $customer->partnerOrganization;
        if ($partnerOrganization && !$partnerOrganization->isValidOn($checkIn)) {
            $partnerOrganization = null;
        }

        $occupants = (int) $adultsCount + (int) $childrenCount;
        $roomPackages = \App\Models\RoomPackage::active()
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($p) => $p->appliesToRoomType($room->room_type_id))
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'contents'    => $p->contentLabels(),
                'mode'        => $p->pricingModeLabel(),
                'amount'      => (int) ($p->amountFor($nights, $occupants) / 100),
                'room_discount' => (int) ($p->roomDiscountFor((int) round($totalRoomAmount * 100), $nights) / 100),
                'discount_type'  => $p->room_discount_type,
                'discount_value' => $p->room_discount_type === \App\Models\RoomPackage::DISCOUNT_AMOUNT
                    ? (int) ($p->room_discount_value / 100)
                    : (int) $p->room_discount_value,
            ])
            ->values()
            ->all();

        return view('bookings.confirm', [
            'customerId' => $customer->id,
            'bookerId' => $bookerId,
            'partnerOrganization' => $partnerOrganization,
            'roomPackages' => $roomPackages,
            'partnerRoomDiscount' => $partnerOrganization
                ? (int) ($partnerOrganization->roomDiscountFor((int) round($totalRoomAmount * 100), $nights) / 100)
                : 0,
            'room' => $room,
            'checkIn' => $draft->check_in->format('Y-m-d'),
            'checkOut' => $draft->check_out->format('Y-m-d'),
            'checkInTime' => $checkInTime,
            'nights' => $nights,
            'adultsCount' => $adultsCount,
            'childrenCount' => $childrenCount,
            'source' => $source,
            'notes' => $notes,
            'pricePerNight' => $pricePerNight,
            'totalRoomAmount' => $totalRoomAmount,
            'minDepositPercentage' => $minDepositPercentage,
            'maxDiscountPercentage' => $maxDiscountPercentage,
            'draftToken' => $draftToken,
        ]);
    }

    // ── Suppression / Abandon ─────────────────────────────────────────────────

    /**
     * Marque un brouillon comme abandonné (soft-delete logique).
     */
    public function destroy(string $token)
    {
        $draft = BookingDraft::where('token', $token)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $draft->markAbandoned();

        return redirect()->route('bookings.drafts.index')
            ->with('success', 'Brouillon supprimé.');
    }

    // ── Nettoyage (utilisé par scheduled command) ─────────────────────────────

    /**
     * Abandonne tous les brouillons expirés.
     */
    public static function pruneExpired(): int
    {
        return BookingDraft::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'abandoned']);
    }
}
