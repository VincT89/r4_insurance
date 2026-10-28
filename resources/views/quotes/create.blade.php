<x-app-layout title="Nuovo preventivo"><x-page-header><x-slot:title>Nuovo preventivo</x-slot:title><x-slot:actions>@if($client->exists)<a class="btn btn-g" href="{{ route('clients.show',$client) }}">{{ $client->name }}</a>@endif<a class="btn btn-g" href="{{ route('quotes.index', $client->exists ? ['client'=>$client->id] : []) }}">Preventivi</a></x-slot:actions></x-page-header>
<x-panel padded><form method="POST" enctype="multipart/form-data" action="{{ route('quotes.store') }}" data-quote-form x-data="{
    purpose: @js(old('purpose',$purpose)),
    currentPolicy: @js((string)old('current_policy_id',$source?->id??'')),
    branch: @js((string)old('insurance_branch_id',$source?->insurance_branch_id??'')),
    subbranch: @js((string)old('insurance_subbranch_id',$source?->insurance_subbranch_id??'')),
    subbranches: @js($branches->mapWithKeys(fn($b)=>[$b->id=>$b->subbranches->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->all()])),
    useCurrentClassification() {
        if (this.purpose === 'new') return;
        const option = this.$refs.currentPolicy.selectedOptions[0];
        if (!option?.value) { this.branch = ''; this.subbranch = ''; return; }
        this.branch = option.dataset.branch || '';
        this.$nextTick(() => { this.subbranch = option.dataset.subbranch || ''; });
    }
}">@csrf
@include('policies.client-picker')
<x-form-section title="Richiesta assicurativa"><div class="form-row">
<x-form-group label="Operazione da richiedere" name="purpose" required><select name="purpose" class="form-sel" x-model="purpose" @change="useCurrentClassification()" required>@foreach(\App\Models\InsuranceQuoteCase::PURPOSES as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><p class="u-text-meta">Sostituzione: nuovo numero. Aggiunta di coperture: stesso numero e premio aggiuntivo per il periodo concordato.</p></x-form-group>
<x-form-group label="Ramo" name="insurance_branch_id" required><select name="insurance_branch_id" class="form-sel" x-model="branch" @change="subbranch = ''" required><option value="">Seleziona il ramo</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-form-group>
<x-form-group label="Sottoramo" name="insurance_subbranch_id"><select name="insurance_subbranch_id" class="form-sel" x-model="subbranch"><option value="">Non specificato</option><template x-for="sub in (subbranches[branch] || [])" :key="sub.id"><option :value="sub.id" x-text="sub.name"></option></template></select></x-form-group>
<x-form-group label="Oggetto della richiesta" name="subject" required><input name="subject" class="form-in" value="{{ old('subject') }}" maxlength="255" required></x-form-group>
<x-form-group label="Nome del richiedente" name="requester_name"><input name="requester_name" class="form-in" value="{{ old('requester_name') }}" maxlength="255"><p class="u-text-meta">La persona che presenta la richiesta, anche per conto di un’azienda.</p></x-form-group>
</div></x-form-section>
@include('quotes.vehicle-fields')
<x-form-section title="Polizza attuale e coperture da richiedere"><p class="u-text-meta u-mb-md">Compila questa sezione se il cliente ha già una polizza o desidera altre coperture.</p><div class="form-row">
<x-form-group label="Polizza già presente nel gestionale" name="current_policy_id"><select name="current_policy_id" class="form-sel" x-ref="currentPolicy" x-model="currentPolicy" @change="useCurrentClassification()" :required="purpose !== 'new'"><option value="">Nessuna polizza selezionata</option>@foreach($currentPolicies as $policy)<option value="{{ $policy->id }}" data-branch="{{ $policy->insurance_branch_id }}" data-subbranch="{{ $policy->insurance_subbranch_id }}" data-vehicle="{{ $policy->vehicle_id }}" data-cu="{{ $policy->merit_class_cu }}" data-internal="{{ $policy->merit_class_internal }}">{{ $policy->number }} / {{ $policy->company }} / {{ $policy->category }}</option>@endforeach</select><p class="u-text-meta" x-show="purpose !== 'new'">Selezionando la polizza vengono ripresi ramo e sottoramo. Per le aggiunte devono restare quelli del contratto.</p></x-form-group>
<x-form-group label="Riferimenti di una polizza non registrata" name="current_policy_reference"><input name="current_policy_reference" class="form-in" value="{{ old('current_policy_reference') }}" maxlength="500" :disabled="currentPolicy !== ''"><p class="u-text-meta">Compagnia e numero, se la polizza non è presente nell’elenco.</p></x-form-group>
<x-form-group label="Coperture attuali dichiarate" name="current_coverages"><textarea name="current_coverages" class="form-ta" rows="3" maxlength="5000">{{ old('current_coverages') }}</textarea><p class="u-text-meta">Riporta le garanzie della polizza attuale dopo averle controllate.</p></x-form-group>
<x-form-group label="Coperture aggiuntive desiderate" name="additional_coverages"><textarea name="additional_coverages" class="form-ta" rows="3" maxlength="5000" :required="purpose === 'addition'">{{ old('additional_coverages') }}</textarea><p class="u-text-meta">Saranno riportate nella richiesta alla compagnia come coperture da quotare. Obbligatorie per un’aggiunta.</p></x-form-group>
</div></x-form-section>
<x-form-section title="Documenti per il preventivo">@include('quotes.document-fields',['readRegistration'=>true])<p class="u-text-meta u-mt-md">I documenti restano nel fascicolo del cliente. Potrai scegliere quali allegare a ciascuna email.</p></x-form-section>
<x-form-section title="Note interne"><x-form-group label="Note per gli operatori" name="notes"><textarea class="form-ta" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea><p class="u-text-meta">Non vengono inserite automaticamente nelle email.</p></x-form-group></x-form-section>
<div class="insurance-form-actions"><button class="btn btn-p">Apri pratica preventivi</button></div></form></x-panel></x-app-layout>
