<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espace : là où la prestation se déroule.
 *
 * Distinct du point de vente, qui dit où le chiffre se comptabilise. Un
 * banquet se tient dans la salle de l'un ou l'autre restaurant tout en
 * facturant sur sa propre série : l'espace change, le point de vente non.
 *
 * Les confondre empêcherait de tenir deux banquets dans deux salles, et de
 * voir qu'un banquet et le service du soir se disputent la même salle.
 */
class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'capacity', 'point_of_sale_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Espaces qu'aucun point de vente ne revendique : salles polyvalentes. */
    public function scopePolyvalents(Builder $query): Builder
    {
        return $query->whereNull('point_of_sale_id');
    }
}
