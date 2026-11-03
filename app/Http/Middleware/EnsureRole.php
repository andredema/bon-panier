<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à certains rôles : ->middleware('role:gerant').
 * Un compte désactivé est déconnecté à sa prochaine requête.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        if ($allowed && ! $user->hasRole(...$allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
