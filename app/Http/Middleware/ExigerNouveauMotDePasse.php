<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un mot de passe provisoire ne sert qu'à entrer.
 *
 * Après une réinitialisation, la personne se connecte avec le mot de passe
 * que son responsable lui a remis ; elle n'atteint aucun écran avant d'avoir
 * choisi le sien. Le responsable connaît le provisoire : le laisser servir
 * reviendrait à partager le compte.
 */
class ExigerNouveauMotDePasse
{
    /** Ce qui reste permis : choisir son mot de passe, ou se déconnecter. */
    private const ROUTES_PERMISES = ['password.nouveau', 'password.nouveau.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->must_change_password || $request->routeIs(...self::ROUTES_PERMISES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Choisissez votre mot de passe avant de continuer.'], 423);
        }

        return redirect()->route('password.nouveau');
    }
}
