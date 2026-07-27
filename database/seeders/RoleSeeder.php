<?php

namespace Database\Seeders;

use App\Support\RoleCatalog;
use Illuminate\Database\Seeder;

/**
 * Crée les rôles à l'installation. Le référentiel lui-même vit dans
 * App\Support\RoleCatalog, partagé avec la commande roles:sync qui le rejoue
 * à chaque démarrage — un rôle ajouté au catalogue se propage donc seul,
 * y compris sur un établissement déjà installé.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        RoleCatalog::sync();
    }
}
