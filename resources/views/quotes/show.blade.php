<x-app-layout :title="$case->subject"><x-page-header><x-slot:title>{{ $case->subject }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show',$case->client_id) }}">{{ $case->client->name }}</a><a class="btn btn-g" href="{{ route('quotes.index',['client'=>$case->client_id]) }}">Preventivi</a></x-slot:actions></x-page-header>
<x-panel title="Richiesta del cliente" padded><p>{{ $case->branch->name }}{{ $case->subbranch ? ' / '.$case->subbranch->name : '' }}</p>@if($case->notes)<p class="u-whitespace-pre-wrap">{{ $case->notes }}</p>@endif
<p>{{ \App\Models\InsuranceQuoteCase::PURPOSES[$case->purpose] }}.</p>
@if($case->purpose==='addition')<p>La richiesta riguarda la compagnia della polizza attuale. Il premio è il solo importo aggiuntivo per il periodo proposto.</p>@else<p>Compagnie prioritarie: {{ $companies->where('quote_priority',true)->pluck('name')->join(', ') ?: 'Da impostare nelle compagnie' }}.</p>@endif</x-panel>
@if($case->requestContext())
<x-panel title="Situazione attuale e coperture richieste" padded><div class="email-message-body">{{ $case->requestContext() }}</div>
@if($case->currentPolicy)<p class="u-mt-md"><a href="{{ route('clients.policies.show',[$case->client_id,$case->currentPolicy]) }}">Apri la polizza attuale</a></p>@endif
@if($case->additional_coverages)<p class="u-text-meta u-mt-md">Le coperture aggiuntive sono una richiesta da valutare con la compagnia e approvare con il cliente.</p>@endif
</x-panel>
@endif
@can('updateRegistry',$case->client)@if($case->status==='open')
<x-panel title="Richieste alle compagnie" padded><p class="u-mb-md">{{ $case->purpose==='addition' ? 'Invia la richiesta alla compagnia della polizza attuale.' : 'Invia prima alle compagnie prioritarie, poi alle altre.' }} Potrai controllare destinatario, testo e allegati prima di confermare ogni invio.</p><a class="btn btn-p" href="{{ route('quotes.emails.create',$case) }}">Invia richiesta di preventivo</a>
<details class="client-detail-section" data-manual-quote-request><summary>Registra un contatto già effettuato fuori dal gestionale</summary><p class="u-text-meta">Questa registrazione conserva il contatto manuale e non invia un’email. Per rendere disponibili le altre compagnie, completa gli invii prioritari oppure registra le proposte ricevute con i relativi PDF.</p><form method="POST" action="{{ route('quotes.request',$case) }}">@csrf<div class="form-row">
<x-form-group label="Compagnia" name="supplier_company_id" required><select class="form-sel" name="supplier_company_id" required><option value="">Seleziona</option>@foreach($companies->whereNotIn('id',$case->quotes->pluck('supplier_company_id')) as $company)<option value="{{ $company->id }}">{{ $company->name }}{{ $company->quote_priority ? ' — prioritaria' : '' }}</option>@endforeach</select></x-form-group>
<x-form-group label="Data della richiesta" name="requested_on" required><input class="form-in" type="date" name="requested_on" max="{{ today()->toDateString() }}" value="{{ old('requested_on',today()->toDateString()) }}" required></x-form-group></div>
<label><input type="checkbox" name="confirmed" value="1" required> Confermo di aver richiesto il preventivo alla compagnia indicata.</label><div class="insurance-form-actions"><button class="btn btn-p">Registra richiesta</button></div></form></details></x-panel>
@endif@endcan
<x-panel title="Proposte"><div class="client-table-scroll" role="region" aria-label="Proposte delle compagnie" tabindex="0"><table class="t-table"><thead><tr><th>Compagnia</th><th>Agenzia</th><th>Prodotto</th><th>{{ $case->purpose==='addition' ? 'Premio aggiuntivo' : 'Premio totale' }}</th><th>Frazionamento</th><th>Validità preventivo</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>@forelse($case->quotes as $quote)<tr><td>{{ $quote->company->name }}</td><td>{{ $quote->agency?->name ?: 'Rapporto diretto' }}</td><td>{{ $quote->product ?: 'In attesa' }}</td><td>{{ $quote->premium_cents !== null ? \App\Support\Money::format($quote->premium_cents) : 'In attesa' }}</td><td>{{ $quote->payment_frequency ?: 'In attesa' }}</td><td><x-status-text :alert="$quote->valid_until?->lt(today())">{{ $quote->valid_until?->format('d/m/Y') ?: 'In attesa' }}</x-status-text></td><td>{{ $case->purpose==='addition' && $quote->status==='converted' ? 'Aggiunta registrata' : \App\Models\InsuranceQuote::STATUSES[$quote->status] }}</td><td><a href="#proposal-{{ $quote->id }}">{{ match($quote->status) { 'requested'=>'Registra risposta', 'received'=>'Presenta al cliente', 'pending'=>'Registra esito cliente', 'approved'=>'Procedi con il contratto', default=>'Apri proposta' } }}</a></td></tr>@empty<tr><td colspan="8">Registra la prima richiesta.</td></tr>@endforelse</tbody></table></div></x-panel>
@foreach($case->quotes as $quote)
<x-panel :title="$quote->company->name" id="proposal-{{ $quote->id }}" padded><div><p>{{ $case->purpose==='addition' && $quote->status==='converted' ? 'Aggiunta registrata' : \App\Models\InsuranceQuote::STATUSES[$quote->status] }} · Richiesta del {{ $quote->requested_at->format('d/m/Y') }}@if($quote->reference) · Rif. {{ $quote->reference }}@endif</p>
@if($quote->starts_on)<p>Periodo proposto: {{ $quote->starts_on->format('d/m/Y') }} – {{ $quote->expires_on->format('d/m/Y') }}. @if($quote->approved_on)Approvazione del {{ $quote->approved_on->format('d/m/Y') }}.@endif</p>@endif
@foreach($quote->documents as $document)<p><a href="{{ route('attachments.download',$document) }}">{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$document->document_category] }}: {{ $document->original_name }}</a></p>@endforeach
@if($quote->approval_note)<p>{{ $quote->approval_note }}</p>@endif
@can('updateRegistry',$case->client)
@if($case->status==='open')
@if($quote->status==='requested') @include('quotes.receive') @endif
@if($quote->status==='received')<form method="POST" action="{{ route('quotes.change',[$quote,'submit']) }}">@csrf<label><input type="checkbox" name="confirmed" value="1" required> Confermo di aver presentato questa proposta al cliente.</label><div class="insurance-form-actions"><button class="btn btn-p">Metti in approvazione cliente</button></div></form>@endif
@if($quote->status==='pending')
<form method="POST" enctype="multipart/form-data" action="{{ route('quotes.change',[$quote,'approve']) }}">@csrf<x-form-section title="Approvazione ricevuta dal cliente"><div class="form-row">
<x-form-group label="Data approvazione" name="approved_on" :for="'approved-on-'.$quote->id" required><input class="form-in" type="date" name="approved_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></x-form-group>
<x-form-group label="Prova di approvazione (PDF)" name="files" :for="'approval-files-'.$quote->id" required><input class="form-in" type="file" name="files[]" accept="application/pdf,.pdf" multiple required></x-form-group>
<x-form-group label="Riferimenti dell’approvazione" name="approval_note" :for="'approval-note-'.$quote->id"><textarea class="form-ta" name="approval_note" rows="2" maxlength="5000"></textarea></x-form-group></div>
<label><input type="checkbox" name="confirmed" value="1" required> L’allegato documenta l’approvazione del cliente per questa proposta.</label><div class="insurance-form-actions"><button class="btn btn-p">Registra approvazione</button></div></x-form-section></form>
@endif
@if(in_array($quote->status,['received','pending']))<details class="form-section"><summary>Il cliente ha rifiutato la proposta</summary><form method="POST" action="{{ route('quotes.change',[$quote,'reject']) }}">@csrf<x-form-group label="Motivo o riferimento del rifiuto" name="approval_note" :for="'reject-note-'.$quote->id" required><input class="form-in" name="approval_note" minlength="5" maxlength="5000" required></x-form-group><button class="btn btn-g">Registra rifiuto</button></form></details>@endif
@endif
@if($quote->status==='approved')
@if($case->purpose==='new')<a class="btn btn-p" href="{{ route('policies.create',['quote_id'=>$quote->id]) }}">Crea polizza da questo preventivo</a>
@else<a class="btn btn-p" href="{{ route('clients.policies.changes.create',[$case->client_id,$case->current_policy_id,'kind'=>$case->purpose,'quote_id'=>$quote->id]) }}">{{ $case->purpose==='replacement' ? 'Prepara sostitutiva' : 'Registra coperture aggiuntive' }}</a>@endif
@endif
@endcan
@if($quote->policy)<a class="btn btn-g" href="{{ route('clients.policies.show',[$case->client_id,$quote->policy]) }}">Polizza {{ $quote->policy->number }}</a>@endif
@if($quote->policyChange)<a class="btn btn-g" href="{{ route('clients.policy-changes.show',[$case->client_id,$quote->policyChange]) }}">Variazione registrata</a>@endif
</div></x-panel>@endforeach
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
@include('quotes.email-history')
</x-app-layout>
