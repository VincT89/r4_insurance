@if($winner)
    <x-panel :title="$winner->status === 'converted' ? ($case->purpose === 'addition' ? 'Coperture aggiuntive registrate' : 'Polizza registrata') : 'Proposta approvata dal cliente'" padded
        :id="$activeQuote?->id === $winner->id ? null : 'proposal-'.$winner->id">
        <div class="proposal-outcome">
            <div>
                <strong>{{ $winner->company->name }} · {{ $winner->agency?->name ?: 'Rapporto diretto' }}</strong>
                <p>{{ $winner->product }} · {{ $winner->premium_cents !== null ? \App\Support\Money::format($winner->premium_cents) : 'Premio non indicato' }} · {{ $winner->payment_frequency }}</p>
                <p class="u-text-meta">Proposta {{ $winner->reference }}@if($winner->approved_on) · Approvata il {{ $winner->approved_on->format('d/m/Y') }}@endif</p>
                @php
                    $proof = $winner->documents->where('document_category', 'quote_approval');
                @endphp
                @if($proof->count() === 1)<a href="{{ $proof->first()->openUrl() }}" target="_blank" rel="noopener">Apri prova di approvazione</a>
                @elseif($proof->isNotEmpty())<a href="{{ $proposalUrl($winner) }}">Prove di approvazione ({{ $proof->count() }})</a>@endif
            </div>
            <div class="proposal-actions">
                @if($winner->policy)
                    <a class="btn btn-p" href="{{ route('clients.policies.show', [$case->client_id, $winner->policy]) }}">Apri polizza {{ $winner->policy->number }}</a>
                    <x-policy-contract-link :policy="$winner->policy" :button="true" />
                @endif
                @if($winner->policyChange)
                    <a class="btn btn-g" href="{{ route('clients.policy-changes.show', [$case->client_id, $winner->policyChange]) }}">Apri variazione registrata</a>
                @elseif($winner->status === 'approved' && $case->status === 'approved' && $canManage)
                    @if(!$winner->valid_until)
                        <span>Verifica la validità della proposta prima di procedere.</span>
                    @elseif($winner->valid_until->lt(today()))
                        <span>Proposta scaduta il {{ $winner->valid_until->format('d/m/Y') }}. Per procedere serve una proposta valida.</span>
                    @elseif($case->purpose === 'new')
                        <a class="btn btn-p" href="{{ route('policies.create', ['quote_id'=>$winner->id]) }}">Carica contratto</a>
                    @elseif($case->currentPolicy)
                        <a class="btn btn-p" href="{{ route('clients.policies.changes.create', [$case->client_id,$case->current_policy_id,'kind'=>$case->purpose,'quote_id'=>$winner->id]) }}">{{ $case->purpose === 'replacement' ? 'Carica contratto sostitutivo' : 'Registra coperture aggiuntive' }}</a>
                    @endif
                @endif
            </div>
        </div>
    </x-panel>
@endif
@if($activeQuote)
    @include('quotes.proposal-editor', ['quote'=>$activeQuote])
