<x-app-layout title="Associa le colonne">
    <x-page-header><x-slot:title>Associa le colonne</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('registry-imports.index') }}">Importazioni</a></x-slot:actions></x-page-header>
    <div class="deadline-notice"><p><strong>{{ \App\Services\RegistryImportSchema::KINDS[$batch['kind']] }}</strong> · {{ $batch['filename'] }}</p><p>Controlla le associazioni proposte. I campi facoltativi possono restare su «Non importare».</p></div>
    <form method="GET" action="{{ route('registry-imports.mapping', $batch['id']) }}" class="client-search-form">
        <x-form-group label="Foglio da leggere" name="sheet"><select class="form-sel" name="sheet" id="field-sheet">@foreach($batch['sheets'] as $index=>$source)<option value="{{ $index }}" @selected($sheet===$index)>{{ $source['name'] }} ({{ max(0, count($source['rows'])-1) }} righe)</option>@endforeach</select></x-form-group><button class="btn btn-g" type="submit">Mostra foglio</button>
    </form>
    <x-panel padded>
        <form method="POST" action="{{ route('registry-imports.prepare', $batch['id']) }}">
            @csrf <input type="hidden" name="sheet" value="{{ $sheet }}">
            <x-form-section title="Colonne del file e campi del gestionale">
                @error('mapping')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
                @error('sheet')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
                <div class="registry-import-mapping">
                    @foreach($fields as $field=>$definition)
                        <x-form-group :label="$definition['label']" :name="'mapping.'.$field" :required="$definition['required'] ?? false" :for="'map-'.$field">
                            <select class="form-sel" name="mapping[{{ $field }}]" id="map-{{ $field }}" @required($definition['required'] ?? false)>
                                <option value="">{{ ($definition['required'] ?? false) ? 'Scegli la colonna' : 'Non importare' }}</option>
                                @foreach($headers as $column=>$header)@if($header !== '')<option value="{{ $column }}" @selected((string) old('mapping.'.$field, $mapping[$field] ?? '')===(string)$column)>Colonna {{ $column + 1 }}: {{ \Illuminate\Support\Str::limit($header, 90) }}</option>@endif @endforeach
                            </select>
                            @if($field==='companies')<p class="u-text-meta">Una sola riga per agenzia. Più compagnie nella stessa cella, separate da |.</p>@endif
                            @if($field==='branch')<p class="u-text-meta">Il nome del ramo deve corrispondere a una voce già presente.</p>@endif
                            @if($field==='status')<p class="u-text-meta">Se vuoto o non associato, le nuove voci saranno attive.</p>@endif
                        </x-form-group>
                    @endforeach
                </div>
            </x-form-section>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('registry-imports.index') }}">Torna alle importazioni</a><button class="btn btn-p" type="submit">Controlla anteprima</button></div>
        </form>
    </x-panel>
    <x-panel title="Prime righe del file" class="u-mt-md">
        <div class="client-table-scroll" role="region" aria-label="Prime righe del foglio selezionato" tabindex="0"><table class="t-table"><thead><tr><th>Riga</th>@foreach($headers as $column=>$header)<th>Colonna {{ $column+1 }}: {{ \Illuminate\Support\Str::limit($header, 90) }}</th>@endforeach</tr></thead><tbody>
            @foreach(array_slice($batch['sheets'][$sheet]['rows'], 1, 3) as $source)<tr><td>{{ $source['number'] }}</td>@foreach($headers as $column=>$header)<td>{{ \Illuminate\Support\Str::limit($source['cells'][$column]['value'] ?? '', 120) }}</td>@endforeach</tr>@endforeach
        </tbody></table></div>
    </x-panel>
</x-app-layout>
