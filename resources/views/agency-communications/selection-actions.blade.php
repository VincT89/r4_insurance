<x-panel title="Selezione per l’invio" padded>
    <div class="communication-selection-actions">
        <button type="button" class="btn btn-g" data-select-page>Seleziona gli inviabili di questa pagina</button>
        <button type="button" class="btn btn-g" data-clear-selection>Svuota selezione</button>
        <strong data-selection-count role="status" aria-live="polite">Nessun elemento selezionato</strong>
    </div>
    <p class="form-help u-mt-sm">La selezione comprende anche le altre pagine e le righe nascoste dai filtri della tabella. Cambiando i filtri del periodo, dell’agenzia o della ricerca si azzera. Massimo 500 elementi per agenzia.</p>
    <p class="form-help" data-selection-message role="status" aria-live="polite"></p>
    <div class="communication-selection-groups">
        @foreach($reviewAgencies as $reviewAgency)
            <form method="POST" action="{{ route($reviewRoute, $reviewAgency) }}" data-review-agency="{{ $reviewAgency->id }}" hidden>
                @csrf
                @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <div><strong>{{ $reviewAgency->name }}</strong><p class="form-help">{{ $reviewAgency->email ?: 'Email non indicata: completa i recapiti dell’agenzia.' }}</p></div>
                <span data-agency-count></span><div data-selection-inputs></div>
                <button type="submit" class="btn btn-p" @disabled($reviewAgency->status !== 'active' || !filter_var($reviewAgency->email, FILTER_VALIDATE_EMAIL))>Controlla invio a {{ $reviewAgency->name }}</button>
            </form>
        @endforeach
    </div>
    <noscript>Attiva JavaScript per conservare la selezione tra le pagine e preparare il riepilogo.</noscript>
</x-panel>
