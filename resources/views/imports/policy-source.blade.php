<x-app-layout :title="'Origine polizza '.$entry->number">
    <x-page-header><x-slot:title>Origine polizza {{ $entry->number }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('insurance-import.index', ['section'=>'policies']) }}">Esito polizze</a>@if($entry->policy)<a class="btn btn-p" href="{{ route('clients.policies.show', [$entry->policy->client_id, $entry->policy]) }}">Apri polizza</a>@endif</x-slot:actions></x-page-header>
    <x-panel padded><p>{{ $entry->source_payload['source_file'] ?? 'File Gestionale' }}, foglio Dati. {{ $entry->status_label }}.</p>
        @if($entry->issues)<ul>@foreach($entry->issue_labels as $label)<li>{{ $label }}</li>@endforeach</ul>@endif
        <p class="u-text-meta">Questi valori conservano la fonte al momento dell’importazione. Archivio e OLD non sono stati tradotti in uno stato della copertura. Rate, rinnovi, consensi e allegati non vengono creati dai riferimenti testuali.</p>
    </x-panel>
    @if($resolution = $entry->auditLogs->first())
        <x-panel title="Accorpamento delle righe" padded>
            <p>{{ $resolution->description }}</p>
            <p class="u-text-meta">Registrato il {{ $resolution->created_at->format('d/m/Y H:i') }} da {{ $resolution->user?->name ?? 'Operatore non più disponibile' }}. Le etichette Archivio e OLD sono conservate nelle singole righe; le differenze nei dati delle rate non generano movimenti o nuove polizze.</p>
        </x-panel>
    @endif
    <x-panel title="Confronto delle righe originali"><div class="client-table-scroll" role="region" aria-label="Dati polizza originali" tabindex="0"><table class="t-table"><thead><tr><th>Campo</th>@foreach($entry->source_payload['rows'] as $row)<th>Riga {{ $row['row'] }}</th>@endforeach</tr></thead><tbody>
        @foreach($fields as $field)<tr><th scope="row">{{ $field }}</th>@foreach($entry->source_payload['rows'] as $row)<td>{{ ($row['values'][$field] ?? '') !== '' ? $row['values'][$field] : 'Non indicato' }}</td>@endforeach</tr>@endforeach
    </tbody></table></div></x-panel>
</x-app-layout>
