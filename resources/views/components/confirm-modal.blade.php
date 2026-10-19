@props([
    'title' => 'Conferma', 
    'message' => 'Sei sicuro di voler procedere?',
    'confirmText' => 'Conferma',
    'confirmMethod' => null,
    'confirmClass' => 'btn btn-p',
    'icon' => 'alert-circle',
    'variant' => 'warning',
    'disabled' => false
])
@php($dialogId = 'confirm-dialog-'.\Illuminate\Support\Str::uuid())

<div x-data="{ open: false }" class="confirm-modal-trigger">
    <div @click="if(!{{ $disabled ? 'true' : 'false' }}) open = true">
        {{ $slot }}
    </div>

    <template x-teleport="body" wire:ignore>
        <div x-show="open" x-cloak class="confirm-modal-overlay" data-dialog-overlay :aria-hidden="open ? 'false' : 'true'" @click.self="open = false" @keydown.escape.window="open = false">
        <div class="confirm-modal-box" role="dialog" aria-modal="true" aria-labelledby="{{ $dialogId }}-title" aria-describedby="{{ $dialogId }}-message" tabindex="-1" @click.stop>
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon {{ $variant }}">
                    <i data-lucide="{{ $icon }}" class="confirm-modal-icon-svg"></i>
                </div>
                <h2 class="confirm-modal-title" id="{{ $dialogId }}-title">
                    {{ $title }}
                </h2>
            </div>
            
            <p class="confirm-modal-message" id="{{ $dialogId }}-message">
                {{ $message }}
            </p>
            
            <div class="confirm-modal-footer">
                <button type="button" @click="open = false" class="btn btn-g confirm-modal-btn" data-dialog-initial-focus>Annulla</button>
                <button type="button" class="{{ $confirmClass }} confirm-modal-btn" 
                        @if($confirmMethod) wire:click="{{ $confirmMethod }}" @endif
                        @click="open = false">
                    {{ $confirmText }}
                </button>
            </div>
        </div>
        </div>
    </template>
</div>
