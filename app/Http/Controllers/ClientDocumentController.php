<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClientDocumentRequest;
use App\Models\{Attachment, Client};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class ClientDocumentController extends Controller
{
    public function create(Request $request, Client $client): View
    {
        $this->authorize('updateRegistry', $client);
        $request->validate(['replace' => ['nullable', 'integer', 'prohibits:request'], 'request' => ['nullable', 'integer']]);
        if ($request->filled('replace')) {
            $previous = $client->attachments()->whereNotNull('document_category')->findOrFail($request->integer('replace'));
            abort_if($previous->newerVersion()->exists(), 409, 'Apri la versione corrente del documento.');
            return $this->form($client, new Attachment($previous->only(['document_category', 'identity_type', 'insurance_policy_id', 'vehicle_id', 'description', 'document_request_id']) + ['previous_version_id' => $previous->id, 'version_number' => $previous->version_number + 1]));
        }
        if ($request->filled('request')) {
            $item = $client->documentRequests()->findOrFail($request->integer('request'));
            return $this->form($client, new Attachment(['document_category' => $item->document_category, 'insurance_policy_id' => $item->insurance_policy_id, 'document_request_id' => $item->id, 'description' => $item->label]));
        }
        $policy = $request->filled('policy') ? $client->policies()->findOrFail($request->integer('policy')) : null;
        return $this->form($client, new Attachment(['insurance_policy_id' => $policy?->id]));
    }

    public function store(SaveClientDocumentRequest $request, Client $client): RedirectResponse
    {
        $files = $request->file('files') ?? [$request->file('file')];
        $details = $request->input('file_details') ?? [];
        if ($request->hasFile('file')) $details = [$request->only(['expires_on', 'identity_type'])];
        $documents = app(\App\Services\ClientDocumentRegistry::class)->uploadMany($client, $files, $request->safe()->except(['file', 'files', 'file_details', 'expires_on', 'identity_type']), $details);
        $response = redirect()->route('clients.show', [$client, 'tab' => 'documents'])->with('success', count($documents).' documenti caricati.');
        $warnings = array_map(fn ($document) => $document->original_name.': '.$document->validity_label, array_filter($documents, fn ($document) => $document->expires_on?->lt(today()) || ($document->document_category === 'identity' && !$document->expires_on)));
        return $warnings ? $response->with('warning', implode(' · ', $warnings)) : $response;
    }

    public function edit(Client $client, Attachment $document): View
    {
        $this->authorize('updateRegistry', $client);
        abort_unless($document->attachable_type === $client->getMorphClass()
            && (int) $document->attachable_id === (int) $client->id && $document->document_category !== null, 404);
        return $this->form($client, $document);
    }

    public function update(SaveClientDocumentRequest $request, Client $client, Attachment $document): RedirectResponse
    {
        app(\App\Services\ClientDocumentRegistry::class)->update($document, $request->validated());
        return $this->saved($client, $document->fresh(), 'Dati del documento aggiornati.');
    }

    private function saved(Client $client, Attachment $document, string $message): RedirectResponse
    {
        $response = redirect()->route('clients.show', [$client, 'tab'=>'documents'])->with('success', $message);
        if ($document->expires_on?->lt(today()) || ($document->document_category === 'identity' && !$document->expires_on)) return $response->with('warning', $document->validity_label);
        return $response;
    }

    private function form(Client $client, Attachment $document): View
    {
        $policies = $client->policies()->orderBy('category')->orderBy('number')->get();
        $vehicles = $client->vehicles()->orderBy('plate')->get();
        return view('clients.documents.form', compact('client', 'document', 'policies', 'vehicles'));
    }

    public function validateDocument(Request $request, Client $client, Attachment $document): RedirectResponse
    {
        $this->authorize('updateRegistry', $client);
        abort_unless($document->attachable_type === $client->getMorphClass() && (int) $document->attachable_id === (int) $client->id && $document->document_category !== null, 404);
        $data = $request->validate(['validation_note' => ['nullable', 'string', 'max:500']]);
        app(\App\Services\ClientDocumentRegistry::class)->validateDocument($document, $data['validation_note'] ?? null);
        return redirect()->route('clients.show', [$client, 'tab' => 'documents'])->with('success', 'Documento validato.');
    }
}
