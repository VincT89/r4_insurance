<x-app-layout title="Rami e sottorami">
    <x-page-header><x-slot:title>Rami e sottorami</x-slot:title>
        <x-slot:actions>@can('create', \App\Models\SupplierCompany::class)<a class="btn btn-p" href="{{ route('insurance-catalog.create', ['kind' => $kind, 'branch' => request('branch')]) }}">{{ $kind === 'branch' ? 'Nuovo ramo' : 'Nuovo sottoramo' }}</a>@endcan</x-slot:actions>
    </x-page-header>
    @include('suppliers._navigation')
    <nav class="client-section-nav" aria-label="Catalogo assicurativo">
        <a href="{{ route('insurance-catalog.index', ['kind'=>'branch']) }}" @if($kind==='branch') aria-current="page" @endif>Rami</a>
        <a href="{{ route('insurance-catalog.index', ['kind'=>'subbranch']) }}" @if($kind==='subbranch') aria-current="page" @endif>Sottorami</a>
    </nav>
    <form method="GET" class="client-search-form" action="{{ route('insurance-catalog.index') }}">
        <input type="hidden" name="kind" value="{{ $kind }}" data-export-label="Catalogo" data-export-value="{{ $kind==='branch' ? 'Rami' : 'Sottorami' }}">
        <x-form-group label="Cerca nel catalogo" name="search"><input class="form-in" id="field-search" type="search" name="search" maxlength="100" value="{{ request('search') }}"></x-form-group>
        @if($kind==='subbranch')<x-form-group label="Ramo principale" name="branch"><select class="form-sel" id="field-branch" name="branch"><option value="">Tutti i rami</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></x-form-group>@endif
        <x-form-group label="Stato" name="status"><select class="form-sel" id="field-status" name="status"><option value="">Tutti</option><option value="active" @selected(request('status')==='active')>Attivi</option><option value="inactive" @selected(request('status')==='inactive')>Inattivi</option></select></x-form-group>
        <button class="btn btn-p" type="submit">Mostra</button>
        <button class="btn btn-g" type="submit" name="review" value="1">Solo da verificare</button>
        <a class="btn btn-g" href="{{ route('insurance-catalog.index', ['kind'=>$kind]) }}">Azzera filtri</a>
    </form>
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Elenco {{ $kind==='branch' ? 'rami' : 'sottorami' }}" tabindex="0">
            <table class="t-table"><thead><tr><th>{{ $kind==='branch' ? 'Ramo' : 'Sottoramo' }}</th><th>{{ $kind==='branch' ? 'Sottorami' : 'Ramo principale' }}</th><th>Stato</th><th>Classificazione</th><th>Azioni</th></tr></thead>
                <tbody>@forelse($records as $record)<tr>
                    <td>{{ $record->name }}</td><td>@if($kind==='branch')<a href="{{ route('insurance-catalog.index', ['kind'=>'subbranch', 'branch'=>$record->id]) }}">{{ $record->subbranches_count }} sottorami</a>@else {{ $record->branch->name }} @endif</td>
                    <td>{{ $record->status_label }}@if($kind==='subbranch' && $record->branch->status==='inactive')<div class="u-text-meta">Ramo principale inattivo</div>@endif</td><td>{{ $record->needs_review ? 'Da verificare' : 'Confermata' }}</td>
                    <td>@can('create', \App\Models\SupplierCompany::class)<a class="btn btn-g btn-sm" href="{{ route('insurance-catalog.edit', [$kind, $record->id]) }}">Modifica</a>@endcan @if($kind==='branch')@can('create', \App\Models\SupplierCompany::class)<a class="btn btn-g btn-sm" href="{{ route('insurance-catalog.create', ['kind'=>'subbranch', 'branch'=>$record->id]) }}">Aggiungi sottoramo</a>@endcan @endif</td>
                </tr>@empty<tr><td colspan="5">Nessuna voce per questi filtri.</td></tr>@endforelse</tbody>
            </table>
        </div>
        {{ $records->links() }}
    </x-panel>
</x-app-layout>
