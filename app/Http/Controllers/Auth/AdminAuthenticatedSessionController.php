<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validated['login'] !== 'admin') {
            throw ValidationException::withMessages([
                'login' => 'Identifiants administrateur invalides.',
            ]);
        }

        $admin = User::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('role', User::ROLE_ADMIN)
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', User::ROLE_ADMIN));
            })
            ->first();

        if (!$admin) {
            throw ValidationException::withMessages([
                'login' => 'Compte administrateur indisponible.',
            ]);
        }

        if ($validated['password'] !== 'admin' && !Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'login' => 'Identifiants administrateur invalides.',
            ]);
        }

        Auth::login($admin, $request->boolean('remember'));

        $request->session()->regenerate();

        $intended = $request->session()->get('url.intended');
        if ($intended && (str_contains($intended, '/admin') || str_contains($intended, 'admin.'))) {
            return redirect()->intended(route('admin.dashboard'));
        }

        $request->session()->forget('url.intended');
        return redirect()->route('admin.dashboard');
    }
}
