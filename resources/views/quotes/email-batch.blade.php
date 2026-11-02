<x-app-layout title="Controlla le richieste di preventivo">
<x-page-header><x-slot:title>Richieste di preventivo</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Torna alla pratica</a>@if($case->status==='open')<a class="btn btn-g" href="{{ route('quotes.emails.create',$case) }}">Prepara un’altra richiesta</a>@endif</x-slot:actions></x-page-header>
<x-panel title="Destinatari e riepilogo" padded>
<p>{{ $case->client->name }} — {{ $case->subject }}</p>
<p class="u-mt-sm">Mittente: {{ $payload['from']['name'] }} — {{ $payload['from']['address'] ?: 'Da configurare' }}.</p>
<div class="client-table-scroll u-mt-md" role="region" aria-label="Destinatari delle richieste" tabindex="0"><table class="t-table"><thead><tr><th>Compagnia</th><th>Destinatario</th><th>Email</th><th>Esito</th><th>Richiesta</th></tr></thead><tbody>
@foreach($messages as $message)@php $envelope=$message->payload; @endphp<tr><td>{{ $envelope['target']['company_name'] }}</td><td>{{ $envelope['target']['agency_name'] ?: 'Direttamente alla compagnia' }}</td><td>{{ $envelope['recipient']['address'] }}</td><td>{{ $message->status_label }}</td><td><a href="{{ route('quote-emails.show',$message) }}">Apri richiesta completa</a></td></tr>@endforeach
</tbody></table></div>
<p class="u-text-meta u-mt-md">Ogni destinatario riceverà un messaggio separato con gli allegati qui sotto. Per le agenzie, il testo indica anche la compagnia per cui si richiede il preventivo.</p>
</x-panel>
<x-panel title="Oggetto e testo" padded><p class="u-mb-md"><strong>{{ $payload['subject'] }}</strong></p><pre class="email-message-body">{{ $payload['request_body'] }}</pre></x-panel>
<x-panel title="Allegati per tutti i destinatari" padded>@forelse($payload['attachments'] as $file)<div class="u-mb-md"><a href="{{ route('attachments.download',$file['id']) }}">{{ $file['name'] }}</a> — {{ number_format($file['size']/1024,1,',','.') }} KB
@if($file['validity']??null)<div><x-status-text :alert="$file['expired']??false">{{ $file['validity'] }}</x-status-text></div>@endif</div>@empty<p>Nessun allegato incluso.</p>@endforelse</x-panel>
@php
    $reviews = $messages->where('status','review');
    $onlyReady = $reviews->count() + $messages->where('status','sent')->count() === $messages->count();
    $expired = $reviews->contains(fn($message)=>$message->review_expires_at->isPast());
    $own = $messages->every(fn($message)=>(int)$message->user_id===auth()->id());
@endphp
<x-panel padded>
@if($own && $reviews->count()===$messages->count() && $case->status==='open')
@can('updateRegistry',$case->client)<div class="u-mb-md"><a class="btn btn-g" href="{{ route('quotes.emails.create',[$case,'draft'=>$messages->first()->id]) }}">Modifica richiesta</a></div>@endcan
@endif
@if($reviews->isEmpty() && $messages->every(fn($message)=>$message->status==='sent'))
    <p>Tutte le richieste sono registrate nella pratica. L’accettazione del server di posta non certifica la consegna ai destinatari.</p>
@elseif($messages->every(fn($message)=>$message->status==='superseded'))
    <p>Questa anteprima è stata sostituita dopo una modifica. Apri la richiesta più recente nello storico della pratica.</p>
@elseif(!$onlyReady)
    <p>Ci sono invii in corso o da verificare. Apri le singole richieste per controllare gli esiti con l’admin prima di ripetere l’invio.</p>
@elseif($expired)
    <p>Il controllo è scaduto. Prepara una nuova richiesta per verificare i dati aggiornati.</p>
@elseif(!$own)
    <p>La conferma è riservata all’operatore che ha preparato queste richieste.</p>
@else
    @if(!$mailReady)<p class="u-mb-md">La casella di invio deve essere configurata dall’admin.@can('system.admin') <a href="{{ route('email-settings.edit') }}">Apri Impostazioni email</a>.@endcan Dopo la configurazione prepara nuove richieste con il mittente corretto.</p>@endif
    @can('updateRegistry',$case->client)<form method="POST" action="{{ route('quote-email-batches.send',$batch) }}" x-data="{reviewed:false,busy:false}" @submit="busy=true">@csrf
    <label class="email-review-confirm"><input type="checkbox" name="reviewed" value="1" x-model="reviewed" required> Ho controllato tutti i destinatari, gli indirizzi email, il testo e gli allegati.</label>
    <div class="insurance-form-actions"><button class="btn btn-p" @disabled(!$mailReady) :disabled="!reviewed || busy || {{ $mailReady ? 'false' : 'true' }}" x-text="busy ? 'Invio in corso…' : 'Conferma e invia a {{ $reviews->count() }} destinatari'">Conferma e invia a {{ $reviews->count() }} destinatari</button></div>
    </form>@endcan
@endif
</x-panel>
</x-app-layout>
