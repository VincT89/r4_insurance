@props(['alert' => false])
<span {{ $attributes->class(['status-alert' => $alert]) }}>{{ $slot }}</span>
