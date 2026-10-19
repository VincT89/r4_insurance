<x-app-layout :title="($supplier->exists ? 'Modifica ' : 'Nuova ').strtolower($supplier->type_label)">
    <x-page-header>
        <x-slot:title>{{ ($supplier->exists ? 'Modifica ' : 'Nuova ').strtolower($supplier->type_label) }}</x-slot:title>
        <x-slot:actions><a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-g">{{ $supplier->exists ? 'Scheda agenzia' : 'Agenzie' }}</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        @php
            $selectedCompanies = array_map('strval', array_filter((array) old('supplier_company_ids', $selectedCompanies), 'is_scalar'));
            $selectedParent = is_scalar(old('parent_agency_id', $supplier->parent_agency_id)) ? (string) old('parent_agency_id', $supplier->parent_agency_id) : '';
        @endphp
        <form action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" method="POST"
            x-data="{ companyIds: {{ \Illuminate\Support\Js::from($selectedCompanies) }}, parentId: {{ \Illuminate\Support\Js::from($selectedParent) }} }">
            @csrf
            @if($supplier->exists) @method('PATCH') @endif
            <p class="u-text-meta u-mb-md">I campi con * sono obbligatori.</p>
            <x-form-section title="Compagnie e collegamenti">
            @if($companies->isEmpty())
                <p class="u-mb-md">Per aggiungere un’agenzia, @can('create', \App\Models\SupplierCompany::class)<a href="{{ route('supplier-companies.create') }}">crea prima la compagnia di appartenenza</a>@else occorre prima registrare la compagnia di appartenenza @endcan.</p>
            @endif
            <div class="form-row">
                <fieldset class="form-group choice-fieldset">
                    <legend class="form-lbl">Compagnie collegate *</legend>
                    <p class="u-text-meta u-mb-sm">Seleziona tutte le compagnie con cui lavora questa agenzia.</p>
                    <input type="hidden" name="supplier_company_ids" value="">
                    <div class="agency-company-options">
                        @foreach($companies as $company)
                            <label><input type="checkbox" name="supplier_company_ids[]" value="{{ $company->id }}" x-model="companyIds" @checked(in_array((string) $company->id, $selectedCompanies, true))> <span>{{ $company->name }}{{ $company->status === 'inactive' ? ' (inattiva)' : '' }}</span></label>
                        @endforeach
                    </div>
                    @error('supplier_company_ids')<p class="field-error">{{ $message }}</p>@enderror
                    @error('supplier_company_ids.*')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
                <x-form-group label="Agenzia principale (solo per subagenzie)" name="parent_agency_id">
                    <select id="field-parent-agency-id" name="parent_agency_id" class="form-sel" x-model="parentId" :disabled="!companyIds.length || {{ $hasSubagencies ? 'true' : 'false' }}" @disabled($hasSubagencies)>
                        <option value="">Nessuna: agenzia collegata alla compagnia</option>
                        @foreach($parentAgencies as $parent)
                            <option value="{{ $parent->id }}" @selected($selectedParent === (string) $parent->id)
                                :disabled="!companyIds.every(id => {{ \Illuminate\Support\Js::from($parent->companies->modelKeys()) }}.map(String).includes(id))">{{ $parent->name }} ({{ $parent->companies->pluck('name')->join(', ') }})</option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>
            @if($hasSubagencies)<p class="u-text-meta u-mb-md">Questa agenzia ha subagenzie collegate: puoi aggiungere compagnie; per rimuovere quelle usate dalle subagenzie, riassegna prima i relativi collegamenti.</p>@endif
            </x-form-section>
            @foreach([
                'Dati dell’agenzia' => ['name' => ['Nome agenzia', 'text', 255], 'company_name' => ['Ragione sociale', 'text', 255], 'agency_code' => ['Codice agenzia', 'text', 50], 'vat_number' => ['Partita IVA', 'text', 20], 'tax_code' => ['Codice fiscale', 'text', 20]],
                'Contatti' => ['email' => ['Email', 'email', 255], 'pec' => ['PEC', 'email', 255], 'phone' => ['Telefono', 'tel', 50], 'mobile' => ['Cellulare', 'tel', 50]],
                'Referente principale' => ['reference_person' => ['Nome referente', 'text', 255], 'reference_role' => ['Ruolo referente', 'text', 255], 'reference_email' => ['Email referente', 'email', 255], 'reference_phone' => ['Telefono referente', 'tel', 50]],
                'Sede' => ['address' => ['Indirizzo', 'text', 255], 'city' => ['Città', 'text', 100], 'province' => ['Provincia', 'text', 5], 'postal_code' => ['CAP', 'text', 10], 'country' => ['Paese', 'text', 100]],
            ] as $section => $fields)
                <x-form-section :title="$section">
                <div class="form-row">
                    @foreach($fields as $field => [$label, $type, $length])
                        <x-form-group :label="$label" :name="$field" :required="$field === 'name'">
                            <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" type="{{ $type }}" name="{{ $field }}" value="{{ is_scalar(old($field, $supplier->$field)) ? old($field, $supplier->$field) : '' }}"
                                class="form-in @error($field) is-invalid @enderror" maxlength="{{ $length }}" @required($field === 'name')>
                        </x-form-group>
                    @endforeach
                </div>
                </x-form-section>
            @endforeach
            <x-form-section title="Gestione">
            <div class="form-row">
                <x-form-group label="Stato agenzia" name="status" required>
                    <select id="field-status" name="status" class="form-sel" required>
                        <option value="active" @selected(old('status', $supplier->status) === 'active')>Attiva</option>
                        <option value="inactive" @selected(old('status', $supplier->status) === 'inactive')>Inattiva</option>
                    </select>
                </x-form-group>
            </div>
            <x-form-group label="Note" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="4" maxlength="10000">{{ is_scalar(old('notes', $supplier->notes)) ? old('notes', $supplier->notes) : '' }}</textarea></x-form-group>
            </x-form-section>
            <div class="insurance-form-actions">
                <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva agenzia</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
