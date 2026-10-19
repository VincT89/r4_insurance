@props([
    'name',
    'id' => null,
    'showLabel' => 'Mostra password',
    'hideLabel' => 'Nascondi password',
])

@php
    $inputId = $id ?: $name;
    $statusId = $inputId.'-visibility-status';
@endphp

<div
    class="password-field"
    data-password-field
    data-show-label="{{ $showLabel }}"
    data-hide-label="{{ $hideLabel }}"
>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="password"
        {{ $attributes->except(['id', 'name'])->merge(['class' => 'form-in']) }}
    >
    <button
        type="button"
        class="password-toggle"
        data-password-toggle
        aria-controls="{{ $inputId }}"
        aria-pressed="false"
        aria-label="{{ $showLabel }}"
        title="{{ $showLabel }}"
    >
        <svg data-password-icon-hidden width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-.722-3.25"/>
            <path d="M2 8a10.645 10.645 0 0 0 20 0"/>
            <path d="m20 15-1.726-2.05"/>
            <path d="m4 15 1.726-2.05"/>
            <path d="m9 18 .722-3.25"/>
        </svg>
        <svg data-password-icon-visible hidden width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.71 7.7 7.36 5 12 5c4.64 0 8.29 2.7 9.94 6.65a1 1 0 0 1 0 .7C20.29 16.3 16.64 19 12 19c-4.64 0-8.29-2.7-9.94-6.65Z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        <span class="sr-only" data-password-toggle-label>Mostra</span>
    </button>
    <span
        id="{{ $statusId }}"
        class="sr-only"
        data-password-status
        role="status"
        aria-live="polite"
    >Password nascosta.</span>
</div>
