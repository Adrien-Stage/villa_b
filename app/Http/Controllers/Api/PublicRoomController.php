<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API publique (site vitrine) : chambres et types de chambres.
 *
 * - /rooms       : chambres physiques individuelles disponibles (le site
 *   vitrine affiche une carte par chambre réellement créée dans l'app).
 * - /room-types  : regroupement par type (conservé pour compatibilité et
 *   pour les usages où le regroupement par catégorie est pertinent).
 *
 * Dans les deux cas, seules les entités actives et réellement disponibles
 * (RoomStatus::AVAILABLE) sont exposées.
 */
class PublicRoomController extends Controller
{
    /**
     * Chambres individuelles disponibles, infos du type aplaties.
     */
    public function rooms(): AnonymousResourceCollection
    {
        $rooms = Room::query()
            ->where('is_active', true)
            ->where('status', RoomStatus::AVAILABLE)
            ->whereHas('roomType', fn ($q) => $q->where('is_active', true))
            ->with(['roomType', 'images'])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->roomType->base_price ?? 0) <=> ($b->roomType->base_price ?? 0),
                fn ($a, $b) => strnatcasecmp($a->number, $b->number),
            ])
            ->values();

        return RoomResource::collection($rooms);
    }

    /**
     * Détail d'une chambre individuelle disponible.
     */
    public function roomShow(Room $room): RoomResource
    {
        abort_unless($room->is_active, 404);
        abort_unless($room->status === RoomStatus::AVAILABLE, 404);

        $room->load(['roomType', 'images']);
        abort_unless($room->roomType && $room->roomType->is_active, 404);

        return new RoomResource($room);
    }

    public function index(): AnonymousResourceCollection
    {
        $availableRoomsScope = function ($query) {
            $query->where('status', RoomStatus::AVAILABLE)->where('is_active', true);
        };

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->whereHas('rooms', $availableRoomsScope)
            ->withCount(['rooms as available_rooms_count' => $availableRoomsScope])
            ->orderBy('base_price')
            ->get();

        return RoomTypeResource::collection($roomTypes);
    }

    public function show(RoomType $roomType): RoomTypeResource
    {
        abort_unless($roomType->is_active, 404);

        $roomType->loadCount(['rooms as available_rooms_count' => function ($query) {
            $query->where('status', RoomStatus::AVAILABLE)->where('is_active', true);
        }]);

        abort_if($roomType->available_rooms_count === 0, 404);

        return new RoomTypeResource($roomType);
    }
}
