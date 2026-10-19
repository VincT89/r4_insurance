<x-app-layout title="Registro attività">
    <x-page-header meta="Accessi e operazioni eseguite dagli utenti, in ordine cronologico.">
        <x-slot:title>Registro attività</x-slot:title>
    </x-page-header>

    @if($errors->any())
        <div class="u-alert-error u-mb-md" role="alert">
            Controlla il periodo: la data finale non può precedere quella iniziale.
        </div>
    @endif

    
        <form action="{{ route('audit-logs.index') }}" method="GET" class="client-search-form">
            <div class="form-g">
                <label for="audit-entity" class="form-lbl">Sezione</label>
                <select id="audit-entity" name="auditable_type" class="form-in">
                    <option value="">Tutte le sezioni</option>
                    @foreach($entityFilters as $value => $filter)
                        <option value="{{ $value }}" @selected(request('auditable_type') === $value)>
                            {{ $filter['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-g">
                <label for="audit-action" class="form-lbl">Tipo di attività</label>
                <select id="audit-action" name="action" class="form-in">
                    <option value="">Tutte le attività</option>
                    @foreach($actionFilters as $value => $filter)
                        <option value="{{ $value }}" @selected(request('action') === $value)>
                            {{ $filter['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-g">
                <label for="audit-user" class="form-lbl">Utente</label>
                <select id="audit-user" name="user_id" class="form-in">
                    <option value="">Tutti gli utenti</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-g">
                <label for="audit-date-from" class="form-lbl">Dal giorno</label>
                <input id="audit-date-from" type="date" name="date_from" value="{{ old('date_from', request('date_from')) }}"
                       class="form-in @error('date_from') is-invalid @enderror">
            </div>

            <div class="form-g">
                <label for="audit-date-to" class="form-lbl">Al giorno</label>
                <input id="audit-date-to" type="date" name="date_to" value="{{ old('date_to', request('date_to')) }}"
                       class="form-in @error('date_to') is-invalid @enderror">
            </div>

            <div class="search-form-actions">
                <button type="submit" class="btn btn-p">Applica filtri</button>
                @if($activeFiltersCount > 0)
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-g">Azzera filtri</a>
                @endif
            </div>
        </form>

    <div class="audit-log-results-summary" aria-live="polite">
        @if($logs->total() > 0)
            Visualizzate {{ $logs->firstItem() }}-{{ $logs->lastItem() }} di {{ $logs->total() }} attività
            @if($activeFiltersCount > 0)
                corrispondenti ai filtri selezionati
            @endif
        @else
            Nessuna attività corrisponde ai filtri selezionati
        @endif
    </div>

    <x-audit-timeline :logs="$logs" title="Attività registrate" />

    @if($logs->hasPages())
        <div class="u-mt-md">
            {{ $logs->links() }}
        </div>
    @endif
</x-app-layout>
