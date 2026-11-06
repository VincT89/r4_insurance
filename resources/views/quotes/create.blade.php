<x-app-layout title="Nuovo preventivo">
    <x-page-header>
        <x-slot:title>Nuovo preventivo</x-slot:title>
        <x-slot:actions>
            @if($client->exists)<a class="btn btn-g" href="{{ route('clients.show', $client) }}">{{ $client->name }}</a>@endif
            <a class="btn btn-g" href="{{ route('quotes.index', $client->exists ? ['client'=>$client->id] : []) }}">Preventivi</a>
        </x-slot:actions>
    </x-page-header>
    @php
        $oldText = fn ($key, $default = '') => is_scalar($value = old($key, $default)) ? (string)$value : (string)$default;
        $vehicleDocuments = fn ($entry) => in_array('vehicle', array_merge($entry->document_categories ?? [], $entry->person_document_categories ?? [], $entry->company_document_categories ?? []), true);
        $subbranches = $branches->mapWithKeys(fn ($branch) => [$branch->id => $branch->subbranches->map(fn ($sub) => [
            'id'=>$sub->id, 'name'=>$sub->name, 'vehicle'=>$vehicleDocuments($sub),
        ])]);
        $sourceFields = ['current_policy_id','current_policy_reference','current_coverages','additional_coverages'];
        $showSource = $oldText('purpose',$purpose) !== 'new' || $source || collect($sourceFields)->contains(fn ($key) => filled($oldText($key)) || $errors->has($key));
        $documentErrors = collect($errors->keys())->contains(fn ($key) => $key === 'email' || \Illuminate\Support\Str::startsWith($key, ['identity_file','vehicle_file','files','document_ids']));
    @endphp
    <x-panel padded>
        <form method="POST" enctype="multipart/form-data" action="{{ route('quotes.store') }}" data-quote-form class="quote-entry"
            x-data="{purpose:@js($oldText('purpose',$purpose)), currentPolicy:@js($oldText('current_policy_id',$source?->id)),
                branch:@js($oldText('insurance_branch_id',$source?->insurance_branch_id)), subbranch:@js($oldText('insurance_subbranch_id',$source?->insurance_subbranch_id)), subbranches:@js($subbranches),
                useCurrentClassification() {
                    if (this.purpose === 'new') return;
                    const policy = this.$refs.currentPolicy.selectedOptions[0];
                    this.branch = policy?.dataset.branch || '';
                    this.$nextTick(() => { this.subbranch = policy?.dataset.subbranch || ''; });
                }
            }">
            @csrf
            @include('policies.client-picker')
            <x-form-section title="Richiesta di preventivo">
                <div class="form-row">
                    <x-form-group label="Operazione da richiedere" name="purpose" required>
                        <select name="purpose" class="form-sel" x-model="purpose" @change="if (purpose !== 'new') $refs.sourceFields.open = true; useCurrentClassification()" required>
                            @foreach(\App\Models\InsuranceQuoteCase::PURPOSES as $value=>$label)<option value="{{ $value }}" @selected($oldText('purpose',$purpose)===$value)>{{ $label }}</option>@endforeach
                        </select>
                    </x-form-group>
                    <x-form-group label="Nome del richiedente" name="requester_name">
                        <input name="requester_name" class="form-in" data-quote-requester value="{{ $oldText('requester_name',$client->name) }}" maxlength="255">
                        <p class="u-text-meta">Il campo riprende il nome del cliente. Puoi indicare un’altra persona.</p>
                    </x-form-group>
                </div>
                <details class="quote-disclosure" x-ref="sourceFields" @if($showSource) open @endif @invalid.capture="$el.open = true">
                    <summary x-text="purpose === 'new' ? 'Polizza attuale e coperture (facoltative)' : 'Polizza attuale e coperture richieste'">Polizza attuale e coperture</summary>
                    <div class="form-row">
                        <x-form-group label="Polizza già presente nel gestionale" name="current_policy_id">
                            <select name="current_policy_id" class="form-sel" x-ref="currentPolicy" x-model="currentPolicy" @change="useCurrentClassification()" :required="purpose !== 'new'">
                                <option value="">Seleziona una polizza</option>
                                @foreach($currentPolicies as $policy)
                                    <option value="{{ $policy->id }}" data-branch="{{ $policy->insurance_branch_id }}" data-subbranch="{{ $policy->insurance_subbranch_id }}" data-vehicle="{{ $policy->vehicle_id }}" data-cu="{{ $policy->merit_class_cu }}" data-internal="{{ $policy->merit_class_internal }}">{{ $policy->number }} / {{ $policy->company }} / {{ $policy->category }}</option>
                                @endforeach
                            </select>
                            <p class="u-text-meta" x-show="purpose !== 'new' && !currentPolicy">Seleziona la polizza per riprendere ramo, sottoramo e veicolo.</p>
                        </x-form-group>
                        <div x-show="purpose === 'new' && !currentPolicy">
                            <x-form-group label="Riferimenti di una polizza non registrata" name="current_policy_reference">
                                <input name="current_policy_reference" class="form-in" value="{{ $oldText('current_policy_reference') }}" maxlength="500" :disabled="purpose !== 'new' || currentPolicy !== ''" placeholder="Compagnia e numero">
                            </x-form-group>
                        </div>
                        <x-form-group label="Coperture attuali dichiarate" name="current_coverages">
                            <textarea name="current_coverages" class="form-ta" rows="2" maxlength="5000">{{ $oldText('current_coverages') }}</textarea>
                        </x-form-group>
                        <x-form-group label="Coperture aggiuntive desiderate" name="additional_coverages">
                            <textarea name="additional_coverages" class="form-ta" rows="2" maxlength="5000" :required="purpose === 'addition'">{{ $oldText('additional_coverages') }}</textarea>
                            <p class="u-text-meta" x-show="purpose === 'addition'">Indica le garanzie da aggiungere alla polizza.</p>
                        </x-form-group>
                    </div>
                </details>
                <div class="form-row">
                    <x-form-group label="Ramo" name="insurance_branch_id" required>
                        <select name="insurance_branch_id" class="form-sel" x-model="branch" @change="subbranch = ''" :disabled="purpose === 'addition'" required>
                            <option value="">Seleziona il ramo</option>
                            @foreach($branches as $entry)
                                <option value="{{ $entry->id }}" data-vehicle="{{ (int)$vehicleDocuments($entry) }}" data-sub-vehicles="{{ (int)$entry->subbranches->contains($vehicleDocuments) }}">{{ $entry->name }}</option>
                            @endforeach
                        </select>
                        <template x-if="purpose === 'addition'"><input type="hidden" name="insurance_branch_id" :value="branch"></template>
                    </x-form-group>
                    <div x-show="(subbranches[branch] || []).length || @js($errors->has('insurance_subbranch_id'))" x-cloak>
                        <x-form-group label="Sottoramo" name="insurance_subbranch_id">
                            <select name="insurance_subbranch_id" class="form-sel" x-model="subbranch" :disabled="purpose === 'addition' || !(subbranches[branch] || []).length">
                                <option value="">Non specificato</option>
                                <template x-for="sub in (subbranches[branch] || [])" :key="sub.id"><option :value="sub.id" :data-vehicle="sub.vehicle ? '1' : '0'" x-text="sub.name"></option></template>
                            </select>
                            <template x-if="purpose === 'addition'"><input type="hidden" name="insurance_subbranch_id" :value="subbranch"></template>
                        </x-form-group>
                    </div>
                </div>
                <p class="u-text-meta" x-show="purpose === 'addition'">Ramo e sottoramo vengono dalla polizza selezionata.</p>
                <x-form-group label="Oggetto della richiesta" name="subject" required><textarea name="subject" class="form-ta" rows="3" maxlength="255" required>{{ $oldText('subject') }}</textarea></x-form-group>
            </x-form-section>
            @include('quotes.vehicle-fields')
            <details class="quote-disclosure" @if($documentErrors) open @endif>
                <summary>Documenti per il preventivo (facoltativi)</summary>
                @include('quotes.document-fields', ['contextualVehicle'=>true])
            </details>
            <details class="quote-disclosure" @if(filled($oldText('notes')) || $errors->has('notes')) open @endif>
                <summary>Note interne</summary>
                <x-form-group label="Note per gli operatori" name="notes"><textarea class="form-ta" name="notes" rows="3" maxlength="5000">{{ $oldText('notes') }}</textarea></x-form-group>
            </details>
            <p class="u-text-meta">Dopo aver creato la richiesta potrai scegliere compagnie, agenzie e allegati per l’invio.</p>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.index', $client->exists ? ['client'=>$client->id] : []) }}">Annulla</a><button class="btn btn-p">Crea richiesta di preventivo</button></div>
        </form>
    </x-panel>
</x-app-layout>
