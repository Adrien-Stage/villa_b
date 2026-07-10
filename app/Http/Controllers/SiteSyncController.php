<?php

namespace App\Http\Controllers;

use App\Support\TenantModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * Indicateur de synchronisation avec le site vitrine, côté application :
 * vérifie que le container "web" de cet établissement répond, via le
 * réseau Docker partagé (convention de nommage meka-erp-{slug}-web fixée
 * par TenantProvisioningService dans erp_pms). Consommé en AJAX par le
 * badge du header (layouts/hotel.blade.php).
 */
class SiteSyncController extends Controller
{
    public function status(): JsonResponse
    {
        if (!TenantModules::has('website')) {
            return response()->json(['enabled' => false, 'online' => null]);
        }

        $slug = (string) env('TENANT_SLUG', '');
        $online = false;

        if ($slug !== '') {
            try {
                $online = Http::timeout(2)
                    ->get("http://meka-erp-{$slug}-web:3000/")
                    ->successful();
            } catch (\Throwable) {
                $online = false;
            }
        }

        return response()->json(['enabled' => true, 'online' => $online]);
    }
}
