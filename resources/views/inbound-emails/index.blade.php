<x-app-layout title="Email ricevute">
    <x-page-header><x-slot:title>Email ricevute</x-slot:title><x-slot:meta>Messaggi e allegati da collegare a clienti, preventivi e polizze.</x-slot:meta></x-page-header>
    @include('agency-communications.navigation')
    @unless($ready)
        <p>La ricezione email è predisposta nel progetto e deve ancora essere attivata nell’archivio. La casella verrà collegata successivamente.</p>
    @else
        @unless($connected)<p class="communication-mail-notice">Il collegamento alla casella non è attivo. I messaggi già ricevuti restano consultabili.</p>@endunless
        @if(filled($filters['case']??null))<p>Elenco limitato alla pratica preventivo selezionata.</p>@elseif(filled($filters['policy']??null))<p>Elenco limitato alla polizza selezionata.</p>@elseif(filled($filters['client']??null))<p>Elenco limitato al cliente selezionato.</p>@endif
        @php($contextFilters=\Illuminate\Support\Arr::only($filters,['client','case','policy']))
        <nav class="inbound-email-views" aria-label="Stato delle email">
            @foreach(['unassigned'=>'Da associare','assigned'=>'Associate','all'=>'Tutte'] as $key=>$label)<a href="{{ route('inbound-emails.index',array_merge($filters,['status'=>$key])) }}" @if($filters['status']===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
        </nav>
        <details class="inbound-email-filters" @if(filled($filters['sender']??null) || filled($filters['from']??null) || filled($filters['to']??null) || $errors->any()) open @endif>
        <summary>Filtra per mittente o data</summary>
        <form method="GET" action="{{ route('inbound-emails.index') }}" class="client-search-form" aria-label="Filtra email ricevute">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            @foreach(['client','case','policy'] as $filter)@if(filled($filters[$filter]??null))<input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">@endif @endforeach
            <div class="form-row">
                <x-form-group label="Email esatta del mittente" name="sender"><input id="field-sender" class="form-in" type="email" name="sender" value="{{ $filters['sender']??'' }}" maxlength="254"></x-form-group>
                <x-form-group label="Ricevute dal" name="from"><input id="field-from" class="form-in" type="date" name="from" value="{{ $filters['from']??'' }}"></x-form-group>
                <x-form-group label="Al" name="to"><input id="field-to" class="form-in" type="date" name="to" value="{{ $filters['to']??'' }}"></x-form-group>
            </div>
            <div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('inbound-emails.index',array_merge($contextFilters,['status'=>$filters['status']])) }}">Azzera mittente e date</a></div>
        </form>
        </details>
        @if($contextFilters)<p><a href="{{ route('inbound-emails.index',['status'=>'all']) }}">Mostra la posta di tutti i clienti</a></p>@endif
        <p>{{ $emails->total() }} email</p>
        <div class="inbound-email-list" aria-label="Email ricevute">
            @forelse($emails as $email)
                <article class="inbound-email-row">
                    <div><h2><a href="{{ route('inbound-emails.show',$email) }}">{{ $email->payload['subject'] ?: 'Senza oggetto' }}</a></h2><p>{{ $email->payload['from']['name'] ?: $email->payload['from']['address'] }}@if($email->payload['from']['name']) · {{ $email->payload['from']['address'] }}@endif</p></div>
                    <div><p><time datetime="{{ $email->received_at->toIso8601String() }}">{{ $email->received_at->format('d/m/Y H:i') }}</time> · {{ $email->files_count }} {{ $email->files_count===1 ? 'allegato' : 'allegati' }}</p><p>{{ $email->status_label }}@if($email->client) · {{ $email->client->name }}@endif</p></div>
                </article>
            @empty<p>{{ $filters['status']==='unassigned' ? 'Non ci sono email da associare con i filtri attuali.' : 'Nessuna email corrisponde ai filtri.' }}</p>@if($filters['status']==='unassigned')<a href="{{ route('inbound-emails.index',array_merge($contextFilters,['status'=>'assigned'])) }}">Consulta le email già associate</a>@endif@endforelse
        </div>
        {{ $emails->links() }}
    @endunless
</x-app-layout>
