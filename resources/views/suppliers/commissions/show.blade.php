<x-app-layout title="Condizioni provvigionali">
<x-page-header><x-slot:title>Condizioni provvigionali · {{ $supplier->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('suppliers.commissions.index',$supplier) }}">Tutte le aliquote</a>
@if($schedule->editable())
@can('system.admin')<a class="btn btn-p" href="{{ route('suppliers.commissions.edit',[$supplier,$schedule]) }}">Modifica condizioni</a>@endcan
@endif
</x-slot:actions></x-page-header>
<x-panel title="Mandato e validità" padded><dl class="client-registry-details"><div><dt>Compagnia</dt><dd>{{ $schedule->company->name }}</dd></div><div><dt>Stato</dt><dd>{{ $schedule->state_label }}</dd></div><div><dt>Dal</dt><dd>{{ $schedule->valid_from->format('d/m/Y') }}</dd></div><div><dt>Al</dt><dd>{{ $schedule->valid_to?->format('d/m/Y') ?: 'Senza termine' }}</dd></div><div><dt>Maturazione</dt><dd>{{ \App\Models\AgencyCommissionSchedule::ACCRUAL_EVENTS[$schedule->accrual_event]??'Da definire con il mandante' }}</dd></div><div><dt>Registrata da</dt><dd>{{ $schedule->author?->name ?: 'Utente non disponibile' }} · {{ $schedule->created_at->format('d/m/Y H:i') }}</dd></div></dl>
@if($schedule->notes)<p class="u-mt-md u-whitespace-pre-wrap">{{ $schedule->notes }}</p>@endif
@if($schedule->revision_reason)<p class="u-mt-md">Motivo della revisione: {{ $schedule->revision_reason }}</p>@endif
@if($schedule->void_reason)<p class="u-mt-md">Motivo dell’annullamento: {{ $schedule->void_reason }}</p>@endif
<div class="insurance-form-actions">@if($schedule->previous)<a class="btn btn-g" href="{{ route('suppliers.commissions.show',[$supplier,$schedule->previous]) }}">Versione precedente</a>@endif @if($schedule->replacement)<a class="btn btn-g" href="{{ route('suppliers.commissions.show',[$supplier,$schedule->replacement]) }}">Versione successiva</a>@endif</div>
</x-panel>
<x-panel title="Rami, sottorami e percentuali"><div class="client-table-scroll" role="region" aria-label="Condizioni del mandato" tabindex="0"><table class="t-table"><thead><tr><th>Ramo</th><th>Sottoramo</th><th>Base</th><th>Precisazioni</th><th>Nuova emissione</th><th>Rinnovo</th></tr></thead><tbody>
@foreach($schedule->rates as $rate)<tr><td>{{ $rate->branch->name }}</td><td>{{ $rate->subbranch?->name ?: 'Non specificato' }}</td><td>{{ \App\Models\AgencyCommissionRate::BASES[$rate->basis]??'Da definire' }}</td><td>{{ $rate->basis_note ?: 'Non indicate' }}</td><td>{{ $rate->issue_rate_units===null ? 'Da definire' : \App\Models\AgencyCommissionRate::percent($rate->issue_rate_units,',').' %' }}</td><td>{{ $rate->renewal_rate_units===null ? 'Da definire' : \App\Models\AgencyCommissionRate::percent($rate->renewal_rate_units,',').' %' }}</td></tr>@endforeach
</tbody></table></div></x-panel>
<p class="u-mb-md">Le aliquote di emissione e rinnovo sono distinte; le voci senza percentuale restano da definire. Questa configurazione non genera ancora importi nei report.</p>
@if($schedule->editable())
@can('system.admin')<x-panel padded><details><summary>Annulla questa configurazione</summary><form method="POST" action="{{ route('suppliers.commissions.void',[$supplier,$schedule]) }}" class="u-mt-md">@csrf<x-form-group label="Motivo dell’annullamento" name="reason" required><textarea class="form-ta" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></x-form-group><label class="email-review-confirm u-mt-md"><input type="checkbox" name="confirmed" value="1" required> Confermo l’annullamento; la configurazione resta nello storico.</label><div class="insurance-form-actions"><button class="btn btn-g btn-danger-outline">Annulla configurazione</button></div></form></details></x-panel>@endcan
@endif
</x-app-layout>
