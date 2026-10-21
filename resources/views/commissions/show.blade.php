<x-app-layout title="Provvigione della rata">
    <x-page-header><x-slot:title>Provvigione · {{ $title->policy->number }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('commissions.index', ['month'=>$title->coverage_from->format('Y-m')]) }}">Report provvigioni</a><a class="btn btn-g" href="{{ route('titles.show',$title) }}">Rata e incassi</a></x-slot:actions></x-page-header>
    <x-panel title="Rata e mandato" padded>
        <dl class="client-registry-details">
            <div><dt>Contraente</dt><dd>{{ $title->policy->client->name }}</dd></div><div><dt>Agenzia</dt><dd>{{ $title->policy->supplierAgency?->name ?: 'Non assegnata' }}</dd></div><div><dt>Compagnia</dt><dd>{{ $title->policy->company }}</dd></div>
            <div><dt>Ramo / sottoramo</dt><dd>{{ $title->policy->category }}{{ $title->policy->subcategory ? ' / '.$title->policy->subcategory : '' }}</dd></div><div><dt>Titolo</dt><dd>{{ \App\Models\InsuranceTitle::KINDS[$title->kind] }} #{{ $title->id }}</dd></div><div><dt>Copertura</dt><dd>{{ $title->coverage_from->format('d/m/Y') }} – {{ $title->coverage_to->format('d/m/Y') }}</dd></div>
            <div><dt>Premio della rata</dt><dd>{{ \App\Support\Money::format($title->amount_cents) }}</dd></div><div><dt>Premio incassato</dt><dd>{{ \App\Support\Money::format($title->collected_cents) }}</dd></div><div><dt>Stato provvigione</dt><dd>{{ $commission ? \App\Models\InsuranceCommission::STATES[$commission->state] : 'Da completare' }}</dd></div>
        </dl>
        @if(in_array($title->policy->status, \App\Models\InsurancePolicy::CLOSED_STATUSES,true) && !$title->cancelled_at)<p class="u-mt-md"><x-status-text alert>La polizza è {{ mb_strtolower($title->policy->status_label) }}. Verifica con il mandante se occorre stornare la rata o la provvigione: la chiusura della polizza non cancella i movimenti registrati.</x-status-text></p>@endif
    </x-panel>
    <x-panel class="collection-summary" aria-label="Importi della provvigione">
        <dl class="collection-summary-values"><div><dt>Prevista</dt><dd>{{ $commission?->expected_cents === null ? 'Da completare' : \App\Support\Money::format($commission->expected_cents) }}</dd></div><div><dt>Maturata</dt><dd>{{ \App\Support\Money::format($commission?->accrued_cents ?? 0) }}</dd></div><div><dt>Liquidata</dt><dd>{{ \App\Support\Money::format($commission?->settled_cents ?? 0) }}</dd></div></dl>
        <p class="collection-summary-note">Da liquidare: <x-status-text :alert="($commission?->accrued_cents ?? 0) > ($commission?->settled_cents ?? 0)">{{ \App\Support\Money::format(max(0,($commission?->accrued_cents ?? 0)-($commission?->settled_cents ?? 0))) }}</x-status-text>. Gli importi rappresentano fasi della stessa provvigione e non si sommano.</p>
        @if($commission && $commission->settled_cents > $commission->accrued_cents)<p class="u-mt-md"><x-status-text alert>Da riconciliare: {{ \App\Support\Money::format($commission->settled_cents-$commission->accrued_cents) }} già liquidati oltre il maturato. Nessun rimborso viene registrato automaticamente.</x-status-text></p>@endif
    </x-panel>
    @if(!$commission?->locked_at && !$title->cancelled_at)
        <x-panel title="Completa il calcolo" padded>
            <p class="u-mb-md">@if($commission?->incomplete_reason)Ultimo tentativo di calcolo: {{ $commission->incomplete_reason }} Se hai aggiornato le condizioni, ripeti il calcolo.@else Calcola la provvigione usando le condizioni del mandato valide all’inizio della copertura di questa rata.@endif</p>
            @if($title->policy->supplier_agency_id)<p class="u-mb-md"><a class="btn btn-g" href="{{ route('suppliers.show',[$title->policy->supplier_agency_id,'tab'=>'commissions']) }}">Configura condizioni del mandato</a></p>@endif
            <form method="POST" action="{{ route('commissions.prepare',$title) }}">
                @csrf<input type="hidden" name="version" value="{{ $commission?->version ?? -1 }}">
                <x-form-section title="Base provvigionale della singola rata">
                    <p class="u-text-meta u-mb-md">Per “Premio totale” viene usato automaticamente l’importo della rata. Per imponibile o altra base inserisci l’importo indicato dal mandante; imposte e componenti escluse non vengono dedotte automaticamente.</p>
                    <div class="form-row"><x-form-group label="Base della rata (€)" name="base_amount"><input class="form-in" type="number" name="base_amount" min="0" max="9999999999.99" step="0.01" value="{{ old('base_amount', $commission?->base_cents !== null ? \App\Support\Money::decimal($commission->base_cents) : '') }}"></x-form-group><x-form-group label="Riferimento del documento o rendiconto" name="base_reference"><input class="form-in" name="base_reference" maxlength="500" value="{{ old('base_reference',$commission?->base_reference) }}"></x-form-group></div>
                </x-form-section>
                <div class="insurance-form-actions"><button class="btn btn-p">Calcola provvigione</button></div>
            </form>
        </x-panel>
    @elseif($commission?->locked_at)
        @php
            $rule = $commission->rule_snapshot;
        @endphp
        <x-panel title="Condizioni applicate" padded>
            <dl class="client-registry-details"><div><dt>Mandato</dt><dd>{{ $rule['mandate_reference'] ?: 'Riferimento non indicato' }}</dd></div><div><dt>Base</dt><dd>{{ \App\Models\AgencyCommissionRate::BASES[$rule['basis']] }} · {{ \App\Support\Money::format($commission->base_cents) }}</dd></div><div><dt>Aliquota</dt><dd>{{ \App\Models\AgencyCommissionRate::percent($rule['rate_units'],',') }} %</dd></div><div><dt>Maturazione</dt><dd>{{ \App\Models\AgencyCommissionSchedule::ACCRUAL_EVENTS[$rule['accrual_event']] }}</dd></div>@if($rule['accrual_event']==='collection')<div><dt>Pagamenti parziali</dt><dd>{{ \App\Models\AgencyCommissionSchedule::COLLECTION_MODES[$rule['collection_mode']] }}</dd></div>@endif<div><dt>Base documentata da</dt><dd>{{ $commission->base_reference ?: 'Premio totale della rata' }}</dd></div></dl>
            <p class="u-text-meta u-mt-md">Le condizioni sono conservate con la rata: modificare le aliquote del mandato non riscrive questi movimenti. Gli storni restano nello storico.</p>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('suppliers.commissions.show',[$title->policy->supplier_agency_id,$rule['schedule_id']]) }}">Apri versione applicata</a></div>
        </x-panel>
        @if(!$title->cancelled_at)
            @if($rule['accrual_event']==='confirmation' && $commission->accrued_cents < $commission->expected_cents)
                @include('commissions.movement-form',['kind'=>'accrual','formTitle'=>'Registra conferma del mandante','limit'=>$commission->expected_cents-$commission->accrued_cents])
            @endif
            @if($commission->accrued_cents > $commission->settled_cents)
                @include('commissions.movement-form',['kind'=>'settlement','formTitle'=>'Registra liquidazione','limit'=>$commission->accrued_cents-$commission->settled_cents])
            @endif
        @endif
    @endif
    <x-panel title="Storico provvigione">
        <div class="client-table-scroll" role="region" aria-label="Storico della provvigione" tabindex="0"><table class="t-table"><thead><tr><th data-export-type="date">Data movimento</th><th>Tipo</th><th data-export-type="currency">Importo</th><th>Riferimento</th><th>Causale</th><th>Registrato da</th><th>Azioni</th></tr></thead><tbody>
            @forelse($movements as $movement)
                <tr><td>{{ $movement->effective_on->format('d/m/Y') }}</td><td>{{ \App\Models\InsuranceCommissionMovement::KINDS[$movement->kind] }}{{ $movement->amount_cents < 0 ? ' · Storno' : '' }}</td><td><x-status-text :alert="$movement->amount_cents < 0">{{ \App\Support\Money::format($movement->amount_cents) }}</x-status-text></td><td>{{ $movement->reference ?: 'Automatico' }}</td><td>{{ $movement->reason }}</td><td>{{ $movement->author?->name ?: 'Sistema' }}</td><td>
                    @if($movement->idempotency_key && $movement->amount_cents > 0 && !$movement->reversal)
                        <details><summary>Storna movimento</summary><form class="commission-reversal" method="POST" action="{{ route('commissions.reverse',[$title,$movement]) }}">@csrf<input type="hidden" name="version" value="{{ $commission->version }}"><x-form-group label="Data storno" name="effective_on" :for="'reverse-date-'.$movement->id" required><input class="form-in" name="effective_on" type="date" min="{{ $movement->effective_on->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></x-form-group><x-form-group label="Motivo dello storno" name="reason" :for="'reverse-reason-'.$movement->id" required><textarea class="form-ta" name="reason" rows="2" minlength="10" maxlength="1000" required></textarea></x-form-group><label class="email-review-confirm"><input type="checkbox" name="confirmed" value="1" required> Confermo lo storno.</label><button class="btn btn-g btn-danger-outline">Registra storno</button></form></details>
                    @elseif($movement->reversal) Stornato con movimento #{{ $movement->reversal->id }}
                    @elseif($movement->reversal_of_id) Storno del movimento #{{ $movement->reversal_of_id }}
                    @endif
                </td></tr>
            @empty<tr><td colspan="7">Nessun movimento provvigionale registrato.</td></tr>@endforelse
        </tbody></table></div>{{ $movements->links() }}
    </x-panel>
</x-app-layout>
