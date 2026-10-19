@props([
    'type' => 'info', // success, error, warning, info
    'title' => null,
    'message' => null,
    'icon' => null,
])

@php
    $typeClasses = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'warning' => 'bg-orange-50 text-orange-800 border-orange-200',
        'info' => 'bg-blue-50 text-blue-800 border-blue-200',
    ];

    $defaultIcons = [
        'success' => 'check-circle',
        'error' => 'x-circle',
        'warning' => 'alert-triangle',
        'info' => 'info',
    ];

    $currentClass = $typeClasses[$type] ?? $typeClasses['info'];
    $currentIcon = $icon ?? $defaultIcons[$type] ?? 'info';
@endphp

<div {{ $attributes->merge(['class' => "flex p-4 mb-4 text-sm border rounded-lg {$currentClass}"]) }} role="alert">
    <i data-lucide="{{ $currentIcon }}" class="flex-shrink-0 inline w-5 h-5 mr-3 mt-[2px]"></i>
    <div>
        @if($title)
            <span class="font-medium block mb-1">{{ $title }}</span>
        @endif
        @if($message)
            <div>{{ $message }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
