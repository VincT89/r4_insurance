<x-app-layout :title="$case->subject">
    @php
        $canManage = auth()->user()->can('updateRegistry', $case->client);
        $mayWork = $canManage && $case->status === 'open';
        $waiting = $case->quotes->where('status', 'requested');
        $responses = $case->quotes->where('status', '!=', 'requested');
        $winner = $responses->first(fn ($quote) => in_array($quote->status, ['approved','converted'], true));
        $proposalUrl = fn ($quote) => route('quotes.show', [$case, 'proposal'=>$quote->id]).'#proposal-'.$quote->id;
    @endphp
    <x-page-header>
        <x-slot:title>{{ $case->subject }}</x-slot:title>
        <x-slot:meta>{{ $case->client->name }} · {{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}@if($case->vehicle_snapshot['plate'] ?? null) · {{ $case->vehicle_snapshot['plate'] }}@endif</x-slot:meta>
        <x-slot:actions>
            <a class="btn btn-g" href="{{ route('quotes.offers',['case'=>$case->id]) }}">Riepilogo offerte</a>
            @php($comparisonIds=$case->quotes->filter(fn($quote)=>$quote->documents->contains('document_category','quote'))->take(6)->modelKeys())
            @if($comparisonIds)<a class="btn btn-g" href="{{ route('quotes.compare',['quotes'=>$comparisonIds]) }}">Confronta garanzie</a>@endif
            @if($mayWork)<a @class(['btn', 'btn-p'=>$case->quotes->isEmpty(), 'btn-g'=>$case->quotes->isNotEmpty()]) href="{{ route('quotes.emails.create',$case) }}">Prepara email</a>@endif
            <a class="btn btn-g" href="{{ route('clients.show',$case->client_id) }}">Cliente</a>
            <a class="btn btn-g" href="{{ route('quotes.index',['client'=>$case->client_id]) }}">Preventivi</a>
        </x-slot:actions>
    </x-page-header>
    @include('quotes.proposals')
    <x-inbound-emails :client="$case->client" :quote-case="$case" />
@can('updateRegistry',$case->client)@if($case->status==='open')
<x-panel padded>
<details class="client-detail-section" data-manual-quote-request @if($errors->hasAny(['recipient_key','requested_on','confirmed'])) open @endif><summary>Registra un contatto già effettuato fuori dal gestionale</summary><p class="u-text-meta">Qui puoi annotare una richiesta già fatta per telefono o via email.</p><form method="POST" action="{{ route('quotes.request',$case) }}">@csrf<div class="form-row">
<x-form-group label="Compagnia o agenzia contattata" name="recipient_key" required><select id="field-recipient-key" class="form-sel" name="recipient_key" required><option value="">Seleziona il destinatario</option>@foreach($companies as $company)<optgroup label="{{ $company->name }}">@unless($case->quotes->contains(fn($quote)=>$quote->supplier_company_id===$company->id && $quote->recipient_key==='direct'))<option value="{{ \App\Services\QuoteRecipients::key($company->id) }}">Direttamente a {{ $company->name }}</option>@endunless @foreach($company->agencies->where('status','active') as $agency)@unless($case->quotes->contains(fn($quote)=>$quote->supplier_company_id===$company->id && $quote->recipient_key==='agency:'.$agency->id))<option value="{{ \App\Services\QuoteRecipients::key($company->id,$agency->id) }}">{{ $company->name }} tramite {{ $agency->name }}</option>@endunless @endforeach</optgroup>@endforeach</select></x-form-group>
<x-form-group label="Data della richiesta" name="requested_on" required><input class="form-in" type="date" name="requested_on" max="{{ today()->toDateString() }}" value="{{ old('requested_on',today()->toDateString()) }}" required></x-form-group></div>
<label><input type="checkbox" name="confirmed" value="1" required> Confermo di aver richiesto il preventivo al destinatario indicato.</label><div class="insurance-form-actions"><button class="btn btn-p">Registra richiesta</button></div></form></details></x-panel>
@endif@endcan
    <details class="quote-case-extra">
        <summary>Dati della richiesta</summary>
<x-panel title="Richiesta del cliente" padded><p>{{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}</p>@if($case->notes)<p class="u-whitespace-pre-wrap">{{ $case->notes }}</p>@endif
<p>{{ \App\Models\InsuranceQuoteCase::PURPOSES[$case->purpose] }}.</p>
@if($case->purpose==='addition')<p>La richiesta riguarda la compagnia della polizza attuale e le sue agenzie. Il premio è il solo importo aggiuntivo per il periodo proposto.</p>@endif
@if(($case->recipient_group_snapshot['source'] ?? null)==='manual')
    <p>Destinatari scelti: {{ collect($case->recipient_group_snapshot['recipients'] ?? [])->map(fn($recipient) => $recipient['agency_name'] ? $recipient['company_name'].' tramite '.$recipient['agency_name'] : $recipient['company_name'])->join('; ') ?: 'da scegliere in «Prepara email»' }}. Puoi modificarli prima dell’invio.</p>
@elseif($case->recipient_group_snapshot)<p>Gruppo iniziale: <strong>{{ $case->recipient_group_snapshot['name'] }}</strong>@if($case->merit_class_cu) — Classe CU {{ $case->merit_class_cu }}@endif. I destinatari possono essere modificati prima dell’invio.</p>@endif</x-panel>
@if($case->requestContext())
<x-panel title="Situazione attuale e coperture richieste" padded><div class="email-message-body">{{ $case->requestContext() }}</div>
@if($case->currentPolicy)<p class="u-mt-md"><a href="{{ route('clients.policies.show',[$case->client_id,$case->currentPolicy]) }}">Apri la polizza attuale</a></p>@endif
@if($case->additional_coverages)<p class="u-text-meta u-mt-md">Le coperture aggiuntive sono una richiesta da valutare con la compagnia e approvare con il cliente.</p>@endif
</x-panel>
@endif
    </details>
    <details class="quote-case-extra" @if($errors->any() && !$activeQuote) open @endif>
        <summary>Documenti e moduli</summary>
<x-panel title="Documenti della richiesta">
@include('clients.documents.table',['client'=>$case->client,'documents'=>$case->documents])
@can('updateRegistry',$case->client)@if($case->status==='open')<div class="panel-footer-link"><details><summary class="btn btn-g">Aggiungi documenti</summary><form method="POST" enctype="multipart/form-data" action="{{ route('quotes.documents.store',$case) }}" class="u-mt-md">@csrf @include('quotes.document-fields')<div class="insurance-form-actions"><button class="btn btn-p">Aggiungi alla pratica</button></div></form></details></div>@endif @endcan
</x-panel>
@php($quoteChecklist = app(\App\Services\InsuranceDocumentation::class)->categories($case->client, $case->branch, $case->subbranch))
<x-panel title="Documentazione per il contratto" padded>
    @if($quoteChecklist)<p>{{ $case->client->client_type === 'company' ? 'Azienda' : 'Privato' }} · checklist {{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}:</p>
        <ul class="document-checklist-preview">@foreach($quoteChecklist as $documentCategory)<li>{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$documentCategory] }}</li>@endforeach</ul>
        <p class="u-text-meta">La checklist si applica alla polizza dopo l’approvazione. In questa richiesta gli allegati restano facoltativi.</p>
    @else<p class="u-text-meta">La checklist di questo ramo per {{ $case->client->client_type === 'company' ? 'le aziende' : 'i privati' }} non è ancora configurata.</p>@endif
    <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.forms.index', [$case->client, 'reason'=>'client']) }}">Moduli e firme del cliente</a><a class="btn btn-g" href="{{ route('clients.show', [$case->client, 'tab'=>'documents']) }}">Documenti del cliente</a></div>
</x-panel>
    </details>
    <details class="quote-case-extra" @if(request()->hasAny(['email_company','email_state','email_month','requests_page'])) open @endif>
        <summary>Storico richieste email</summary>
        @include('quotes.email-history')
    </details>
</x-app-layout>
