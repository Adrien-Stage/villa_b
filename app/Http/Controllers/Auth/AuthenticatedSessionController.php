<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\CashRegisterSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $forceStandby = $request->query('expired') === 'inactivity'
            || $request->boolean('veille')
            || (bool) session('veille');

        return view('auth.login', ['forceStandby' => $forceStandby]);
    }

    /**
     * Affiche la page de veille (reprise de session après inactivité).
     */
    public function standby(): View
    {
        return view('auth.login', ['forceStandby' => true]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('last_user_activity_at', now()->timestamp);

        $user = Auth::user();
        if ($user && ! $user->isAdmin()) {
            $pausedSession = CashRegisterSession::where('user_id', $user->id)
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
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if ($user && ! $user->isAdmin()) {
            $openSession = CashRegisterSession::where('user_id', $user->id)
                ->whereNull('closed_at')
                ->where('status', 'open')
                ->first();

            if ($openSession) {
                if (! $request->has('force')) {
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

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => $request->boolean('inactivity')
                    ? route('login', ['expired' => 'inactivity'], false)
                    : route('login', [], false),
            ]);
        }

        if ($request->boolean('inactivity')) {
            return redirect()->route('login', ['expired' => 'inactivity'])
                ->with('veille', true);
        }

        return redirect('/');
    }
}
