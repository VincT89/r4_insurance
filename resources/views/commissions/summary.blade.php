@can('system.admin')
    @if($commissionSummary)
        <x-panel title="Riepilogo provvigioni" class="commission-overview" aria-label="Riepilogo provvigioni">
            <x-slot:headerActions>
                <a class="btn btn-g btn-sm" href="{{ route('commissions.index', ['client'=>$client->id, 'policy'=>$commissionPolicy?->id, 'period'=>'all']) }}">Dettaglio provvigioni</a>
            </x-slot:headerActions>
            <p class="commission-overview-scope">{{ $commissionPolicy ? 'Tutte le rate della polizza, inclusi i rinnovi.' : 'Tutte le polizze del cliente, indipendentemente dal ramo selezionato.' }} Intero storico; saldi aggiornati alle operazioni registrate.</p>
            @if((int)$commissionSummary->rows_count === 0)
                <p class="commission-overview-empty">Nessuna rata registrata. Le provvigioni saranno disponibili dopo la creazione delle rate e la configurazione delle condizioni del mandato.</p>
            @else
                <dl class="collection-summary-values commission-overview-values">
                    @foreach(['expected'=>'Previste', 'accrued'=>'Maturate', 'settled'=>'Liquidate'] as $key=>$label)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ (int)$commissionSummary->incomplete === (int)$commissionSummary->rows_count ? 'Da configurare' : \App\Support\Money::format((int)$commissionSummary->$key) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="collection-summary-note commission-overview-note">Previste secondo le condizioni delle rate; maturate secondo il mandato; liquidate secondo le registrazioni di pagamento delle provvigioni. Sono fasi dello stesso importo e non si sommano.</p>
                @if($commissionSummary->incomplete > 0)
                    <p class="commission-overview-note">@if((int)$commissionSummary->incomplete < (int)$commissionSummary->rows_count)<strong>Totali parziali.</strong> @endif{{ (int)$commissionSummary->incomplete }} {{ (int)$commissionSummary->incomplete === 1 ? 'rata da completare' : 'rate da completare' }}: verifica condizioni, base di calcolo e calcoli da aggiornare nel dettaglio.</p>
                @endif
                @if($commissionSummary->payable > 0 || $commissionSummary->reconcile > 0)
                    <p class="commission-overview-note">
                        @if($commissionSummary->payable > 0)Da liquidare: <x-status-text alert>{{ \App\Support\Money::format((int)$commissionSummary->payable) }}</x-status-text>.@endif
                        @if($commissionSummary->reconcile > 0)Da riconciliare dopo storni o annullamenti: <x-status-text alert>{{ \App\Support\Money::format((int)$commissionSummary->reconcile) }}</x-status-text>.@endif
                    </p>
                @endif
            @endif
        </x-panel>
    @endif
@endcan
