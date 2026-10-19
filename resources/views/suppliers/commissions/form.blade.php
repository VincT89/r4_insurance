<x-app-layout :title="$schedule->exists ? 'Modifica condizioni provvigionali' : 'Configura aliquote'">
<x-page-header><x-slot:title>{{ $schedule->exists ? 'Modifica condizioni' : 'Configura aliquote' }} · {{ $supplier->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('suppliers.commissions.index',$supplier) }}">Provvigioni agenzia</a></x-slot:actions></x-page-header>
@php
    $safeOld = fn($key,$default='') => is_scalar(old($key,$default)) ? old($key,$default) : '';
    $entryState = collect($entries)->map(function($entry,$i) {
        $old = old('entries.'.$i,[]);
        if (!is_array($old)) $old=[];
        return ['selected'=>old('form_complete') ? (bool)($old['selected']??false) : $entry['selected'],'basis'=>is_string($old['basis']??null) ? $old['basis'] : $entry['basis']];
    });
@endphp
<x-panel padded><form method="POST" action="{{ $schedule->exists ? route('suppliers.commissions.update',[$supplier,$schedule]) : route('suppliers.commissions.store',$supplier) }}" x-data="{rows:@js($entryState),search:'',selectedOnly:false}">
@csrf
@if($schedule->exists) @method('PATCH') @endif
<x-form-section title="Mandato e periodo"><div class="form-row">
<x-form-group label="Compagnia" name="supplier_company_id" required><select name="supplier_company_id" class="form-sel" required>@if(!$schedule->exists)<option value="">Seleziona un mandato dell’agenzia</option>@endif @foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('supplier_company_id',$schedule->supplier_company_id)==$company->id) @disabled($schedule->exists && $schedule->supplier_company_id!==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Quando matura la provvigione" name="accrual_event"><select name="accrual_event" class="form-sel"><option value="">Da definire con il mandante</option>@foreach(\App\Models\AgencyCommissionSchedule::ACCRUAL_EVENTS as $key=>$label)<option value="{{ $key }}" @selected(old('accrual_event',$schedule->accrual_event)===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
<x-form-group label="Validità dal" name="valid_from" required><input name="valid_from" class="form-in" type="date" value="{{ $safeOld('valid_from',$schedule->valid_from?->toDateString()) }}" required></x-form-group>
<x-form-group label="Validità fino al" name="valid_to"><input name="valid_to" class="form-in" type="date" value="{{ $safeOld('valid_to',$schedule->valid_to?->toDateString()) }}"><p class="u-text-meta">Lascia vuoto se il mandato non prevede un termine.</p></x-form-group>
</div><p class="u-text-meta">Le condizioni vengono conservate nello storico. Questa configurazione non registra provvigioni maturate o incassate.</p></x-form-section>
<x-form-section title="Rami, sottorami e aliquote"><p class="u-mb-md">Seleziona le voci previste dal mandato. Ogni voce è autonoma: il ramo non seleziona i sottorami e le aliquote non vengono copiate. Un campo vuoto significa “Da definire”; 0 indica una provvigione nulla.</p>
<div class="commission-picker-tools"><x-form-group label="Cerca ramo o sottoramo" name="catalog_search"><input class="form-in" id="field-catalog-search" type="search" x-model="search" placeholder="Cerca nel catalogo"></x-form-group><button type="button" class="btn btn-g" :aria-pressed="selectedOnly" @click="selectedOnly=!selectedOnly" x-text="selectedOnly ? 'Mostra tutte le voci' : 'Mostra solo selezionate'">Mostra solo selezionate</button></div>
@error('entries')<p class="invalid-feedback">{{ $message }}</p>@enderror
@foreach($groups as $group)
<section class="commission-branch" aria-labelledby="commission-branch-{{ $group['branch']->id }}" x-show="@js($group['indices']).some(i => (!selectedOnly || rows[i].selected)) && (!search || @js(mb_strtolower($group['branch']->name.' '.collect($group['indices'])->map(fn($i)=>$entries[$i]['label'])->join(' '))).includes(search.toLocaleLowerCase('it')))">
<h2 id="commission-branch-{{ $group['branch']->id }}">{{ $group['branch']->name }}</h2>
@foreach($group['indices'] as $i)
@php $entry=$entries[$i]; @endphp
<div class="commission-option" x-show="!selectedOnly || rows[{{ $i }}].selected">
<label class="commission-selection"><input type="checkbox" name="entries[{{ $i }}][selected]" value="1" x-model="rows[{{ $i }}].selected"> <span>{{ $entry['label'] }}</span></label>
<input type="hidden" name="entries[{{ $i }}][branch_id]" value="{{ $entry['branch_id'] }}" :disabled="!rows[{{ $i }}].selected">
<input type="hidden" name="entries[{{ $i }}][subbranch_id]" value="{{ $entry['subbranch_id'] }}" :disabled="!rows[{{ $i }}].selected">
<div class="commission-rate-fields" x-show="rows[{{ $i }}].selected" x-cloak>
<x-form-group label="Base di calcolo" :name="'entries.'.$i.'.basis'"><select name="entries[{{ $i }}][basis]" class="form-sel" x-model="rows[{{ $i }}].basis" :disabled="!rows[{{ $i }}].selected"><option value="">Da definire</option>@foreach(\App\Models\AgencyCommissionRate::BASES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></x-form-group>
<x-form-group label="Nuova emissione (%)" :name="'entries.'.$i.'.issue_rate'"><input name="entries[{{ $i }}][issue_rate]" class="form-in" inputmode="decimal" maxlength="8" value="{{ $safeOld('entries.'.$i.'.issue_rate',$entry['issue_rate']) }}" :disabled="!rows[{{ $i }}].selected" placeholder="Da definire"></x-form-group>
<x-form-group label="Rinnovo (%)" :name="'entries.'.$i.'.renewal_rate'"><input name="entries[{{ $i }}][renewal_rate]" class="form-in" inputmode="decimal" maxlength="8" value="{{ $safeOld('entries.'.$i.'.renewal_rate',$entry['renewal_rate']) }}" :disabled="!rows[{{ $i }}].selected" placeholder="Da definire"></x-form-group>
<div class="commission-basis-note"><x-form-group label="Precisazioni sulla base contrattuale" :name="'entries.'.$i.'.basis_note'"><input name="entries[{{ $i }}][basis_note]" class="form-in" maxlength="500" value="{{ $safeOld('entries.'.$i.'.basis_note',$entry['basis_note']) }}" :disabled="!rows[{{ $i }}].selected" :required="rows[{{ $i }}].selected && rows[{{ $i }}].basis==='other'"><p class="u-text-meta">Obbligatorio per “Altra base contrattuale”. Percentuali da 0 a 100, fino a quattro decimali.</p></x-form-group></div>
@error('entries.'.$i.'.selected')<p class="invalid-feedback">{{ $message }}</p>@enderror
</div></div>
@endforeach
</section>
@endforeach
@if(!$groups)<p>Nessun ramo disponibile: completa prima il catalogo assicurativo.</p>@endif
</x-form-section>
<x-form-section title="Riferimenti e storico"><x-form-group label="Note e riferimenti del mandato" name="notes"><textarea class="form-ta" name="notes" rows="3" maxlength="3000">{{ $safeOld('notes',$schedule->notes) }}</textarea></x-form-group>
@if($schedule->exists)<div class="u-mt-md"><x-form-group label="Motivo della revisione" name="revision_reason" required><textarea class="form-ta" name="revision_reason" rows="2" minlength="10" maxlength="1000" required>{{ $safeOld('revision_reason') }}</textarea><p class="u-text-meta">Il salvataggio crea una nuova versione e conserva integralmente la precedente.</p></x-form-group></div>@endif
</x-form-section>
<input type="hidden" name="form_complete" value="1">
<div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('suppliers.commissions.index',$supplier) }}">Annulla</a><button class="btn btn-p">{{ $schedule->exists ? 'Salva nuova versione' : 'Salva condizioni' }}</button></div>
</form></x-panel></x-app-layout>
