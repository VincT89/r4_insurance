<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    // Forza il cambio password al primo accesso
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Delega l'autenticazione al middleware auth
        if (! $user) {
            return $next($request);
        }

        // Procede se la password è già stata aggiornata
        if ($user->password_changed_at !== null) {
            return $next($request);
        }

        // Whitelist rotte per setup password e logout
        $allowedRoutes = [
            'password.setup',
            'password.setup.update',
            'logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes)) {
            return $next($request);
        }

        // Forza redirect al setup password
        return redirect()->route('password.setup');
    }
}
