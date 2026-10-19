@props(['label' => '', 'value' => '', 'delta' => '', 'trend' => ''])
<div class="kpi-cell {{ $trend === 'accent' ? 'accent-line' : '' }}">
    <div class="kpi-label-t">{{ $label }}</div>
    <div class="kpi-val-t">{{ $value }}</div>
    @if($delta)
        <div class="kpi-delta-t {{ $trend }}">{{ $delta }}</div>
    @endif
</div>