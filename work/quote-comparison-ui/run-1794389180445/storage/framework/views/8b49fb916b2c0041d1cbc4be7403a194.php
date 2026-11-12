<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'name',
    'id' => null,
    'showLabel' => 'Mostra password',
    'hideLabel' => 'Nascondi password',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'name',
    'id' => null,
    'showLabel' => 'Mostra password',
    'hideLabel' => 'Nascondi password',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $inputId = $id ?: $name;
    $statusId = $inputId.'-visibility-status';
?>

<div
    class="password-field"
    data-password-field
    data-show-label="<?php echo e($showLabel); ?>"
    data-hide-label="<?php echo e($hideLabel); ?>"
>
    <input
        id="<?php echo e($inputId); ?>"
        name="<?php echo e($name); ?>"
        type="password"
        <?php echo e($attributes->except(['id', 'name'])->merge(['class' => 'form-in'])); ?>

    >
    <button
        type="button"
        class="password-toggle"
        data-password-toggle
        aria-controls="<?php echo e($inputId); ?>"
        aria-pressed="false"
        aria-label="<?php echo e($showLabel); ?>"
        title="<?php echo e($showLabel); ?>"
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
        id="<?php echo e($statusId); ?>"
        class="sr-only"
        data-password-status
        role="status"
        aria-live="polite"
    >Password nascosta.</span>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/password-input.blade.php ENDPATH**/ ?>