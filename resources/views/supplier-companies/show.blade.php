<x-app-layout :title="$company->name">
    <div class="page-back-row"><a href="{{ route('supplier-companies.index') }}" class="btn btn-g">Compagnie</a></div>
    <x-page-header>
        <x-slot:title>{{ $company->name }}</x-slot:title>
        <x-slot:actions>@can('update', $company)<a href="{{ route('supplier-companies.edit', $company) }}" class="btn btn-g">Modifica compagnia</a>@endcan</x-slot:actions>
    </x-page-header>
    <p class="client-identity-line">Compagnia {{ strtolower($company->status_label) }}</p>
    <x-panel title="Compagnia e contatti" padded>
        <dl class="client-registry-details">
            @foreach(['email' => 'Email', 'phone' => 'Telefono', 'reference_person' => 'Referente'] as $field => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $company->$field ?: 'Non indicato' }}</dd></div>
            @endforeach
        </dl>
        @if($company->notes)<section class="client-detail-section"><h2 class="sec-lbl">Note</h2><p class="u-whitespace-pre-wrap">{{ $company->notes }}</p></section>@endif
    </x-panel>
    <x-panel title="Agenzie e subagenzie">
        <x-slot:headerActions>@can('create', \App\Models\SupplierAgency::class)<a href="{{ route('suppliers.create', ['supplier_company_id' => $company->id]) }}" class="btn btn-p btn-sm">Nuova agenzia</a>@endcan</x-slot:headerActions>
        <div class="client-table-scroll" role="region" aria-label="Agenzie di {{ $company->name }}" tabindex="0">
            <table class="t-table">
                <thead><tr><th>Anagrafica</th><th>Tipo</th><th>Contatti</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    @forelse($agencies as $agency)
                        @foreach(collect([$agency])->concat($agency->subagencies) as $entry)
                            <tr>
                                <td class="name-col"><a href="{{ route('suppliers.show', $entry) }}">{{ $entry->name }}</a>@if($entry->parent_agency_id)<div class="u-text-meta">Agenzia principale: {{ $agency->name }}</div>@endif</td>
                                <td>{{ $entry->type_label }}</td>
                                <td><div>{{ $entry->email ?: 'Email non indicata' }}</div><div class="u-text-meta">{{ $entry->phone ?: $entry->mobile ?: 'Telefono non indicato' }}</div></td>
                                <td>{{ $entry->status_label }}</td>
                                <td>@can('create', \App\Models\SupplierAgency::class)@if(! $entry->parent_agency_id)<a href="{{ route('suppliers.create', ['parent_agency_id' => $entry->id]) }}" class="btn btn-g btn-sm" aria-label="Aggiungi subagenzia a {{ $entry->name }}">Nuova subagenzia</a>@endif@endcan</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="u-empty-state">Nessuna agenzia collegata a questa compagnia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $agencies->links() }}
    </x-panel>
    @can('system.admin')<x-audit-timeline :logs="$company->auditLogs()->with('user')->latest()->limit(30)->get()" />@endcan
    @can('delete', $company)
        <x-delete-modal action="{{ route('supplier-companies.destroy', $company) }}" title="Elimina compagnia" message="Puoi eliminare solo compagnie senza agenzie o polizze collegate. In alternativa puoi impostare lo stato in inattiva.">
            <button type="button" class="btn btn-g btn-danger-outline">Elimina compagnia</button>
        </x-delete-modal>
    @endcan
</x-app-layout>
