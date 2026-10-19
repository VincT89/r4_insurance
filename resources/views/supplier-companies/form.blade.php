<x-app-layout :title="$company->exists ? 'Modifica compagnia' : 'Nuova compagnia'">
    <x-page-header>
        <x-slot:title>{{ $company->exists ? 'Modifica compagnia' : 'Nuova compagnia' }}</x-slot:title>
        <x-slot:actions><a href="{{ $company->exists ? route('supplier-companies.show', $company) : route('supplier-companies.index') }}" class="btn btn-g">{{ $company->exists ? 'Scheda compagnia' : 'Compagnie' }}</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        <form action="{{ $company->exists ? route('supplier-companies.update', $company) : route('supplier-companies.store') }}" method="POST">
            @csrf
            @if($company->exists) @method('PATCH') @endif
            <p class="u-text-meta u-mb-md">I campi con * sono obbligatori.</p>
            <x-form-section title="Compagnia e contatti">
            <div class="form-row">
                @foreach(['name' => ['Nome compagnia', 'text', 255], 'email' => ['Email', 'email', 255], 'phone' => ['Telefono', 'tel', 50], 'reference_person' => ['Referente', 'text', 255]] as $field => [$label, $type, $length])
                    <x-form-group :label="$label" :name="$field" :required="$field === 'name'">
                        <input id="field-{{ \Illuminate\Support\Str::slug($field) }}" type="{{ $type }}" name="{{ $field }}" value="{{ is_scalar(old($field, $company->$field)) ? old($field, $company->$field) : '' }}" class="form-in @error($field) is-invalid @enderror" maxlength="{{ $length }}" @required($field === 'name')>
                    </x-form-group>
                @endforeach
                <x-form-group label="Stato compagnia" name="status" required>
                    <select id="field-status" name="status" class="form-sel" required>
                        <option value="active" @selected(old('status', $company->status) === 'active')>Attiva</option>
                        <option value="inactive" @selected(old('status', $company->status) === 'inactive')>Inattiva</option>
                    </select>
                </x-form-group>
            </div>
            <input type="hidden" name="quote_priority" value="0"><label class="u-flex-center u-gap-sm"><input type="checkbox" name="quote_priority" value="1" @checked(old('quote_priority',$company->quote_priority))> Compagnia prioritaria della nostra agenzia per le richieste di preventivo</label>
            </x-form-section><x-form-section title="Note"><x-form-group label="Note" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="4" maxlength="10000">{{ is_scalar(old('notes', $company->notes)) ? old('notes', $company->notes) : '' }}</textarea></x-form-group></x-form-section>
            <div class="insurance-form-actions">
                <a href="{{ $company->exists ? route('supplier-companies.show', $company) : route('supplier-companies.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva compagnia</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
