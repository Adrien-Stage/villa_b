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
                $defaultTab = 'hebergement';
            } elseif ($user->hasRole('housekeeping_leader')) {
                $defaultTab = 'housekeeping';
            } elseif ($user->hasRole('restaurant_chief')) {
                $defaultTab = 'restaurant';
            } elseif ($user->hasRole('shop_manager')) {
                $defaultTab = 'shop';
            }
        }

        $tab = $request->query('tab', $defaultTab);

        // « reception » et « hebergement » ne font plus qu'un onglet à l'écran.
        // La clé de stockage « reception » reste distincte — le formulaire des
        // horaires poste dessus et l'application l'y relit — donc la
        // redirection d'après enregistrement, comme un ancien favori, arrive
        // ici avec l'ancien nom : on la ramène sur l'onglet affiché.
        if ($tab === 'reception') {
            $tab = 'hebergement';
        }

        // Une seule base = un seul établissement : pas besoin de filtrer par tenant_id.
        $tenant = \App\Models\Tenant::first();
        $tenantSettings = $tenant?->settings ?? [];

        // Catalogue des prestations, groupé par catégorie (onglet "Prestations")
        $serviceItems = \App\Models\ServiceItem::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $serviceCategories = \App\Models\ServiceItem::CATEGORIES;

        // Organisations partenaires (onglet "Partenaires"). Le catalogue à plat
        // sert à cocher les prestations offertes dans le formulaire.
        $partnerOrganizations = \App\Models\PartnerOrganization::query()
            ->orderBy('name')
            ->get();

        $serviceItemsFlat = \App\Models\ServiceItem::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $partnerTypes = \App\Models\PartnerOrganization::TYPES;

        // Packs d'hébergement (onglet "Hébergement").
        $roomPackages = \App\Models\RoomPackage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomTypes         = \App\Models\RoomType::orderBy('name')->get();
        $mealServices      = \App\Models\RestaurantMenuItem::MEAL_SERVICES;
        $packPricingModes  = \App\Models\RoomPackage::PRICING_MODES;

        return view('settings.index', compact(
            'tab',
            'user',
            'tenant',
            'tenantSettings',
            'serviceItems',
            'serviceCategories',
            'partnerOrganizations',
            'serviceItemsFlat',
            'partnerTypes',
            'roomPackages',
            'roomTypes',
            'mealServices',
            'packPricingModes'
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['manager', 'reception', 'housekeeping_leader', 'restaurant_chief', 'shop_manager'])) {
            abort(403, 'Accès non autorisé aux paramètres.');
        }

        $tenant = \App\Models\Tenant::firstOrFail();

        // On récupère les anciens settings
        $settings = $tenant->settings ?? [];
        $dirty = false;

        // Logo : clé de premier niveau, gérée indépendamment des onglets
        // (affiché tel quel par layouts/hotel.blade.php et auth/login.blade.php).
        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'mimes:png,jpg,jpeg,gif', 'max:2048']]);

            if (!empty($settings['logo'])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($settings['logo']);
            }

            $settings['logo'] = $request->file('logo')->store('logos', 'public');
            $dirty = true;
        }

        // L'onglet actuel pour savoir quelle clé mettre à jour
        $tab = $request->query('tab');

        if ($tab && $request->has('settings')) {
            $tabData = $request->input('settings');

            // Fusionne avec les données existantes de cet onglet ou crée l'onglet
            $settings[$tab] = array_merge($settings[$tab] ?? [], $tabData);
            $dirty = true;
        }

        if ($dirty) {
            $tenant->settings = $settings;
            $tenant->save();
        }

        return redirect()->route('settings.index', ['tab' => $tab])->with('success', 'Les paramètres ont été enregistrés avec succès.');
    }
}
