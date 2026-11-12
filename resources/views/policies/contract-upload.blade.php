<x-form-section title="Carica il contratto">
    @if($selectedQuote)
        <div class="contract-approved-proposal">
            <strong>Proposta approvata · {{ $selectedQuote->reference }}</strong>
            <p>{{ $client->name }} · {{ $selectedQuote->company->name }} · {{ $selectedQuote->agency?->name ?: 'Rapporto diretto' }}</p>
            <p>{{ $selectedQuote->product }} · {{ \App\Support\Money::format($selectedQuote->premium_cents) }} · {{ $selectedQuote->payment_frequency }}</p>
        </div>
    @endif
    <p class="u-mb-md">Carica il PDF della compagnia per leggere il numero di polizza, le date, il premio e il frazionamento. Potrai controllare i dati e i titoli prima di confermare.</p>
    @php($contractAiReady=app(\App\Services\AiConfiguration::class)->ready())
    @if($contractAiReady)
        <label class="contract-confirm"><input type="checkbox" data-use-contract-ai checked> Leggi il contratto con AI</label>
        <p class="u-text-meta u-mb-md">Il PDF sarà elaborato da OpenAI. Consumi e costi vengono registrati in AI e consumi; i dati restano da controllare prima della conferma.</p>
    @else
        @can('system.admin')<p class="u-text-meta u-mb-md">Per abilitare la lettura AI, configura la chiave in <a href="{{ route('ai.index') }}">AI e consumi</a>. La lettura standard rimane disponibile.</p>@endcan
    @endif
    <script type="application/json" data-contract-ai>{!! json_encode(['ready'=>$contractAiReady,'url'=>route('ai.contracts.read')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <x-form-group label="Contratto PDF" name="contract" required>
        <input id="field-contract" class="form-in" type="file" name="contract" accept="application/pdf,.pdf" required aria-describedby="contract-read-status">
        <p class="u-text-meta">Massimo 10 MB. Il contratto verrà conservato nella polizza dopo la tua conferma.</p>
    </x-form-group>
    <p id="contract-read-status" data-contract-status role="status" aria-live="polite">Carica il contratto per iniziare.</p>
    <a class="btn btn-g btn-sm" data-contract-original target="_blank" rel="noopener" hidden>Apri il PDF originale</a>
    <p class="u-text-meta" data-contract-ai-usage hidden></p>
    <button type="button" class="btn btn-g btn-sm" data-contract-ai-retry hidden>Riprova lettura AI (nuovo consumo)</button>
    <div class="insurance-form-actions"><button type="button" class="btn btn-g" data-contract-manual hidden>Completa i dati manualmente</button><a class="btn btn-g" data-contract-initial-cancel href="{{ isset($source) ? route('clients.policies.show',[$client,$source]) : ($selectedQuote ? route('quotes.show',$selectedQuote->quoteCase) : ($client->exists ? route('clients.show', $client) : route('policies.index'))) }}">Annulla</a></div>
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
