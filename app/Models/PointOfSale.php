<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

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

    /** Modes de service d'un restaurant. */
    public const MODE_CARTE = 'carte';

    public const MODE_BUFFET = 'buffet';

    public const MODES_SERVICE = [
        self::MODE_CARTE => 'À la carte',
        self::MODE_BUFFET => 'Buffet',
    ];

    /**
     * Services qu'un restaurant exploite. Un bar de piscine sert sans
     * cuisine, une cuisine de production prépare sans salle : chacun dit
     * lesquels il a, et l'application n'ouvre que ceux-là.
     */
    public const SERVICE_SALLE = 'salle';

    public const SERVICE_CUISINE = 'cuisine';

    public const SERVICE_BAR = 'bar';

    public const SERVICE_STOCK = 'stock';

    public const SERVICES = [
        self::SERVICE_SALLE => 'Salle',
        self::SERVICE_CUISINE => 'Cuisine',
        self::SERVICE_BAR => 'Bar',
        self::SERVICE_STOCK => 'Stock',
    ];

    /** Ce que chaque service ouvre, dit à qui le règle. */
    public const DESCRIPTIONS_SERVICES = [
        self::SERVICE_SALLE => 'Commandes à table, salles, buffets.',
        self::SERVICE_CUISINE => 'Écran cuisine : les plats y sont préparés.',
        self::SERVICE_BAR => 'Écran bar : les boissons y sont préparées.',
        self::SERVICE_STOCK => "Garde-manger, inventaires, pertes, livraisons de l'économat.",
    ];

    protected $fillable = [
        'code', 'name', 'slug', 'kind', 'service_modes', 'services', 'series_prefix', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'service_modes' => 'array',
        'services' => 'array',
    ];

    /** Le personnel affecté à ce point de vente — pour un restaurant, son équipe. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopeRestaurants(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_RESTAURATION);
    }

    /** Ce restaurant sert-il dans ce mode ? Un restaurant sans réglage sert à la carte. */
    public function sert(string $mode): bool
    {
        return in_array($mode, $this->service_modes ?: [self::MODE_CARTE], true);
    }

    /** @return list<string> libellés des modes de service */
    public function libellesModes(): array
    {
        return array_values(array_map(
            static fn (string $m): string => self::MODES_SERVICE[$m] ?? $m,
            $this->service_modes ?: [self::MODE_CARTE]
        ));
    }

    /**
     * Ce restaurant exploite-t-il ce service ? Un restaurant sans réglage les
     * exploite tous, comme avant que chacun ne les choisisse.
     */
    public function offre(string $service): bool
    {
        return in_array($service, $this->services ?? array_keys(self::SERVICES), true);
    }

    /**
     * Refuse ce qui suppose un service que ce restaurant n'exploite pas.
     *
     * @throws ValidationException
     */
    public function exiger(string $service): void
    {
        if (! $this->offre($service)) {
            throw ValidationException::withMessages([
                'restaurant' => "« {$this->name} » n'a pas de " . mb_strtolower(self::SERVICES[$service] ?? $service)
                    . " : ce service s'active dans Paramètres › Restaurant.",
            ]);
        }
    }

    /** @return list<string> libellés des services exploités */
    public function libellesServices(): array
    {
        return array_values(array_filter(
            self::SERVICES,
            fn (string $libelle, string $service): bool => $this->offre($service),
            ARRAY_FILTER_USE_BOTH
        ));
    }

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
