<?php

namespace App\Livewire\Dashboard;

use App\Models\UserDailyNote;
use App\Models\UserDailyNoteEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UserDailyNotes extends Component
{
    #[Locked]
    public string $currentDate;

    #[Locked]
    public ?int $editingEntryId = null;

    public string $newContent = '';
    public string $editingContent = '';
    public string $statusMessage = '';

    public function mount(): void
    {
        $this->currentDate = Carbon::today()->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subDay()->format('Y-m-d');
        $this->cancelEdit();
        $this->statusMessage = '';
    }

    public function nextDay(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addDay()->format('Y-m-d');
        $this->cancelEdit();
        $this->statusMessage = '';
    }

    public function addEntry(): void
    {
        $this->newContent = trim($this->newContent);
        $this->validate(['newContent' => ['required', 'string', 'max:10000']], [
            'newContent.required' => 'Scrivi la nota prima di premere OK.',
            'newContent.max' => 'La nota può contenere al massimo 10.000 caratteri.',
        ]);

        $note = UserDailyNote::whereDate('date', $this->currentDate)->firstOrCreate(['user_id'=>Auth::id()], ['date'=>$this->currentDate]);
        $note->entries()->create([
            'content'=>$this->newContent,
            'sort_order'=>($note->entries()->max('sort_order') ?? 0) + 1,
        ]);

        $this->newContent = '';
        $this->statusMessage = 'Nota creata.';
        $this->dispatch('note-created');
    }

    public function editEntry(int $entryId): void
    {
        $entry = $this->ownedEntry($entryId);
        $this->resetValidation();
        $this->editingEntryId = $entry->id;
        $this->editingContent = $entry->content ?? '';
        $this->statusMessage = '';
        $this->dispatch('note-editing', id: $entry->id);
    }

    public function saveEntry(): void
    {
        abort_unless($this->editingEntryId, 404);
        $entry = $this->ownedEntry($this->editingEntryId);
        $this->editingContent = trim($this->editingContent);
        $this->validate(['editingContent'=>['required', 'string', 'max:10000']], [
            'editingContent.required'=>'La nota non può essere vuota. Per rimuoverla usa Elimina.',
            'editingContent.max'=>'La nota può contenere al massimo 10.000 caratteri.',
        ]);
        $entry->update(['content'=>$this->editingContent]);
        $this->cancelEdit();
        $this->statusMessage = 'Nota modificata.';
    }

    public function cancelEdit(): void
    {
        $id = $this->editingEntryId;
        $this->editingEntryId = null;
        $this->editingContent = '';
        $this->resetValidation();
        if ($id) $this->dispatch('note-edit-ended', id: $id);
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = $this->ownedEntry($entryId);
        $entry->delete();
        if ($this->editingEntryId === $entryId) $this->cancelEdit();
        $this->statusMessage = 'Nota eliminata.';
    }

    private function ownedEntry(int $entryId): UserDailyNoteEntry
    {
        /** Personal notes stay private: every mutation checks ownership again on the server. */
        return UserDailyNoteEntry::whereHas('userDailyNote', fn($query) => $query->where('user_id', Auth::id()))->findOrFail($entryId);
    }

    public function render()
    {
        $note = UserDailyNote::with(['entries.checklistItems'])->where('user_id', Auth::id())->whereDate('date', $this->currentDate)->first();
        return view('livewire.dashboard.user-daily-notes', ['note'=>$note])->layout('layouts.app', ['title'=>'Blocco note']);
    }
}
