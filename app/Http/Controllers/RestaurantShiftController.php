<?php

namespace App\Http\Controllers;

use App\Models\RestaurantShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Prise et fin de service des serveurs en salle. Seuls les serveurs en service
 * reçoivent une part des commandes du portail.
 */
class RestaurantShiftController extends Controller
{
    public function open(): RedirectResponse
    {
        $userId = (int) Auth::id();

        // Idempotent : si une prise est déjà ouverte, on n'en crée pas une seconde.
        $existing = RestaurantShift::query()
            ->where('user_id', $userId)
            ->open()
            ->exists();

        if (!$existing) {
            RestaurantShift::create([
                'user_id' => $userId,
                'opened_at' => now(),
            ]);
        }

        return back()->with('success', 'Vous avez pris votre service. Les commandes du portail peuvent vous être confiées.');
    }

    public function close(): RedirectResponse
    {
        RestaurantShift::query()
            ->where('user_id', (int) Auth::id())
            ->open()
            ->update(['closed_at' => now()]);

        return back()->with('success', 'Fin de service enregistrée. Vous ne recevrez plus de nouvelles commandes.');
    }
}
