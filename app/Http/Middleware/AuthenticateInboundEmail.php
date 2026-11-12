<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuthenticateInboundEmail
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $hash = (string) config('inbound-email.token_sha256');
        $source = (string) config('inbound-email.source');
        if (!config('inbound-email.enabled') || !preg_match('/\A[a-f0-9]{64}\z/i', $hash) || !preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $source)) {
            return response()->json(['message'=>'Ricezione email non attiva.'], 503);
        }
        /** L'endpoint esterno non usa sessioni web e non accetta credenziali in URL o nel messaggio. */
        $token = $request->bearerToken();
        if (!is_string($token) || strlen($token) < 32 || strlen($token) > 256 || !hash_equals(strtolower($hash), hash('sha256', $token))) {
            return response()->json(['message'=>'Credenziale non valida.'], 401);
        }
        if (!$request->isSecure() && !app()->environment('testing')) {
            return response()->json(['message'=>'Il collegamento richiede HTTPS.'], 400);
        }
        if ((int) $request->header('Content-Length', 0) > 28 * 1024 * 1024) {
            return response()->json(['message'=>'Messaggio troppo grande.'], 413);
        }
        if (!Schema::hasTable('inbound_emails') || !Schema::hasTable('inbound_email_files')) {
            return response()->json(['message'=>'Ricezione email non ancora predisposta nell’archivio.'], 503);
        }
        return $next($request);
    }
}
