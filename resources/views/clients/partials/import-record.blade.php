@if($entry = $client->importEntry)
    @if($entry->deferred_data)<details class="client-detail-section"><summary>Altri riferimenti</summary><dl class="client-registry-details">@foreach($entry->deferred_data as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></details>@endif
    @can('system.admin')<div class="source-reference"><a href="{{ route('insurance-import.compare',$entry) }}">Origine dati</a></div>@endcan
@endif
@can('system.admin')
    @foreach($client->linkedImportEntries as $linkedEntry)<div class="source-reference"><a href="{{ route('insurance-import.compare', $linkedEntry) }}">Origine dati collegati</a></div>@endforeach
@endcan
