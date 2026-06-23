<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            $pausedSession = \App\Models\CashRegisterSession::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->where('status', 'paused')
                ->first();

            if ($pausedSession) {
                session(['paused_caisse_session' => [
                    'id' => $pausedSession->id,
                    'module' => $pausedSession->module,
                ]]);
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            $openSession = \App\Models\CashRegisterSession::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->where('status', 'open')
                ->first();

            if ($openSession) {
                if (!$request->has('force')) {
                    return redirect()->back()->with([
                        'confirm_logout_caisse_open' => true,
                        'caisse_module' => $openSession->module,
                        'caisse_id' => $openSession->id,
                    ]);
                } else {
                    $openSession->update(['status' => 'paused']);
                }
            }
        }

        $redirectTo = $user?->isAdmin() ? '/admin' : '/';

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect($redirectTo);
    }
}
