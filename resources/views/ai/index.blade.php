<x-app-layout title="AI e consumi">
    <x-page-header meta="Configura il collegamento OpenAI e controlla i consumi."><x-slot:title>AI e consumi</x-slot:title><x-slot:actions><a class="btn btn-g" href="#ai-consumption">Vedi consumi</a></x-slot:actions></x-page-header>
    @if(!$installed)<p class="u-alert-error">La sezione richiede l’aggiornamento del database. Nessuna lettura AI è attiva.</p>@endif
    @include('ai.configuration')
    <h2 id="ai-consumption" tabindex="-1">Consumi AI</h2>
    <form method="GET" action="{{ route('ai.index') }}" class="client-search-form" aria-label="Periodo dei consumi AI">
        <x-form-group name="from" label="Dal"><input id="field-from" type="date" name="from" class="form-in" value="{{ $from }}"></x-form-group>
        <x-form-group name="to" label="Al"><input id="field-to" type="date" name="to" class="form-in" value="{{ $to }}"></x-form-group>
        <div class="search-form-actions"><button class="btn btn-p">Mostra consumi</button><a class="btn btn-g" href="{{ route('ai.index') }}">Questo mese</a></div>
    </form>
    <dl class="ai-totals" aria-label="Contatori AI nel periodo">
        <div><dt>Token letti</dt><dd>{{ number_format($totals->input_tokens,0,',','.') }}</dd></div>
        <div><dt>Token generati</dt><dd>{{ number_format($totals->output_tokens,0,',','.') }}</dd></div>
        <div><dt>Token totali</dt><dd>{{ number_format($totals->total_tokens,0,',','.') }}</dd></div>
        <div><dt>Spesa API stimata</dt><dd>{{ \App\Services\AiPricing::format((int)$totals->cost) }}</dd></div>
    </dl>
    <p class="u-text-meta u-mb-md">{{ number_format($totals->requests,0,',','.') }} richieste nel periodo. I token sono quelli dichiarati dall’API; i costi sono calcolati con la tariffa conservata per ciascuna richiesta, in dollari, esclusi cambio, imposte e altri costi del fornitore.</p>
    @if($totals->reserved)<p class="u-alert-info u-mb-md">Importo ancora riservato per richieste in corso o senza conferma: {{ \App\Services\AiPricing::format((int)$totals->reserved) }}. Non è conteggiato come spesa confermata.</p>@endif
    @if($totals->uncertain)<p class="u-alert-error u-mb-md">{{ $totals->uncertain }} richieste senza conferma dei consumi. Controlla la piattaforma OpenAI prima di ripeterle.</p>@endif
    @if($groups->isNotEmpty())<x-panel title="Consumi per funzione">
        <div class="client-table-scroll"><table class="t-table"><thead><tr><th>Funzione</th><th>Modello</th><th>Richieste</th><th>Token totali</th><th>Costo stimato</th></tr></thead><tbody>
        @forelse($groups as $group)<tr><td>{{ match($group->feature) {'policy_contract'=>'Lettura contratti','insurance_quote'=>'Lettura preventivi',default=>$group->feature} }}</td><td>{{ \App\Services\AiPricing::MODELS[$group->model]['name'] ?? $group->model }}</td><td>{{ $group->requests }}</td><td>{{ number_format($group->total_tokens,0,',','.') }}</td><td>{{ \App\Services\AiPricing::format((int)$group->cost) }}</td></tr>@empty<tr><td colspan="5">Nessun consumo AI nel periodo.</td></tr>@endforelse
        </tbody></table></div>
    </x-panel>@else<p class="u-text-meta u-mb-md">Nessun consumo AI nel periodo selezionato.</p>@endif
    @if($usages && $usages->total())<x-panel title="Registro delle elaborazioni">
        <div class="client-table-scroll"><table class="t-table"><thead><tr><th>Data e operatore</th><th>Attività</th><th>Token letti</th><th>Token generati</th><th>Totale token</th><th>Costo</th><th>Esito</th></tr></thead><tbody>
        @forelse($usages ?? [] as $usage)
            <tr>
                <td>{{ $usage->started_at->format('d/m/Y H:i') }}<div class="u-text-meta">{{ $usage->user?->name ?: 'Utente non più presente' }}</div></td>
                <td>{{ match($usage->feature) {'policy_contract'=>'Lettura contratto','insurance_quote'=>'Lettura preventivo',default=>$usage->feature} }}<div class="u-text-meta">{{ \App\Services\AiPricing::MODELS[$usage->model]['name'] ?? $usage->model }}</div>@if($usage->insurance_quote_id)<a href="{{ route('quotes.compare',['quotes'=>[$usage->insurance_quote_id]]) }}">Offerta #{{ $usage->insurance_quote_id }}</a>@endif</td>
                <td>{{ $usage->input_tokens===null ? '—' : number_format($usage->input_tokens,0,',','.') }}@if($usage->cached_input_tokens)<div class="u-text-meta">{{ number_format($usage->cached_input_tokens,0,',','.') }} già in cache</div>@endif</td>
                <td>{{ $usage->output_tokens===null ? '—' : number_format($usage->output_tokens,0,',','.') }}@if($usage->reasoning_tokens)<div class="u-text-meta">Di cui {{ number_format($usage->reasoning_tokens,0,',','.') }} di ragionamento</div>@endif</td>
                <td>{{ $usage->total_tokens===null ? '—' : number_format($usage->total_tokens,0,',','.') }}</td>
                <td>{{ \App\Services\AiPricing::format($usage->cost_microusd) }}@if($usage->reconciled_at)<div class="u-text-meta">Verificato manualmente</div>@endif</td>
                <td>{{ $usage->state_label }}
                    @if($usage->state==='uncertain')<details class="ai-reconciliation"><summary>Verifica consumo</summary>
                        <p class="u-text-meta">Riporta i consumi effettivi verificati sulla piattaforma OpenAI, anche se pari a zero. Questa verifica non effettua nuove chiamate.</p>
                        <form method="POST" action="{{ route('ai.reconcile',$usage) }}">@csrf
                            <label>Token letti<input class="form-in" type="number" name="input_tokens" min="0" max="2000000" required></label>
                            <label>Di cui già in cache<input class="form-in" type="number" name="cached_input_tokens" min="0" max="2000000" value="0" required></label>
                            <label>Token generati<input class="form-in" type="number" name="output_tokens" min="0" max="6000" required></label>
                            <label>Costo verificato (USD)<input class="form-in" type="number" name="reported_cost_usd" min="0" max="10000" step="0.000001" required></label>
                            <label>Riferimento della verifica<textarea class="form-in" name="reconciliation_note" minlength="10" maxlength="1000" required></textarea></label>
                            <button class="btn btn-g btn-sm" type="submit">Conferma consumo verificato</button>
                        </form>
                    </details>@endif
                </td>
            </tr>
        @empty<tr><td colspan="7">Le elaborazioni compariranno qui quando verrà usata l’AI.</td></tr>@endforelse
        </tbody></table></div>
        @if($usages){{ $usages->links() }}@endif
    </x-panel>@endif
</x-app-layout>
