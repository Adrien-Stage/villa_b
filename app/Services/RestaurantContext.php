<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Le restaurant dans lequel on travaille.
 *
 * L'hôtel exploite autant de restaurants qu'il veut ; chacun a sa carte, sa
 * cuisine et son bar, son garde-manger, son équipe et sa caisse. Le personnel
 * d'un restaurant ne voit que les restaurants où il est affecté. La direction
 * et le contrôle les voient tous, ensemble ou un par un. La réception, elle,
 * voit dans chacun les seules notes que des résidents ont reportées sur leur
 * séjour.
 *
 * Un établissement qui n'a qu'un restaurant n'a rien à cloisonner : tout le
 * monde y voit ce que ses droits lui ouvrent, comme avant.
 */
class RestaurantContext
{
    /** Rôles qui voient tous les restaurants à la fois. */
    public const VUE_GLOBALE = ['admin', 'manager', 'controller', 'quality_auditor', 'support'];

    /** Rôles du personnel d'un restaurant : ceux qu'on affecte à une équipe. */
    public const ROLES_DU_RESTAURANT = ['restaurant_manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier'];

    /** Clé de session du restaurant choisi ; « tous » pour la vue d'ensemble. */
    private const SESSION = 'restaurant_courant';

    public const TOUS = 'tous';

    /** @var Collection<int, PointOfSale>|null */
    private ?Collection $restaurants = null;

    /** @var array<int, Collection<int, PointOfSale>> */
    private array $accessibles = [];

    /** @return Collection<int, PointOfSale> restaurants actifs de l'établissement */
    public function restaurants(): Collection
    {
        return $this->restaurants ??= PointOfSale::query()->restaurants()->active()
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function plusieurs(): bool
    {
        return $this->restaurants()->count() > 1;
    }

    public function vueGlobale(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::VUE_GLOBALE);
    }

    /**
     * La réception voit, dans tous les restaurants, les notes que des résidents
     * ont reportées sur leur séjour — et seulement elles.
     */
    public function residentsSeulement(?User $user): bool
    {
        return $user !== null
            && ! $this->vueGlobale($user)
            && $user->exerce(['reception'])
            && ! $user->restaurants()->exists();
    }

    /** @return Collection<int, PointOfSale> restaurants que cette personne peut voir */
    public function accessibles(?User $user): Collection
    {
        $tous = $this->restaurants();

        if ($user === null || $tous->count() <= 1 || $this->vueGlobale($user) || $this->residentsSeulement($user)) {
            return $tous;
        }

        return $this->accessibles[$user->id] ??= $tous
            ->whereIn('id', $user->restaurants()->pluck('points_of_sale.id'))
            ->values();
    }

    /**
     * Restaurant choisi, ou null pour la vue d'ensemble. Une personne qui n'en
     * voit qu'un est toujours dans celui-là.
     */
    public function courant(?User $user): ?PointOfSale
    {
        $accessibles = $this->accessibles($user);

        if ($accessibles->count() <= 1) {
            return $accessibles->first();
        }

        $choix = session(self::SESSION);

        if ($choix === self::TOUS) {
            return null;
        }

        return $accessibles->firstWhere('id', (int) $choix)
            // Sans choix, la direction voit l'ensemble ; une équipe, son
            // premier restaurant.
            ?? ($this->vueGlobale($user) || $this->residentsSeulement($user) ? null : $accessibles->first());
    }

    /** Retient le restaurant choisi. Faux si la personne ne peut pas le voir. */
    public function choisir(?User $user, int|string|null $choix): bool
    {
        if ($choix === self::TOUS || $choix === null || $choix === '') {
            session([self::SESSION => self::TOUS]);

            return true;
        }

        if ($this->accessibles($user)->firstWhere('id', (int) $choix) === null) {
            return false;
        }

        session([self::SESSION => (int) $choix]);

        return true;
    }

