<?php

namespace App\Http\Middleware;

use App\Models\CashRegisterSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque toute action métier (réservations, encaissements...) tant que la
 * caisse de l'utilisateur n'est pas ouverte. Complète le masquage des
 * boutons côté vue : empêche aussi un POST direct hors interface.
 */
class EnsureCashRegisterOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        // Une caisse déjà comptée, en attente de contresignature, n'est plus
        // close mais n'encaisse plus : le comptage déclaré cesserait sinon de
        // correspondre au contenu du tiroir.
        $open = CashRegisterSession::where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->exists();

        if (!$open) {
            return back()->withErrors([
                'cash_register' => 'Vous devez ouvrir votre caisse avant d\'effectuer cette action.',
            ]);
        }

        return $next($request);
    }
}
