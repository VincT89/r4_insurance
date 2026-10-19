<x-panel title="Lavorazione" padded>
    @if($policy->work_closed_at)
        <p>Completata il {{ $policy->work_closed_at->format('d/m/Y H:i') }} da {{ $policy->workClosedBy?->name ?: 'Utente non disponibile' }}.</p>
        <p class="u-text-meta">Dal {{ $policy->work_closed_at->copy()->addDay()->format('d/m/Y') }} le modifiche sono riservate all’admin. Lo stato del contratto resta {{ mb_strtolower($policy->status_label) }}.</p>
        @if($policy->workLocked() && !auth()->user()->canManageSystem())<p>Per correggere un errore, invia una segnalazione all’admin.</p>@endif
    @else
        <p>Lavorazione aperta. Chiudila quando hai completato e controllato i dati della polizza.</p>
        @can('updateRegistry', $client)
            <form method="POST" action="{{ route('clients.policies.close-work', [$client, $policy]) }}">
                @csrf
                <input type="hidden" name="fingerprint" value="{{ $policy->workFingerprint() }}">
                <label class="u-flex-center u-gap-sm"><input type="checkbox" name="confirmed" value="1" required> Ho completato la lavorazione. Dal giorno successivo le modifiche saranno riservate all’admin.</label>
                <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Chiudi lavorazione</button></div>
            </form>
        @endcan
    @endif
    @can('updateRegistry', $client)<a class="btn btn-g" href="#policy-corrections">Segnala un errore all’admin</a>@endcan
</x-panel>
