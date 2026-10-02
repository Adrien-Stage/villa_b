<?php

namespace App\Http\Controllers;

use App\Models\SupportSession;
use Illuminate\View\View;

/**
 * Sessions du support de l'éditeur, ouvertes par le mode assistance : l'hôtel
 * voit qui est entré chez lui, quand, et combien de temps.
 */
class SupportSessionController extends Controller
{
    public function index(): View
    {
        return view('administration.support-sessions', [
            'sessions' => SupportSession::latest('debut')->paginate(30),
        ]);
    }
}
