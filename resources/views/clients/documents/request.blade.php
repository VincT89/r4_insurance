<x-app-layout title="Documenti da ricevere">
    <x-page-header><x-slot:title>Documenti da ricevere</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <p class="u-text-meta u-mb-md">Registra il lavoro da seguire internamente. L’aggiunta alla lista non invia messaggi al cliente.</p>
    <x-panel title="Aggiungi un documento" padded>
        <form method="POST" action="{{ route('clients.document-requests.store', $client) }}">@csrf
            <div class="form-row">
                <x-form-group label="Documento richiesto" name="label" required><input class="form-in" id="field-label" name="label" maxlength="255" value="{{ old('label') }}" required></x-form-group>
                <x-form-group label="Categoria" name="document_category" required><select class="form-sel" id="field-document-category" name="document_category" required><option value="">Seleziona</option>@foreach(\App\Models\Attachment::DOCUMENT_CATEGORIES as $value=>$name)<option value="{{ $value }}" @selected(old('document_category')===$value)>{{ $name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Polizza collegata" name="insurance_policy_id"><select class="form-sel" id="field-insurance-policy-id" name="insurance_policy_id"><option value="">Anagrafica cliente</option>@foreach($policies as $policy)<option value="{{ $policy->id }}" @selected((string)old('insurance_policy_id')===(string)$policy->id)>{{ $policy->category }} / {{ $policy->number }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Da ricevere entro" name="due_on"><input class="form-in" id="field-due-on" name="due_on" type="date" value="{{ old('due_on') }}"></x-form-group>
            </div>
            <x-form-group label="Note" name="notes"><textarea class="form-ta" id="field-notes" name="notes" maxlength="2000" rows="2">{{ old('notes') }}</textarea></x-form-group>
            <div class="insurance-form-actions"><button class="btn btn-p" type="submit">Aggiungi alla lista</button></div>
        </form>
    </x-panel>
    <x-panel title="Usa una checklist configurata" padded>
        @php($subbranches = $branches->mapWithKeys(fn($branch)=>[(string)$branch->id=>$branch->subbranches->map(fn($s)=>['id'=>(string)$s->id,'name'=>$s->name])->all()]))
        <form method="POST" action="{{ route('clients.document-requests.apply', $client) }}" x-data="{ branch: @js((string)old('insurance_branch_id', '')), subbranch: @js((string)old('insurance_subbranch_id', '')), children: @js($subbranches) }">@csrf
            <div class="form-row">
                <x-form-group label="Ramo" name="insurance_branch_id" required><select class="form-sel" id="field-insurance-branch-id" name="insurance_branch_id" x-model="branch" @change="subbranch = ''" required><option value="">Seleziona</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Sottoramo" name="insurance_subbranch_id"><select class="form-sel" id="field-insurance-subbranch-id" name="insurance_subbranch_id" x-model="subbranch"><option value="">Solo checklist del ramo</option><template x-for="item in (children[branch] || [])" :key="item.id"><option :value="item.id" x-text="item.name" :selected="item.id === subbranch"></option></template></select></x-form-group>
                <x-form-group label="Polizza per la checklist" name="checklist_policy" for="checklist-policy"><select class="form-sel" id="checklist-policy" name="insurance_policy_id"><option value="">Anagrafica cliente</option>@foreach($policies as $policy)<option value="{{ $policy->id }}" @selected((string)old('insurance_policy_id')===(string)$policy->id)>{{ $policy->category }} / {{ $policy->number }}</option>@endforeach</select>@error('insurance_policy_id')<p class="invalid-feedback">{{ $message }}</p>@enderror</x-form-group>
                <x-form-group label="Termine per la checklist" name="checklist_due" for="checklist-due"><input class="form-in" id="checklist-due" type="date" name="due_on" value="{{ old('due_on') }}">@error('due_on')<p class="invalid-feedback">{{ $message }}</p>@enderror</x-form-group>
            </div>
            <p class="u-text-meta">Le voci già presenti non vengono duplicate. I documenti caricati in precedenza non sono assegnati automaticamente a nuove richieste.</p>
            <div class="insurance-form-actions">@can('create', \App\Models\SupplierCompany::class)<a class="btn btn-g" href="{{ route('insurance-catalog.index') }}">Configura checklist</a>@endcan<button class="btn btn-p" type="submit">Applica checklist</button></div>
        </form>
    </x-panel>
</x-app-layout>
