<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Rules\ItalianFiscalIdentifier;
use App\Services\ClientFiscalIdentity;
use App\Support\FiscalIdentifier;
use Illuminate\Http\{JsonResponse, Request};

class ClientFiscalIdentityController extends Controller
{
    public function __invoke(Request $request, ClientFiscalIdentity $identity): JsonResponse
    {
        $this->authorize('lookup', Client::class);
        $values = $request->validate([
            'vat_number' => ['nullable', 'string', 'max:30'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'client_id' => ['nullable', 'integer'],
        ]);
        $client = $request->filled('client_id') ? Client::current()->findOrFail($request->integer('client_id')) : null;
        if ($client) $this->authorize('updateRegistry', $client);
        foreach (['vat_number', 'tax_code'] as $field) $values[$field] = FiscalIdentifier::normalize($values[$field] ?? '', $field);
        $validator = validator($values, [
            'vat_number' => ['nullable', new ItalianFiscalIdentifier],
            'tax_code' => ['nullable', new ItalianFiscalIdentifier],
        ]);
        $errors = $validator->errors()->toArray();
        $matches = $identity->matches($values, $client);
        $conflict = $identity->conflicts($client ? $identity->matches($values) : $matches, $values);

        /** I risultati restano soggetti ai permessi anche se in futuro cambia la visibilità dei clienti. */
        $visible = $matches->filter(fn ($candidate) => $request->user()->can('view', $candidate));
        return response()->json([
            'status' => $conflict ? 'conflict' : ($matches->isNotEmpty() ? 'duplicate' : ($errors ? 'invalid' : 'clear')),
            'errors' => $errors,
            'message' => $conflict
                ? 'Partita IVA e codice fiscale rimandano a clienti diversi. Controlla entrambi i dati prima di salvare.'
                : ($matches->isNotEmpty() ? 'Il cliente è già presente. Apri la sua scheda oppure correggi i dati inseriti.' : ($errors ? 'Correggi i dati indicati.' : 'Nessun duplicato trovato nei dati inseriti.')),
            'matches' => $visible->map(fn ($candidate) => [
                'id' => $candidate->id, 'name' => $candidate->name,
                'fields' => $identity->fields($candidate, $values),
                'url' => route('clients.show', $candidate),
            ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
