<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\UserDailyNote;
use App\Models\UserDailyNoteEntry;
use App\Models\UserDailyNoteChecklistItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UserDailyNotes extends Component
{
    public $currentDate;
    
    public $entryContents = [];
    public $entryPostScripts = [];
    public $newChecklistLabels = [];

    public function mount()
    {
        $this->currentDate = Carbon::today()->format('Y-m-d');
        $this->loadState();
    }

    public function previousDay()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subDay()->format('Y-m-d');
        $this->loadState();
    }

    public function nextDay()
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addDay()->format('Y-m-d');
        $this->loadState();
    }

    public function loadState()
    {
        if (auth()->user()->can('system.admin')) $this->cleanupEmptyEntries();

        $note = UserDailyNote::with(['entries' => function($q) {
            $q->orderBy('sort_order');
        }])->where('user_id', Auth::id())
          ->whereDate('date', $this->currentDate)
          ->first();

        $this->entryContents = [];
        $this->entryPostScripts = [];
        $this->newChecklistLabels = [];

        if ($note) {
            foreach ($note->entries as $entry) {
                $this->entryContents[$entry->id] = $entry->content;
                $this->entryPostScripts[$entry->id] = $entry->post_script;
                $this->newChecklistLabels[$entry->id] = '';
            }
        }
    }

    protected function cleanupEmptyEntries()
    {
        $note = UserDailyNote::where('user_id', Auth::id())
            ->whereDate('date', $this->currentDate)
            ->first();

        if ($note) {
            foreach ($note->entries as $entry) {
                if (empty(trim($entry->content)) && empty(trim($entry->post_script)) && $entry->checklistItems()->count() === 0) {
                    $entry->delete();
                }
            }
        }
    }

    protected function getOrCreateDailyNote()
    {
        return UserDailyNote::whereDate('date', $this->currentDate)->firstOrCreate([
            'user_id' => Auth::id(),
        ], [
            'date' => $this->currentDate,
        ]);
    }

    public function addEntry()
    {
        $note = $this->getOrCreateDailyNote();
        
        $maxSort = $note->entries()->max('sort_order') ?? 0;
        
        $entry = $note->entries()->create([
            'content' => '',
            'post_script' => null,
            'sort_order' => $maxSort + 1,
        ]);

        $this->entryContents[$entry->id] = '';
        $this->entryPostScripts[$entry->id] = '';
        $this->newChecklistLabels[$entry->id] = '';
        
        $this->dispatch('note-added', id: $entry->id);
    }

    public function updatedEntryContents($value, $key)
    {
        $entry = UserDailyNoteEntry::whereHas('userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($key);

        if ($entry) {
            $entry->update(['content' => $value]);
        }
    }

    public function updatedEntryPostScripts($value, $key)
    {
        $entry = UserDailyNoteEntry::whereHas('userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($key);

        if ($entry) {
            $entry->update(['post_script' => $value]);
        }
    }

    public function deleteEntry($entryId)
    {
        \Illuminate\Support\Facades\Gate::authorize('system.admin');
        $entry = UserDailyNoteEntry::whereHas('userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($entryId);

        if ($entry) {
            $entry->delete();
            unset($this->entryContents[$entryId]);
            unset($this->entryPostScripts[$entryId]);
            unset($this->newChecklistLabels[$entryId]);
        }
    }

    public function addChecklistItem($entryId)
    {
        $label = trim($this->newChecklistLabels[$entryId] ?? '');
        if (empty($label)) return;

        $entry = UserDailyNoteEntry::whereHas('userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($entryId);

        if ($entry) {
            $maxSort = $entry->checklistItems()->max('sort_order') ?? 0;
            $entry->checklistItems()->create([
                'label' => $label,
                'is_completed' => false,
                'sort_order' => $maxSort + 1,
            ]);
            $this->newChecklistLabels[$entryId] = '';
        }
    }

    public function toggleChecklistItem($itemId)
    {
        $item = UserDailyNoteChecklistItem::whereHas('userDailyNoteEntry.userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($itemId);

        if ($item) {
            $item->update(['is_completed' => !$item->is_completed]);
        }
    }

    public function deleteChecklistItem($itemId)
    {
        \Illuminate\Support\Facades\Gate::authorize('system.admin');
        $item = UserDailyNoteChecklistItem::whereHas('userDailyNoteEntry.userDailyNote', function($q) {
            $q->where('user_id', Auth::id());
        })->find($itemId);

        if ($item) {
            $item->delete();
        }
    }

    public function render()
    {
        $note = UserDailyNote::with(['entries' => function($q) {
            $q->orderBy('sort_order');
        }, 'entries.checklistItems' => function($q) {
            $q->orderBy('sort_order');
        }])->where('user_id', Auth::id())
          ->whereDate('date', $this->currentDate)
          ->first();

        return view('livewire.dashboard.user-daily-notes', [
            'note' => $note
        ])->layout('layouts.app', ['title' => 'Blocco Note']);
    }
}
