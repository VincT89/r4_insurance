<x-app-layout :title="$company->exists ? 'Modifica compagnia' : 'Nuova compagnia'">
    <x-page-header><x-slot:title>{{ $company->exists ? 'Modifica compagnia' : 'Nuova compagnia' }}</x-slot:title><x-slot:actions><a href="{{ $company->exists ? route('supplier-companies.show',$company) : route('supplier-companies.index') }}" class="btn btn-g">{{ $company->exists ? 'Scheda compagnia' : 'Compagnie' }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
    <form method="POST" action="{{ $company->exists ? route('supplier-companies.update',$company) : route('supplier-companies.store') }}">
        @csrf @if($company->exists) @method('PATCH') @endif
        <p class="u-text-meta u-mb-md">I campi con * sono obbligatori. Puoi completare gli altri dati in seguito.</p>
        @foreach([
            'Dati della compagnia'=>['name'=>['Nome compagnia','text',255],'company_name'=>['Ragione sociale','text',255],'vat_number'=>['Partita IVA','text',20],'tax_code'=>['Codice fiscale','text',20]],
            'Contatti'=>['email'=>['Email per i preventivi','email',255],'pec'=>['PEC','email',255],'phone'=>['Telefono','tel',50],'mobile'=>['Cellulare','tel',50]],
            'Referente principale'=>['reference_person'=>['Nome referente','text',255],'reference_role'=>['Ruolo referente','text',255],'reference_email'=>['Email referente','email',255],'reference_phone'=>['Telefono referente','tel',50]],
            'Sede'=>['address'=>['Indirizzo','text',255],'city'=>['Città','text',100],'province'=>['Provincia','text',5],'postal_code'=>['CAP','text',10],'country'=>['Paese','text',100]],
        ] as $section=>$fields)
            <x-form-section :title="$section"><div class="form-row">
            @foreach($fields as $field=>[$label,$type,$length])
                <x-form-group :label="$label" :name="$field" :required="$field==='name'">
                    <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" class="form-in @error($field) is-invalid @enderror" type="{{ $type }}" name="{{ $field }}" maxlength="{{ $length }}" value="{{ is_scalar(old($field,$company->$field)) ? old($field,$company->$field) : '' }}" @required($field==='name')>
                </x-form-group>
            @endforeach
            </div></x-form-section>
        @endforeach
        @include('suppliers.bank-fields',['entity'=>$company])
        <x-form-section title="Gestione">
            <x-form-group label="Stato compagnia" name="status" required><select id="field-status" class="form-sel" name="status" required><option value="active" @selected(old('status',$company->status)==='active')>Attiva</option><option value="inactive" @selected(old('status',$company->status)==='inactive')>Inattiva</option></select></x-form-group>
            <x-form-group label="Note" name="notes"><textarea id="field-notes" class="form-ta" name="notes" rows="4" maxlength="10000">{{ is_scalar(old('notes',$company->notes)) ? old('notes',$company->notes) : '' }}</textarea></x-form-group>
        </x-form-section>
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ $company->exists ? route('supplier-companies.show',$company) : route('supplier-companies.index') }}">Annulla</a><button class="btn btn-p">Salva compagnia</button></div>
    </form>
    </x-panel>
</x-app-layout>
