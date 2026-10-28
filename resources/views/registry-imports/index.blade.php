<x-app-layout title="Importazioni CSV ed Excel">
    <x-page-header><x-slot:title>Importazioni CSV ed Excel</x-slot:title></x-page-header>
    @include('profile.partials.settings-navigation')
    <div class="deadline-notice"><p>Carica compagnie, agenzie, rami o sottorami. Prima del salvataggio vedrai l’anteprima con i duplicati e le righe da correggere. Le schede esistenti non vengono sovrascritte.</p></div>
    <x-panel padded>
        <form method="POST" action="{{ route('registry-imports.upload') }}" enctype="multipart/form-data">
            @csrf
            <x-form-section title="File da importare">
                <div class="form-row">
                    <x-form-group label="Cosa vuoi importare" name="kind" required><select class="form-sel" name="kind" id="field-kind" required>@foreach($kinds as $value => $label)<option value="{{ $value }}" @selected(old('kind', request('kind', 'company')) === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
                    <x-form-group label="File CSV o Excel (.xlsx)" name="file" required><input class="form-in" type="file" name="file" id="field-file" accept=".csv,.xlsx" required><p class="u-text-meta">Massimo 5 MB, 2.000 righe e 40 colonne. La prima riga non vuota contiene le intestazioni. Per Excel potrai scegliere il foglio.</p></x-form-group>
                </div>
                <details class="document-checklist-config" @if($errors->has('separator') || $errors->has('encoding')) open @endif>
                    <summary>Opzioni per file CSV</summary>
                    <div class="form-row u-mt-md">
                        <x-form-group label="Separatore" name="separator"><select class="form-sel" name="separator" id="field-separator">@foreach(['auto'=>'Rileva automaticamente','semicolon'=>'Punto e virgola (;)','comma'=>'Virgola (,)','tab'=>'Tabulazione'] as $value=>$label)<option value="{{ $value }}" @selected(old('separator', 'auto')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
                        <x-form-group label="Codifica del testo" name="encoding"><select class="form-sel" name="encoding" id="field-encoding"><option value="UTF-8" @selected(old('encoding', 'UTF-8')==='UTF-8')>UTF-8</option><option value="Windows-1252" @selected(old('encoding')==='Windows-1252')>Windows-1252 (CSV meno recenti)</option></select></x-form-group>
                    </div>
                </details>
            </x-form-section>
            <div class="insurance-form-actions"><button class="btn btn-p" type="submit">Carica e associa le colonne</button></div>
        </form>
    </x-panel>
    <x-panel title="Modelli e collegamenti" padded class="u-mt-md">
        <p>Scarica un modello vuoto oppure usa il tuo file. Compila i codici, le partite IVA, i telefoni e i CAP come testo per conservare gli zeri iniziali.</p>
        <div class="search-form-actions u-mt-md">@foreach($kinds as $kind=>$label)<a class="btn btn-g btn-sm" href="{{ route('registry-imports.template', $kind) }}">Modello {{ mb_strtolower($label) }} CSV</a>@endforeach</div>
        <p class="u-text-meta u-mt-md">Importa le compagnie prima delle agenzie e i rami prima dei sottorami. Per un’agenzia con più compagnie usa un’unica riga e separa i nomi con il carattere |. Ogni compagnia e ogni ramo indicati devono già esistere con un nome univoco. Lo stato vuoto significa Attivo; sono accettati Attivo e Inattivo.</p>
        <p class="u-text-meta u-mt-md">Questa importazione aggiunge solo anagrafiche: aliquote, checklist documentali e priorità dei preventivi restano configurabili nelle rispettive schede.</p>
    </x-panel>
    @if($recent)
        <x-panel title="I tuoi caricamenti delle ultime 24 ore" class="u-mt-md">
            <div class="client-table-scroll" role="region" aria-label="Caricamenti recenti" tabindex="0"><table class="t-table"><thead><tr><th>File</th><th>Anagrafiche</th><th>Esito</th><th>Azioni</th></tr></thead><tbody>
            @foreach($recent as $item)<tr><td>{{ $item['filename'] }}</td><td>{{ $kinds[$item['kind']] }}</td><td>{{ isset($item['result']) ? $item['result']['created'].($item['result']['created']===1 ? ' voce creata' : ' voci create') : 'In preparazione' }}</td><td><div class="search-form-actions"><a class="btn btn-g btn-sm" href="{{ route('registry-imports.preview', $item['id']) }}">Apri</a><form method="POST" action="{{ route('registry-imports.discard', $item['id']) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-g btn-sm">Rimuovi file</button></form></div></td></tr>@endforeach
            </tbody></table></div>
        </x-panel>
    @endif
    <p class="source-reference">I file di preparazione sono riservati all’admin che li ha caricati e scadono dopo 24 ore. Rimuovere un file non elimina le anagrafiche importate. <a href="{{ route('insurance-import.index') }}">Archivio dell’importazione precedente</a></p>
</x-app-layout>
