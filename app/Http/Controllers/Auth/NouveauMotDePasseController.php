<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Après une réinitialisation ou à la première connexion, la personne remplace
 * le mot de passe initial/provisoire par un mot de passe personnel et confidentiel.
 */
class NouveauMotDePasseController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.nouveau-mot-de-passe');
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $valide = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Les deux saisies ne correspondent pas.',
        ]);

        $defaultPassword = config('auth.default_user_password', 'Wetchah@2026!');

        // Le mot de passe initial ou provisoire ne peut pas être réutilisé
        if (Hash::check($valide['password'], $user->password) || $valide['password'] === $defaultPassword) {
            $message = 'Choisissez un mot de passe différent du mot de passe provisoire ou par défaut.';
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['password' => [$message]]], 422);
            }
            return back()->withErrors(['password' => $message]);
        }

        DB::transaction(function () use ($user, $valide) {
            $user->forceFill([
                'password' => Hash::make($valide['password']),
                'must_change_password' => false,
                'temporary_password_expires_at' => null,
            ])->save();

            AuditLog::record(
                $user->id,
                'user_management',
                "{$user->name} a défini son nouveau mot de passe personnel",
                'users',
                ['target_user_id' => $user->id]
            );
        });

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe est enregistré avec succès.',
                'redirect' => route('dashboard'),
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Votre mot de passe est enregistré.');
    }
}
