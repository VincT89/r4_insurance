<x-app-layout title="Email ricevuta">
    <div class="page-back-row"><a class="btn btn-g" href="{{ route('inbound-emails.index') }}">Email ricevute</a></div>
    <x-page-header><x-slot:title><span class="inbound-email-subject">{{ $email->payload['subject'] ?: 'Senza oggetto' }}</span></x-slot:title><x-slot:meta>{{ $email->status_label }} · Ricevuta il {{ $email->received_at->format('d/m/Y H:i') }}</x-slot:meta><x-slot:actions><a class="btn btn-p" href="#email-association-title">{{ $email->status==='assigned' ? 'Modifica associazione' : 'Associa email' }}</a></x-slot:actions></x-page-header>
    @include('agency-communications.navigation')
    <section class="inbound-email-message" aria-label="Messaggio ricevuto">
        <dl class="inbound-email-details">
            <div><dt>Da</dt><dd>{{ $email->payload['from']['name'] }} {{ $email->payload['from']['address'] }}</dd></div>
            <div><dt>A</dt><dd>{{ implode(', ',$email->payload['to']) }}</dd></div>
            @if($email->payload['cc'])<div><dt>Copia</dt><dd>{{ implode(', ',$email->payload['cc']) }}</dd></div>@endif
            @if($email->payload['sent_at'])<div><dt>Data dichiarata dal mittente</dt><dd>{{ \Carbon\Carbon::parse($email->payload['sent_at'])->format('d/m/Y H:i P') }}</dd></div>@endif
        </dl>
        <div class="inbound-email-body">{{ $email->displayBody() ?: 'Il messaggio non contiene testo.' }}</div>
    </section>
    <section class="inbound-email-section" aria-labelledby="email-attachments-title">
        <h2 id="email-attachments-title">Allegati ricevuti</h2>
        <p class="u-text-meta">Conservati come ricevuti. Il caricamento non equivale a una verifica del contenuto.</p>
        <ul class="inbound-email-files">@forelse($email->files as $file)<li><a href="{{ route('inbound-emails.files.download',[$email,$file->id]) }}">Scarica {{ $file->original_name }}</a><span>{{ number_format($file->size/1024,1,',','.') }} KB</span></li>@empty<li>Nessun allegato.</li>@endforelse</ul>
    </section>
    <section class="inbound-email-section" aria-labelledby="email-association-title">
        <h2 id="email-association-title" tabindex="-1">Associazione</h2>
        @if($email->client)<p>Cliente: <a href="{{ route('clients.show',$email->client) }}">{{ $email->client->name }}</a>@if($email->quoteCase) · Pratica: <a href="{{ route('quotes.show',$email->quoteCase) }}">{{ $email->quoteCase->subject }}</a>@elseif($email->policy) · Polizza: <a href="{{ route('clients.policies.show',[$email->client,$email->policy]) }}">{{ $email->policy->number }}</a>@endif</p>@endif
        @if($email->assigned_at)<p class="u-text-meta">Ultimo aggiornamento: {{ $email->assigned_at->format('d/m/Y H:i') }} · {{ $email->operator?->name ?? 'Operatore non disponibile' }}</p>@endif
        <details class="inbound-email-client-search" @if(!$selectedClient || filled($filters['client_search']??null)) open @endif>
        <summary>{{ $selectedClient ? 'Cambia cliente' : 'Cerca cliente' }}</summary>
        <form method="GET" action="{{ route('inbound-emails.show',$email) }}#email-association-title" class="client-search-form" aria-label="Cerca cliente per l’email">
            <x-form-group label="Nome, email o codice fiscale" name="client_search"><input id="field-client-search" type="search" name="client_search" class="form-in" value="{{ $filters['client_search']??'' }}" maxlength="150" required></x-form-group><button class="btn btn-g">Cerca cliente</button>
        </form>
        @if(filled($filters['client_search']??null))
            <ul class="inbound-email-client-results">@forelse($clients as $candidate)<li><a href="{{ route('inbound-emails.show',[$email,'client'=>$candidate->id]) }}#email-association-title">{{ $candidate->name }}</a> {{ $candidate->email }}</li>@empty<li>Nessun cliente trovato. Prova un nome, un indirizzo email o il codice fiscale.</li>@endforelse</ul>
            @if($clients->count()===20)<p>Mostrati i primi 20 risultati. Affina la ricerca se il cliente non è presente.</p>@endif
        @endif
        </details>
        @if($selectedClient || $email->status==='assigned')
            @php($target=old('_inbound_email')===$email->public_id ? old('target','client') : ($email->insurance_quote_case_id?'quote':($email->insurance_policy_id?'policy':'client')))
            <form method="POST" action="{{ route('inbound-emails.associate',$email) }}" x-data="{target:@js($target)}" class="inbound-email-association" aria-label="Associa email">
                @csrf @method('PATCH')
                <input type="hidden" name="_inbound_email" value="{{ $email->public_id }}"><input type="hidden" name="version" value="{{ $email->version }}"><input type="hidden" name="client_id" value="{{ $selectedClient?->id }}">
                <p>Cliente scelto: <strong>{{ $selectedClient?->name }}</strong></p>
                <div class="form-row">
                    <x-form-group label="Destinazione" name="target" required><select id="field-target" name="target" class="form-sel" x-model="target">@foreach(['client'=>'Scheda cliente','quote'=>'Pratica preventivo','policy'=>'Polizza','unassigned'=>'Rimetti in Da associare'] as $key=>$label)<option value="{{ $key }}" @selected($target===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                    <div x-show="target==='quote'" @if($target!=='quote') x-cloak @endif><x-form-group label="Pratica preventivo" name="insurance_quote_case_id"><select id="field-insurance-quote-case-id" name="insurance_quote_case_id" class="form-sel" :disabled="target!=='quote'" :required="target==='quote'"><option value="">Scegli la pratica</option>@foreach($cases as $case)<option value="{{ $case->id }}" @selected((string)old('insurance_quote_case_id',$email->insurance_quote_case_id)===(string)$case->id)>{{ $case->subject }}</option>@endforeach</select></x-form-group></div>
                    <div x-show="target==='policy'" @if($target!=='policy') x-cloak @endif><x-form-group label="Polizza" name="insurance_policy_id"><select id="field-insurance-policy-id" name="insurance_policy_id" class="form-sel" :disabled="target!=='policy'" :required="target==='policy'"><option value="">Scegli la polizza</option>@foreach($policies as $policy)<option value="{{ $policy->id }}" @selected((string)old('insurance_policy_id',$email->insurance_policy_id)===(string)$policy->id)>{{ $policy->number }}</option>@endforeach</select></x-form-group></div>
                    <x-form-group label="Tipo di documento" name="document_kind" required><select id="field-document-kind" name="document_kind" class="form-sel">@foreach(\App\Models\InboundEmail::KINDS as $key=>$label)<option value="{{ $key }}" @selected(old('document_kind',$email->document_kind)===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                </div>
                <details class="inbound-email-note" @if(filled(old('association_note',$email->association_note)) || $errors->has('association_note')) open @endif><summary>Nota interna facoltativa</summary><x-form-group label="Nota interna sull’associazione" name="association_note"><textarea id="field-association-note" name="association_note" class="form-in" rows="2" maxlength="1000">{{ old('association_note',$email->association_note) }}</textarea></x-form-group></details>
                <p>Email e allegati saranno consultabili dalla destinazione scelta. L’associazione può essere corretta o rimossa.</p>
                <label class="inbound-email-confirm"><input type="checkbox" name="confirmed" value="1" required> Confermo la destinazione scelta.</label>
                <button class="btn btn-p">Salva associazione</button>
            </form>
        @else<p>Cerca e scegli il cliente a cui collegare il messaggio.</p>@endif
    </section>
    @if($history->isNotEmpty())<details class="inbound-email-section"><summary>Storico ricezione e associazioni</summary><ul>@foreach($history as $event)<li>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->user?->name ?? 'Collegamento email' }} · {{ $event->action==='inbound_email_received' ? 'Messaggio acquisito' : 'Associazione aggiornata' }}@if($event->new_values['client_id']??null) · Cliente #{{ $event->new_values['client_id'] }}@endif @if($event->new_values['insurance_quote_case_id']??null) · Pratica #{{ $event->new_values['insurance_quote_case_id'] }}@endif @if($event->new_values['insurance_policy_id']??null) · Polizza #{{ $event->new_values['insurance_policy_id'] }}@endif</li>@endforeach</ul><p>Ultimi 20 eventi. Lo storico completo resta nel registro attività.</p></details>@endif
</x-app-layout>
