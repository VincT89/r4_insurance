<x-app-layout title="Confronto anagrafica Excel">
    <x-page-header><x-slot:title>{{ $entry->source_name }}</x-slot:title>
        <x-slot:actions><a class="btn btn-g" href="{{ route('insurance-import.index', ['section'=>'registry', 'kind'=>'client', 'result'=>'held']) }}">Torna alle anagrafiche in sospeso</a></x-slot:actions>
    </x-page-header>
    <x-panel padded>
        <h2 class="workspace-section-title">Confronto dei dati originali</h2>
        <p class="import-source-name u-mt-md">{{ $sourceFile }}</p>
        <p class="u-text-meta u-mt-md">Esito: {{ $entry->status_label }}. {{ $fiscalValid ? 'L’identificativo supera il controllo di formato e carattere di controllo; l’identità non è stata verificata presso l’anagrafe fiscale.' : 'L’identificativo non supera il controllo formale: occorre verificarlo sul documento originale.' }}</p>
        @if($entry->status === 'held')
            <p class="u-mt-md">{{ $entry->source === 'clients' ? 'Le differenze sono elencate per prime. Indica la riga corretta, oppure i singoli campi da conservare. Non è stata scelta automaticamente una versione.' : 'Questi sono i soli dati del contraente presenti nel file Gestionale. Le eventuali corrispondenze sotto richiedono conferma prima di collegare le schede.' }}</p>
        @endif
        <div class="client-table-scroll u-mt-md" role="region" aria-label="Confronto righe Excel" tabindex="0">
            <table class="t-table import-review-table"><thead><tr><th>Campo</th><th>Confronto</th>@foreach($rows as $number => $values)<th>Riga {{ $number }}</th>@endforeach</tr></thead>
                <tbody>@foreach($comparison as $field)<tr><th scope="row">{{ $field['field'] }}</th><td>{{ $field['different'] ? 'Diverso' : (count($rows) > 1 ? 'Coincidente' : 'Valore originale') }}</td>@foreach($field['values'] as $value)<td>{{ $value !== '' ? $value : 'Non indicato' }}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        </div>
        @if($entry->entity_id)
            <a class="btn btn-p u-mt-md" href="{{ route('clients.show', [$entry->entity_id, 'tab'=>'registry']) }}">Apri la scheda cliente</a>
        @endif
        @if($matches)
            <h2 class="workspace-section-title u-mt-md">Possibili schede corrispondenti</h2>
            <p class="u-text-meta">La somiglianza segnala un caso da controllare. Non prova che si tratti dello stesso cliente.</p>
            <div class="client-table-scroll u-mt-md" role="region" aria-label="Possibili corrispondenze" tabindex="0">
                <table class="t-table"><thead><tr><th>Cliente presente</th><th>Identificativi registrati o originali</th><th>Righe anagrafica</th><th>Motivo</th><th>Azioni</th></tr></thead>
                    <tbody>@foreach($matches as $match)<tr><td>{{ $match['name'] }}</td><td>{{ implode(' / ', $match['identifiers']) }}</td><td>{{ implode(', ', $match['source_rows']) ?: 'Scheda inserita nel gestionale' }}</td><td>{{ $match['reason'] }}</td><td><a class="btn btn-g btn-sm" href="{{ route('clients.show', [$match['id'], 'tab'=>'registry']) }}">Apri scheda</a></td></tr>@endforeach</tbody>
                </table>
            </div>
        @elseif($entry->source === 'policies' && $entry->status === 'held')
            <p class="u-mt-md">Nessuna corrispondenza individuata tra le schede già caricate. Questo non esclude un’anagrafica con dati molto diversi o ancora in sospeso.</p>
        @endif
    </x-panel>
</x-app-layout>
