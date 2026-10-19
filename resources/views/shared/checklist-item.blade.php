<div class="u-flex-center u-gap-sm task-checklist-item" data-checklist-item="{{ $item->id }}">
    <form action="{{ route($type . '-checklist-items.toggle', $item) }}" method="POST" class="js-checklist-toggle">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-g btn-sm" data-checklist-toggle-button>
            {{ $item->is_completed ? '✓' : '○' }}
        </button>
    </form>



    <div class="u-flex-1">
        <div class="{{ $item->is_completed ? 'u-text-muted task-checklist-completed' : 'u-text-strong' }}" data-checklist-title>
            {{ $item->title }}
        </div>

        <div class="u-text-meta task-checklist-meta">
            <span data-checklist-completed-by-wrapper class="{{ $item->is_completed ? '' : 'task-checklist-hidden' }}">
                completato da <span data-checklist-completed-by>{{ $item->completedBy?->name ?? '—' }}</span>
            </span>
        </div>
    </div>



    <x-delete-modal 
        action="{{ route($type . '-checklist-items.destroy', $item) }}" 
        title="Elimina Voce Checklist" 
        message="Sei sicuro di voler eliminare questa voce?"
        formClass="js-checklist-destroy"
        formIdAttr="{{ $item->id }}">
        <button type="button" class="btn-icon u-text-red">✕</button>
    </x-delete-modal>
</div>
