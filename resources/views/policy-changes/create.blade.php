<x-app-layout :title="\App\Models\InsurancePolicyChange::KINDS[$kind]">
    <x-page-header><x-slot:title>{{ \App\Models\InsurancePolicyChange::KINDS[$kind] }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$policy]) }}">Polizza {{ $policy->number }}</a></x-slot:actions></x-page-header>
    <x-panel title="Polizza di partenza" padded>
        <dl class="client-registry-details"><div><dt>Contraente</dt><dd>{{ $client->name }}</dd></div><div><dt>Numero</dt><dd>{{ $policy->number }}</dd></div><div><dt>Compagnia e agenzia</dt><dd>{{ $policy->company }} / {{ $policy->supplierAgency?->name ?: 'Rapporto diretto' }}</dd></div><div><dt>Periodo</dt><dd>{{ $policy->starts_on->format('d/m/Y') }} – {{ $policy->expires_on->format('d/m/Y') }}</dd></div></dl>
        <p class="u-mt-md">{{ $kind==='replacement' ? 'La nuova polizza avrà un numero diverso. La precedente sarà conservata come sostituita, insieme ai suoi documenti e movimenti.' : 'Le nuove coperture saranno registrate in un’appendice alla stessa polizza. Il numero e i dati del contratto di base restano nello storico.' }}</p>
    </x-panel>
    <x-panel title="Preventivo approvato" padded>
        @if($quotes->isNotEmpty())
        <form method="GET"><input type="hidden" name="kind" value="{{ $kind }}"><div class="form-row"><x-form-group label="Proposta accettata dal cliente" name="quote_id" required><select name="quote_id" class="form-sel" required><option value="">Seleziona la proposta</option>@foreach($quotes as $proposal)<option value="{{ $proposal->id }}" @selected($quote?->id===$proposal->id)>{{ $proposal->company->name }} / {{ $proposal->reference }} / {{ \App\Support\Money::format($proposal->premium_cents) }}</option>@endforeach</select></x-form-group></div><div class="insurance-form-actions"><button class="btn btn-p">Controlla proposta</button></div></form>
        @else<p>Serve una proposta approvata per questa {{ $kind==='replacement' ? 'sostituzione' : 'aggiunta' }}, con il documento della compagnia e la prova di approvazione del cliente.</p>@endif
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.create',['client_id'=>$client->id,'current_policy_id'=>$policy->id,'purpose'=>$kind]) }}">Richiedi preventivo</a></div>
    </x-panel>
    @if($quote)
    <x-panel title="Controlla e registra" padded>
        <form method="POST" enctype="multipart/form-data" action="{{ route('clients.policies.changes.store',[$client,$policy]) }}">@csrf
            <input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="quote_id" value="{{ $quote->id }}"><input type="hidden" name="fingerprint" value="{{ $fingerprint }}"><input type="hidden" name="request_key" value="{{ Str::uuid() }}">
            <x-form-section title="Condizioni approvate">
                <dl class="client-registry-details"><div><dt>Compagnia</dt><dd>{{ $quote->company->name }}</dd></div><div><dt>Agenzia</dt><dd>{{ $quote->agency?->name ?: 'Rapporto diretto' }}</dd></div><div><dt>Prodotto</dt><dd>{{ $quote->product }}</dd></div><div><dt>Decorrenza e scadenza</dt><dd>{{ $quote->starts_on->format('d/m/Y') }} – {{ $quote->expires_on->format('d/m/Y') }}</dd></div><div><dt>{{ $kind==='addition' ? 'Premio aggiuntivo del periodo' : 'Premio del nuovo contratto' }}</dt><dd>{{ \App\Support\Money::format($quote->premium_cents) }}</dd></div><div><dt>Frazionamento proposto</dt><dd>{{ $quote->payment_frequency }}</dd></div></dl>
                <p class="u-mt-md"><a href="{{ route('quotes.show',$quote->quoteCase) }}">Apri preventivo, allegati e approvazione</a></p>
            </x-form-section>
            <x-form-section title="{{ $kind==='replacement' ? 'Nuovo contratto' : 'Appendice alla polizza' }}"><div class="form-row">
                @if($kind==='replacement')<x-form-group label="Nuovo numero di polizza" name="number" required><input class="form-in" name="number" value="{{ old('number') }}" maxlength="100" required><p class="u-text-meta">Deve essere diverso da {{ $policy->number }}.</p></x-form-group>
                @else<x-form-group label="Numero polizza" name="unchanged_number"><input class="form-in" value="{{ $policy->number }}" readonly></x-form-group><x-form-group label="Riferimento dell’appendice" name="reference" required><input class="form-in" name="reference" value="{{ old('reference') }}" maxlength="100" required></x-form-group>@endif
                <x-form-group label="Coperture e motivo della variazione" name="description" required><textarea class="form-ta" name="description" rows="3" minlength="10" maxlength="5000" required>{{ old('description',$quote->quoteCase->additional_coverages ?: $quote->quoteCase->subject) }}</textarea></x-form-group>
            </div></x-form-section>
            <x-form-section title="Documenti della compagnia"><x-form-group label="Contratto, appendice e altri allegati" name="documents"><input type="file" class="form-in" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple><p class="u-text-meta">Selezione multipla, fino a 10 file da 10 MB e 50 MB complessivi. Preventivo e approvazione saranno collegati automaticamente.</p></x-form-group></x-form-section>
            <x-form-section title="Controllo finale">
                <p class="u-mb-md">Rate, incassi e provvigioni già registrati saranno conservati. Controlla manualmente rate residue, eventuale premio aggiuntivo e rimborsi. Dopo il salvataggio potrai preparare i moduli della variazione e raccogliere le firme.</p>
                @if($kind==='replacement' && $quote->starts_on->gt(today()))<p class="u-text-meta">La conferma sarà disponibile dalla decorrenza del {{ $quote->starts_on->format('d/m/Y') }}.</p>@endif
                <label class="insurance-checkbox"><input type="checkbox" name="confirmed" value="1" required> Ho controllato polizza, condizioni approvate e collegamenti contabili.</label>
            </x-form-section>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$policy]) }}">Annulla</a><button class="btn btn-p" @disabled($kind==='replacement' && $quote->starts_on->gt(today()))>{{ $kind==='replacement' ? 'Registra sostitutiva' : 'Registra aggiunta' }}</button></div>
        </form>
    </x-panel>
    @endif
</x-app-layout>
