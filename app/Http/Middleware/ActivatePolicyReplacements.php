<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActivatePolicyReplacements
{
    public function handle(Request $request, Closure $next)
    {
        /** The first authenticated request also applies due changes when the local scheduler is not running. */
        if ($request->user() && app(\App\Services\ScheduledPolicyReplacements::class)->activateDue()) {
            foreach ($request->route()?->parameters() ?? [] as $parameter) if ($parameter instanceof \Illuminate\Database\Eloquent\Model) $parameter->refresh();
        }
        return $next($request);
    }
}
