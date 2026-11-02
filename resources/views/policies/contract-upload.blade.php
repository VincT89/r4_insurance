<x-form-section title="Carica il contratto">
    <p class="u-mb-md">Scegli il PDF ricevuto dalla compagnia: leggeremo numero di polizza, date, premio e frazionamento. Poi potrai controllare e correggere i dati prima di confermare.</p>
    @if($selectedQuote)<p class="u-mb-md">Preventivo {{ $selectedQuote->reference }} di {{ $client->name }} / {{ $selectedQuote->company->name }}.</p>@endif
    <x-form-group label="Contratto PDF" name="contract" required>
        <input id="field-contract" class="form-in" type="file" name="contract" accept="application/pdf,.pdf" required aria-describedby="contract-read-status">
        <p class="u-text-meta">Massimo 10 MB. Il contratto verrà conservato nella polizza dopo la tua conferma.</p>
    </x-form-group>
    <p id="contract-read-status" data-contract-status role="status" aria-live="polite">Carica il contratto per iniziare.</p>
    <div class="insurance-form-actions"><button type="button" class="btn btn-g" data-contract-manual hidden>Completa i dati manualmente</button><a class="btn btn-g" data-contract-initial-cancel href="{{ $client->exists ? route('clients.show', $client) : route('policies.index') }}">Annulla</a></div>
    <ul data-contract-warnings class="contract-warnings" hidden></ul>
    <details data-contract-source hidden><summary>Consulta il testo letto dal contratto</summary><pre class="contract-source" data-contract-text></pre></details>
    @if(!$selectedQuote && $approvedQuotes->isNotEmpty())
        <details class="u-mt-md" data-contract-quote-choice><summary>Collega un preventivo già approvato nel gestionale</summary>
            <div class="form-row u-mt-md"><div class="form-g"><label class="form-lbl" for="contract-quote">Preventivo approvato</label><select id="contract-quote" class="form-sel" data-contract-quote><option value="">Seleziona la proposta</option>@foreach($approvedQuotes as $proposal)<option value="{{ isset($source) ? route('policy-transitions.create',[$source,$kind,'quote_id'=>$proposal->id]) : route('policies.create', ['quote_id'=>$proposal->id]) }}">{{ $proposal->quoteCase->client->name }} / {{ $proposal->company->name }} / {{ $proposal->reference }} / {{ \App\Support\Money::format($proposal->premium_cents) }}</option>@endforeach</select></div><div class="insurance-form-actions"><button type="button" class="btn btn-g" data-use-contract-quote>Usa questo preventivo</button></div></div>
        </details>
    @endif
    <script type="application/json" data-contract-catalog>{!! json_encode(['companies'=>$companies->pluck('name')->values(), 'categories'=>$categories->values(), 'subcategories'=>$subcategories, 'agencies'=>$agenciesByCompany, 'client'=>$client->exists ? ['name'=>$client->name] : null, 'quote'=>$selectedQuote ? app(\App\Services\InsuranceQuoteWorkflow::class)->policyValues($selectedQuote) : null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <noscript><p>Attiva JavaScript per leggere il contratto e controllare l’anteprima dei titoli.</p></noscript>
</x-form-section>
