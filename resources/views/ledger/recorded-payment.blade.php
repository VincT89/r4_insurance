@php($recorded = session('recorded_payment'))
@if($recorded && (int)$recorded['policy_id'] === (int)$policy->id)
    <div class="recorded-payment">
        <span>{{ $recorded['refund'] ? 'Rimborso' : 'Incasso' }} del {{ \Illuminate\Support\Carbon::parse($recorded['date'])->format('d/m/Y') }} · {{ $recorded['amount'] }} · {{ $recorded['method'] }}</span>
        <a class="btn btn-g" href="{{ route('collections.index', ['policy'=>$policy->id, 'from'=>$recorded['date'], 'to'=>$recorded['date']]) }}#movimenti">Vedi in contabilità</a>
    </div>
@endif
