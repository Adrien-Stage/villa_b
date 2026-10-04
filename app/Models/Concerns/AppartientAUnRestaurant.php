<?php

namespace App\Models\Concerns;

use App\Models\PointOfSale;
use App\Models\User;
use App\Services\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qui appartient à un restaurant : sa carte, sa cuisine, son garde-manger,
 * ses inventaires, ses commandes…
 *
 * - À la création, l'enregistrement rejoint le restaurant où l'on travaille,
 *   quel que soit le chemin qui le crée (écran, import, service).
 * - Une adresse qui le désigne ne l'ouvre qu'à qui peut voir son restaurant :
 *   le serveur d'un restaurant n'ouvre pas une commande de l'autre en
 *   devinant son numéro.
 */
trait AppartientAUnRestaurant
{
    public static function bootAppartientAUnRestaurant(): void
    {
        static::creating(function ($modele): void {
            if (empty($modele->point_of_sale_id)) {
                $modele->point_of_sale_id = app(RestaurantContext::class)->pourCreation(auth()->user())?->id;
            }
        });
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    /** Restreint aux restaurants que cette personne voit maintenant. */
    public function scopeVisiblesPour(Builder $requete, ?User $user): Builder
    {
        return app(RestaurantContext::class)->restreindre($requete, $user, $this->qualifyColumn('point_of_sale_id'));
    }

    /** Restreint à un restaurant précis. */
    public function scopeDuRestaurant(Builder $requete, PointOfSale|int|null $restaurant): Builder
    {
        $id = $restaurant instanceof PointOfSale ? $restaurant->id : $restaurant;

        return $id === null ? $requete : $requete->where($this->qualifyColumn('point_of_sale_id'), $id);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $requete = $this->resolveRouteBindingQuery($this, $value, $field);

        if ($user = auth()->user()) {
            app(RestaurantContext::class)->restreindreAuxAccessibles($requete, $user, $this->qualifyColumn('point_of_sale_id'));
        }

        return $requete->first();
    }
}
