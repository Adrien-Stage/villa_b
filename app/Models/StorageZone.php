<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une zone de rangement de l'économat : cave, épicerie, chambre froide,
 * magasin central. Elle dit où trouver un article ; le stock, lui, reste un
 * seul chiffre par article.
 */
class StorageZone extends Model
{
    use \App\Models\Concerns\VaALaCorbeille;

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function items(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDansLOrdre(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** La première zone en service : là où va un article créé sans zone. */
    public static function parDefaut(): ?self
    {
        return static::active()->dansLOrdre()->first();
    }
}
