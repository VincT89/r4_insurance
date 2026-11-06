@php
    $allowanceOld = fn($key, $fallback = '') => is_scalar(old($key, $fallback)) ? (string)old($key, $fallback) : (string)$fallback;
    $allowanceTitles = $allowanceTitles->where('direction','receipt')->values();
    $defaultAllowanceTitle = isset($selectedAllowanceTitle) ? (string)$selectedAllowanceTitle : ($allowanceTitles->count() === 1 ? (string)$allowanceTitles->first()->id : '');
    $selectedAllowance = $allowanceTitles->firstWhere('id', $allowanceOld('allowance_title_id', $defaultAllowanceTitle));
    $allowanceOptions = $allowanceTitles->mapWithKeys(fn($row) => [(string)$row->id=>['balance'=>$row->balance_cents, 'company'=>$row->contract->company]])->all();
    $allowanceKey = $allowanceOld('allowance_key');
    if (!\Illuminate\Support\Str::isUuid($allowanceKey)) $allowanceKey = (string)\Illuminate\Support\Str::uuid();
@endphp
@if($allowanceTitles->isNotEmpty())
<details id="allowance-entry" class="allowance-entry" @if(old('allowance_key')) open @endif>
    <summary class="btn btn-g">Registra abbuono</summary>
    <form method="POST" action="{{ $allowanceAction }}" class="u-mt-md" data-allowance-form
        x-data="{titles:@js($allowanceOptions),title:@js((string)($selectedAllowance?->id ?? '')),amount:@js($allowanceOld('allowance_amount')),bearer:@js($allowanceOld('allowance_borne_by')),
            remaining(){const value=this.amount.replace(',','.');if(!/^\d+(\.\d{1,2})?$/.test(value))return null;const cents=Math.round(Number(value)*100);return this.titles[this.title] ? this.titles[this.title].balance-cents : null},
            money(cents){return new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(cents/100)}}">
        @csrf
        <input type="hidden" name="allowance_key" value="{{ $allowanceKey }}">
        <input type="hidden" name="allowance_balance" :value="titles[title]?.balance || ''" value="{{ $selectedAllowance?->balance_cents }}">
        <p class="u-text-meta u-mb-md">L’abbuono riduce il residuo senza registrare un incasso e non conferma la copertura della polizza. Registralo prima del pagamento: l’importo da incassare si aggiorna automaticamente.</p>
        <div class="form-row">
            @if($chooseAllowanceTitle)
                <x-form-group label="Titolo dell’abbuono" name="allowance_title_id" required>
                    <select class="form-sel" name="allowance_title_id" x-model="title" @change="amount=''" required>
                        <option value="">Scegli il titolo</option>
                        @foreach($allowanceTitles as $row)<option value="{{ $row->id }}" @selected($selectedAllowance?->id === $row->id)>#{{ $row->id }} / {{ $row->due_on->format('d/m/Y') }} / {{ $row->reference ?: \App\Models\InsuranceTitle::KINDS[$row->kind] }} / Residuo {{ \App\Support\Money::format($row->balance_cents) }}</option>@endforeach
                    </select>
                </x-form-group>
            @endif
            <x-form-group label="Importo dell’abbuono (€)" name="allowance_amount" required>
                <input class="form-in" name="allowance_amount" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,2})?" maxlength="13" x-model="amount" value="{{ $allowanceOld('allowance_amount') }}" required aria-describedby="allowance-balance-help">
            </x-form-group>
            <x-form-group label="Data dell’abbuono" name="allowance_on" required><input class="form-in" type="date" name="allowance_on" value="{{ $allowanceOld('allowance_on',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></x-form-group>
            <x-form-group label="Chi sostiene l’abbuono" name="allowance_borne_by" required>
                <select class="form-sel" name="allowance_borne_by" x-model="bearer" required><option value="">Scegli chi lo sostiene</option>@foreach(\App\Models\InsuranceAllowance::BEARERS as $value=>$label)<option value="{{ $value }}" @selected($allowanceOld('allowance_borne_by') === $value)>{{ $label }}</option>@endforeach</select>
                <p class="u-text-meta u-mt-sm" x-show="bearer==='company' &amp;&amp; title" x-cloak x-text="titles[title]?.company"></p>
            </x-form-group>
            <div x-show="bearer==='agency'" x-cloak>
                <x-form-group label="Agenzia che sostiene l’abbuono" name="allowance_agency_id" required><select class="form-sel" name="allowance_agency_id" :disabled="bearer!=='agency'" :required="bearer==='agency'"><option value="">Scegli l’agenzia</option>@foreach($allowanceAgencies as $agency)<option value="{{ $agency->id }}" @selected($allowanceOld('allowance_agency_id') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
            </div>
        </div>
        <p id="allowance-balance-help" class="u-text-meta u-mb-md" aria-live="polite" x-text="!title ? 'Scegli il titolo per vedere il residuo.' : (remaining() === null ? 'Residuo del titolo: '+money(titles[title].balance) : (remaining() &lt; 0 ? 'L’abbuono supera il residuo del titolo.' : 'Dopo l’abbuono restano '+money(remaining())+' da incassare.'))"></p>
        <div class="form-row full">
            <x-form-group label="Motivo dell’abbuono" name="allowance_reason" required><textarea class="form-ta" name="allowance_reason" rows="2" minlength="5" maxlength="1000" required>{{ $allowanceOld('allowance_reason') }}</textarea></x-form-group>
            <x-form-group label="Riferimento della comunicazione (facoltativo)" name="allowance_reference"><input class="form-in" name="allowance_reference" maxlength="150" value="{{ $allowanceOld('allowance_reference') }}"></x-form-group>
        </div>
        <div class="insurance-form-actions"><button class="btn btn-p" :disabled="!title || remaining() === null || remaining() &lt; 0 || remaining() === titles[title]?.balance">Registra abbuono</button></div>
    </form>
</details>
@endif
