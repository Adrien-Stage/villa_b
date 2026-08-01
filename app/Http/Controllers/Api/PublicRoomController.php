<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API publique (site vitrine) : chambres et types de chambres.
 *
 * - /rooms       : chambres physiques individuelles commercialisables (le site
 *   vitrine affiche une carte par chambre réellement créée dans l'app).
 * - /room-types  : regroupement par type (conservé pour compatibilité et
 *   pour les usages où le regroupement par catégorie est pertinent).
 *
 * Les chambres occupées sont exposées : une chambre prise cette semaine se vend
 * pour le mois prochain, la masquer ferait perdre la réservation. Chacune porte
 * son état du moment et ses périodes déjà prises (bloc « availability » de
 * RoomResource), au site de refuser les bonnes dates dans son calendrier.
 * C'est la période demandée, pas le statut courant, qui décide de l'acceptation.
 *
 * Restent masquées les chambres en maintenance ou hors service : leur
 * indisponibilité n'a pas d'échéance, les afficher n'apporterait au client
 * qu'une carte sur laquelle aucune date n'est retenable.
 */
class PublicRoomController extends Controller
{
    /** Chambres vendables : tout sauf maintenance et hors service. */
    private function sellableScope(): \Closure
    {
        return fn ($query) => $query->sellable();
    }

    /**
     * Chambres vendables du catalogue, infos du type aplaties.
     */
    public function rooms(): AnonymousResourceCollection
    {
        $rooms = Room::query()
            ->sellable()
            ->whereHas('roomType', fn ($q) => $q->where('is_active', true))
            ->with(['roomType', 'images'])
            ->get()
            ->sortBy([
                // Les chambres occupables tout de suite passent devant : à prix
                // égal, on met en avant ce que le client peut prendre maintenant.
                fn ($a, $b) => ($a->status === \App\Enums\RoomStatus::AVAILABLE ? 0 : 1)
                    <=> ($b->status === \App\Enums\RoomStatus::AVAILABLE ? 0 : 1),
                fn ($a, $b) => ($a->roomType->base_price ?? 0) <=> ($b->roomType->base_price ?? 0),
                fn ($a, $b) => strnatcasecmp($a->number, $b->number),
            ])
            ->values();

        return RoomResource::collection($rooms);
    }

    /**
     * Détail d'une chambre vendable.
     */
    public function roomShow(Room $room): RoomResource
    {
        abort_unless(app(\App\Services\RoomAvailabilityService::class)->isSellable($room), 404);

        $room->load(['roomType', 'images']);
        abort_unless($room->roomType && $room->roomType->is_active, 404);

        return new RoomResource($room);
    }

    public function index(): AnonymousResourceCollection
    {
        $scope = $this->sellableScope();

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->whereHas('rooms', $scope)
            ->withCount(['rooms as available_rooms_count' => $scope])
            ->orderBy('base_price')
            ->get();

        return RoomTypeResource::collection($roomTypes);
    }

    public function show(RoomType $roomType): RoomTypeResource
    {
        abort_unless($roomType->is_active, 404);

        $roomType->loadCount(['rooms as available_rooms_count' => $this->sellableScope()]);

        abort_if($roomType->available_rooms_count === 0, 404);

        return new RoomTypeResource($roomType);
    }
}
