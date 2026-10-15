<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\MinibarService;
use Illuminate\Http\RedirectResponse;

/**
 * Départ du client, côté réception : le relevé du mini-bar se demande avant de
 * clore la note, pour que la consommation y figure.
 */
class BookingMinibarController extends Controller
{
    public function request(Booking $booking, MinibarService $minibars): RedirectResponse
    {
        try {
            $minibars->demanderControle($booking);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['minibar' => $e->getMessage()]);
        }

        return back()->with('success', "Contrôle du mini-bar demandé : le housekeeping est prévenu. La consommation s'ajoutera au folio dès le relevé.");
    }
}
