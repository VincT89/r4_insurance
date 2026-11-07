@props(['title' => null, 'dot' => null, 'padded' => false])
<div {{ $attributes->merge(['class' => 'panel']) }}>
    @if($title)
        <div class="panel-header">
            <h2 class="panel-title">
                @if($dot)
                    <span class="dot {{ $dot ? 'color-' . str_replace('var(--', '', str_replace(')', '', $dot)) : '' }}"></span>
                @endif
                {{ $title }}
            </h2>
            @if(isset($headerActions))
                {{ $headerActions }}
            @endif
        </div>
    @endif
    <div class="panel-body {{ $padded ? 'pad' : '' }}">
        {{ $slot }}
    </div>
</div>
