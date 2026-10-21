<?php

namespace App\Http\Controllers;

use App\Models\{Attachment, Client, ClientDocumentRequest, InsuranceBranch};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class ClientDocumentRequestController extends Controller
{
    public function create(Request $request, Client $client)
    {
        $this->authorize('updateRegistry', $client);
        $request->validate(['policy'=>['nullable','integer']]);
        $selectedPolicy = $request->filled('policy') ? $client->policies()->findOrFail($request->integer('policy')) : null;
        return view('clients.documents.request', ['client' => $client, 'selectedPolicy'=>$selectedPolicy, 'policies' => $client->policies()->orderBy('number')->get(),
            'branches' => InsuranceBranch::where('status', 'active')->with(['subbranches' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get()]);
    }

    public function store(Request $request, Client $client)
    {
        $this->authorize('updateRegistry', $client);
        $values = $request->validate(['document_category' => ['required', Rule::in(array_keys(Attachment::DOCUMENT_CATEGORIES))], 'label' => ['required', 'string', 'max:255'],
            'insurance_policy_id' => ['nullable', 'integer', Rule::exists('insurance_policies', 'id')->where('client_id', $client->id)],
            'due_on' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($client, $values) {
            $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $item = $client->documentRequests()->create($values + ['requested_by' => auth()->id()]);
            $this->log($client, 'Registrato documento da ricevere: '.$item->label);
        });
        return $this->back($client, 'Documento aggiunto alla lista da ricevere.');
    }

    public function edit(Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $documentRequest->client_id === (int) $client->id, 404);
        $documentRequest->load(['documents.newerVersion', 'linkedDocuments.newerVersion']);
        $existing = $documentRequest->documents->pluck('id')->merge($documentRequest->linkedDocuments->pluck('id'));
        $availableDocuments = app(\App\Services\InsuranceDocumentation::class)->candidates($documentRequest)->whereNotIn('id', $existing)->latest('id')->get();
        return view('clients.documents.request-edit', compact('client', 'documentRequest', 'availableDocuments'));
    }

    public function link(Request $request, Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int)$documentRequest->client_id === (int)$client->id, 404);
        $data = $request->validate(['documents'=>['required','array','min:1','max:50'], 'documents.*'=>['required','integer','distinct'], 'confirmed'=>['accepted']]);
        app(\App\Services\InsuranceDocumentation::class)->link($client, $documentRequest, $data['documents']);
        return to_route('clients.document-requests.edit', [$client, $documentRequest])->with('success', 'Documenti collegati. Gli originali e il loro stato di verifica sono conservati.');
    }

    public function update(Request $request, Client $client, ClientDocumentRequest $documentRequest)
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $documentRequest->client_id === (int) $client->id, 404);
        $values = $request->validate(['label' => ['required', 'string', 'max:255'], 'due_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'], 'document_category' => ['prohibited'], 'insurance_policy_id' => ['prohibited'], 'insurance_branch_id' => ['prohibited']]);
        DB::transaction(function () use ($client, $documentRequest, $values) {
            $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
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
            $client = Client::current()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $sub = !empty($values['insurance_subbranch_id']) ? $branch->subbranches()->whereKey($values['insurance_subbranch_id'])->where('status', 'active')->firstOrFail() : null;
            $policy = !empty($values['insurance_policy_id']) ? $client->policies()->findOrFail($values['insurance_policy_id']) : null;
            if ($policy && (\App\Services\InsuranceCatalog::key($policy->category) !== $branch->name_key || ($sub && \App\Services\InsuranceCatalog::key($policy->subcategory ?? '') !== $sub->name_key))) throw ValidationException::withMessages(['insurance_policy_id' => 'La polizza deve appartenere al ramo e sottoramo scelti.']);
            /** A policy always includes its actual subbranch; a blank selector must not omit its configured documents. */
            if ($policy) {
                if ($policy->insurance_branch_id && (int)$policy->insurance_branch_id !== (int)$branch->id) throw ValidationException::withMessages(['insurance_policy_id'=>'Il ramo non corrisponde alla polizza.']);
                if ($sub && (int)$policy->insurance_subbranch_id !== (int)$sub->id) throw ValidationException::withMessages(['insurance_subbranch_id'=>'Il sottoramo non corrisponde alla polizza.']);
                $sub = $policy->subbranch;
            }
            $categories = app(\App\Services\InsuranceDocumentation::class)->categories($client, $branch, $sub);
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
            if ($documentRequest->documents()->exists() || $documentRequest->linkedDocuments()->exists()) throw ValidationException::withMessages(['document' => 'La richiesta ha già documenti ricevuti e viene conservata nello storico.']);
            $this->log($client, 'Rimossa richiesta documentale senza file: '.$documentRequest->label);
            $documentRequest->delete();
        });
        return $this->back($client, 'Richiesta rimossa.');
    }

    private function log(Client $client, string $text): void { app(\App\Services\AuditLogService::class)->log('document_requested', $client, description: $text); }
    private function back(Client $client, string $text) { return redirect()->route('clients.show', [$client, 'tab' => 'documents'])->with('success', $text); }
}