    /** Restaurant où s'enregistre ce qu'on crée sans l'avoir précisé. */
    public function pourCreation(?User $user): ?PointOfSale
    {
        return $this->courant($user) ?? $this->accessibles($user)->first() ?? $this->restaurants()->first();
    }

    /**
     * Restaurant dont on administre la carte, le garde-manger, les fiches… :
     * celui qui est choisi. Null depuis la vue d'ensemble de plusieurs
     * restaurants — on choisit d'abord lequel.
     */
    public function pourSaisie(?User $user): ?PointOfSale
    {
        return $this->plusieurs() ? $this->courant($user) : $this->pourCreation($user);
    }

    /**
     * Comme pourSaisie(), mais refuse la saisie depuis la vue d'ensemble.
     *
     * @throws ValidationException
     */
    public function exigerPourSaisie(?User $user): PointOfSale
    {
        return $this->pourSaisie($user) ?? throw ValidationException::withMessages([
            'restaurant' => 'Choisissez d\'abord le restaurant concerné, en haut de la page.',
        ]);
    }

    /** Depuis la vue d'ensemble de plusieurs restaurants ? */
    public function vueEnsemble(?User $user): bool
    {
        return $this->plusieurs() && $this->courant($user) === null;
    }

    /** @return list<int> restaurants visibles maintenant : le choisi, ou tous ceux qu'on peut voir */
    public function idsVisibles(?User $user): array
    {
        $courant = $this->courant($user);

        return $courant ? [$courant->id] : $this->accessibles($user)->pluck('id')->all();
    }

    /** Borne une requête aux restaurants visibles maintenant. */
    public function restreindre(Builder $requete, ?User $user, string $colonne = 'point_of_sale_id'): Builder
    {
        return $requete->whereIn($colonne, $this->idsVisibles($user));
    }

    /** Borne une requête à tous les restaurants que la personne peut voir. */
    public function restreindreAuxAccessibles(Builder $requete, ?User $user, string $colonne = 'point_of_sale_id'): Builder
    {
        return $requete->whereIn($colonne, $this->accessibles($user)->pluck('id')->all());
    }

    /**
     * Borne une requête de personnes à l'équipe d'un restaurant. Un
     * établissement qui n'a qu'un restaurant n'a qu'une équipe.
     */
    public function equipe(Builder $personnes, PointOfSale|int|null $restaurant): Builder
    {
        $id = $restaurant instanceof PointOfSale ? $restaurant->id : $restaurant;

        if ($id === null || ! $this->plusieurs()) {
            return $personnes;
        }

        return $personnes->whereHas('restaurants', fn (Builder $q) => $q->where('points_of_sale.id', $id));
    }

    /** Cette personne peut-elle voir ce restaurant ? */
    public function peutVoir(?User $user, PointOfSale|int|null $restaurant): bool
    {
        $id = $restaurant instanceof PointOfSale ? $restaurant->id : $restaurant;

        return $id !== null && $this->accessibles($user)->contains('id', $id);
    }

    /**
     * Les restaurants que cette personne peut voir et qui exploitent ce
     * service (PointOfSale::SERVICE_*).
     *
     * @return Collection<int, PointOfSale>
     */
    public function offrant(?User $user, string $service): Collection
    {
        return $this->accessibles($user)->filter(fn (PointOfSale $r): bool => $r->offre($service))->values();
    }

    /**
     * Le restaurant choisi n'exploite pas ce service. Faux depuis la vue
     * d'ensemble : on y voit ce que les autres restaurants font.
     */
    public function serviceAbsentIci(?User $user, string $service): ?PointOfSale
    {
        $courant = $this->courant($user);

        return $courant !== null && ! $courant->offre($service) ? $courant : null;
    }

    /** Oublie ce qui a été mémorisé : après une affectation, ou entre deux tests. */
    public function oublier(): void
    {
        $this->restaurants = null;
        $this->accessibles = [];
    }
}
