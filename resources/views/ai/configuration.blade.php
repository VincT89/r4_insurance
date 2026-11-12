@php
    $editingKey = !$settings['key_saved'] || old('replace_api_key') || $errors->has('ai_api_key');
    $connectionResult = session('ai_connection_check');
    if (($connectionResult['revision'] ?? null) !== $settings['revision']) $connectionResult = null;
@endphp
<x-panel title="Configurazione OpenAI" id="ai-configuration" padded>
    @if($installed)
        <div data-ai-configuration data-key-saved="{{ $settings['key_saved'] ? 'true' : 'false' }}" data-saved-model="{{ $settings['model'] }}">
            <form method="POST" action="{{ route('ai.update') }}" autocomplete="off" class="form-stack" data-ai-settings-form aria-label="Configurazione OpenAI">
                @csrf
                @method('PATCH')
                <div class="ai-key-setting">
                    <div class="ai-key-status">
                        <div>
                            <h3>Chiave API</h3>
                            <p><strong>{{ $settings['key_saved'] ? 'Chiave salvata' : 'Nessuna chiave salvata' }}</strong></p>
                            <p class="u-text-meta">{{ $settings['key_saved'] ? 'Resta configurata anche quando chiudi la pagina. Usa Modifica per sostituirla.' : 'Inserisci la chiave del tuo progetto OpenAI e salva la configurazione.' }}</p>
                        </div>
                        @if($settings['key_saved'])
                            <button type="button" class="btn btn-g" data-ai-key-edit aria-controls="ai-key-editor" aria-expanded="{{ $editingKey ? 'true' : 'false' }}" @if($editingKey) hidden @endif>Modifica</button>
                        @endif
                    </div>
                    <input type="hidden" name="replace_api_key" value="{{ $settings['key_saved'] && $editingKey ? '1' : '0' }}">
                    <div id="ai-key-editor" class="ai-key-editor" @if(!$editingKey) hidden @endif>
                        <x-form-group name="ai_api_key" :label="$settings['key_saved'] ? 'Nuova chiave API OpenAI' : 'Chiave API OpenAI'">
                            <input id="field-ai-api-key" name="ai_api_key" type="password" class="form-in" autocomplete="new-password" autocapitalize="none" spellcheck="false" minlength="20" maxlength="2048" aria-describedby="ai-key-help" @disabled(!$editingKey) @required($settings['key_saved'] && $editingKey)>
                            <p id="ai-key-help" class="u-text-meta">La chiave è conservata cifrata.{{ $settings['key_saved'] ? ' Quella attuale resta in uso fino al salvataggio della nuova.' : '' }}{{ $errors->has('ai_api_key') || old('replace_api_key') ? ' Reinserisci la chiave: per riservatezza non viene riproposta dopo un errore.' : '' }}</p>
                        </x-form-group>
                        @if($settings['key_saved'])<button type="button" class="btn btn-g" data-ai-key-cancel>Annulla modifica</button>@endif
                    </div>
                    @if($settings['key_saved'])<noscript><p>Per sostituire la chiave abilita JavaScript nel browser. La chiave salvata resta configurata.</p></noscript>@endif
                </div>
                <div class="ai-settings-fields">
                    <x-form-group name="model" label="Modello"><select id="field-ai-model" name="model" class="form-sel">@foreach(\App\Services\AiPricing::MODELS as $id=>$model)<option value="{{ $id }}" @selected(old('model',$settings['model'])===$id)>{{ $model['name'] }}</option>@endforeach</select></x-form-group>
                    <x-form-group name="monthly_budget" label="Limite mensile (USD)"><input id="field-ai-monthly-budget" name="monthly_budget" type="number" min="0.01" max="10000" step="0.01" class="form-in" value="{{ old('monthly_budget',$settings['monthly_budget']) }}" required></x-form-group>
                    <x-form-group name="document_budget" label="Massimo per documento (USD)"><input id="field-ai-document-budget" name="document_budget" type="number" min="0.01" max="10000" step="0.01" class="form-in" value="{{ old('document_budget',$settings['document_budget']) }}" required></x-form-group>
                </div>
                <input type="hidden" name="enabled" value="0">
                <label class="contract-confirm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings['enabled']))> Abilita l’AI per la lettura dei contratti</label>
                <p class="u-text-meta">{{ $ready ? 'Lettura AI abilitata nella configurazione salvata.' : 'Lettura AI non attiva nella configurazione salvata. La lettura locale resta disponibile.' }}</p>
                <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Salva configurazione AI</button></div>
            </form>
            <div class="ai-connection">
                <form method="POST" action="{{ route('ai.connection.check') }}" data-ai-connection-form aria-label="Verifica collegamento OpenAI">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $settings['revision'] }}">
                    <button type="submit" class="btn btn-g" data-ai-connection-button @disabled(!$settings['key_saved'] || $editingKey || old('model',$settings['model']) !== $settings['model'])>Verifica collegamento</button>
                    <p class="u-text-meta">Controlla la chiave e l’accesso al modello salvato. Non invia documenti e non verifica il credito o la lettura dei file.</p>
                </form>
                <p class="u-text-meta" data-ai-check-hint @if($settings['key_saved'] && !$editingKey && old('model',$settings['model']) === $settings['model']) hidden @endif>{{ !$settings['key_saved'] ? 'Salva la chiave per verificare il collegamento.' : 'Salva la nuova chiave o il modello prima di verificare.' }}</p>
                <div class="ai-connection-result" data-ai-connection-result data-state="{{ $connectionResult['state'] ?? '' }}" role="status" aria-live="polite" aria-atomic="true" @if(!$connectionResult) hidden @endif>{{ $connectionResult ? $connectionResult['message'].' Verifica del '.$connectionResult['checked_at'].'.' : '' }}</div>
            </div>
            <details class="ai-configuration-help">
                <summary>Elaborazione dei documenti e costi</summary>
                <p class="u-text-meta">Abilitando l’AI, i documenti caricati per questa funzione saranno elaborati da OpenAI. Prima di ogni elaborazione vengono controllati i limiti di spesa. La polizza viene creata soltanto dopo il controllo dell’operatore.</p>
                <p class="u-text-meta">Tariffe di riferimento verificate il {{ \Carbon\Carbon::parse(\App\Services\AiPricing::VERIFIED_ON)->format('d/m/Y') }}: <a href="{{ \App\Services\AiPricing::SOURCE }}" target="_blank" rel="noopener">prezzi ufficiali OpenAI</a>. Il contatore riguarda le chiamate effettuate da questo gestionale.</p>
            </details>
        </div>
    @endif
</x-panel>
