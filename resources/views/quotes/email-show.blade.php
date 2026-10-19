<x-app-layout title="Richiesta di preventivo">
<x-page-header><x-slot:title>{{ $message->status==='review' ? 'Controlla la richiesta prima di inviarla' : 'Richiesta di preventivo' }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.show',$message->quoteCase) }}">Torna alla pratica</a>
@if($message->quoteCase->status==='open')
@can('updateRegistry',$message->quoteCase->client)<a class="btn btn-g" href="{{ route('quotes.emails.create',$message->quoteCase) }}">Prepara un’altra richiesta</a>@endcan
@endif
</x-slot:actions></x-page-header>
<x-panel title="Destinatario e invio" padded><dl class="email-envelope">
<div><dt>Cliente</dt><dd>{{ $message->quoteCase->client->name }}</dd></div><div><dt>Mittente</dt><dd>{{ $payload['from']['name'] }}<br>{{ $payload['from']['address'] ?: 'Da configurare' }}</dd></div>
<div><dt>Compagnia destinataria</dt><dd>{{ $payload['recipient']['name'] }}<br>{{ $payload['recipient']['address'] }}</dd></div><div><dt>Oggetto</dt><dd>{{ $payload['subject'] }}</dd></div>
<div><dt>Esito</dt><dd>{{ $message->status_label }}@if($message->sent_at)<br>{{ $message->sent_at->format('d/m/Y H:i') }}@endif</dd></div><div><dt>Preparata da</dt><dd>{{ $message->user?->name ?: 'Utente non disponibile' }}</dd></div>
</dl>@if($message->status==='sent')<p class="u-mt-md">La richiesta è stata registrata nella pratica. L’accettazione del server di posta non certifica la consegna alla casella della compagnia.</p>@endif</x-panel>
<x-panel title="Testo da inviare" padded><pre class="email-message-body">{{ $payload['body'] }}</pre></x-panel>
<x-panel title="Allegati inclusi" padded>@forelse($payload['attachments'] as $file)<div class="u-mb-md"><a href="{{ route('attachments.download',$file['id']) }}">{{ $file['name'] }}</a> · {{ number_format($file['size']/1024,1,',','.') }} KB
@if(isset($file['category']))<div class="u-text-meta">{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$file['category']] ?? 'Documento' }}</div>@endif
@if($file['validity']??null)<div><x-status-text :alert="$file['expired']??false">{{ $file['validity'] }}</x-status-text></div>@endif</div>@empty<p>Nessun allegato incluso.</p>@endforelse</x-panel>
@if($message->status==='review')<x-panel padded>
@if(!$mailReady)<p class="u-mb-md">La casella di invio deve essere configurata dall’admin.@can('system.admin') <a href="{{ route('email-settings.edit') }}">Apri Impostazioni email</a>.@endcan Dopo la configurazione prepara una nuova richiesta con il mittente corretto.</p>@endif
@if($message->review_expires_at->isPast())<p>Il controllo è scaduto. Prepara una nuova richiesta per verificare i dati aggiornati.</p>
@elseif((int)$message->user_id !== auth()->id())<p>La conferma è riservata all’operatore che ha preparato questa richiesta.</p>
@else @can('updateRegistry',$message->quoteCase->client)<form method="POST" action="{{ route('quote-emails.send',$message) }}" x-data="{reviewed:false,busy:false}" @submit="busy=true">@csrf
<label class="email-review-confirm"><input name="reviewed" type="checkbox" value="1" x-model="reviewed" required> Ho controllato compagnia, indirizzo email, testo e tutti gli allegati da inviare.</label>
<div class="insurance-form-actions"><span class="form-help">Un solo messaggio alla compagnia indicata, senza copie ad altre compagnie.</span><button class="btn btn-p" @disabled(!$mailReady) :disabled="!reviewed || busy || {{ $mailReady ? 'false' : 'true' }}" x-text="busy ? 'Invio in corso…' : 'Conferma e invia richiesta'">Conferma e invia richiesta</button></div></form>@endcan @endif
</x-panel>
@elseif($message->canResolve())<x-panel title="Verifica dell’esito" padded><p class="u-mb-md">Il server non ha confermato l’esito. La richiesta potrebbe essere già arrivata: l’admin deve verificarlo con il servizio di posta prima di riprovare.</p>
@can('system.admin')<form method="POST" action="{{ route('quote-emails.resolve',$message) }}">@csrf<div class="form-row">
<x-form-group label="Esito verificato" name="outcome" required><select id="field-outcome" name="outcome" class="form-sel" required><option value="">Seleziona</option><option value="sent">Il messaggio è stato inviato</option><option value="not_sent">Il messaggio non è stato inviato</option></select></x-form-group>
<x-form-group label="Come hai verificato l’esito" name="note" required><textarea id="field-note" name="note" class="form-ta" rows="2" minlength="10" maxlength="1000" required></textarea></x-form-group>
</div><label><input type="checkbox" name="checked" value="1" required> Ho verificato l’esito con il servizio di posta.</label><div class="insurance-form-actions"><button class="btn btn-p">Registra esito verificato</button></div></form>@endcan</x-panel>
@elseif($message->status==='sending')<x-panel padded><p>Invio in corso. Aggiorna la pagina per verificarne l’esito senza ripetere l’operazione.</p></x-panel>
@elseif($message->status==='failed')<x-panel padded><p>Mancato invio verificato. Puoi preparare e controllare una nuova richiesta.</p></x-panel>@endif
</x-app-layout>
