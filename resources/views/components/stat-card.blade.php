@props(['label' => '', 'value' => '', 'sub' => '', 'highlight' => false, 'subAlert' => false, 'color' => null])
<div class="stat-card {{ $highlight ? 'hi' : '' }} {{ $color ? 'color-' . str_replace('var(--', '', str_replace(')', '', $color)) : '' }}">
    <div class="stat-lbl">{{ $label }}</div>
    <div class="stat-val">{{ $value }}</div>
    @if($sub)
        <div class="stat-sub {{ $subAlert ? 'al' : '' }}">{{ $sub }}</div>
    @endif
</div>