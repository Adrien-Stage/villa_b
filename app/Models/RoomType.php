<?php
// app/Models/RoomType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RoomType : Catégorie de chambre
 * 
 * Exemples : Standard, Supérieure, Suite, Suite Présidentielle
 * 
 * CDC Section 4.3.1 : Paramétrage capacité, équipements, photos
 */
class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_capacity',
        'max_capacity',
        'base_price',
        'amenities',
        'photos',
        'size_sqm',
        'bed_configuration',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'amenities' => 'array',
        'photos' => 'array',
        'base_price' => 'integer', // Stocker en centimes pour éviter les floats
        'is_active' => 'boolean',
    ];

    /**
     * Les chambres physiques de ce type
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Les tarifs spécifiques pour ce type (section 4.6)
     */
    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    /**
     * Helper : Prix formaté en FCFA
     */
    public function formattedBasePrice(): string
    {
        return number_format($this->base_price / 100, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Calcule le prix par nuit en fonction du nombre d'adultes et d'enfants.
     * Applique une surcharge de +10% (ou le taux configuré dans les settings) 
     * si le nombre de personnes dépasse la capacité de base (base_capacity).
     */
    public function getCalculatedPricePerNight(int $adults, int $children = 0, ?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? $this->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $tenantSettings = \App\Models\Tenant::where('id', $tenantId)->value('settings') ?? [];
        $surchargePercentage = $tenantSettings['reception']['capacity_surcharge_percentage'] ?? 10;

        $totalPeople = $adults + $children;

        if ($totalPeople > $this->base_capacity) {
            $surcharged = $this->base_price * (1 + $surchargePercentage / 100);
            return (int) round($surcharged);
        }

        return $this->base_price;
    }
}