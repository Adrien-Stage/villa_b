<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Point de vente : là où le chiffre se comptabilise.
 *
 * Un établissement réel en aligne cinq sur son journal des encaissements —
 * HOTEL, KOTIBE, BALENG, MINI BAR, BANQUET — chacun avec sa série de
 * numérotation et sa ligne de récapitulatif. Restaurant, Boutique et
 * Réception étaient jusqu'ici trois silos écrits en dur : ajouter un second
 * restaurant demandait du code.
 *
 * À ne pas confondre avec l'espace, qui dit où la prestation se déroule.
 */
class PointOfSale extends Model
{
    use HasFactory;

    // Laravel déduirait « point_of_sales » : le pluriel porte sur
    // « point », non sur « sale ».
    protected $table = 'points_of_sale';

    public const KIND_HEBERGEMENT  = 'hebergement';
    public const KIND_RESTAURATION = 'restauration';
    public const KIND_BOUTIQUE     = 'boutique';
    public const KIND_MINI_BAR     = 'mini_bar';
    public const KIND_BANQUET      = 'banquet';

    protected $fillable = [
        'code', 'name', 'slug', 'kind', 'series_prefix', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /**
     * Numéro de pièce préfixé par la série du point de vente.
     *
     * C'est ce préfixe qui rend une note reconnaissable sur un journal papier :
     * « KOT-4853 » se rattache d'un coup d'œil, « 4853 » ne se rattache à rien.
     */
    public function numero(int|string $sequence): string
    {
        return $this->series_prefix === null || $this->series_prefix === ''
            ? (string) $sequence
            : $this->series_prefix . $sequence;
    }
}
