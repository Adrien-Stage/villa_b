<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserOnlineStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // 1. Déconnexion immédiate si le compte est désactivé
            if (! $user->is_active) {
                Auth::logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Ce compte a été désactivé.'], 403);
                }

                return redirect()->guest(route('login'))->withErrors([
                    'login' => 'Ce compte a été désactivé.',
                    'email' => 'Ce compte a été désactivé.',
                ]);
            }

            // 2. Déconnexion automatique après 20 minutes d'inactivité (ou durée configurée)
            if ($request->hasSession()) {
                $maxIdleSeconds = (int) config('session.lifetime', 20) * 60;
                $lastActivityAt = $request->session()->get('last_user_activity_at');

                $isBackgroundPoll = $request->header('X-Background-Poll') === '1'
                    || in_array(optional($request->route())->getName(), [
                        'notifications.unread',
                        'discussions.unreadSummary',
                    ], true);

                if ($lastActivityAt && (now()->timestamp - (int) $lastActivityAt) > $maxIdleSeconds) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'Votre session a expiré après 20 minutes d\'inactivité.',
                            'redirect' => route('login'),
                        ], 401);
                    }

                    return redirect()->guest(route('login'))
                        ->with('status', 'Votre session a expiré après 20 minutes d\'inactivité. Veuillez vous reconnecter.')
                        ->withErrors([
                            'login' => 'Votre session a expiré après 20 minutes d\'inactivité.',
                            'email' => 'Votre session a expiré après 20 minutes d\'inactivité.',
                        ]);
                }

                // Initialiser au premier accès, puis rafraîchir uniquement lors des interactions réelles
                if (! $lastActivityAt) {
                    $request->session()->put('last_user_activity_at', now()->timestamp);
                } elseif (! $isBackgroundPoll) {
                    $request->session()->put('last_user_activity_at', now()->timestamp);
                }
            }

            // Store user's online status in cache for 2 minutes
            $expiresAt = now()->addMinutes(2);
            Cache::put('user-is-online-'.$user->id, true, $expiresAt);
        }

        return $next($request);
    }
}
