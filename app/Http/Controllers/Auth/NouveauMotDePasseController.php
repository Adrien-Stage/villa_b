<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Après une réinitialisation, la personne remplace le mot de passe provisoire
 * que son responsable lui a remis par le sien.
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

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $valide = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Les deux saisies ne correspondent pas.',
        ]);

        // Le provisoire est connu de celui qui l'a remis : on n'en garde pas.
        if (Hash::check($valide['password'], $user->password)) {
            return back()->withErrors(['password' => 'Choisissez un mot de passe différent du provisoire.']);
        }

        $user->forceFill([
            'password' => Hash::make($valide['password']),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        AuditLog::record($user->id, 'user_management', "{$user->name} a choisi son mot de passe après une réinitialisation", 'users', [
            'target_user_id' => $user->id,
        ]);

        return redirect()->route('dashboard')->with('success', 'Votre mot de passe est enregistré.');
    }
}
