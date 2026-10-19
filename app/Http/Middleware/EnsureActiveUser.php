<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ($request->user()->status !== 'active' || !\App\Enums\UserRole::tryFrom((string) $request->user()->getRawOriginal('role')))) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) { $request->session()->invalidate(); $request->session()->regenerateToken(); }
            return $request->expectsJson() ? response()->json(['message'=>'Account disattivato.'],403) : redirect()->route('login')->withErrors(['email'=>'Account disattivato. Contatta l’amministratore.']);
        }
        return $next($request);
    }
}
