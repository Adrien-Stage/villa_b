<?php

namespace App\Http\Controllers;

use App\Services\RestaurantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Change le restaurant dans lequel on travaille. */
class RestaurantContextController extends Controller
{
    public function choisir(Request $request, RestaurantContext $contexte): RedirectResponse
    {
        if (! $contexte->choisir(Auth::user(), $request->input('restaurant'))) {
            return back()->with('error', "Vous n'êtes pas affecté à ce restaurant.");
        }

        return back();
    }
}
