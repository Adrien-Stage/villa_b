<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Console\Command;

/**
 * Écrit l'annexe du guide des rôles et droits : qui détient quoi, droit par
 * droit, tirée du catalogue lui-même.
 *
 * Le guide explique ; l'annexe ne fait que recopier PermissionCatalog, pour
 * qu'elle ne puisse pas mentir. À relancer après tout changement du
 * catalogue. Elle montre le modèle livré : les écarts qu'un établissement ou
 * la console y apportent se lisent à l'écran « Rôles & droits ».
 */
class AnnexeDesDroits extends Command
{
    protected $signature = 'droits:annexe {--sortie=docs/guide-roles-et-droits-annexe.md : Fichier à écrire}';

    protected $description = "Écrit l'annexe du guide des rôles et droits depuis le catalogue";

    /** Libellés courts des rôles, pour les en-têtes de colonnes. */
    private const COURTS = [
        'admin' => 'Admin', 'manager' => 'Manager',
        'reception_chief' => 'Chef réc.', 'reception' => 'Récep.',
        'housekeeping_leader' => 'Gouv.', 'housekeeping_staff' => 'Valet',
        'restaurant_manager' => 'Resp. resto', 'restaurant_chief' => 'Chef cuis.', 'restaurant_staff' => 'Serveur',
        'restaurant_cook' => 'Cuisinier', 'cashier' => 'Caissier',
        'shop_manager' => 'Resp. bout.', 'shop_cashier' => 'Vendeur',
        'econome' => 'Économe', 'storekeeper' => 'Magasinier',
        'finance_manager' => 'RAF', 'accountant' => 'Comptable',
        'controller' => 'Contrôleur', 'quality_auditor' => 'Auditeur', 'support' => 'Support',
    ];

    /** Services, dans l'ordre du guide. */
    private const SERVICES = [
        'rooms' => 'Chambres', 'bookings' => 'Réservations', 'groups' => 'Groupes', 'agenda' => 'Agenda',
        'customers' => 'Clients', 'reception' => 'POS Réception', 'invoices' => 'Factures',
        'housekeeping' => 'Housekeeping', 'restaurant' => 'Restaurant', 'shop' => 'Boutique',
        'economat' => 'Économat', 'accounting' => 'Comptabilité', 'analytics' => 'Analytique',
        'settings' => 'Paramètres', 'users' => 'Utilisateurs', 'droits' => 'Rôles & droits',
        'interventions' => 'Interventions', 'audit' => "Journal d'audit", 'support' => 'Support',
    ];

    public function handle(): int
    {
        $catalogue = PermissionCatalog::all();
        $roles = array_values(array_filter(
            RoleCatalog::all(),
            static fn (array $r): bool => $r['statut'] === RoleCatalog::ACTIF && isset(self::COURTS[$r['slug']])
        ));

        $lignes = [
            '# Annexe — Qui détient quoi',
            '',
            '> Générée par `php artisan droits:annexe` depuis le catalogue des droits',
            '> (`PermissionCatalog`). Ne pas modifier à la main : relancer la commande après',
            '> tout changement du catalogue.',
            '>',
            '> Elle montre le **modèle livré**, hiérarchie comprise (un chef détient les droits',
            '> de ses membres). Les écarts posés par la console, par l\'hôtel ou sur une',
            '> personne se lisent dans l\'application, écran **Rôles & droits**, et sur la fiche',
            '> de chaque employé. Retour au [guide](guide-roles-et-droits.md).',
            '',
            '## Résumé par rôle',
            '',
            '| Rôle | Niveau | Inclut | Consulte | Agit | Services où il agit |',
            '|---|---|---|---:|---:|---|',
        ];

        foreach ($roles as $role) {
            $droits = PermissionCatalog::forRole($role['slug']);
            $actions = array_values(array_filter($droits, static fn (string $d): bool => ! PermissionCatalog::estLecture($d)));
            $services = array_values(array_unique(array_map(
                static fn (string $d): string => self::SERVICES[explode('.', $d)[0]] ?? explode('.', $d)[0],
                $actions
            )));

            $lignes[] = sprintf('| %s (`%s`) | %s | %s | %d | %d | %s |',
                $role['name'], $role['slug'],
                $role['level'] === null ? 'transversal' : (string) $role['level'],
                implode(', ', array_map(static fn (string $s): string => "`{$s}`", $role['includes'] ?? [])) ?: '—',
                count($droits) - count($actions),
                count($actions),
                implode(', ', $services) ?: '—');
        }

        $lignes[] = '';
        $lignes[] = '## Droit par droit';
        $lignes[] = '';
        $lignes[] = '« ✓ » : le rôle détient le droit. La colonne **Nature** dit s\'il consulte ou s\'il agit.';
        $lignes[] = 'Seuls les rôles qui détiennent au moins un droit du service ont une colonne.';

        $parService = [];
        foreach ($catalogue as $droit => $detenteurs) {
            $parService[explode('.', $droit)[0]][$droit] = $detenteurs;
        }

        foreach (self::SERVICES as $prefixe => $libelle) {
            if (! isset($parService[$prefixe])) {
                continue;
            }

            $droits = $parService[$prefixe];
            ksort($droits);

            $colonnes = array_values(array_filter($roles, static function (array $role) use ($droits): bool {
                foreach ($droits as $detenteurs) {
                    if (in_array($role['slug'], $detenteurs, true)) {
                        return true;
                    }
                }

                return false;
            }));

            $lignes[] = '';
            $lignes[] = "### {$libelle}";
            $lignes[] = '';
            $lignes[] = '| Droit | Nature | '.implode(' | ', array_map(static fn (array $r): string => self::COURTS[$r['slug']], $colonnes)).' |';
            $lignes[] = '|---|---|'.str_repeat(':-:|', count($colonnes));

            foreach ($droits as $droit => $detenteurs) {
                $cases = array_map(
                    static fn (array $r): string => in_array($r['slug'], $detenteurs, true) ? '✓' : '',
                    $colonnes
                );

                $lignes[] = "| `{$droit}` | ".(PermissionCatalog::estLecture($droit) ? 'consulte' : '**agit**').' | '.implode(' | ', $cases).' |';
            }
        }

        $chemin = base_path((string) $this->option('sortie'));
        file_put_contents($chemin, implode("\n", $lignes)."\n");

        $this->info("Annexe écrite : {$chemin}");

        return self::SUCCESS;
    }
}
