@if($policyTitles->isNotEmpty())
    <div class="policy-title-list">
        <h2 class="sec-lbl">Titoli della polizza</h2>
        <div class="client-table-scroll policy-list-scroll" role="region" aria-label="Titoli della polizza" tabindex="0">
            <table class="t-table policy-titles-table">
                <thead><tr><th>Titolo</th><th>Scadenza</th><th>Importo</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>@foreach($policyTitles as $policyTitle)
                    <tr>
                        <td data-label="Titolo"><a href="{{ route('titles.show',$policyTitle) }}">{{ $policyTitle->direction === 'refund' ? 'Rimborso' : \App\Models\InsuranceTitle::KINDS[$policyTitle->kind] }}@if($policyTitle->reference) / {{ $policyTitle->reference }}@endif</a><div class="u-text-meta">{{ $policyTitle->coverage_from->format('d/m/Y') }} – {{ $policyTitle->coverage_to->format('d/m/Y') }}</div></td>
                        <td data-label="Scadenza"><x-status-text :alert="$policyTitle->state==='overdue'">{{ $policyTitle->due_on->format('d/m/Y') }}</x-status-text></td>
                        <td data-label="Importo">{{ \App\Support\Money::format($policyTitle->amount_cents) }}@if($policyTitle->allowance_cents>0)<div class="u-text-meta">Abbuoni: {{ \App\Support\Money::format($policyTitle->allowance_cents) }}</div>@endif @if(($policyTitle->collected_cents>0 || $policyTitle->allowance_cents>0) && $policyTitle->balance_cents>0 && !$policyTitle->cancelled_at)<div class="u-text-meta">Da saldare: {{ \App\Support\Money::format($policyTitle->balance_cents) }}</div>@endif</td>
                        <td data-label="Stato"><x-title-status :title="$policyTitle" />@if($policyTitle->allowance_cents>0 && $policyTitle->affects_coverage && !$policyTitle->cancelled_at)<div class="u-text-meta">{{ $policyTitle->coverage_confirmation_valid ? 'Copertura confermata' : ($policyTitle->balance_cents>0 ? 'Copertura da confermare dopo saldo' : 'Copertura da confermare') }}</div>@endif</td>
                        <td data-label="Azioni"><div class="policy-row-actions">@if($payableTitles->contains('id',$policyTitle->id))<a href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments','title'=>$policyTitle->id]).'#payment-entry' }}">{{ $policyTitle->direction === 'refund' ? 'Registra rimborso' : 'Registra pagamento' }}</a>@if($policyTitle->direction === 'receipt')<a href="{{ route('clients.policies.show',[$client,$policy,'tab'=>'payments','title'=>$policyTitle->id]).'#allowance-entry' }}">Registra abbuono</a>@endif @elseif($policyTitle->allowance_cents>0 && $policyTitle->affects_coverage && !$policyTitle->cancelled_at && !$policyTitle->coverage_confirmation_valid)<a href="#allowance-coverage-{{ $policyTitle->id }}">Conferma copertura</a>@endif<a href="{{ route('titles.show',$policyTitle) }}">Dettaglio e movimenti</a></div></td>
                    </tr>
                @endforeach</tbody>
            </table>
        </div>
    </div>
@else
    <p class="u-text-meta">Non sono ancora presenti titoli. Prepara i titoli dal frazionamento della polizza oppure inserisci un titolo manuale.</p>
@endif
