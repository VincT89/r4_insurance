@if($previous=$policy->companyChangeFrom)
    <div class="policy-contract-lineage">
        <div><strong>Polizza precedente</strong><p>{{ $previous->sourcePolicy->company }} / {{ $previous->sourcePolicy->number }}</p></div>
        <a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$previous->sourcePolicy]) }}">Apri polizza precedente</a>
    </div>
@endif
@if($replacement=$policy->replacementFrom)
    <div class="policy-contract-lineage">
        <div><strong>Polizza precedente</strong><p>{{ $replacement->sourcePolicy->company }} / {{ $replacement->sourcePolicy->number }}</p>@if($replacement->replacementSchedule?->state==='pending')<p>Il cambio è programmato dal {{ $replacement->effective_on->format('d/m/Y') }}.</p>@endif</div>
        <a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$replacement->sourcePolicy]) }}">Apri polizza precedente</a>
    </div>
@endif
@if($replacement=$policy->replacementTo)
    <div class="policy-contract-lineage">
        <div><strong>{{ $replacement->replacementSchedule?->state==='pending' ? 'Sostituzione programmata' : 'Polizza sostitutiva' }}</strong><p>{{ $replacement->resultPolicy->company }} / {{ $replacement->resultPolicy->number }} / Dal {{ $replacement->effective_on->format('d/m/Y') }}</p></div>
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$replacement->resultPolicy]) }}">Apri nuova polizza</a>@if($replacement->replacementSchedule?->state==='pending')<a class="btn btn-g" href="{{ route('clients.policy-changes.show',[$client,$replacement]) }}">Gestisci programmazione</a>@endif</div>
    </div>
@endif
@if($following=$policy->companyChangeTo)
    <div class="policy-contract-lineage">
        <div><strong>Nuova polizza alla scadenza</strong><p>{{ $following->resultPolicy->company }} / {{ $following->resultPolicy->number }} / Dal {{ $following->effective_on->format('d/m/Y') }}</p></div>
        <a class="btn btn-g" href="{{ route('clients.policies.show',[$client,$following->resultPolicy]) }}">Apri nuova polizza</a>
    </div>
@endif
