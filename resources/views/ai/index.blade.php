<x-app-layout title="AI e consumi">
    <x-page-header meta="Contatori dei token, costi delle elaborazioni e configurazione OpenAI."><x-slot:title>AI e consumi</x-slot:title><x-slot:actions><a class="btn btn-g" href="#ai-configuration">Configura AI</a></x-slot:actions></x-page-header>
    @if(!$installed)<p class="u-alert-error">La sezione richiede l’aggiornamento del database. Nessuna lettura AI è attiva.</p>@endif
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
    <x-panel title="Configurazione OpenAI" id="ai-configuration" padded>
        <p class="u-mb-md">{{ $ready ? 'Lettura AI abilitata.' : 'Lettura AI non attiva. Il gestionale mantiene disponibile la lettura locale.' }} La chiave API si configura qui e rimane riservata agli amministratori.</p>
        @if($installed)<form method="POST" action="{{ route('ai.update') }}" autocomplete="off" class="form-stack">@csrf @method('PATCH')
            <div class="form-row">
                <x-form-group name="ai_api_key" label="Chiave API OpenAI"><input id="field-ai-api-key" name="ai_api_key" type="password" class="form-in" autocomplete="new-password" autocapitalize="none" spellcheck="false" maxlength="2048"><p class="u-text-meta">{{ $settings['key_saved'] ? 'Chiave salvata. Lascia vuoto per conservarla.' : 'Inserisci una chiave della piattaforma API OpenAI.' }} La chiave viene conservata cifrata e non viene mostrata né inviata al browser degli operatori.</p></x-form-group>
                <x-form-group name="model" label="Modello"><select id="field-ai-model" name="model" class="form-sel">@foreach(\App\Services\AiPricing::MODELS as $id=>$model)<option value="{{ $id }}" @selected(old('model',$settings['model'])===$id)>{{ $model['name'] }}</option>@endforeach</select></x-form-group>
                <x-form-group name="monthly_budget" label="Limite mensile (USD)"><input id="field-ai-monthly-budget" name="monthly_budget" type="number" min="0.01" max="10000" step="0.01" class="form-in" value="{{ old('monthly_budget',$settings['monthly_budget']) }}" required></x-form-group>
                <x-form-group name="document_budget" label="Massimo per documento (USD)"><input id="field-ai-document-budget" name="document_budget" type="number" min="0.01" max="10000" step="0.01" class="form-in" value="{{ old('document_budget',$settings['document_budget']) }}" required></x-form-group>
            </div>
            <input type="hidden" name="enabled" value="0"><label class="contract-confirm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings['enabled']))> Abilita l’AI per la lettura dei contratti</label>
            <p class="u-text-meta">Abilitando l’AI, i contratti caricati per questa funzione saranno elaborati da OpenAI. Prima di ogni elaborazione vengono controllati i limiti di spesa. La polizza viene creata soltanto dopo il controllo dell’operatore.</p>
            <p class="u-text-meta">Tariffe di riferimento verificate il {{ \Carbon\Carbon::parse(\App\Services\AiPricing::VERIFIED_ON)->format('d/m/Y') }}: <a href="{{ \App\Services\AiPricing::SOURCE }}" target="_blank" rel="noopener">prezzi ufficiali OpenAI</a>. Il contatore riguarda le chiamate effettuate da questo gestionale.</p>
            <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Salva configurazione AI</button></div>
        </form>@endif
    </x-panel>
</x-app-layout>
