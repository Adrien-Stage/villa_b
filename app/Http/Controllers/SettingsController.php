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

        return view('settings.index', compact('tab', 'user'));
    }
}
