<div x-data @note-added.window="setTimeout(() => { let id = $event.detail.id || ($event.detail[0] && $event.detail[0].id) || $event.detail; document.getElementById('note-ta-' + id)?.focus() }, 100)">
    <x-page-header 
        meta="Appunti e cose da fare, organizzati per giornata.">
        <x-slot:title>Blocco note</x-slot:title>
        <x-slot:actions><button type="button" wire:click="addEntry" class="btn btn-p">Nuova nota</button></x-slot:actions>
    </x-page-header>

    <div class="notes-container">
        <div class="notes-header">
            <button type="button" wire:click="previousDay" class="btn btn-g notes-nav-btn" aria-label="Giorno precedente">
                <i data-lucide="chevron-left" class="u-icon-md"></i>
            </button>
            
            <div class="notes-date-display">
                <div class="notes-date-title">
                    {{ \Carbon\Carbon::parse($currentDate)->isToday() ? 'Oggi' : \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('D MMMM YYYY') }}
                </div>
                <div class="notes-date-subtitle">
                    {{ \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('dddd') }}
                </div>
            </div>

            <button type="button" wire:click="nextDay" class="btn btn-g notes-nav-btn" aria-label="Giorno successivo">
                <i data-lucide="chevron-right" class="u-icon-md"></i>
            </button>
        </div>

        <div class="notes-grid">
            @if($note && $note->entries->count() > 0)
                @foreach($note->entries as $entry)
                    <div class="note-card">
                        <div class="note-header-actions">
                            <h2 class="note-heading">Nota {{ $loop->iteration }}</h2>
                            @can('system.admin')
                            <x-confirm-modal
                                title="Elimina Nota"
                                message="Sei sicuro di voler eliminare questa nota?"
                                confirmText="Elimina"
                                confirmMethod="deleteEntry({{ $entry->id }})"
                                confirmClass="btn btn-p btn-danger"
                                icon="trash-2"
                                variant="danger"
                            >
                                <button type="button" class="btn btn-g btn-sm btn-danger-outline" aria-label="Elimina nota {{ $loop->iteration }}">
                                    <i data-lucide="trash-2" class="u-icon-sm"></i>
                                </button>
                            </x-confirm-modal>
                            @endcan
                        </div>
                        <textarea id="note-ta-{{ $entry->id }}" wire:model.live.debounce.1000ms="entryContents.{{ $entry->id }}" class="form-ta note-textarea" aria-label="Testo della nota {{ $loop->iteration }}" placeholder="Scrivi i tuoi appunti qui..."></textarea>
                        <div class="note-checklist">
                            @if($entry->checklistItems->count() > 0)
                                @foreach($entry->checklistItems as $item)
                                    <div class="note-checklist-item">
                                        <input type="checkbox" wire:click="toggleChecklistItem({{ $item->id }})" {{ $item->is_completed ? 'checked' : '' }} class="note-check-box" aria-label="{{ $item->label }}">
                                        <div class="note-check-label {{ $item->is_completed ? 'completed' : '' }}">
                                            {{ $item->label }}
                                        </div>
                                        @can('system.admin')
                                        <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="btn btn-g btn-sm btn-danger-outline" aria-label="Elimina voce {{ $item->label }}">
                                            <i data-lucide="x" class="u-icon-sm"></i>
                                        </button>
                                        @endcan
                                    </div>
                                @endforeach
                            @endif
                            
                            <div class="note-check-input-wrap">
                                <input type="text" wire:model="newChecklistLabels.{{ $entry->id }}" wire:keydown.enter="addChecklistItem({{ $entry->id }})" class="form-in note-check-input" aria-label="Nuova voce della nota {{ $loop->iteration }}" placeholder="Aggiungi una voce...">
                                <button type="button" class="btn btn-g btn-sm" wire:click="addChecklistItem({{ $entry->id }})">Aggiungi</button>
                            </div>
                        </div>
                        <div class="note-ps-wrap">
                            <label class="form-lbl" for="note-ps-{{ $entry->id }}">Annotazione aggiuntiva</label>
                            <input type="text" wire:model.live.debounce.1000ms="entryPostScripts.{{ $entry->id }}" id="note-ps-{{ $entry->id }}" class="form-in note-ps-input" placeholder="Facoltativa">
                        </div>
                    </div>
                @endforeach
            @endif
            @if(!$note || $note->entries->count() === 0)
                <div class="notes-empty-state">
                    <h2>Nessuna nota</h2>
                    <p>Nessuna nota per {{ \Carbon\Carbon::parse($currentDate)->isToday() ? 'oggi' : 'questa giornata' }}. Usa «Nuova nota» per aggiungere appunti e cose da fare.</p>
                </div>
            @endif


        </div>
    </div>
</div>
