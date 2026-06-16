<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Si l'utilisateur n'a aucun rôle autorisé, on le bloque (même si la route est protégée par middleware, c'est une double sécurité)
        if (!$user->hasAnyRole(['manager', 'reception', 'housekeeping_leader', 'restaurant_chief', 'shop_manager'])) {
            abort(403, 'Accès non autorisé aux paramètres.');
        }

        // Déterminer l'onglet par défaut en fonction du rôle principal si aucun onglet n'est spécifié
        $defaultTab = 'general';
        
        if (!$user->hasRole('manager')) {
            if ($user->hasRole('reception')) {
                $defaultTab = 'reception';
            } elseif ($user->hasRole('housekeeping_leader')) {
                $defaultTab = 'housekeeping';
            } elseif ($user->hasRole('restaurant_chief')) {
                $defaultTab = 'restaurant';
            } elseif ($user->hasRole('shop_manager')) {
                $defaultTab = 'shop';
            }
        }

        $tab = $request->query('tab', $defaultTab);

        // Récupérer les settings actuels
        $tenantSettings = \App\Models\Tenant::where('id', $user->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id'))->value('settings') ?? [];

        return view('settings.index', compact('tab', 'user', 'tenantSettings'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['manager', 'reception', 'housekeeping_leader', 'restaurant_chief', 'shop_manager'])) {
            abort(403, 'Accès non autorisé aux paramètres.');
        }

        $tenantId = $user->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $tenant = \App\Models\Tenant::findOrFail($tenantId);

        // On récupère les anciens settings
        $settings = $tenant->settings ?? [];

        // L'onglet actuel pour savoir quelle clé mettre à jour
        $tab = $request->query('tab');

        if ($tab && $request->has('settings')) {
            $tabData = $request->input('settings');
            
            // Fusionne avec les données existantes de cet onglet ou crée l'onglet
            $settings[$tab] = array_merge($settings[$tab] ?? [], $tabData);

            $tenant->settings = $settings;
            $tenant->save();
        }

        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Les paramètres ont été enregistrés avec succès.');
    }
}
