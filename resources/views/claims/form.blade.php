<x-app-layout :title="$claim?'Modifica sinistro':'Nuovo sinistro'">
<x-page-header><x-slot:title>{{ $claim?'Modifica sinistro · '.$claim->reference:'Nuovo sinistro' }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ $claim?route('claims.show',$claim):route('claims.index') }}">{{ $claim?'Scheda sinistro':'Sinistri' }}</a></x-slot:actions></x-page-header>
@if(!$policy)
<p class="claim-intro">Cerca il cliente o la polizza, poi seleziona il contratto a cui collegare il sinistro.</p>
@include('claims.filters',['selecting'=>true])
<x-panel><div class="client-table-scroll" role="region" aria-label="Seleziona polizza" tabindex="0"><table class="t-table"><thead><tr><th>Polizza</th><th>Cliente</th><th>Agenzia</th><th>Compagnia</th><th>Ramo</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>@forelse($policies as $item)<tr><td>{{ $item->number }}</td><td>{{ $item->client->name }}</td><td>{{ $item->supplierAgency?->name?:'Rapporto diretto' }}</td><td>{{ $item->company }}</td><td>{{ $item->category }}</td><td><x-policy-status :policy="$item" /></td><td><a class="btn btn-g btn-sm" href="{{ route('claims.create',['policy'=>$item->id]) }}">Seleziona polizza</a></td></tr>@empty<tr><td colspan="7">Nessuna polizza trovata.</td></tr>@endforelse</tbody></table></div>{{ $policies->links() }}</x-panel>
@else
@php($safeOld=fn($key,$default='')=>is_scalar(old($key,$default))?old($key,$default):'')
<x-panel padded><form method="POST" enctype="multipart/form-data" action="{{ $claim?route('claims.change',[$claim,'updated']):route('claims.store') }}">
@csrf<input type="hidden" name="request_key" value="{{ Str::uuid() }}">
@if($claim)<input type="hidden" name="version" value="{{ $claim->version }}">@else<input type="hidden" name="policy_id" value="{{ $policy->id }}"><input type="hidden" name="context_hash" value="{{ $contextHash }}">@endif
<x-form-section title="Cliente e polizza">@include('claims.policy')@unless($claim)<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('claims.create',['client'=>$policy->client_id]) }}">Cambia polizza</a></div>@endunless</x-form-section>
<x-form-section title="Dati del sinistro"><div class="form-row">
<x-form-group label="Numero sinistro della compagnia" name="number"><input class="form-in" name="number" maxlength="100" value="{{ $safeOld('number',$claim?->number) }}"><p class="u-text-meta">Puoi aggiungerlo quando viene comunicato dalla compagnia.</p></x-form-group>
<x-form-group label="Operatore assegnato" name="assigned_to" required><select class="form-sel" name="assigned_to" required><option value="">Seleziona operatore</option>@foreach($operators as $operator)<option value="{{ $operator->id }}" @selected((string)$safeOld('assigned_to',$claim?->assigned_to??auth()->id())===(string)$operator->id)>{{ $operator->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Data dell’evento" name="event_on" required><input class="form-in" type="date" name="event_on" max="{{ today()->toDateString() }}" required value="{{ $safeOld('event_on',$claim?->event_on->toDateString()) }}"></x-form-group>
<x-form-group label="Data denuncia alla compagnia / agenzia" name="reported_on"><input class="form-in" type="date" name="reported_on" max="{{ today()->toDateString() }}" value="{{ $safeOld('reported_on',$claim?->reported_on?->toDateString()) }}"></x-form-group>
</div><x-form-group label="Descrizione dell’evento" name="description" required><textarea class="form-ta" name="description" rows="3" maxlength="10000" required>{{ $safeOld('description',$claim?->description) }}</textarea></x-form-group></x-form-section>
@if($claim)
<x-form-section title="Motivo della modifica"><x-form-group label="Motivazione" name="reason" required><textarea class="form-ta" name="reason" rows="2" maxlength="2000" required>{{ $safeOld('reason') }}</textarea></x-form-group></x-form-section>
@else
<x-form-section title="Prossima attività"><div class="form-row"><x-form-group label="Attività da svolgere" name="activity_label"><input class="form-in" name="activity_label" maxlength="200" value="{{ $safeOld('activity_label') }}" placeholder="Per esempio: richiedere i documenti mancanti"></x-form-group><x-form-group label="Da svolgere entro" name="activity_due_on"><input class="form-in" type="date" name="activity_due_on" value="{{ $safeOld('activity_due_on') }}"></x-form-group></div><p class="u-text-meta">Avviso interno all’operatore assegnato e agli admin 3 giorni prima e il giorno previsto. Le attività non completate restano tra gli arretrati.</p></x-form-section>
<x-form-section title="Documenti iniziali"><x-form-group label="Allegati del sinistro" name="files"><input class="form-in" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png"><p class="u-text-meta">Puoi selezionare più file: PDF, JPG o PNG. Fino a 10 file, 10 MB ciascuno e 50 MB totali.</p></x-form-group></x-form-section>
@endif
<div class="insurance-form-actions"><a class="btn btn-g" href="{{ $claim?route('claims.show',$claim):route('claims.index') }}">Annulla</a><button class="btn btn-p">{{ $claim?'Salva modifiche':'Apri sinistro' }}</button></div>
</form></x-panel>
@endif
</x-app-layout>
