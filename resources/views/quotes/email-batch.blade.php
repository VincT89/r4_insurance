@php
    $reviews = $messages->where('status','review');
    $onlyReady = $reviews->count() + $messages->where('status','sent')->count() === $messages->count();
    $expired = $reviews->contains(fn($message)=>$message->review_expires_at->isPast());
    $own = $messages->every(fn($message)=>(int)$message->user_id===auth()->id());
    $allInReview = $reviews->count() === $messages->count();
    $pageTitle = $allInReview ? 'Anteprima invio' : 'Riepilogo invio';
    $recipientsLabel = $messages->count() === 1 ? '1 destinatario' : $messages->count().' destinatari';
    $sendLabel = $reviews->count() === 1 ? 'Conferma e invia a 1 destinatario' : 'Conferma e invia a '.$reviews->count().' destinatari';
@endphp
<x-app-layout :title="$pageTitle">
<x-page-header>
    <x-slot:title>{{ $pageTitle }}</x-slot:title>
    <x-slot:meta>{{ $case->client->name }} · {{ $case->subject }}</x-slot:meta>
    <x-slot:actions>
        @can('updateRegistry',$case->client)@if($case->status==='open')
            @if($own && $allInReview)<a class="btn btn-g" href="{{ route('quotes.emails.create',[$case,'draft'=>$messages->first()->id]) }}">Modifica richiesta</a>
            @else<a class="btn btn-g" href="{{ route('quotes.emails.create',$case) }}">Prepara un’altra richiesta</a>@endif
        @endif@endcan
        <a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Torna alla pratica</a>
    </x-slot:actions>
</x-page-header>
<x-panel :title="$recipientsLabel" padded>
    @if(isset($payload['recipient_group']))<p>Gruppo di partenza <strong>{{ $payload['recipient_group']['name'] }}</strong>.</p>@endif
    <p>Ogni destinatario riceverà un’email separata. Nei messaggi alle agenzie è indicata la relativa compagnia.</p>
    <div class="client-table-scroll u-mt-md" role="region" aria-label="Destinatari delle richieste" tabindex="0">
        <table class="t-table" data-list-details="quote-deliveries" data-export-only><thead><tr><th>Compagnia</th><th>Destinatario</th><th>Email</th><th>Stato</th><th>Richiesta</th></tr></thead><tbody>
        @foreach($messages as $message)
            @php($envelope = $message->payload)
            <tr data-no-list-details><td>{{ $envelope['target']['company_name'] }}</td><td>{{ $envelope['target']['agency_name'] ?: 'Invio diretto alla compagnia' }}</td><td>{{ $envelope['recipient']['address'] }}</td><td>{{ $message->status_label }}</td>
                <td><a href="{{ route('quote-emails.show',$message) }}" aria-label="Apri email per {{ $envelope['target']['agency_name'] ? $envelope['recipient']['name'].' · '.$envelope['target']['company_name'] : $envelope['target']['company_name'] }}">Apri email</a></td></tr>
        @endforeach
        </tbody></table>
    </div>
    <details class="dispatch-disclosure"><summary>Esporta destinatari</summary><div data-export-controls></div></details>
</x-panel>
<x-panel title="Messaggio e allegati" padded>
    <p class="u-text-meta">Mittente {{ $payload['from']['address'] ?: 'da configurare' }}</p>
    <p class="u-mt-md u-mb-sm"><strong>{{ $payload['subject'] }}</strong></p>
    <pre class="email-message-body">{{ $payload['request_body'] }}</pre>
    <div class="dispatch-preview-files"><strong>Allegati</strong>
        @forelse($payload['attachments'] as $file)
            <p><a href="{{ route('attachments.download',$file['id']) }}">{{ $file['name'] }}</a> · {{ number_format($file['size']/1024,1,',','.') }} KB
            @if($file['validity']??null)<x-status-text :alert="$file['expired']??false">{{ $file['validity'] }}</x-status-text>@endif</p>
        @empty<p>Nessun allegato incluso.</p>@endforelse
    </div>
</x-panel>
<x-panel padded>
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
@elseif($case->status !== 'open')
    <p>La pratica è chiusa. Le richieste preparate restano consultabili nello storico.</p>
@else
    @if(!$mailReady)<p class="u-mb-md">La casella di invio deve essere configurata dall’admin.@can('system.admin') <a href="{{ route('email-settings.edit') }}">Apri Impostazioni email</a>.@endcan Dopo la configurazione prepara nuove richieste con il mittente corretto.</p>@endif
    @can('updateRegistry',$case->client)<form method="POST" action="{{ route('quote-email-batches.send',$batch) }}" x-data="{reviewed:false,busy:false}" @submit="busy=true">@csrf
    <label class="email-review-confirm"><input type="checkbox" name="reviewed" value="1" x-model="reviewed" required> Ho controllato tutti i destinatari, gli indirizzi email, il testo e gli allegati.</label>
    <div class="insurance-form-actions"><button class="btn btn-p" @disabled(!$mailReady) :disabled="!reviewed || busy || {{ $mailReady ? 'false' : 'true' }}" x-text="busy ? 'Invio in corso…' : @js($sendLabel)">{{ $sendLabel }}</button></div>
    </form>@endcan
@endif
</x-panel>
</x-app-layout>
