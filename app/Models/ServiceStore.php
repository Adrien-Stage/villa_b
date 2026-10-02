<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dépôt de service : stock détenu par un service hors du magasin central
 * (étages, mini-bar, bar, pâtisserie…), alimenté par les livraisons de
 * l'économat. Voir la migration create_service_stores_tables.
 */
class ServiceStore extends Model
{
    protected $fillable = ['name', 'department', 'is_active', 'sort_order', 'tenant_id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(ServiceStoreStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ServiceStoreMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function departmentLabel(): string
    {
        return StockRequisition::DEPARTMENTS[$this->department] ?? $this->department;
    }

    /** Valeur du stock détenu, au coût moyen du dépôt. */
    public function stockValue(): int
    {
        return (int) $this->stocks->sum(fn (ServiceStoreStock $s) => $s->stockValue());
    }
}
