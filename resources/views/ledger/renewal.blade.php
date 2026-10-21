<x-app-layout title="Rinnova polizza"><x-page-header><x-slot:title>Rinnova {{ $policy->number }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">Polizza</a></x-slot:actions></x-page-header>
<x-panel padded><p>Registra il rinnovo confermato dalla compagnia. Controlla il premio del nuovo periodo: il tacito rinnovo non costituisce una registrazione di incasso.</p>
<p class="u-text-meta u-mt-sm">I titoli precedenti e i documenti firmati restano nello storico. Dopo il rinnovo controlla i moduli del nuovo periodo nella scheda polizza.</p>
@if($policy->work_closed_at)<p class="u-text-meta u-mt-sm">La lavorazione precedente resta chiusa; il rinnovo non riabilita le modifiche dell’operatore.</p>@endif
<form method="POST" action="{{ route('titles.renewal-preview',$policy) }}">@csrf<x-form-section title="Nuovo periodo"><div class="form-row">
<x-form-group label="Decorrenza rinnovo" name="starts_on" required><input class="form-in" type="date" name="starts_on" value="{{ old('starts_on',$policy->expires_on->toDateString()) }}" readonly required></x-form-group>
<x-form-group label="Scadenza rinnovo" name="expires_on" required><input class="form-in" type="date" name="expires_on" value="{{ old('expires_on') }}" required></x-form-group>
<x-form-group label="Premio totale del nuovo periodo (€)" name="premium" required><input class="form-in" type="number" step="0.01" min="0.01" max="9999999999.99" name="premium" value="{{ old('premium') }}" required></x-form-group>
@include('ledger.payment-fields',['model'=>$policy])</div></x-form-section><div class="insurance-form-actions"><button class="btn btn-p">Controlla anteprima titoli</button></div></form></x-panel></x-app-layout>
