<x-app-layout title="Agenzie">
    <x-page-header>
        <x-slot:title>Agenzie</x-slot:title>
        <x-slot:actions>@can('create', \App\Models\SupplierAgency::class)<a href="{{ route('suppliers.create') }}" class="btn btn-p">Nuova agenzia</a>@endcan</x-slot:actions>
    </x-page-header>
    @include('suppliers._navigation')
    <form method="GET" action="{{ route('suppliers.index') }}" class="client-search-form">
        <x-form-group label="Cerca agenzia" name="search">
            <input id="field-search" type="search" name="search" value="{{ request('search') }}" class="form-in" maxlength="255" placeholder="Nome, codice agenzia, contatti, partita IVA o città">
        </x-form-group>
        <x-form-group label="Compagnia" name="supplier_company_id">
            <select id="field-supplier-company-id" name="supplier_company_id" class="form-sel">
                <option value="">Tutte le compagnie</option>
                @foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) request('supplier_company_id') === (string) $company->id)>{{ $company->name }}</option>@endforeach
            </select>
        </x-form-group>
        @if(request('unassigned'))<input type="hidden" name="unassigned" value="1"><p class="u-text-meta">Compagnia da assegnare</p>@endif
        <x-form-group label="Stato" name="status">
            <select id="field-status" name="status" class="form-sel">
                <option value="">Tutte le agenzie</option>
                <option value="active" @selected(request('status') === 'active')>Attive</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inattive</option>
            </select>
        </x-form-group>
        <button type="submit" class="btn btn-p btn-sm">Cerca</button>
        @if(request('search') || request('status') || request('supplier_company_id') || request('unassigned'))<a href="{{ route('suppliers.index') }}" class="btn btn-g btn-sm">Azzera filtri</a>@endif
    </form>
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Elenco agenzie fornitrici" tabindex="0">
            <table class="t-table">
                <thead><tr><th>Agenzia / subagenzia</th><th>Compagnie</th><th>Contatti</th><th>Referente</th><th>Città</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td class="name-col"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                                @if($supplier->company_name)<div class="u-text-meta">{{ $supplier->company_name }}</div>@endif
                                @if($supplier->agency_code)<div class="u-text-meta">Codice: {{ $supplier->agency_code }}</div>@endif
                                @if($supplier->parentAgency)<div class="u-text-meta">Subagenzia di <a href="{{ route('suppliers.show', $supplier->parentAgency) }}">{{ $supplier->parentAgency->name }}</a></div>@endif
                            </td>
                            <td>@forelse($supplier->companies as $company)<div><a href="{{ route('supplier-companies.show', $company) }}">{{ $company->name }}</a></div>@empty Da assegnare @endforelse</td>
                            <td><div>{{ $supplier->email ?: 'Email non indicata' }}</div><div class="u-text-meta">{{ $supplier->phone ?: $supplier->mobile ?: 'Telefono non indicato' }}</div></td>
                            <td>{{ $supplier->reference_person ?: 'Non indicato' }}</td>
                            <td>{{ $supplier->city ?: 'Non indicata' }}</td>
                            <td>{{ $supplier->status_label }}</td>
                            <td>@can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-g btn-sm" aria-label="Modifica agenzia {{ $supplier->name }}">Modifica</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="u-empty-state">Nessuna agenzia trovata.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $suppliers->links() }}
    </x-panel>
</x-app-layout>
