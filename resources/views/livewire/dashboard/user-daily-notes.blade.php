<div x-data @note-created.window="$nextTick(() => document.getElementById('new-note')?.focus())" @note-editing.window="$nextTick(() => document.getElementById('edit-note-' + $event.detail.id)?.focus())" @note-edit-ended.window="$nextTick(() => document.getElementById('edit-note-button-' + $event.detail.id)?.focus())">
    <x-page-header><x-slot:title>Blocco note</x-slot:title></x-page-header>
    <div class="notes-container">
        <div class="notes-header">
            <button type="button" wire:click="previousDay" @disabled($editingEntryId !== null) class="btn btn-g notes-nav-btn" aria-label="Giorno precedente"><i data-lucide="chevron-left" class="u-icon-md"></i></button>
            <div class="notes-date-display">
                <div class="notes-date-title">{{ \Carbon\Carbon::parse($currentDate)->isToday() ? 'Oggi' : \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('D MMMM YYYY') }}</div>
                <div class="notes-date-subtitle">{{ \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('dddd') }}</div>
            </div>
            <button type="button" wire:click="nextDay" @disabled($editingEntryId !== null) class="btn btn-g notes-nav-btn" aria-label="Giorno successivo"><i data-lucide="chevron-right" class="u-icon-md"></i></button>
        </div>

        <form wire:submit="addEntry" class="notes-compose">
            <label class="form-lbl" for="new-note">Scrivi una nota</label>
            <textarea id="new-note" wire:model="newContent" class="form-ta note-textarea" rows="3" maxlength="10000" required @error('newContent') aria-invalid="true" aria-describedby="new-note-error" @enderror></textarea>
            @error('newContent')<p id="new-note-error" class="invalid-feedback">{{ $message }}</p>@enderror
            <div class="note-actions"><button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="addEntry">Salva nota</button></div>
        </form>
        <p class="notes-status" role="status" aria-live="polite">{{ $statusMessage }}</p>

        <div class="notes-list">
            @forelse($note?->entries ?? [] as $entry)
                <article class="note-card" wire:key="note-{{ $entry->id }}">
                    @if($editingEntryId === $entry->id)
                        <form wire:submit="saveEntry" class="note-edit-form">
                            <label class="form-lbl" for="edit-note-{{ $entry->id }}">Modifica nota</label>
                            <textarea id="edit-note-{{ $entry->id }}" wire:model="editingContent" class="form-ta note-textarea" rows="4" maxlength="10000" required @error('editingContent') aria-invalid="true" aria-describedby="edit-note-error" @enderror></textarea>
                            @error('editingContent')<p id="edit-note-error" class="invalid-feedback">{{ $message }}</p>@enderror
                            <div class="note-actions"><button type="button" wire:click="cancelEdit" class="btn btn-g">Annulla</button><button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="saveEntry">Salva modifiche</button></div>
                        </form>
                    @else
                        <p class="note-content">{{ $entry->content ?: 'Nota senza testo' }}</p>
                    @endif
                    @if(filled($entry->post_script) || $entry->checklistItems->isNotEmpty())
                        <details class="note-existing-details"><summary>Altre annotazioni salvate</summary>
                            @if(filled($entry->post_script))<p class="note-content">{{ $entry->post_script }}</p>@endif
                            @if($entry->checklistItems->isNotEmpty())<ul>@foreach($entry->checklistItems as $item)<li>{{ $item->label }}@if($item->is_completed) — completata @endif</li>@endforeach</ul>@endif
                        </details>
                    @endif
                    <div class="note-footer">
                        <time class="u-text-meta" datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('H:i') }}</time>
                        <div class="note-actions">
                            @if($editingEntryId !== $entry->id)<button type="button" id="edit-note-button-{{ $entry->id }}" wire:click="editEntry({{ $entry->id }})" @disabled($editingEntryId !== null) class="btn btn-g btn-sm">Modifica</button>@endif
                            <x-confirm-modal title="Elimina nota" message="Vuoi eliminare questa nota?" confirmText="Elimina" :confirmMethod="'deleteEntry('.$entry->id.')'" confirmClass="btn btn-p btn-danger" icon="trash-2" variant="danger">
                                <button type="button" class="btn btn-g btn-sm btn-danger-outline">Elimina</button>
                            </x-confirm-modal>
                        </div>
                    </div>
                </article>
            @empty
                <p class="notes-empty-state">Nessuna nota per questa giornata.</p>
            @endforelse
        </div>
    </div>
</div>
