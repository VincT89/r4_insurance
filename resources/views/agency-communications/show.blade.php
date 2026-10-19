<x-app-layout title="Riepilogo email">
    <x-page-header><x-slot:title>{{ $communication->status === 'review' ? 'Controlla l’email prima di inviarla' : 'Dettaglio comunicazione' }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route(($payload['kind']??null)==='collections' ? 'agency-communications.collections' : 'agency-communications.index',$payload['filters']) }}">Torna alla selezione</a><a class="btn btn-g" href="{{ route('agency-communications.history') }}">Storico invii</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <dl class="email-envelope"><div><dt>Mittente</dt><dd>{{ $payload['from']['name'] }}<br>{{ $payload['from']['address'] ?: 'Da configurare' }}</dd></div><div><dt>Destinatario</dt><dd>{{ $payload['recipient']['name'] }}<br>{{ $payload['recipient']['address'] }}</dd></div><div><dt>Oggetto</dt><dd>{{ $payload['subject'] }}</dd></div><div><dt>Esito</dt><dd>{{ $communication->status_label }}@if($communication->sent_at)<br>{{ $communication->sent_at->format('d/m/Y H:i') }}@endif</dd></div></dl>
        @if($communication->status==='sent')<p class="u-mt-md">Il messaggio è stato accettato dal server di posta. La consegna finale alla casella dell’agenzia dipende dal servizio destinatario.</p>@endif
    </x-panel>
    <x-panel title="Polizze selezionate" padded><div class="client-table-scroll" role="region" aria-label="Polizze incluse nell’email" tabindex="0"><table class="t-table"><thead><tr><th>Polizza</th><th>Contraente</th><th>Compagnia</th><th>Decorrenza</th><th>Scadenza</th><th>Frazionamento</th><th>Comunicazione</th></tr></thead><tbody>
        @foreach($payload['policies'] as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['client'] }}</td><td>{{ $row['company'] }}</td><td>{{ $row['starts_on'] }}</td><td>{{ $row['expires_on'] }}</td><td>{{ $row['frequency'] }}</td><td>{{ $row['ad_horas'] ? 'Ad horas' : 'Ordinaria' }}</td></tr>@endforeach
    </tbody></table></div></x-panel>
    @if(($payload['kind']??null)==='collections')<x-panel title="Titoli e incassi selezionati"><div class="client-table-scroll" role="region" aria-label="Incassi inclusi nell’email" tabindex="0"><table class="t-table"><thead><tr><th>Data incasso</th><th>Polizza / titolo</th><th>Tipo titolo</th><th>Importo</th><th>Metodo pagamento</th><th>Destinatario pagamento</th><th>Residuo titolo</th></tr></thead><tbody>@foreach($payload['collections'] as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['number'] }} / #{{ $row['title_id'] }}</td><td>{{ $row['kind'] }}</td><td>{{ $row['amount'] }}</td><td>{{ $row['payment_method'] }}</td><td>{{ $row['recipient'] }}</td><td>{{ $row['balance'] }}</td></tr>@endforeach</tbody></table></div></x-panel>@endif
    <x-panel title="Testo dell’email" padded><pre class="email-message-body">{{ $payload['body'] }}</pre></x-panel>
    @if($communication->status==='review')
        <x-panel padded>
            @if(!$mailReady)<p class="u-mb-md">Completa la casella in <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>, poi prepara un nuovo riepilogo con il mittente configurato.</p>@endif
            @if($communication->review_expires_at->isPast())<p>Il riepilogo è scaduto. Torna alla selezione per controllare i dati aggiornati.</p>
            @elseif((int)$communication->user_id !== auth()->id())<p>Questo riepilogo deve essere confermato dall’utente che lo ha preparato. Puoi creare una nuova selezione.</p>
            @else<form method="POST" action="{{ route('agency-communications.send',$communication) }}" x-data="{reviewed:false,busy:false}" @submit="busy=true">@csrf
                <label class="email-review-confirm"><input type="checkbox" name="reviewed" value="1" x-model="reviewed" required> Ho controllato il destinatario e tutte le polizze elencate</label>
                <div class="insurance-form-actions"><span class="form-help">Verrà inviata una sola email a questa agenzia.</span><button type="submit" class="btn btn-p" @disabled(!$mailReady) :disabled="!reviewed || busy || {{ $mailReady ? 'false' : 'true' }}" x-text="busy ? 'Invio in corso…' : 'Conferma e invia email'">Conferma e invia email</button></div>
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
</x-app-layout>
