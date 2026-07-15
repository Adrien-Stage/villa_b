<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ServiceItem : catalogue des prestations facturables hors restaurant et boutique.
 *
 * Alimente le formulaire d'ajout de prestation au folio (activités, spa,
 * housekeeping, blanchisserie…) pour que la réception n'ait plus à saisir
 * les libellés et les prix à la main.
 */
class ServiceItem extends Model
{
    use HasFactory;

    const CATEGORY_ACTIVITY     = 'activity';
    const CATEGORY_SPA          = 'spa';
    const CATEGORY_HOUSEKEEPING = 'housekeeping';
    const CATEGORY_LAUNDRY      = 'laundry';
    const CATEGORY_MINIBAR      = 'minibar';
    const CATEGORY_OTHER        = 'other';

    /**
     * Catégories du catalogue → libellé affiché.
     * Les clés correspondent aux types de FolioItem, ce qui permet de
     * rattacher directement une prestation choisie à sa ligne de folio.
     */
    public const CATEGORIES = [
        self::CATEGORY_ACTIVITY     => 'Activités',
        self::CATEGORY_SPA          => 'Spa & bien-être',
        self::CATEGORY_HOUSEKEEPING => 'Housekeeping',
        self::CATEGORY_LAUNDRY      => 'Blanchisserie',
        self::CATEGORY_MINIBAR      => 'Minibar',
        self::CATEGORY_OTHER        => 'Autres prestations',
    ];

    protected $fillable = [
        'category',
        'name',
        'description',
        'price',
        'duration_minutes',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price'            => 'integer',
        'duration_minutes' => 'integer',
        'sort_order'       => 'integer',
        'is_active'        => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Autre';
    }

    /**
     * Prix en FCFA (la colonne est en centimes).
     */
    public function priceInFcfa(): int
    {
        return (int) ($this->price / 100);
    }
}
