@php
    $paymentOld = fn($key, $fallback = '') => is_scalar(old($key, $fallback)) ? (string) old($key, $fallback) : (string) $fallback;
    $defaultTitle = isset($selectedPaymentTitle) ? (string) $selectedPaymentTitle : ($collectionTitles->count() === 1 ? (string) $collectionTitles->first()->id : '');
    $selectedTitle = $chooseTitle ? $paymentOld('title_id', $defaultTitle) : $defaultTitle;
    $selected = $collectionTitles->first(fn($row) => (string) $row->id === $selectedTitle);
    $selectedTitle = $selected ? $selectedTitle : '';
    $titleOptions = $collectionTitles->mapWithKeys(fn($row) => [(string) $row->id => ['amount'=>\App\Support\Money::decimal($row->balance_cents), 'method'=>$row->payment_method, 'direction'=>$row->direction, 'agency'=>(bool)$row->supplier_agency_id]])->all();
    $initialMethod = $paymentOld('payment_method', $selected?->payment_method ?? '');
    if ($selected?->direction === 'refund' && !in_array($initialMethod, \App\Models\InsuranceCollection::REFUND_METHODS,true)) $initialMethod = '';
    $initialAmount = $selected ? \App\Support\Money::decimal($selected->balance_cents) : '';
    $initialRecipient = $paymentOld('recipient', str_contains($initialMethod,'a Compagnia') ? 'company' : 'our_agency');
    $paymentKey = $paymentOld('idempotency_key');
    if (!\Illuminate\Support\Str::isUuid($paymentKey)) $paymentKey = (string) \Illuminate\Support\Str::uuid();
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $collectionAction }}"
    @submit="busy=true" :aria-busy="busy.toString()"
    x-data="{busy:false,receiptMethods:@js(\App\Models\InsurancePolicy::PAYMENT_METHODS),refundMethods:@js(\App\Models\InsuranceCollection::REFUND_METHODS),titles:@js($titleOptions),title:@js($selectedTitle),amount:@js($initialAmount),method:@js($initialMethod),recipient:@js($initialRecipient),
        selectTitle(){this.amount=this.titles[this.title]?.amount||'';this.method=this.titles[this.title]?.method||'';if(this.titles[this.title]?.direction==='refund' &amp;&amp; !this.refundMethods.includes(this.method))this.method='';this.selectMethod()},
        selectMethod(){if(this.method.includes('a Compagnia'))this.recipient='company';else if(this.method.includes('a Intermediario') &amp;&amp; this.recipient==='company')this.recipient='our_agency'}}">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $paymentKey }}">
    <div class="form-row">
        @if($chooseTitle)
            <x-form-group label="Titolo da saldare" name="title_id" required>
                <select class="form-sel" name="title_id" x-model="title" @change="selectTitle()" required>
                    <option value="">Scegli il titolo</option>
                    @foreach($collectionTitles as $paymentTitle)
                        <option value="{{ $paymentTitle->id }}" @selected($selectedTitle === (string) $paymentTitle->id)>{{ $paymentTitle->direction === 'refund' ? 'Rimborso / ' : '' }}{{ $paymentTitle->due_on->format('d/m/Y') }} / {{ $paymentTitle->reference ?: \App\Models\InsuranceTitle::KINDS[$paymentTitle->kind] }} / {{ \App\Support\Money::format($paymentTitle->balance_cents) }}</option>
                    @endforeach
                </select>
            </x-form-group>
        @endif
        <x-form-group label="Importo del pagamento (€)" name="amount" required>
            <input class="form-in" type="number" step="0.01" min="0.01" name="amount" x-model="amount" value="{{ $initialAmount }}" inputmode="decimal" readonly required aria-describedby="payment-amount-help">
        </x-form-group>
        <x-form-group label="Data effettiva del pagamento" name="received_on" required>
            <input class="form-in" type="date" name="received_on" value="{{ $paymentOld('received_on',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
        </x-form-group>
        <x-form-group label="Modalità di pagamento" name="payment_method" required>
            <select name="payment_method" class="form-sel" x-model="method" @change="selectMethod()" required>
                <option value="">Scegli la modalità</option>
                <template x-for="item in (titles[title]?.direction === 'refund' ? refundMethods : receiptMethods)" :key="item"><option :value="item" x-text="item" :selected="item === method"></option></template>
            </select>
        </x-form-group>
        <div class="form-g"><label class="form-lbl" for="payment-recipient" x-text="titles[title]?.direction === 'refund' ? 'Chi ha effettuato il rimborso al cliente' : 'Chi ha ricevuto il pagamento'"></label>
            <select id="payment-recipient" name="recipient" class="form-sel" x-model="recipient" required>
                @foreach(\App\Models\InsuranceCollection::RECIPIENTS as $value=>$label)
                    <option x-show="@js($value) !== 'agency' || titles[title]?.agency" value="{{ $value }}" @selected($initialRecipient === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="u-text-meta" id="payment-amount-help"><span x-text="titles[title]?.direction === 'refund' ? 'Stai registrando l’intero rimborso al cliente.' : 'L’importo è compilato automaticamente e salda tutto il residuo del titolo scelto.'">L’importo è compilato automaticamente e salda tutto il residuo del titolo scelto.</span> Indica il giorno in cui è avvenuto il pagamento.</p>
    <details class="u-mt-md" @if(collect(['document_type','reference','notes','consultant_id','files'])->contains(fn($field) => $errors->has($field) || $errors->has($field.'.*'))) open @endif>
        <summary>Riferimenti e documenti facoltativi</summary>
        <div class="form-row u-mt-md">
            <x-form-group label="Tipo di registrazione o documento" name="document_type">
                <select name="document_type" class="form-sel">
                    @foreach(\App\Models\InsuranceCollection::DOCUMENT_TYPES as $value=>$label)<option value="{{ $value }}" @selected($paymentOld('document_type','record') === $value)>{{ $label }}</option>@endforeach
                </select>
            </x-form-group>
            <x-form-group label="Riferimento del pagamento" name="reference"><input class="form-in" name="reference" maxlength="150" value="{{ $paymentOld('reference') }}"></x-form-group>
            <x-form-group label="Documenti di pagamento (PDF)" name="files"><input class="form-in" type="file" name="files[]" multiple accept="application/pdf,.pdf"></x-form-group>
            @can('system.admin')
                <x-form-group label="Consulente" name="consultant_id"><select class="form-sel" name="consultant_id">@foreach($consultants as $user)<option value="{{ $user->id }}" @selected($paymentOld('consultant_id',auth()->id()) === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></x-form-group>
            @endcan
            <x-form-group label="Note" name="notes"><textarea class="form-ta" name="notes" rows="2" maxlength="5000">{{ $paymentOld('notes') }}</textarea></x-form-group>
        </div>
    </details>
    <div class="insurance-form-actions"><button class="btn btn-p" :disabled="busy" x-text="busy ? 'Registrazione…' : (titles[title]?.direction === 'refund' ? 'Registra rimborso al cliente' : 'Registra pagamento')">Registra pagamento</button></div>
</form>
