@props(['id' => '', 'title' => ''])
<div class="overlay" id="{{ $id }}" data-dialog-overlay aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" tabindex="-1">
        <div class="modal-hd">
            <h2 class="modal-title" id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="modal-close" data-dialog-initial-focus aria-label="Chiudi finestra" @click="document.getElementById('{{ $id }}').classList.remove('open')">✕</button>
        </div>
        {{ $slot }}
        @if(isset($footer))
            <div class="modal-ft">{{ $footer }}</div>
        @endif
    </div>
</div>
