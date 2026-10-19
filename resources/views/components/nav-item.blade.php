@props([
    'href' => '#',
    'icon' => '',
    'label' => '',
    'active' => false,
    'badge' => null,
    'badgeClass' => '',
])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'nav-item ' . ($active ? 'active' : '')]) }} x-bind:title="!sidebarOpen ? '{{ $label }}' : ''">
    <div class="nav-icon"><i data-lucide="{{ $icon }}" width="16" height="16" stroke-width="1.8"></i></div>
    <span class="nav-label">{{ $label }}</span>
    @if($badge)
        <span class="badge-mini"></span>
        <span class="nav-badge {{ $badgeClass }}">{{ $badge }}</span>
    @endif
    <div class="nav-tooltip">{{ $label }}{{ $badge ? " ($badge)" : '' }}</div>
</a>