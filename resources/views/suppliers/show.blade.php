<x-app-layout :title="$supplier->name">
    <div class="page-back-row"><a href="{{ route('suppliers.index') }}" class="btn btn-g">Agenzie</a></div>
    <x-page-header>
        <x-slot:title>{{ $supplier->name }}</x-slot:title>
        <x-slot:actions>
            @if($tab === 'commissions')
                @can('system.admin')<a class="btn btn-g" href="{{ route('commissions.index', ['agency'=>$supplier->id]) }}">Report provvigioni</a><a class="btn btn-p" href="{{ route('suppliers.commissions.create', $supplier) }}">Configura aliquote</a>@endcan
            @else
                @can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-p">Modifica agenzia</a>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>
    <p class="client-identity-line">{{ $supplier->type_label }} {{ strtolower($supplier->status_label) }}</p>
    @include('suppliers.section-navigation')
    @if($tab === 'commissions')
        @include('suppliers.commissions.list')
    @else
    <x-panel padded>
        <section class="client-detail-section">
            <h2 class="sec-lbl">Compagnia e collegamenti</h2>
            <dl class="client-registry-details">
                <div><dt>Compagnie</dt><dd>@forelse($supplier->companies as $company)<div><a href="{{ route('supplier-companies.show', $company) }}">{{ $company->name }}</a></div>@empty Da assegnare @endforelse</dd></div>
                @if($supplier->parentAgency)<div><dt>Agenzia principale</dt><dd><a href="{{ route('suppliers.show', $supplier->parentAgency) }}">{{ $supplier->parentAgency->name }}</a></dd></div>@endif
            </dl>
        </section>
        @foreach([
            'Dati dell’agenzia' => ['name' => 'Nome agenzia', 'company_name' => 'Ragione sociale', 'agency_code' => 'Codice agenzia', 'vat_number' => 'Partita IVA', 'tax_code' => 'Codice fiscale'],
            'Contatti' => ['email' => 'Email', 'pec' => 'PEC', 'phone' => 'Telefono', 'mobile' => 'Cellulare'],
            'Referente principale' => ['reference_person' => 'Nome referente', 'reference_role' => 'Ruolo referente', 'reference_email' => 'Email referente', 'reference_phone' => 'Telefono referente'],
            'Sede' => ['address' => 'Indirizzo', 'city' => 'Città', 'province' => 'Provincia', 'postal_code' => 'CAP', 'country' => 'Paese'],
        ] as $section => $fields)
            <section class="client-detail-section">
                <h2 class="sec-lbl">{{ $section }}</h2>
                <dl class="client-registry-details">
                    @foreach($fields as $field => $label)<div><dt>{{ $label }}</dt><dd>{{ $supplier->$field ?: 'Non indicato' }}</dd></div>@endforeach
                </dl>
            </section>
        @endforeach
        @if($supplier->notes)<section class="client-detail-section"><h2 class="sec-lbl">Note</h2><p class="u-whitespace-pre-wrap">{{ $supplier->notes }}</p></section>@endif
    </x-panel>
    @if(! $supplier->parent_agency_id && $supplier->companies->isNotEmpty())
        <x-panel title="Subagenzie">
            <x-slot:headerActions>@can('create', \App\Models\SupplierAgency::class)<a href="{{ route('suppliers.create', ['parent_agency_id' => $supplier->id]) }}" class="btn btn-p btn-sm">Nuova subagenzia</a>@endcan</x-slot:headerActions>
            <div class="client-table-scroll" role="region" aria-label="Subagenzie di {{ $supplier->name }}" tabindex="0">
                <table class="t-table">
                    <thead><tr><th>Subagenzia</th><th>Contatti</th><th>Referente</th><th>Stato</th></tr></thead>
                    <tbody>
                        @forelse($supplier->subagencies as $subagency)
                            <tr><td class="name-col"><a href="{{ route('suppliers.show', $subagency) }}">{{ $subagency->name }}</a></td><td>{{ $subagency->email ?: $subagency->phone ?: 'Non indicati' }}</td><td>{{ $subagency->reference_person ?: 'Non indicato' }}</td><td>{{ $subagency->status_label }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="u-empty-state">Nessuna subagenzia collegata.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif
    @can('system.admin')<x-audit-timeline :logs="$supplier->auditLogs()->with('user')->latest()->limit(30)->get()" />@endcan
    @can('delete', $supplier)
        <x-delete-modal action="{{ route('suppliers.destroy', $supplier) }}" title="Elimina agenzia" message="Puoi eliminare solo anagrafiche senza subagenzie o polizze collegate. Se vuoi conservarla, modifica lo stato in inattiva.">
            <button type="button" class="btn btn-g btn-danger-outline">Elimina agenzia</button>
        </x-delete-modal>
    @endcan
    @endif
</x-app-layout>
