@php($documentation = app(\App\Services\InsuranceDocumentation::class)->summary($client, $policy))
<section id="policy-documentation" aria-label="Documentazione della polizza">
    <x-panel title="Documenti richiesti e firme">
        <x-slot:headerActions>@can('updateRegistry', $client)<a class="btn btn-p btn-sm" href="{{ route('clients.document-requests.create', [$client, 'policy'=>$policy->id]) }}">Checklist e richieste</a>@endcan</x-slot:headerActions>
        <div class="document-checklist-intro">
            <p>{{ $client->client_type === 'company' ? 'Azienda' : 'Privato' }} · {{ $policy->category }}{{ $policy->subcategory ? ' / '.$policy->subcategory : '' }}</p>
            @if(!$documentation['categories'])<p class="u-text-meta">Checklist per questo ramo e tipo di cliente non ancora configurata. Le richieste manuali restano disponibili.</p>
            @elseif($documentation['missing'])<p>Documenti configurati da richiedere: {{ collect($documentation['missing'])->map(fn($category)=>\App\Models\Attachment::DOCUMENT_CATEGORIES[$category])->join(', ') }}.</p>
            @else<p class="u-text-meta">Le voci configurate sono presenti nelle richieste. Controlla lo stato dei file e le firme prima di completare la lavorazione.</p>@endif
        </div>
        @include('clients.documents.requests-table', ['documentRequests'=>$documentation['requests']])
        <div class="document-forms-grid">
            @include('insurance-forms.status', ['formPolicy'=>null])
            @include('insurance-forms.status', ['formPolicy'=>$policy])
        </div>
        <div class="panel-footer-link"><a href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">Documenti e richieste dell’anagrafica cliente</a></div>
    </x-panel>
</section>
