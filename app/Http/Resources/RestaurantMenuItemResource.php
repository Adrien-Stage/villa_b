<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantMenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'image' => $this->image_path ? asset('storage/' . ltrim($this->image_path, '/')) : null,
            'price' => [
                'amount' => $this->price,
                'formatted' => number_format($this->price / 100, 0, ',', ' ') . ' FCFA',
            ],
        ];
    }
}
