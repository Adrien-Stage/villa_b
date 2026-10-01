<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Les cartes disent ce que la personne peut réellement ouvrir : la même
        // question que les routes, posée au moteur de droits.
        $droits = app(\App\Services\PermissionResolver::class);

        $permissionCards = [
            ['label' => 'Acces chambres', 'allowed' => $droits->allows($user, 'rooms.voir')],
            ['label' => 'Acces reservations', 'allowed' => $droits->allows($user, 'bookings.voir')],
            ['label' => 'Acces donnees financieres', 'allowed' => $droits->allows($user, 'accounting.voir')],
            ['label' => 'Gestion staff', 'allowed' => $droits->allows($user, 'users.voir')],
            ['label' => 'Actions housekeeping', 'allowed' => $droits->allows($user, 'housekeeping.voir')],
        ];

        return view('profile.edit', [
            'user' => $user,
            'permissionCards' => $permissionCards,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