@endif
@if($responses->isNotEmpty())
    <x-panel title="Confronto proposte" id="confronto-proposte">
        <div class="client-table-scroll" role="region" aria-label="Confronto proposte delle compagnie" tabindex="0">
            <table class="t-table proposal-comparison" data-list-details="proposals" data-export-only>
                <thead><tr><th>Compagnia</th><th data-list-detail>Agenzia</th><th>Prodotto</th><th data-list-detail>Riferimento</th><th>{{ $case->purpose === 'addition' ? 'Premio aggiuntivo' : 'Premio del periodo' }}</th><th data-list-detail>Frazionamento</th><th>Copertura</th><th data-list-detail>Validità preventivo</th><th>Stato</th></tr></thead>
                <tbody>
                @foreach($responses as $quote)
                    @php
                        $offerDocs = $quote->documents->where('document_category', 'quote');
                        $expired = $quote->valid_until?->lt(today());
                    @endphp
                    <tr data-no-list-details @if($activeQuote?->id !== $quote->id && $winner?->id !== $quote->id) id="proposal-{{ $quote->id }}" @endif>
                        <td><strong>{{ $quote->company->name }}</strong><small data-table-export-ignore>{{ $quote->agency?->name ?: 'Rapporto diretto' }}</small></td>
                        <td>{{ $quote->agency?->name ?: 'Rapporto diretto' }}</td>
                        <td>{{ $quote->product ?: 'Non indicato' }}<small data-table-export-ignore>Rif. {{ $quote->reference ?: 'non indicato' }}</small>
                            @if($offerDocs->count() === 1)
                                <a data-table-export-ignore href="{{ $offerDocs->first()->openUrl() }}" target="_blank" rel="noopener" aria-label="Apri PDF della proposta {{ $quote->reference }} (nuova scheda)">Apri PDF</a>
                            @elseif($offerDocs->isNotEmpty())
                                <a data-table-export-ignore href="{{ $proposalUrl($quote) }}">Vedi {{ $offerDocs->count() }} PDF</a>
                            @endif
                        </td>
                        <td>{{ $quote->reference }}</td>
                        <td><strong>{{ $quote->premium_cents !== null ? \App\Support\Money::format($quote->premium_cents) : 'Non indicato' }}</strong><small data-table-export-ignore>{{ $quote->payment_frequency }}</small></td>
                        <td>{{ $quote->payment_frequency }}</td>
                        <td>{{ $quote->starts_on && $quote->expires_on ? $quote->starts_on->format('d/m/Y').' – '.$quote->expires_on->format('d/m/Y') : 'Periodo non indicato' }}
                            @if($quote->valid_until)<small data-table-export-ignore><x-status-text :alert="$expired && $case->status==='open'">{{ $expired ? 'Scaduta il' : 'Valida fino al' }} {{ $quote->valid_until->format('d/m/Y') }}</x-status-text></small>@endif
                        </td>
                        <td>{{ $quote->valid_until?->format('d/m/Y') }}</td>
                        <td><strong>{{ $case->purpose==='addition' && $quote->status==='converted' ? 'Aggiunta registrata' : \App\Models\InsuranceQuote::STATUSES[$quote->status] }}</strong>
                            @if($mayWork && $quote->status === 'received' && !$expired)
                                <form method="POST" action="{{ route('quotes.change',[$quote,'submit']) }}">@csrf
                                    <input type="hidden" name="confirmed" value="1">
                                    <button class="btn btn-g btn-sm" type="submit" aria-label="Segna {{ $quote->reference }} come presentata al cliente">Segna come presentata</button>
                                </form>
                            @endif
                            <a data-table-export-ignore class="proposal-row-link" href="{{ $proposalUrl($quote) }}">{{ $mayWork && $quote->status==='pending' ? 'Registra approvazione' : 'Dettagli proposta' }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <details class="proposal-export"><summary>Esporta confronto</summary><div data-export-controls></div></details>
    </x-panel>
@endif
@if($waiting->isNotEmpty() || $responses->isEmpty())
    <x-panel :title="$case->quotes->isEmpty() && $mayWork ? 'Richiesta pronta da inviare' : ($case->status==='open' ? 'Risposte attese' : 'Richieste senza risposta')" id="risposte-attese" padded>
        @forelse($waiting as $quote)
            <div class="awaiting-proposal" @if($activeQuote?->id !== $quote->id) id="proposal-{{ $quote->id }}" @endif>
                <div><strong>{{ $quote->request_recipient['company_name'] ?? $quote->company->name }}</strong> · {{ $quote->request_recipient ? ($quote->request_recipient['agency_name'] ?? 'Rapporto diretto') : ($quote->agency?->name ?: 'Rapporto diretto') }}
                    <small>Richiesta del {{ $quote->requested_at->format('d/m/Y') }}</small>
                </div>
                @if($mayWork)<a class="btn btn-g" href="{{ $proposalUrl($quote) }}" aria-label="Registra risposta di {{ $quote->company->name }} · {{ $quote->agency?->name ?: 'Rapporto diretto' }}">Registra risposta</a>@endif
            </div>
        @empty
            @if($case->quotes->isEmpty() && $mayWork)
                <p>Scegli <strong>Prepara email</strong> per selezionare compagnie e agenzie. Potrai controllare il messaggio prima dell’invio.</p>
            @else
                <p>Nessuna risposta registrata. Qui ritroverai le richieste inviate alle compagnie e alle agenzie.</p>
            @endif
        @endforelse
    </x-panel>
@endif
