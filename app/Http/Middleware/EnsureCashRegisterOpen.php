<?php

namespace App\Http\Middleware;

use App\Services\CashRegisterCircuit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque toute action métier (réservations, encaissements...) tant que aucune
 * caisse réception utilisable n'est ouverte pour l'utilisateur courant
 * (sa propre caisse, ou celle d'un réceptionniste pour le chef de réception).
 * Complète le masquage des boutons côté vue : empêche aussi un POST direct hors interface.
 */
class EnsureCashRegisterOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        // Une caisse déjà comptée, en attente de contresignature, n'est plus
        // close mais n'encaisse plus : le comptage déclaré cesserait sinon de
        // correspondre au contenu du tiroir.
        $activeSession = app(CashRegisterCircuit::class)->receptionActivePour(Auth::user());

        if ($activeSession === null) {
            return back()->withErrors([
                'cash_register' => 'Vous devez ouvrir votre caisse avant d\'effectuer cette action.',
            ]);
        }

        return $next($request);
    }
}
