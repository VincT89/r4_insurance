<section id="policy-corrections">
    <x-panel title="Segnalazioni e correzioni" padded>
        @can('updateRegistry', $client)
            <form method="POST" action="{{ route('clients.policies.corrections.store', [$client, $policy]) }}">
                @csrf
                <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <x-form-section title="Segnala un errore all’admin">
                    <x-form-group label="Errore riscontrato e correzione richiesta" name="message" required><textarea id="field-message" name="message" class="form-ta" rows="3" minlength="10" maxlength="3000" required>{{ old('message') }}</textarea></x-form-group>
                    <p class="u-text-meta">La segnalazione resta nello storico e l’admin riceve una notifica nel gestionale.</p>
                    <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Invia segnalazione</button></div>
                </x-form-section>
            </form>
        @endcan
        @forelse($corrections as $correction)
            <x-form-section :title="\App\Models\InsurancePolicyCorrection::STATUSES[$correction->status]" :id="'correction-'.$correction->id">
                <p class="u-text-meta">{{ $correction->created_at->format('d/m/Y H:i') }} · {{ $correction->requester?->name ?: 'Utente non disponibile' }}</p>
                <p class="u-whitespace-pre-wrap">{{ $correction->message }}</p>
                @if($correction->resolved_at)
                    <p class="u-whitespace-pre-wrap">{{ $correction->resolution }}</p>
                    <p class="u-text-meta">Esito registrato da {{ $correction->resolver?->name ?: 'Utente non disponibile' }} il {{ $correction->resolved_at->format('d/m/Y H:i') }}.</p>
                @else
                    @can('system.admin')
                        <form method="POST" action="{{ route('clients.policies.corrections.resolve', [$client, $policy, $correction]) }}">
                            @csrf
                            <div class="form-row">
                                <x-form-group label="Esito" :name="'outcome-'.$correction->id" required><select id="field-outcome-{{ $correction->id }}" name="status" class="form-sel" required><option value="">Seleziona l’esito</option><option value="corrected">Correzione effettuata</option><option value="no_change">Nessuna modifica necessaria</option></select></x-form-group>
                                <x-form-group label="Motivazione dell’esito" :name="'resolution-'.$correction->id" required><textarea id="field-resolution-{{ $correction->id }}" name="resolution" class="form-ta" rows="2" minlength="10" maxlength="3000" required></textarea></x-form-group>
                            </div>
                            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.policies.edit', [$client, $policy]) }}">Correggi polizza</a><button type="submit" class="btn btn-p">Registra esito</button></div>
                        </form>
                    @endcan
                @endif
            </x-form-section>
        @empty
            <p class="u-text-meta">Nessuna segnalazione registrata.</p>
        @endforelse
        {{ $corrections->fragment('policy-corrections')->links() }}
    </x-panel>
</section>
