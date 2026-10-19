@props(['title' => null, 'dot' => null, 'padded' => false])
<div {{ $attributes->merge(['class' => 'panel']) }}>
    @if($title)
        <div class="panel-header">
            <div class="panel-title" role="heading" aria-level="2">
                @if($dot)
                    <div class="dot {{ $dot ? 'color-' . str_replace('var(--', '', str_replace(')', '', $dot)) : '' }}"></div>
                @endif
                {{ $title }}
            </div>
            @if(isset($headerActions))
                {{ $headerActions }}
            @endif
        </div>
    @endif
    <div class="panel-body {{ $padded ? 'pad' : '' }}">
        {{ $slot }}
    </div>
</div>
