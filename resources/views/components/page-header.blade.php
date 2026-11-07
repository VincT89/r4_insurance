@props([
    'eyebrow' => '',
    'title'   => '',
    'meta'    => '',
])
<header class="page-heading">
    <div class="ph-left">
        @if($eyebrow)
            <div class="page-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1 class="page-title">{{ $title ?? '' }}</h1>
        @if($meta)
            <div class="page-meta">{{ $meta }}</div>
        @endif
    </div>
    @if(isset($actions))
        <div class="ph-right">{{ $actions }}</div>
    @endif
</header>
