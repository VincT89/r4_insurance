<x-form-section title="Gestione interna">
<div class="form-row">
    <x-form-group label="Stato cliente" name="status" required>
        <select id="field-status" name="status" class="form-sel @error('status') is-invalid @enderror" required>
            <option value="active" @selected(old('status', $client?->status ?? 'active') === 'active')>Attivo</option>
            <option value="inactive" @selected(old('status', $client?->status) === 'inactive')>Inattivo</option>
        </select>
    </x-form-group>
</div>
<div class="form-row full">
    <x-form-group label="Note interne" name="notes">
        <textarea id="field-notes" name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $client?->notes) }}</textarea>
    </x-form-group>
</div>

</x-form-section>
