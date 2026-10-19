@props([
    'title' => null,
    'message' => 'Nessun elemento trovato.', 
    'icon' => 'inbox',
    'actionLabel' => null,
    'actionHref' => null
])

<div class="empty-state-container">
    <div class="empty-state-icon-wrap">
        <i data-lucide="{{ $icon }}" @if($icon === 'loader') class="spin" @endif class="empty-state-icon"></i>
    </div>
    
    @if($title)
        <div class="empty-state-title">
            {{ $title }}
        </div>
    @endif
    
    <div class="empty-state-msg">
        {{ $message }}
    </div>

    @if($actionLabel && $actionHref)
        <div class="empty-state-action-wrap">
            <a href="{{ $actionHref }}" class="btn btn-p empty-state-action-btn">
                {{ $actionLabel }}
            </a>
        </div>
    @endif
</div>
