<?php

namespace App\Http\Controllers;

use App\Models\{Attachment, Client, ClientDocumentRequest, InsuranceBranch};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class ClientDocumentRequestController extends Controller
{
    public function create(Client $client)
    {
        $this->authorize('updateRegistry', $client);
        return view('clients.documents.request', ['client' => $client, 'policies' => $client->policies()->orderBy('number')->get(),
            'branches' => InsuranceBranch::where('status', 'active')->with(['subbranches' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get()]);
    }

    public function store(Request $request, Client $client)
    {
        $this->authorize('updateRegistry', $client);
        $values = $request->validate(['document_category' => ['required', Rule::in(array_keys(Attachment::DOCUMENT_CATEGORIES))], 'label' => ['required', 'string', 'max:255'],
            'insurance_policy_id' => ['nullable', 'integer', Rule::exists('insurance_policies', 'id')->where('client_id', $client->id)],
            'due_on' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($client, $values) {
            Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $item = $client->documentRequests()->create($values + ['requested_by' => auth()->id()]);
            $this->log($client, 'Registrato documento da ricevere: '.$item->label);
        });
        return $this->back($client, 'Documento aggiunto alla lista da ricevere.');
    }

    public function edit(Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $documentRequest->client_id === (int) $client->id, 404);
        return view('clients.documents.request-edit', compact('client', 'documentRequest'));
    }

    public function update(Request $request, Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $documentRequest->client_id === (int) $client->id, 404);
        $values = $request->validate(['label' => ['required', 'string', 'max:255'], 'due_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'], 'document_category' => ['prohibited'], 'insurance_policy_id' => ['prohibited'], 'insurance_branch_id' => ['prohibited']]);
        DB::transaction(function () use ($client, $documentRequest, $values) {
            Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $item = $client->documentRequests()->whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            $oldDue = $item->due_on?->format('d/m/Y') ?? 'non indicato';
            $item->fill($values);
            if ($item->isDirty()) {
                $item->save();
                $this->log($client, 'Aggiornata richiesta documentale: '.$item->label.'. Termine precedente: '.$oldDue.'; termine attuale: '.($item->due_on?->format('d/m/Y') ?? 'non indicato').'.');
            }
        });
        return $this->back($client, 'Richiesta documentale aggiornata.');
    }

    public function apply(Request $request, Client $client)
    {
        $this->authorize('updateRegistry', $client);
        $values = $request->validate(['insurance_branch_id' => ['required', 'integer', 'exists:insurance_branches,id'],
            'insurance_subbranch_id' => ['nullable', 'integer', Rule::exists('insurance_subbranches', 'id')->where('insurance_branch_id', $request->input('insurance_branch_id'))],
            'insurance_policy_id' => ['nullable', 'integer', Rule::exists('insurance_policies', 'id')->where('client_id', $client->id)], 'due_on' => ['nullable', 'date_format:Y-m-d']]);
        $count = DB::transaction(function () use ($client, $values) {
            $branch = InsuranceBranch::whereKey($values['insurance_branch_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $sub = !empty($values['insurance_subbranch_id']) ? $branch->subbranches()->whereKey($values['insurance_subbranch_id'])->where('status', 'active')->firstOrFail() : null;
            $policy = !empty($values['insurance_policy_id']) ? $client->policies()->findOrFail($values['insurance_policy_id']) : null;
            if ($policy && (\App\Services\InsuranceCatalog::key($policy->category) !== $branch->name_key || ($sub && \App\Services\InsuranceCatalog::key($policy->subcategory ?? '') !== $sub->name_key))) throw ValidationException::withMessages(['insurance_policy_id' => 'La polizza deve appartenere al ramo e sottoramo scelti.']);
            $categories = array_unique(array_merge($branch->document_categories ?? [], $sub?->document_categories ?? []));
            if (!$categories) throw ValidationException::withMessages(['insurance_branch_id' => 'La checklist è vuota. Configura i documenti richiesti nel catalogo rami e sottorami.']);
            $count = 0;
            foreach ($categories as $category) {
                if ($client->documentRequests()->where('insurance_policy_id', $policy?->id)->where('insurance_branch_id', $branch->id)->where('document_category', $category)->exists()) continue;
                $key = hash('sha256', implode('|', [$client->id, $policy?->id, $branch->id, $category]));
                $item = $client->documentRequests()->firstOrCreate(['source_key' => $key], ['insurance_policy_id' => $policy?->id, 'insurance_branch_id' => $branch->id,
                    'document_category' => $category, 'label' => Attachment::DOCUMENT_CATEGORIES[$category], 'due_on' => $values['due_on'] ?? null, 'requested_by' => auth()->id(), 'notes' => 'Checklist configurata: '.$branch->name.($sub ? ' / '.$sub->name : '')]);
                if ($item->wasRecentlyCreated) $count++;
            }
            if ($count) $this->log($client, 'Aggiunti '.$count.' documenti da ricevere dalla checklist '.$branch->name.'.');
            return $count;
        });
        return $this->back($client, $count.' documenti aggiunti; le richieste già presenti sono conservate.');
    }

    public function destroy(Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $documentRequest->client_id === (int) $client->id, 404);
        DB::transaction(function () use ($client, $documentRequest) {
            Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $documentRequest = ClientDocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
            if ($documentRequest->documents()->exists()) throw ValidationException::withMessages(['document' => 'La richiesta ha già documenti ricevuti e viene conservata nello storico.']);
            $this->log($client, 'Rimossa richiesta documentale senza file: '.$documentRequest->label);
            $documentRequest->delete();
        });
        return $this->back($client, 'Richiesta rimossa.');
    }

    private function log(Client $client, string $text): void { app(\App\Services\AuditLogService::class)->log('document_requested', $client, description: $text); }
    private function back(Client $client, string $text) { return redirect()->route('clients.show', [$client, 'tab' => 'documents'])->with('success', $text); }
}
