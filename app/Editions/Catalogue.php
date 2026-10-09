<?php

namespace App\Editions;

use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\TenantModules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Le catalogue des éditions : tout ce que l'établissement imprime, réuni en
 * un seul endroit.
 *
 * Deux sortes d'entrées. Les éditions (Edition) sont des registres et des
 * situations que la rubrique produit elle-même, avec leurs filtres. Les
 * éditions spécialisées sont des documents qui ont déjà leur écran — fiches
 * de comptage, procès-verbaux, grand livre — et vers lesquels la rubrique
 * mène. Chacune n'apparaît qu'à qui peut déjà l'ouvrir.
 *
 * Ajouter une édition : écrire sa classe dans App\Editions\Documents, puis
 * l'inscrire ici.
 */
class Catalogue
{
    /** @var list<class-string<Edition>> */
    private const EDITIONS = [
        Documents\SituationJournaliere::class,
        Documents\SituationSynthetique::class,
        Documents\JournalVentes::class,
        Documents\JournalEncaissements::class,
        Documents\RecapEncaissements::class,
        Documents\SessionsCaisse::class,
        Documents\Factures::class,
        Documents\Creances::class,
        Documents\Depenses::class,
        Documents\Arrivees::class,
        Documents\Departs::class,
        Documents\ClientsPresents::class,
        Documents\RegistreVoyageurs::class,
        Documents\EtatChambres::class,
        Documents\VentesRestaurantArticles::class,
        Documents\VentesBoutiqueProduits::class,
        Documents\Pertes::class,
        Documents\BonsCommande::class,
        Documents\BonsReception::class,
        Documents\BonsRequisition::class,
        Documents\SortiesHorsEtablissement::class,
        Documents\EtatStocks::class,
        Documents\MouvementsStock::class,
        Documents\PlanningQuarts::class,
    ];

    /**
     * Documents qui ont leur propre écran d'impression.
     *
     * @var list<array{famille: string, titre: string, description: string, route: string, droit: string, module?: string}>
     */
    private const SPECIALISEES = [
        ['famille' => Edition::FINANCES, 'titre' => 'Grand livre', 'description' => 'Le grand livre général, compte par compte, à imprimer ou exporter depuis la comptabilité.', 'route' => 'accounting.ledger.general', 'droit' => 'accounting.ledger.general'],
        ['famille' => Edition::FINANCES, 'titre' => 'Balance des comptes', 'description' => 'La balance générale de la période.', 'route' => 'accounting.ledger.balance', 'droit' => 'accounting.ledger.balance'],
        ['famille' => Edition::FINANCES, 'titre' => 'Journaux comptables', 'description' => 'Les écritures, journal par journal.', 'route' => 'accounting.ledger.journals', 'droit' => 'accounting.ledger.journals'],
        ['famille' => Edition::FINANCES, 'titre' => 'Balance âgée des clients', 'description' => 'Les créances par ancienneté.', 'route' => 'accounting.ledger.aged', 'droit' => 'accounting.ledger.aged'],
        ['famille' => Edition::FINANCES, 'titre' => 'Tour de contrôle (version imprimable)', 'description' => "Indicateurs de pilotage de l'établissement.", 'route' => 'analytics.print', 'droit' => 'analytics.voir', 'module' => 'analytics'],
        ['famille' => Edition::ACHATS, 'titre' => 'Fiches de comptage', 'description' => "Les listes à remplir le jour de l'inventaire, service par service.", 'route' => 'economat.count_sheets.index', 'droit' => 'economat.count_sheets.voir'],
        ['famille' => Edition::ACHATS, 'titre' => "Procès-verbaux d'inventaire", 'description' => 'Les inventaires du magasin et leurs procès-verbaux.', 'route' => 'economat.stock_counts.index', 'droit' => 'economat.stock_counts.voir'],
        ['famille' => Edition::ACHATS, 'titre' => 'Contrôle et ratios (rapport)', 'description' => 'Le rapport de contrôle des stocks et du food cost.', 'route' => 'economat.control.print', 'droit' => 'economat.control.voir'],
        ['famille' => Edition::HEBERGEMENT, 'titre' => 'Fiches techniques des chambres', 'description' => 'Le coût par nuitée et la marge de chaque type de chambre.', 'route' => 'rooms.cost_sheets.index', 'droit' => 'rooms.cost_sheets.voir'],
    ];

    /** @return Collection<int, Edition> */
    public function toutes(): Collection
    {
        return collect(self::EDITIONS)->map(fn (string $classe) => app($classe));
    }

    public function trouver(string $cle): ?Edition
    {
        return $this->toutes()->first(fn (Edition $e) => $e->cle() === $cle);
    }

    /** @return Collection<int, Edition> */
    public function pour(?User $user): Collection
    {
        return $this->toutes()->filter(fn (Edition $e) => $e->accessiblePour($user))->values();
    }

    /** @return Collection<int, array{famille: string, titre: string, description: string, url: string}> */
    public function specialiseesPour(?User $user): Collection
    {
        $resolveur = app(PermissionResolver::class);

        return collect(self::SPECIALISEES)
            ->filter(fn (array $s) => Route::has($s['route'])
                && (! isset($s['module']) || TenantModules::has($s['module']))
                && $user !== null && $resolveur->allows($user, $s['droit']))
            ->map(fn (array $s) => $s + ['url' => route($s['route'])])
            ->values();
    }
}
