<x-app-layout title="Confronto anagrafiche">
    <x-page-header><x-slot:title>Unifica {{ $source->name }} in {{ $client->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.duplicates', $client) }}">Annulla</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <p class="u-mb-md">La scheda principale sarà {{ $client->name }} (#{{ $client->id }}). La scheda #{{ $source->id }} verrà archiviata e rimanderà a questa. I dati originali saranno conservati nello storico dell’unificazione.</p>
        <p class="u-mb-md">Saranno trasferiti polizze, veicoli, beni, referenti, documenti, contatti, consensi e relazioni. Le relazioni identiche e le appartenenze allo stesso nucleo saranno ricondotte a una sola; il collegamento tra queste due schede verrà assorbito.</p>
        <dl class="client-registry-details">@foreach(['policies' => 'Polizze', 'vehicles' => 'Veicoli', 'assets' => 'Beni', 'attachments' => 'Documenti e materiali', 'contacts' => 'Referenti', 'interactions' => 'Contatti', 'consents' => 'Consensi'] as $key => $label)<div><dt>{{ $label }} da trasferire</dt><dd>{{ count($preview['snapshot']['source'][$key]) }}</dd></div>@endforeach</dl>
        @if($preview['blockers'])
            <div role="alert" class="client-detail-section"><h2 class="sec-lbl">Verifiche necessarie</h2><ul>@foreach($preview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
        @else
            <form method="POST" action="{{ route('clients.merge.store', $client) }}">
                @csrf <input type="hidden" name="source" value="{{ $source->id }}"><input type="hidden" name="token" value="{{ $preview['token'] }}">
                @if($preview['fill'])<section class="client-detail-section"><h2 class="sec-lbl">Campi vuoti che saranno completati</h2><dl class="client-registry-details">@foreach($preview['fill'] as $entry)<div><dt>{{ $entry['label'] }}</dt><dd class="u-whitespace-pre-wrap">{{ $entry['source'] }}</dd></div>@endforeach</dl></section>@endif
                @foreach($preview['conflicts'] as $field => $entry)
                    <x-form-section :title="$entry['label'].': scegli il valore da conservare'">
                        @foreach(['target' => 'Scheda principale', 'source' => 'Scheda da unificare'] as $key => $label)
                            <label class="registry-merge-choice"><input type="radio" name="choices[{{ $field }}]" value="{{ $key }}" required @checked(old('choices.'.$field) === $key)><span>{{ $label }}<br><span class="u-whitespace-pre-wrap">{{ $entry[$key] }}</span></span></label>
                        @endforeach
                    </x-form-section>
                @endforeach
                <div class="client-detail-section"><label><input type="checkbox" name="confirm" value="1" required> Ho verificato che le due schede identificano lo stesso cliente e confermo il confronto.</label></div>
                <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.duplicates', $client) }}">Annulla</a><button class="btn btn-p" type="submit">Conferma unificazione</button></div>
            </form>
        @endif
    </x-panel>
</x-app-layout>
