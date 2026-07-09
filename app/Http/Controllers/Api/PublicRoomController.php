<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoomTypeResource;
use App\Models\RoomType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API publique (site vitrine) : types de chambres.
 *
 * Seuls les types actifs ayant au moins une chambre physique avec le statut
 * RoomStatus::AVAILABLE sont exposés — un type entièrement occupé/en
 * maintenance disparaît de la liste plutôt que d'afficher "0 disponible".
 */
class PublicRoomController extends Controller
{
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
