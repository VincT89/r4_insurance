@props([
    'eyebrow' => '',
    'title'   => '',
    'meta'    => '',
])
<div class="page-heading">
    <div class="ph-left">
        @if($eyebrow)
            <div class="page-eyebrow">{{ $eyebrow }}</div>
        @endif
        <div class="page-title" role="heading" aria-level="1">{{ $title ?? '' }}</div>
        @if($meta)
            <div class="page-meta">{{ $meta }}</div>
        @endif
    </div>
    @if(isset($actions))
        <div class="ph-right">{{ $actions }}</div>
    @endif
</div>
