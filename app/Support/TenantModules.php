<?php

namespace App\Support;

/**
 * Modules métier activables/désactivables par établissement, pilotés
 * depuis erp_pms via la variable d'environnement TENANT_MODULES (JSON,
 * ex: ["restaurant","shop"]) — voir TenantProvisioningService côté admin.
 *
 * Les modules cœur (chambres, réservations, clients, utilisateurs,
 * paramètres) ne sont pas concernés : toujours actifs, non listés ici.
 */
class TenantModules
{
    private static ?array $enabled = null;

    public static function enabled(): array
    {
        if (self::$enabled === null) {
            $decoded = json_decode((string) env('TENANT_MODULES', '[]'), true);
            self::$enabled = is_array($decoded) ? $decoded : [];
        }

        return self::$enabled;
    }

    public static function has(string $module): bool
    {
        return in_array($module, self::enabled(), true);
    }
}
