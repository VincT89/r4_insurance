<x-app-layout title="Riepilogo email">
    <div x-data="{attachmentsDirty:false}">
    @if(in_array($communication->status,['sending','sent','uncertain']))
        <span hidden data-communication-claimed data-selection-user="{{ auth()->id() }}" data-selection-path="{{ route(($payload['kind']??null)==='collections' ? 'agency-communications.collections' : 'agency-communications.index',[],false) }}" data-claimed-ids="{{ json_encode($payload['selection_keys'] ?? array_column($payload[($payload['kind']??null)==='collections' ? 'collections' : 'policies'],'id')) }}"></span>
    @endif
    <x-page-header><x-slot:title>{{ $communication->status === 'review' ? 'Controlla l’email prima di inviarla' : 'Dettaglio comunicazione' }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route(($payload['kind']??null)==='collections' ? 'agency-communications.collections' : 'agency-communications.index',$payload['filters']) }}">Torna alla selezione</a><a class="btn btn-g" href="{{ route('agency-communications.history') }}">Storico invii</a></x-slot:actions></x-page-header>
    @if($voidedCollections->isNotEmpty())
        <x-panel title="Incassi stornati dopo la preparazione" padded>
            <p class="client-overdue">Questo riepilogo contiene incassi ora stornati. Se l’email è già partita o ha un esito incerto, verifica con l’agenzia la rettifica necessaria.</p>
            @foreach($voidedCollections as $voided)<p class="u-mt-sm"><a href="{{ route('titles.show',$voided->insurance_title_id) }}">Incasso #{{ $voided->id }}</a> · Stornato il {{ $voided->voided_at->format('d/m/Y H:i') }} · {{ $voided->void_reason }}</p>@endforeach
        </x-panel>
    @endif
    <x-panel padded>
        <dl class="email-envelope"><div><dt>Mittente</dt><dd>{{ $payload['from']['name'] }}<br>{{ $payload['from']['address'] ?: 'Da configurare' }}</dd></div><div><dt>Destinatario</dt><dd>{{ $payload['recipient']['name'] }}<br>{{ $payload['recipient']['address'] }}</dd></div><div><dt>Oggetto</dt><dd>{{ $payload['subject'] }}</dd></div><div><dt>Esito</dt><dd>{{ $communication->status_label }}@if($communication->sent_at)<br>{{ $communication->sent_at->format('d/m/Y H:i') }}@endif</dd></div></dl>
        @if($communication->status==='sent')<p class="u-mt-md">Il messaggio è stato accettato dal server di posta. La consegna finale alla casella dell’agenzia dipende dal servizio destinatario.</p>@endif
    </x-panel>
    @php($baseRows = array_filter($payload['policies'], fn ($row) => !isset($payload['policy_ids']) || in_array($row['id'], $payload['policy_ids'], true)))
    @if(count($baseRows))
        <x-panel title="Polizze selezionate" padded><div class="client-table-scroll" role="region" aria-label="Polizze incluse nell’email" tabindex="0"><table class="t-table"><thead><tr><th>Polizza</th><th>Contraente</th><th>Compagnia</th><th>Decorrenza</th><th>Scadenza</th><th>Premio polizza</th><th>Frazionamento</th><th>Comunicazione</th></tr></thead><tbody>
            @foreach($baseRows as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['client'] }}</td><td>{{ $row['company'] }}</td><td>{{ $row['starts_on'] }}</td><td>{{ $row['expires_on'] }}</td><td>{{ $row['premium'] ?? 'Non riportato' }}</td><td>{{ $row['frequency'] }}</td><td>{{ $row['ad_horas'] ? 'Ad horas' : 'Ordinaria' }}</td></tr>@endforeach
        </tbody></table></div></x-panel>
    @endif
    @if(!empty($payload['changes']))
        <x-panel title="Aggiunte di coperture selezionate" padded>
            <p class="form-help u-mb-md">Gli importi sono i premi aggiuntivi delle appendici. Non comprendono il premio del contratto di base e non attestano un incasso.</p>
            <div class="client-table-scroll" role="region" aria-label="Appendici incluse nell’email" tabindex="0"><table class="t-table"><thead><tr><th>Polizza</th><th>Appendice</th><th>Contraente</th><th>Compagnia</th><th>Registrata il</th><th>Decorrenza</th><th>Scadenza</th><th>Premio aggiuntivo</th><th>Coperture concordate</th></tr></thead><tbody>
                @foreach($payload['changes'] as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['client'] }}</td><td>{{ $row['company'] }}</td><td>{{ $row['recorded_at'] }}</td><td>{{ $row['starts_on'] }}</td><td>{{ $row['expires_on'] }}</td><td>{{ $row['premium'] }}</td><td>{{ $row['description'] }}</td></tr>@endforeach
            </tbody></table></div>
        </x-panel>
    @endif
    @if(($payload['kind']??null)==='collections')<x-panel title="Titoli e incassi selezionati"><div class="client-table-scroll" role="region" aria-label="Incassi inclusi nell’email" tabindex="0"><table class="t-table"><thead><tr><th>Data incasso</th><th>Polizza / titolo</th><th>Tipo titolo</th><th>Importo</th><th>Metodo pagamento</th><th>Destinatario pagamento</th><th>Residuo titolo</th></tr></thead><tbody>@foreach($payload['collections'] as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['number'] }} / #{{ $row['title_id'] }}</td><td>{{ $row['kind'] }}</td><td>{{ $row['amount'] }}</td><td>{{ $row['payment_method'] }}</td><td>{{ $row['recipient'] }}</td><td><x-status-text :alert="$row['balance'] !== \App\Support\Money::format(0)">{{ $row['balance'] }}</x-status-text></td></tr>@endforeach</tbody></table></div></x-panel>@endif
    <x-panel title="Allegati dell’email" padded>
        <p class="u-mb-md">Allegati inclusi: {{ $includedDocuments->count() }}. @if($canEditAttachments)I documenti disponibili vengono recuperati dalle polizze selezionate e dai clienti associati. Scegli quelli pertinenti per {{ $payload['recipient']['name'] }}.@else Le copie conservate corrispondono ai file inclusi nel riepilogo.@endif</p>
        @if($canEditAttachments)
            <form method="POST" action="{{ route('agency-communications.documents',$communication) }}" enctype="multipart/form-data" class="form-stack" @change="attachmentsDirty=true">
                @csrf
                <input type="hidden" name="attachment_revision" value="{{ $communication->attachment_revision }}">
                @include('agency-communications.documents',['editable'=>true])
                @if($availableDocuments->isNotEmpty())
                    <div class="communication-document-source">
                        <h3>Documenti già presenti</h3>
                        @foreach($availableDocuments->groupBy('attachable_id') as $documents)
                            <details class="communication-document-group" @if($availableDocuments->pluck('attachable_id')->unique()->count()===1) open @endif>
                                <summary>{{ $documents->first()->attachable->name }} · {{ $documents->count() }} {{ $documents->count()===1 ? 'documento disponibile' : 'documenti disponibili' }}</summary>
                                @foreach($documents as $document)
                                    <div class="communication-document-row">
                                        <label><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(in_array($document->id,old('document_ids',[])))>
                                            <span><strong>{{ $document->original_name }}</strong><small>{{ $document->insurancePolicy ? 'Polizza '.$document->insurancePolicy->number : 'Anagrafica cliente' }}@if($document->policyChange) · Appendice {{ $document->policyChange->reference }}@endif · {{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento' }} · {{ number_format(ceil($document->size/1024),0,',','.') }} KB</small>
                                                @if($document->document_category==='identity')<small class="{{ $document->expires_on?->lt(today()) ? 'client-overdue' : '' }}">{{ $document->validity_label }}</small>@endif
                                            </span>
                                        </label>
                                        <a class="btn btn-g" href="{{ $document->downloadUrl() }}" aria-label="Scarica {{ $document->original_name }}">Scarica</a>
                                    </div>
                                @endforeach
                            </details>
                        @endforeach
                    </div>
                @else<p class="form-help">Non ci sono altri documenti PDF, JPG o PNG disponibili per questa selezione.</p>@endif
                <x-form-group label="Carica altri allegati" name="files">
                    <input type="file" id="field-files" class="form-in" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                    <p class="form-help">PDF, JPG e PNG: fino a 10 nuovi file per caricamento e 50 allegati per email. Massimo 10 MB per file e 20 MB complessivi. I nuovi file rimarranno nello storico di questa comunicazione.</p>
                </x-form-group>
                <div class="insurance-form-actions"><span class="form-help">Salva la selezione per aggiornare il riepilogo. Nessuna email viene inviata.</span><button class="btn btn-p" type="submit">Aggiorna allegati</button></div>
            </form>
        @else
            @include('agency-communications.documents',['editable'=>false])
        @endif
    </x-panel>
    <x-panel title="Testo dell’email" padded><pre class="email-message-body">{{ $payload['body'] }}</pre></x-panel>
    @if($communication->status==='review')
        <x-panel padded>
            @if(!$mailReady)<p class="u-mb-md">Completa la casella in <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>, poi prepara un nuovo riepilogo con il mittente configurato.</p>@endif
            @if($voidedCollections->isNotEmpty())<p>Prepara una nuova selezione con gli incassi aggiornati.</p>
            @elseif($communication->review_expires_at->isPast())<p>Il riepilogo è scaduto. Torna alla selezione per controllare i dati aggiornati.</p>
            @elseif((int)$communication->user_id !== auth()->id())<p>Questo riepilogo deve essere confermato dall’utente che lo ha preparato. Puoi creare una nuova selezione.</p>
            @else<form method="POST" action="{{ route('agency-communications.send',$communication) }}" x-data="{reviewed:false,busy:false}" @submit="busy=true">@csrf
                <input type="hidden" name="attachment_revision" value="{{ $communication->attachment_revision }}">
                <p x-cloak x-show="attachmentsDirty" class="u-mb-md">Hai modificato la selezione dei file: premi «Aggiorna allegati», poi controlla il riepilogo aggiornato.</p>
                <label class="email-review-confirm"><input type="checkbox" name="reviewed" value="1" x-model="reviewed" :disabled="attachmentsDirty" required> Ho controllato il destinatario, {{ ($payload['kind']??null)==='collections' ? 'gli incassi, gli importi e i metodi di pagamento' : 'le operazioni elencate e i relativi premi' }} e gli allegati inclusi</label>
                <div class="insurance-form-actions"><span class="form-help">Verrà inviata una sola email a questa agenzia. Allegati inclusi: {{ $includedDocuments->count() }}.</span><button type="submit" class="btn btn-p" @disabled(!$mailReady) :disabled="!reviewed || busy || attachmentsDirty || {{ $mailReady ? 'false' : 'true' }}" x-text="busy ? 'Invio in corso…' : 'Conferma e invia email'">Conferma e invia email</button></div>
            </form>@endif
        </x-panel>
    @elseif($communication->canResolve())
        <x-panel title="Verifica dell’esito" padded>
            <p class="u-mb-md">La connessione non ha confermato l’esito. Il messaggio potrebbe essere stato accettato: verifica con il servizio di posta prima di riprovare.</p>
            <form method="POST" action="{{ route('agency-communications.resolve',$communication) }}" class="form-stack">@csrf<div class="form-row">
                <x-form-group name="outcome" label="Esito verificato"><select id="field-outcome" class="form-sel" name="outcome" required><option value="">Seleziona l’esito</option><option value="sent">Il messaggio è stato inviato</option><option value="not_sent">Il messaggio non è stato inviato</option></select></x-form-group>
                <x-form-group name="note" label="Come hai verificato"><input id="field-note" class="form-in" name="note" minlength="5" maxlength="500" required></x-form-group>
            </div><label class="email-review-confirm"><input type="checkbox" name="checked" value="1" required> Ho verificato l’esito con il servizio di posta</label><div class="insurance-form-actions"><button class="btn btn-p">Registra esito</button></div></form>
        </x-panel>
    @elseif($communication->status==='sending')<x-panel padded><p>Invio in corso. Aggiorna questa pagina per verificarne l’esito; non ripetere l’operazione.</p></x-panel>
    @elseif($communication->status==='failed')<x-panel padded><p>Mancato invio confermato. Per riprovare torna alla selezione e controlla un nuovo riepilogo.</p></x-panel>@endif
    </div>
</x-app-layout>
