@if($title->allowances->isNotEmpty())
<x-panel title="Abbuoni del titolo" id="abbuoni-del-titolo">
    <div class="client-table-scroll" role="region" aria-label="Abbuoni del titolo" tabindex="0"><table class="t-table" data-list-details>
        <thead><tr><th>Data</th><th>Importo</th><th>Chi lo sostiene</th><th>Stato</th><th data-list-detail>Operatore</th><th data-list-detail>Motivo</th><th data-list-detail>Riferimento</th><th data-list-detail>Dettagli storno</th><th>Azioni</th></tr></thead>
        <tbody>@foreach($title->allowances->sortByDesc('id') as $allowance)
            <tr data-list-label="Abbuono #{{ $allowance->id }} del {{ $allowance->effective_on->format('d/m/Y') }}">
                <td>{{ $allowance->effective_on->format('d/m/Y') }}</td><td>{{ \App\Support\Money::format($allowance->amount_cents) }}</td><td>{{ $allowance->bearer_label }}</td><td>{{ $allowance->voided_at ? 'Stornato' : 'Registrato' }}@if($allowance->borne_by==='agency' && !$allowance->voided_at)<div>{{ $allowance->recovery_state_label }}</div><div>Da recuperare {{ \App\Support\Money::format($allowance->recoverable_cents) }}</div>@endif</td>
                <td>{{ $allowance->author?->name ?: 'Operatore nello storico' }} / {{ $allowance->created_at->format('d/m/Y H:i') }}</td><td class="u-whitespace-pre-wrap">{{ $allowance->reason }}</td><td>{{ $allowance->reference ?: 'Non indicato' }}</td><td>@if($allowance->voided_at){{ $allowance->voided_at->format('d/m/Y H:i') }} / {{ $allowance->voidedBy?->name ?: 'Operatore nello storico' }} / {{ $allowance->void_reason }}@else Non stornato @endif</td>
                <td><a href="{{ route('allowances.show',$allowance) }}">Apri abbuono</a>@can('ledger.manage')@can('updateRegistry',$title->policy->client)@if(!$allowance->voided_at && !$title->cancelled_at && !$allowance->recovered_cents)
                    <details><summary>Storna abbuono</summary><form method="POST" action="{{ route('allowances.void',$allowance) }}" class="u-mt-md">@csrf<input type="hidden" name="allowance_origin" value="title"><x-form-group label="Motivo dello storno" name="allowance_void_reason" :for="'void-allowance-'.$allowance->id" required><textarea class="form-ta" name="allowance_void_reason" rows="2" minlength="5" maxlength="1000" required></textarea></x-form-group><div class="insurance-form-actions"><button class="btn btn-g">Conferma storno</button></div></form></details>
                @endif @endcan @endcan</td>
            </tr>
        @endforeach</tbody>
    </table></div>
</x-panel>
@endif
