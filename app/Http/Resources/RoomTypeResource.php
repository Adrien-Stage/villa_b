<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation publique d'un type de chambre pour le site vitrine.
 * N'expose que ce qui est pertinent pour un visiteur — pas de champs internes
 * (tenant_id, timestamps...).
 */
class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'base_capacity' => $this->base_capacity,
            'max_capacity' => $this->max_capacity,
            'size_sqm' => $this->size_sqm,
            'bed_configuration' => $this->bed_configuration,
            'amenities' => $this->amenities ?? [],
            // URL absolue basée sur APP_URL (navigable), pas asset() qui
            // dépendrait du hostname interne en contexte Docker.
            'photos' => collect($this->photos ?? [])
                ->map(fn (string $path) => rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($path, '/'))
                ->all(),
            'price' => [
                'amount' => $this->base_price,
                'formatted' => $this->formattedBasePrice(),
            ],
            // Nombre de chambres de ce type actuellement disponibles à la vente
            // (statut RoomStatus::AVAILABLE) — seuls les types en ayant au moins
            // une sont exposés par le contrôleur (voir PublicRoomController).
            'available_rooms_count' => $this->available_rooms_count,
        ];
    }
}
