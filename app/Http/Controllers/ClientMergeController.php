<?php

namespace App\Http\Controllers;

use App\Domain\Core\Actions\MergeClientsAction;
use App\Domain\Core\Queries\ClientDuplicateQuery;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientMergeController extends Controller
{
    public function duplicates(Client $client, ClientDuplicateQuery $query)
    {
        $this->authorize('view', $client);

        return view('clients.merge.duplicates', ['client' => $client, 'candidates' => $query->forClient($client, auth()->user())]);
    }

    public function preview(Request $request, Client $client, MergeClientsAction $action)
    {
        abort_unless($request->user()->canManageSystem(), 403);
        $data = $request->validate(['source' => ['required', 'integer']]);
        $source = Client::current()->findOrFail($data['source']);
        $preview = $action->preview($client, $source);

        return view('clients.merge.preview', compact('client', 'source', 'preview'));
    }

    public function store(Request $request, Client $client, MergeClientsAction $action)
    {
        abort_unless($request->user()->canManageSystem(), 403);
        $data = $request->validate(['source' => ['required', 'integer'], 'token' => ['required', 'string', 'size:64'], 'choices' => ['nullable', 'array'], 'choices.*' => ['required', 'in:source,target'], 'confirm' => ['accepted']]);
        $source = Client::findOrFail($data['source']);
        $result = $action->execute($client, $source, $data['token'], array_intersect_key($data['choices'] ?? [], MergeClientsAction::FIELDS));

        return redirect()->route('clients.show', [$result, 'tab' => 'registry'])->with('success', 'Anagrafiche unificate. Collegamenti e dati originali conservati nello storico.');
    }
}
