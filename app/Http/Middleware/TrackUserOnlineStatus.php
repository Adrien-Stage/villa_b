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
            if (! $user->is_active) {
                Auth::logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Ce compte a été désactivé.'], 403);
                }
                return redirect()->guest(route('login'))->withErrors(['login' => 'Ce compte a été désactivé.']);
            }

            // Store user's online status in cache for 2 minutes
            $expiresAt = now()->addMinutes(2);
            Cache::put('user-is-online-' . $user->id, true, $expiresAt);
        }

        return $next($request);
    }
}
