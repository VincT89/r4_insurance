<x-app-layout :title="$client->name">
    @can('viewAny', \App\Models\Client::class)
        <div class="page-back-row"><a href="{{ route('clients.index') }}" class="btn btn-g">Anagrafica clienti</a></div>
    @endcan
    <x-page-header>
        <x-slot:title>{{ $client->name }}</x-slot:title>
        <x-slot:actions>
            @can('updateRegistry', $client)
                <a href="{{ route('clients.policies.create', [$client, 'category' => request('category'), 'subcategory' => request('subcategory')]) }}" class="btn btn-p">Nuova polizza</a>
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-g">Modifica anagrafica</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <p class="client-identity-line">{{ $client->client_type_label }} / {{ $client->status_label }}
        @if($client->tax_code) / Codice fiscale: {{ $client->tax_code }}@endif
        @if($client->vat_number) / Partita IVA: {{ $client->vat_number }}@endif
    </p>
    <nav class="client-section-nav client-main-nav" aria-label="Sezioni della scheda cliente">
        @foreach(['policies' => 'Polizze', 'registry' => 'Anagrafica', 'documents' => 'Documenti', 'deadlines' => 'Scadenze'] as $key => $label)
            <a href="{{ route('clients.show', [$client, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        @php
            $secondaryTabs = ['network' => 'Referenti e relazioni', 'vehicles' => 'Veicoli e targhe', 'assets' => 'Immobili e beni', 'history' => 'Contatti e note', 'consents' => 'Consensi'];
        @endphp
        <details class="client-nav-more" x-data @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false; $el.querySelector('summary').focus()">
            <summary @class(['is-current' => isset($secondaryTabs[$tab])])>{{ $secondaryTabs[$tab] ?? 'Altre sezioni' }}</summary>
            <div class="client-more-links">
                <a href="{{ route('clients.forms.index',$client) }}">Moduli e firme</a>
                <a href="{{ route('claims.index',['client'=>$client->id]) }}">Sinistri</a>
                @foreach($secondaryTabs as $key => $label)
                    <a href="{{ route('clients.show', [$client, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="{{ route('quotes.index',['client'=>$client->id]) }}">Preventivi</a>
                <a href="{{ route('titles.index',['client'=>$client->id]) }}">Rate e quietanze</a>
                <a href="{{ route('collections.index',['client'=>$client->id,'month'=>'']) }}">Incassi</a>
            </div>
        </details>
    </nav>
    @include('commissions.summary', ['commissionPolicy'=>null])
    @if($tab === 'registry')
        <x-panel padded>
            @include('clients.partials.registry-details')
            @if(auth()->user()->can('view', $client))
                @if($client->notes)<section class="client-detail-section"><h2 class="sec-lbl">Note interne</h2><p class="u-whitespace-pre-wrap">{{ $client->notes }}</p></section>@endif
            @endif
        </x-panel>
        @include('clients.partials.commercial-notes')
        <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.duplicates', $client) }}">Verifica possibili duplicati</a></div>
        @can('system.admin')
            @php
                $sourceIds = $client->mergedSources()->pluck('id');
                $mergeHistory = \App\Models\ClientMerge::whereIn('target_id', $sourceIds->push($client->id))->with('author')->latest()->get();
            @endphp
            @if($mergeHistory->isNotEmpty())
                <x-panel title="Storico unificazioni" padded>
                    @foreach($mergeHistory as $merge)
                        <details class="client-detail-section"><summary>Dati originali della scheda #{{ $merge->source_id }} / {{ $merge->created_at->format('d/m/Y H:i') }} / {{ $merge->author?->name ?? 'Utente non più disponibile' }}</summary>
                            @foreach(['source' => 'Scheda unificata', 'target' => 'Scheda principale prima dell’unificazione'] as $key => $title)
                                <h2 class="sec-lbl u-mt-md">{{ $title }}</h2><dl class="client-registry-details">@foreach(\App\Domain\Core\Actions\MergeClientsAction::FIELDS as $field => $label)@if(filled($merge->snapshot[$key]['client'][$field] ?? null))<div><dt>{{ $label }}</dt><dd class="u-whitespace-pre-wrap">{{ $merge->snapshot[$key]['client'][$field] }}</dd></div>@endif @endforeach</dl>
                            @endforeach
                        </details>
                    @endforeach
                </x-panel>
            @endif
            <x-audit-timeline :logs="\App\Models\AuditLog::where('auditable_type', $client->getMorphClass())->whereIn('auditable_id', $sourceIds)->with('user')->latest()->limit(30)->get()" />
        @endcan
        @can('delete', $client)
            <x-delete-modal action="{{ route('clients.destroy', $client) }}" title="Elimina cliente" message="Il cliente può essere eliminato solo se non contiene dati collegati o storico da conservare. In alternativa, impostalo come inattivo.">
                <button type="button" class="btn btn-g btn-danger-outline">Elimina cliente</button>
            </x-delete-modal>
        @endcan
    @elseif($tab === 'policies')
        @include('clients.partials.policies')
    @elseif($tab === 'vehicles')
        @include('clients.partials.vehicles')
    @elseif($tab === 'documents')
        @include('clients.partials.documents')
    @elseif($tab === 'network')
        @include('clients.partials.network')
    @elseif($tab === 'assets')
        @include('clients.partials.registry-records', ['section' => 'assets', 'records' => $assets])
    @elseif($tab === 'history')
        @include('clients.partials.registry-records', ['section' => 'interactions', 'records' => $interactions])
    @elseif($tab === 'consents')
        @include('clients.partials.registry-records', ['section' => 'consents', 'records' => $consents])
    @else
        @include('clients.partials.deadlines')
    @endif
</x-app-layout>
