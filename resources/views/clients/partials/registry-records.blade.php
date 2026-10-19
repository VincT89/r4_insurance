@php
    $titles = ['contacts' => 'Altri referenti', 'assets' => 'Immobili e altri beni', 'interactions' => 'Storico contatti e note', 'consents' => 'Consensi documentati'];
@endphp
<x-panel :title="$titles[$section]" padded>
    <x-slot:headerActions>@can('updateRegistry', $client)<a class="btn btn-p btn-sm" href="{{ route('clients.records.create', [$client, $section]) }}">Aggiungi {{ mb_strtolower(\App\Support\ClientRegistryFields::SECTIONS[$section]) }}</a>@endcan</x-slot:headerActions>
    @if($section === 'contacts')<p class="u-text-meta u-mb-md">Il referente principale si modifica nella scheda Anagrafica. Qui puoi aggiungere gli altri referenti.</p>@endif
    @if($section === 'consents')<p class="u-text-meta u-mb-md">Storico delle dichiarazioni documentate, dalla più recente. Verifica finalità, ambito ed eventuali revoche. L’invio elettronico dei documenti non autorizza comunicazioni promozionali.</p>@endif
    @forelse($records as $record)
        <section class="client-detail-section" id="{{ $section }}-{{ $record->id }}">
            <h2 class="sec-lbl">{{ $record->name ?? $record->subject ?? $record->purpose }}</h2>
            <dl class="client-registry-details">
                @foreach(\App\Support\ClientRegistryFields::for($section) as $field => [$label, $type, $required, $limit])
                    @if(filled($record->$field) && !in_array($field, ['name', 'subject', 'purpose']))
                        <div><dt>{{ $label }}</dt><dd class="u-whitespace-pre-wrap">{{ $type === 'select' ? ($limit[$record->$field] ?? $record->$field) : ($type === 'datetime-local' ? $record->$field->format('d/m/Y H:i') : $record->$field) }}</dd></div>
                    @endif
                @endforeach
                @if($section === 'assets')
                    <div><dt>Polizze collegate</dt><dd>@forelse($record->policies as $linkedPolicy)<a href="{{ route('clients.policies.show', [$client, $linkedPolicy]) }}">{{ $linkedPolicy->number }}</a>{{ $loop->last ? '' : ', ' }}@empty Nessuna @endforelse</dd></div>
                @endif
                @if($section === 'consents')
                    @if($record->insurancePolicy)<div><dt>Polizza interessata</dt><dd><a href="{{ route('clients.policies.show', [$client, $record->insurancePolicy]) }}">{{ $record->insurancePolicy->company }} / {{ $record->insurancePolicy->number }}</a></dd></div>@endif
                    @if($record->attachment)<div><dt>Prova</dt><dd><a href="{{ route('attachments.download', $record->attachment) }}">{{ $record->attachment->original_name }}</a></dd></div>@endif
                @endif
                @if(in_array($section, ['consents', 'interactions']))<div><dt>Registrato da</dt><dd>{{ $record->author?->name ?? 'Utente non più disponibile' }} / {{ $record->created_at->format('d/m/Y H:i') }}</dd></div>@endif
            </dl>
            @if($section !== 'consents')
                @can('updateRegistry', $client)
                    <div class="insurance-form-actions">
                        <a class="btn btn-g btn-sm" href="{{ route('clients.records.edit', [$client, $section, $record->id]) }}">Modifica</a>
                        @can('system.admin')<x-delete-modal :action="route('clients.records.destroy', [$client, $section, $record->id])" title="Elimina registrazione" message="Confermi la rimozione di questa registrazione? I beni collegati a una polizza devono essere conservati."><button type="button" class="btn btn-g btn-sm btn-danger-outline">Elimina</button></x-delete-modal>@endcan
                    </div>
                @endcan
            @endif
        </section>
    @empty
        <p>Nessuna registrazione presente.</p>
    @endforelse
    @if($records instanceof \Illuminate\Contracts\Pagination\Paginator){{ $records->links() }}@endif
</x-panel>
