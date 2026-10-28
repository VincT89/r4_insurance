<x-app-layout title="Gruppi preventivi">
    <x-page-header><x-slot:title>Gruppi preventivi</x-slot:title><x-slot:actions><a class="btn btn-p" href="{{ route('quote-groups.create') }}">Nuovo gruppo</a></x-slot:actions></x-page-header>
    @include('profile.partials.settings-navigation')
    <form method="GET" class="client-search-form">
        <x-form-group label="Nome gruppo" name="search"><input id="field-search" class="form-in" name="search" value="{{ $filters['search'] ?? '' }}"></x-form-group>
        <x-form-group label="Tipo di assicurazione" name="branch"><select id="field-branch" class="form-sel" name="branch"><option value="">Tutti i rami</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)($filters['branch'] ?? '')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Classe CU" name="cu"><select id="field-cu" class="form-sel" name="cu"><option value="">Tutte</option>@for($cu=1;$cu<=18;$cu++)<option value="{{ $cu }}" @selected((string)($filters['cu'] ?? '')===(string)$cu)>{{ $cu }}</option>@endfor</select></x-form-group>
        <x-form-group label="Stato" name="status"><select id="field-status" class="form-sel" name="status"><option value="">Tutti</option><option value="active" @selected(($filters['status'] ?? '')==='active')>Attivi</option><option value="inactive" @selected(($filters['status'] ?? '')==='inactive')>Inattivi</option></select></x-form-group>
        <div class="insurance-filter-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('quote-groups.index') }}">Azzera</a></div>
    </form>
    <x-panel title="Gruppi di compagnie per le richieste">
        <div class="client-table-scroll" role="region" aria-label="Gruppi preventivi" tabindex="0"><table class="t-table"><thead><tr><th>Gruppo</th><th>Tipo di assicurazione</th><th>Sottoramo</th><th>Classe CU</th><th>Compagnie</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
            @forelse($groups as $group)<tr><td>{{ $group->name }}</td><td>{{ $group->branch?->name ?: 'Da impostare' }}</td><td>{{ $group->subbranch?->name ?: 'Tutti del ramo' }}</td><td>{{ $group->class_label }}</td><td>{{ $group->companies->map(fn($company)=>$company->name.($company->status==='active' ? '' : ' (inattiva)'))->join(', ') }}</td><td>{{ $group->status==='active' ? 'Attivo' : 'Inattivo' }}</td><td><a class="btn btn-g btn-sm" href="{{ route('quote-groups.edit',$group) }}">Modifica</a></td></tr>@empty<tr><td colspan="7" class="u-empty-state">Nessun gruppo corrisponde ai filtri. Scegli il ramo assicurativo e le compagnie; la classe CU è facoltativa.</td></tr>@endforelse
        </tbody></table></div>
        {{ $groups->links() }}
    </x-panel>
</x-app-layout>
