@props(['title', 'empty' => false, 'message' => ''])
<section class="dashboard-section {{ $empty ? 'is-empty' : '' }}">
    @if($empty)
        <div class="dashboard-empty">
            <div><h2>{{ $title }}</h2><p>{{ $message }}</p></div>
            @isset($actions)
                <div class="dashboard-section-actions">{{ $actions }}</div>
            @endisset
        </div>
    @else
        <x-panel :title="$title">
            <x-slot:headerActions>
                @isset($actions)
                    <div class="dashboard-section-actions">{{ $actions }}</div>
                @endisset
            </x-slot:headerActions>
            {{ $slot }}
        </x-panel>
    @endif
</section>
