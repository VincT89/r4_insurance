<?php

namespace App\Http\Middleware;

use App\Support\Appearance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberAppearance
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($user = $request->user()) {
            // Only colors are remembered. No identity or account data is stored here.
            $colors = json_encode(Appearance::colors($user->appearance), JSON_THROW_ON_ERROR);
            if ($request->cookie('appearance_theme') !== $colors) {
                $response->headers->setCookie(cookie('appearance_theme', $colors, 525600, '/', null, $request->isSecure(), true, false, 'lax'));
            }
        }

        return $response;
    }
}
