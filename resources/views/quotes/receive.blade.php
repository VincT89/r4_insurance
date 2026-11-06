@php
    $proposalBag = 'proposal_'.$quote->id.'_receive';
    $inputValue = fn($field, $default = '') => $failedAction === 'receive' && is_scalar($value = old($field)) ? $value : $default;
@endphp
<form class="proposal-form" method="POST" enctype="multipart/form-data" action="{{ route('quotes.change',[$quote,'receive']) }}">@csrf
    <div class="form-row">
        <x-form-group label="Proposta della compagnia (PDF)" name="files" :for="'quote-files-'.$quote->id" :error-bag="$proposalBag" required>
            <input class="form-in" type="file" name="files[]" accept="application/pdf,.pdf" multiple required>
            <p class="u-text-meta">{{ $proposalErrors->any() ? 'Riseleziona i PDF prima di salvare.' : 'Puoi allegare fino a 10 PDF, massimo 10 MB ciascuno.' }}</p>
        </x-form-group>
        <x-form-group label="Ricevuta il" name="received_on" :for="'received-on-'.$quote->id" :error-bag="$proposalBag" required>
            <input class="form-in" type="date" name="received_on" min="{{ $quote->requested_at->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ $inputValue('received_on',today()->toDateString()) }}" required>
        </x-form-group>
        @foreach(['product'=>'Nome del prodotto','reference'=>'Riferimento preventivo'] as $key=>$label)
            <x-form-group :label="$label" :name="$key" :for="$key.'-'.$quote->id" :error-bag="$proposalBag" required>
                <input class="form-in" name="{{ $key }}" value="{{ $inputValue($key) }}" maxlength="{{ $key==='reference' ? 100 : 255 }}" required>
            </x-form-group>
        @endforeach
        <x-form-group :label="$case->purpose==='addition' ? 'Premio aggiuntivo del periodo (€)' : 'Premio totale del periodo (€)'" name="premium" :for="'premium-'.$quote->id" :error-bag="$proposalBag" required>
            <input class="form-in" type="number" step="0.01" min="{{ $case->purpose==='addition' ? '0' : '0.01' }}" max="9999999999.99" name="premium" value="{{ $inputValue('premium') }}" required>
        </x-form-group>
        <x-form-group label="Frazionamento" name="payment_frequency" :for="'quote-frequency-'.$quote->id" :error-bag="$proposalBag" required>
            <select name="payment_frequency" class="form-sel" required><option value="">Seleziona</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option @selected($inputValue('payment_frequency') === $frequency)>{{ $frequency }}</option>@endforeach</select>
        </x-form-group>
        @foreach(['starts_on'=>'Decorrenza proposta','expires_on'=>'Scadenza proposta','valid_until'=>'Proposta valida fino al'] as $key=>$label)
            <x-form-group :label="$label" :name="$key" :for="$key.'-'.$quote->id" :error-bag="$proposalBag" required>
                <input class="form-in" type="date" name="{{ $key }}" value="{{ $inputValue($key) }}" required>
            </x-form-group>
        @endforeach
        <x-form-group label="Agenzia intermediaria" name="supplier_agency_id" :for="'quote-agency-'.$quote->id" :error-bag="$proposalBag">
            @if($case->purpose==='addition')
                <input type="hidden" name="supplier_agency_id" value="{{ $case->currentPolicy?->supplier_agency_id }}">
                <span>{{ $case->currentPolicy?->supplierAgency?->name ?: 'Rapporto diretto con la compagnia' }}</span>
            @else
                <select name="supplier_agency_id" class="form-sel">
                    <option value="">Rapporto diretto con la compagnia</option>@foreach($quote->company->agencies->where('status','active') as $agency)<option value="{{ $agency->id }}" @selected((string)$inputValue('supplier_agency_id',$quote->supplier_agency_id) === (string)$agency->id)>{{ $agency->name }}</option>@endforeach
                </select>
            @endif
        </x-form-group>
    </div>
    <div class="insurance-form-actions"><button class="btn btn-p">Registra risposta</button></div>
</form>
