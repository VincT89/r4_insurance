<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UseCurrentClient
{
    public function handle(Request $request, Closure $next)
    {
        $client = $request->route('client');
        if (! $client instanceof Client) {
            return $next($request);
        }
        $proceed = function () use ($request, $next, $client) {
            $fresh = Client::query()->when(! $request->isMethodSafe() && ! $request->routeIs('clients.merge.store'), fn ($q) => $q->lockForUpdate())->findOrFail($client->id);
            if ($fresh->merged_into_id) {
                abort_unless($request->user()->can('view', $fresh), 403);
                $target = Client::findOrFail($fresh->merged_into_id);
                abort_unless($request->user()->can('view', $target), 403);
                if ($request->isMethodSafe()) {
                    return redirect()->route('clients.show', [$target, 'tab' => 'registry'])->with('success', 'Questa anagrafica è stata unificata nella scheda visualizzata.');
                }
                abort(409, 'Anagrafica già unificata. Riapri la scheda cliente prima di salvare.');
            }
            $request->route()->setParameter('client', $fresh);

            return $next($request);
        };

        // Merge takes both client locks in ID order in its own transaction.
        return $request->isMethodSafe() || $request->routeIs('clients.merge.store')
            ? $proceed()
            : DB::transaction($proceed, 3);
    }
}
