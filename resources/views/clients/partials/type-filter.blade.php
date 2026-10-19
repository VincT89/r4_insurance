<x-form-group label="Tipo cliente" name="client_type">
    <select id="field-client-type" name="client_type" class="form-sel">
        <option value="">Tutti</option>
        @foreach(['person' => 'Persone fisiche', 'company' => 'Aziende', 'unclassified' => 'Da classificare'] as $value => $label)
            <option value="{{ $value }}" @selected(request('client_type') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</x-form-group>
