<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle ultra-léger (pas de base de données) utilisé par le
 * site vitrine (template_site) pour vérifier que la communication avec
 * cette application fonctionne — pilote l'indicateur vert/rouge de sa
 * topbar. Voir aussi SiteSyncController pour le sens inverse.
 */
class PublicPingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => 'meka_template',
            'time' => now()->toIso8601String(),
        ]);
    }
}
