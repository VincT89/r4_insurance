<x-app-layout title="Compagnie">
    <x-page-header>
        <x-slot:title>Compagnie</x-slot:title>
        <x-slot:actions>@can('create', \App\Models\SupplierCompany::class)<a href="{{ route('supplier-companies.create') }}" class="btn btn-p">Nuova compagnia</a>@endcan</x-slot:actions>
    </x-page-header>
    @include('suppliers._navigation')
    @if($unassignedCount)
        <p class="u-mb-md"><a href="{{ route('suppliers.index', ['unassigned' => 1]) }}">Anagrafiche con compagnia da assegnare: {{ $unassignedCount }}</a></p>
    @endif
    <form method="GET" action="{{ route('supplier-companies.index') }}" class="client-search-form">
        <x-form-group label="Cerca compagnia" name="search"><input id="field-search" type="search" name="search" value="{{ request('search') }}" class="form-in" maxlength="255" placeholder="Nome, email o referente"></x-form-group>
        <x-form-group label="Stato" name="status"><select class="form-sel" id="field-status" name="status"><option value="">Tutti</option><option value="active" @selected(request('status')==='active')>Attive</option><option value="inactive" @selected(request('status')==='inactive')>Inattive</option></select></x-form-group>
        <button type="submit" class="btn btn-p btn-sm">Cerca</button>
        @if(request('search') || request('status'))<a href="{{ route('supplier-companies.index') }}" class="btn btn-g btn-sm">Azzera filtri</a>@endif
    </form>
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Compagnie fornitrici" tabindex="0">
            <table class="t-table">
                <thead><tr><th>Compagnia</th><th>Contatti</th><th>Referente</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td class="name-col"><a href="{{ route('supplier-companies.show', $company) }}">{{ $company->name }}</a></td>
                            <td><div>{{ $company->email ?: 'Email non indicata' }}</div><div class="u-text-meta">{{ $company->phone ?: 'Telefono non indicato' }}</div></td>
                            <td>{{ $company->reference_person ?: 'Non indicato' }}</td><td>{{ $company->status_label }}</td>
                            <td><a href="{{ route('supplier-companies.show', $company) }}" class="btn btn-g btn-sm" aria-label="Apri compagnia {{ $company->name }}">Apri</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="u-empty-state">Nessuna compagnia trovata.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    </x-panel>
</x-app-layout>
