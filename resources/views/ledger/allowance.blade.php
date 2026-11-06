@php
    $title = $allowance->title;
    $policy = $title->policy;
    $client = $policy->client;
    $recoveryOld = fn($key, $default='') => is_scalar(old($key, $default)) ? (string)old($key, $default) : (string)$default;
    $recoveryKey = $recoveryOld('recovery_key');
    if (!\Illuminate\Support\Str::isUuid($recoveryKey)) $recoveryKey = (string)\Illuminate\Support\Str::uuid();
@endphp
<x-app-layout :title="'Abbuono #'.$allowance->id">
    <x-page-header>
        <x-slot:title>Abbuono #{{ $allowance->id }}</x-slot:title>
        <x-slot:meta>{{ $client->name }} / Polizza {{ $title->contract->number }}</x-slot:meta>
        <x-slot:actions>
            <a class="btn btn-g" href="{{ route('allowances.index',['policy'=>$policy->id,'month'=>'']) }}">Abbuoni della polizza</a>
            <a class="btn btn-g" href="{{ route('titles.show',$title) }}#abbuoni-del-titolo">Titolo #{{ $title->id }}</a>
        </x-slot:actions>
    </x-page-header>
    <x-panel :title="$allowance->borne_by==='agency' ? 'Recupero dall’agenzia' : 'Dati dell’abbuono'" padded>
        <dl class="client-registry-details">
            <div><dt>Chi sostiene l’abbuono</dt><dd>{{ $allowance->bearer_label }}</dd></div>
            <div><dt>Data dell’abbuono</dt><dd>{{ $allowance->effective_on->format('d/m/Y') }}</dd></div>
            <div><dt>Importo dell’abbuono</dt><dd>{{ \App\Support\Money::format($allowance->amount_cents) }}</dd></div>
            <div><dt>Stato abbuono</dt><dd>{{ $allowance->voided_at ? 'Stornato' : 'Registrato' }}</dd></div>
            @if($allowance->borne_by==='agency')
                <div><dt>Recuperato dall’agenzia</dt><dd>{{ \App\Support\Money::format($allowance->recovered_cents) }}</dd></div>
                <div><dt>Da recuperare dall’agenzia</dt><dd><strong>{{ \App\Support\Money::format($allowance->recoverable_cents) }}</strong></dd></div>
                <div><dt>Stato recupero</dt><dd>{{ $allowance->recovery_state_label }}</dd></div>
            @endif
        </dl>
        <p class="u-whitespace-pre-wrap">{{ $allowance->reason }}</p>
        @if($allowance->reference)<p>Riferimento {{ $allowance->reference }}</p>@endif
        <p class="u-text-meta">Registrato da {{ $allowance->author?->name ?: 'Operatore nello storico' }} il {{ $allowance->created_at->format('d/m/Y H:i') }}.</p>
        @if($allowance->voided_at)<p>Stornato il {{ $allowance->voided_at->format('d/m/Y H:i') }} da {{ $allowance->voidedBy?->name ?: 'Operatore nello storico' }}. {{ $allowance->void_reason }}</p>@endif
    </x-panel>
    @if($allowance->borne_by==='agency')
        @can('ledger.manage')@can('updateRegistry',$client)@if($allowance->recoverable_cents>0)
        <x-panel title="Registra rientro" padded>
            <p class="u-text-meta u-mb-md">Registra quanto riconosciuto dall’agenzia per questo abbuono, anche in più rientri. Il riepilogo degli incassi del cliente resta separato.</p>
            <form method="POST" action="{{ route('allowances.recoveries.store',$allowance) }}" class="allowance-recovery-form" x-data="{busy:false}" @submit="if(busy)$event.preventDefault();else busy=true">
                @csrf
                <input type="hidden" name="recovery_key" value="{{ $recoveryKey }}">
                <input type="hidden" name="recovery_fingerprint" value="{{ $allowance->recoveryFingerprint() }}">
                <div class="form-row">
                    <x-form-group label="Importo del rientro (€)" name="recovery_amount" required><input class="form-in" name="recovery_amount" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,2})?" maxlength="13" value="{{ $recoveryOld('recovery_amount',\App\Support\Money::decimal($allowance->recoverable_cents)) }}" required></x-form-group>
                    <x-form-group label="Data del rientro" name="recovery_on" required><input class="form-in" type="date" name="recovery_on" value="{{ $recoveryOld('recovery_on',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></x-form-group>
                    <x-form-group label="Modalità del rientro" name="recovery_method" required><select class="form-sel" name="recovery_method" required><option value="">Scegli la modalità</option>@foreach(\App\Models\InsuranceAllowanceRecovery::METHODS as $value=>$label)<option value="{{ $value }}" @selected($recoveryOld('recovery_method')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
                </div>
                <x-form-group label="Riferimento del rientro (facoltativo)" name="recovery_reference"><input class="form-in" name="recovery_reference" maxlength="150" value="{{ $recoveryOld('recovery_reference') }}"></x-form-group>
                <details class="u-mb-md" @if(old('recovery_notes') || $errors->has('recovery_notes')) open @endif><summary>Note sul rientro</summary><x-form-group label="Note" name="recovery_notes"><textarea class="form-ta" name="recovery_notes" rows="2" maxlength="1000">{{ $recoveryOld('recovery_notes') }}</textarea></x-form-group></details>
                <div class="insurance-form-actions"><button class="btn btn-p" :disabled="busy">Registra rientro</button></div>
            </form>
        </x-panel>
        @endif @endcan @endcan
        <x-panel title="Storico dei rientri" id="rientri-abbuono">
            <div class="client-table-scroll" role="region" aria-label="Storico dei rientri" tabindex="0"><table class="t-table" data-list-details>
                <thead><tr><th>Data</th><th>Importo</th><th>Modalità</th><th>Stato</th><th data-list-detail>Riferimento</th><th data-list-detail>Operatore</th><th data-list-detail>Note</th><th data-list-detail>Storno</th><th>Azioni</th></tr></thead>
                <tbody>@forelse($allowance->recoveries->sortByDesc('id') as $recovery)
                    <tr data-list-label="Rientro #{{ $recovery->id }} del {{ $recovery->received_on->format('d/m/Y') }}">
                        <td>{{ $recovery->received_on->format('d/m/Y') }}</td><td>{{ \App\Support\Money::format($recovery->amount_cents) }}</td><td>{{ \App\Models\InsuranceAllowanceRecovery::METHODS[$recovery->method] }}</td><td>{{ $recovery->voided_at ? 'Stornato' : 'Registrato' }}</td>
                        <td>{{ $recovery->reference ?: 'Non indicato' }}</td><td>{{ $recovery->author?->name ?: 'Operatore nello storico' }} / {{ $recovery->created_at->format('d/m/Y H:i') }}</td><td class="u-whitespace-pre-wrap">{{ $recovery->notes }}</td>
                        <td>@if($recovery->voided_at){{ $recovery->voided_at->format('d/m/Y H:i') }} / {{ $recovery->voidedBy?->name ?: 'Operatore nello storico' }} / {{ $recovery->void_reason }}@else Non stornato @endif</td>
                        <td>@can('ledger.manage')@can('updateRegistry',$client)@if(!$recovery->voided_at)
                            <details><summary>Storna rientro</summary><form method="POST" action="{{ route('allowances.recoveries.void',[$allowance,$recovery]) }}" class="u-mt-md">@csrf<x-form-group label="Motivo dello storno" name="recovery_void_reason" :for="'void-recovery-'.$recovery->id" required><textarea class="form-ta" name="recovery_void_reason" rows="2" minlength="5" maxlength="1000" required></textarea></x-form-group><button class="btn btn-g">Conferma storno</button></form></details>
                        @endif @endcan @endcan</td>
                    </tr>
                @empty<tr><td colspan="9">Nessun rientro registrato.</td></tr>@endforelse</tbody>
            </table></div>
        </x-panel>
    @endif
</x-app-layout>
