@props([
    'action' => null,
    'wireClick' => null, 
    'title' => 'Conferma Eliminazione', 
    'message' => 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
    'confirmText' => null,
    'formClass' => '',
    'formIdAttr' => null,
])
@php
    $dialogId = 'delete-dialog-'.\Illuminate\Support\Str::uuid();
    $confirmInputId = $dialogId.'-confirmation';
@endphp

<div x-data="{ open: false, confirm: '' }" class="confirm-modal-trigger">
    <div @click.stop="open = true; confirm = ''">
        {{ $slot }}
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="confirm-modal-overlay" data-dialog-overlay :aria-hidden="open ? 'false' : 'true'" @click.self="open = false" @keydown.escape.window="open = false">
        <div class="confirm-modal-box" role="dialog" aria-modal="true" aria-labelledby="{{ $dialogId }}-title" aria-describedby="{{ $dialogId }}-message" tabindex="-1" @click.stop>
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <h2 class="confirm-modal-title" id="{{ $dialogId }}-title">
                    {{ $title }}
                </h2>
            </div>
            
            <p class="confirm-modal-message" id="{{ $dialogId }}-message">
                {{ $message }}
            </p>

            @if($confirmText)
            <div class="u-mb-lg">
                <label class="u-text-label u-w-full delete-modal-confirm-label" for="{{ $confirmInputId }}">
                    Digita <strong class="u-text-strong delete-modal-confirm-token">{{ $confirmText }}</strong> per confermare
                </label>
                <input id="{{ $confirmInputId }}" type="text" x-model="confirm" class="form-in u-w-full delete-modal-confirm-input" placeholder="{{ $confirmText }}" autocomplete="off">
            </div>
            @endif
            
            <div class="confirm-modal-footer">
                <button type="button" @click="open = false" class="btn btn-g confirm-modal-btn" data-dialog-initial-focus>Annulla</button>
                @if($wireClick)
                    <button type="button" wire:click="{{ $wireClick }}" class="btn btn-p btn-danger confirm-modal-btn" 
                            @if($confirmText) :disabled="confirm !== '{{ addslashes($confirmText) }}'" :class="{ 'is-disabled': confirm !== '{{ addslashes($confirmText) }}' }" @endif
                            @click="open = false">
                        Sì, elimina
                    </button>
                @else
                <form action="{{ $action }}" method="POST" class="u-m-0 {{ $formClass }}" @if($formIdAttr) data-item-id="{{ $formIdAttr }}" @endif>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-p btn-danger confirm-modal-btn" 
                            @if($confirmText) :disabled="confirm !== '{{ addslashes($confirmText) }}'" :class="{ 'is-disabled': confirm !== '{{ addslashes($confirmText) }}' }" @endif>
                        Sì, elimina
                    </button>
                </form>
                @endif
            </div>
        </div>
        </div>
    </template>
</div>
