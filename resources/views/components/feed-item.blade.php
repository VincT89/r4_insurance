@props(['initials' => '', 'gradient' => 'linear-gradient(135deg,#5b8ef5,#a78bfa)', 'time' => ''])
<div class="feed-item">
    <div class="feed-av feed-av-gradient">{{ $initials }}</div>
    <div class="feed-body">
        {{ $slot }}
        <div class="feed-time">{{ $time }}</div>
    </div>
</div>