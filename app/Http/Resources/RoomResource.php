<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation publique d'une chambre individuelle pour le site vitrine.
 * Aplatit les informations commerciales du type de chambre (nom, prix,
 * capacités, équipements) avec les caractéristiques propres à la chambre
 * (numéro, étage, vue). Les photos sont celles de la chambre si elle en a,
 * sinon celles du type — jamais de champs internes (statut, tenant_id...).
 *
 * @property \App\Models\Room $resource
 */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->roomType;

        // Photos propres à la chambre (RoomImage), sinon photos du type.
        $roomPhotos = $this->images
            ->map(fn ($img) => asset('storage/' . ltrim($img->path, '/')))
            ->all();

        $typePhotos = collect($type?->photos ?? [])
            ->map(fn (string $path) => asset('storage/' . ltrim($path, '/')))
            ->all();

        return [
            'id'                => $this->id,
            'number'            => $this->number,
            'floor'             => $this->floor,
            'view_type'         => $this->view_type,

            // Hérité du type de chambre
            'type_id'           => $type?->id,
            'name'              => $type?->name,
            'description'       => $type?->description,
            'base_capacity'     => $type?->base_capacity,
            'max_capacity'      => $type?->max_capacity,
            'size_sqm'          => $type?->size_sqm,
            'bed_configuration' => $type?->bed_configuration,
            'amenities'         => $type?->amenities ?? [],
            'photos'            => !empty($roomPhotos) ? $roomPhotos : $typePhotos,
            'price'             => [
                'amount'    => $type?->base_price,
                'formatted' => $type?->formattedBasePrice(),
            ],
        ];
    }
}
