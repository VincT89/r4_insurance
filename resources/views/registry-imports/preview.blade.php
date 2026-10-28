<x-app-layout title="Anteprima importazione">
    @php
        $newCount = $batch['preview']['counts']['new'];
        $newLabel = $newCount.' '.($newCount === 1 ? 'nuova voce' : 'nuove voci');
    @endphp
    <x-page-header><x-slot:title>{{ isset($batch['result']) ? 'Importazione completata' : 'Anteprima importazione' }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('registry-imports.index') }}">Importazioni</a><a class="btn btn-g" href="{{ route('registry-imports.report', $batch['id']) }}">Scarica controllo CSV</a></x-slot:actions></x-page-header>
    <div class="deadline-notice"><p><strong>{{ \App\Services\RegistryImportSchema::KINDS[$batch['kind']] }}</strong> · {{ $batch['filename'] }} · {{ $batch['sheets'][$batch['preview']['sheet']]['name'] }}</p>
        @if(isset($batch['result']))<p>Creazione completata: <strong>{{ $batch['result']['created'] }} {{ $batch['result']['created']===1 ? 'voce' : 'voci' }}</strong>. Righe escluse: {{ $batch['result']['excluded'] }}. Nessuna scheda esistente è stata modificata.</p>
        @else<p>Non è ancora stato salvato nulla nelle anagrafiche. Confermando verranno create solo le voci nuove; duplicati e righe da correggere resteranno esclusi.</p>@endif
    </div>
    <div class="overview-totals">@foreach($states as $state=>$label)<a class="overview-total {{ $state==='review' ? 'is-alert' : '' }}" href="{{ route('registry-imports.preview', ['id'=>$batch['id'], 'state'=>$state]) }}"><span>{{ $label }}</span><strong>{{ $batch['preview']['counts'][$state] }}</strong></a>@endforeach</div>
    <form class="client-search-form" method="GET" action="{{ route('registry-imports.preview', $batch['id']) }}">
        <x-form-group label="Cerca nel file" name="search"><input class="form-in" type="search" id="field-search" name="search" value="{{ request('search') }}" maxlength="100"></x-form-group>
        <x-form-group label="Esito del controllo" name="state"><select class="form-sel" id="field-state" name="state"><option value="">Tutte le righe</option>@foreach($states as $state=>$label)<option value="{{ $state }}" @selected(request('state')===$state)>{{ $label }}</option>@endforeach</select></x-form-group>
        <button class="btn btn-p" type="submit">Applica filtri</button><a class="btn btn-g" href="{{ route('registry-imports.preview', $batch['id']) }}">Azzera</a>
    </form>
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Controllo righe da importare" tabindex="0"><table class="t-table registry-import-table"><thead><tr><th>Riga</th><th>Nome e dati</th><th>Collegamenti</th><th>Esito</th><th>Dettaglio</th></tr></thead><tbody>
            @forelse($rows as $row)<tr>
                <td>{{ $row['row'] }}</td><td><strong>{{ $row['values']['name'] ?: 'Nome mancante' }}</strong><details class="registry-import-details"><summary>Dati della riga</summary><dl>@foreach($row['values'] as $field=>$value)@if($value!==null)<dt>{{ $fields[$field]['label'] }}</dt><dd>{{ $value }}</dd>@endif @endforeach</dl></details></td>
                <td>@forelse($row['links'] as $link)<div>{{ $link['name'] }}{{ $link['status']!=='active' ? ' (inattivo)' : '' }}</div>@empty<span class="u-text-meta">Nessun collegamento</span>@endforelse</td>
                <td><strong @if($row['state']==='review') class="status-alert" @endif>{{ \App\Services\RegistryImportSchema::ROW_STATES[$row['state']] }}</strong></td>
                <td>@forelse($row['messages'] as $message)<p>{{ $message }}</p>@empty<p>{{ isset($batch['result']) ? 'Voce creata.' : 'Pronta per la creazione.' }}</p>@endforelse</td>
            </tr>@empty<tr><td colspan="5" class="u-empty-state">Nessuna riga corrisponde ai filtri.</td></tr>@endforelse
        </tbody></table></div>
        {{ $rows->links() }}
    </x-panel>
    @unless(isset($batch['result']))
        <x-panel title="Conferma delle nuove voci" padded class="u-mt-md">
            <p>Da creare: <strong>{{ $newLabel }}</strong>. La conferma comprende tutte le righe nuove dell’anteprima, anche nelle altre pagine. I filtri servono solo al controllo. Per correggere le righe escluse, modifica il file e caricalo di nuovo.</p>
            <form method="POST" action="{{ route('registry-imports.prepare', $batch['id']) }}" class="u-mt-md">@csrf<input type="hidden" name="sheet" value="{{ $batch['preview']['sheet'] }}">@foreach($batch['preview']['mapping'] as $field=>$column)<input type="hidden" name="mapping[{{ $field }}]" value="{{ $column }}">@endforeach<div class="search-form-actions"><a class="btn btn-g" href="{{ route('registry-imports.mapping', $batch['id']) }}">Modifica associazioni</a><button class="btn btn-g" type="submit">Ricalcola anteprima</button></div></form>
            @error('confirm')<p class="invalid-feedback u-mt-md" role="alert">{{ $message }}</p>@enderror
            @error('hash')<p class="invalid-feedback u-mt-md" role="alert">{{ $message }}</p>@enderror
            @if($newCount)<form method="POST" action="{{ route('registry-imports.confirm', $batch['id']) }}" class="u-mt-md">@csrf<input type="hidden" name="hash" value="{{ $batch['preview']['hash'] }}"><label class="registry-import-confirm"><input type="checkbox" name="confirm" value="1" required><span>Ho controllato l’anteprima e confermo la creazione di {{ $newLabel }}.</span></label><div class="insurance-form-actions"><button type="submit" class="btn btn-p">Importa {{ $newLabel }}</button></div></form>
            @else<p class="u-mt-md">Nessuna voce nuova da importare.</p>@endif
        </x-panel>
    @endunless
</x-app-layout>
