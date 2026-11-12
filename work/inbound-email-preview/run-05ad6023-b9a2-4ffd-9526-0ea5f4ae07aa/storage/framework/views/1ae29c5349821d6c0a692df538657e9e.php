<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['floating' => false]));

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

foreach (array_filter((['floating' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (\App\Support\TableListing::exporting())): ?>
<div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['page-guide', 'page-guide-floating' => $floating]); ?>" data-page-guide data-guide-route="<?php echo e(request()->route()?->getName()); ?>">
    <button class="btn btn-g page-guide-trigger" type="button" data-guide-open aria-haspopup="dialog" aria-controls="page-guide-panel" hidden><i data-lucide="circle-help" width="16" height="16" aria-hidden="true"></i><span>Guida</span></button>
    <dialog class="page-guide-panel" id="page-guide-panel" aria-labelledby="page-guide-title">
        <header class="page-guide-header">
            <h2 id="page-guide-title">Guida</h2>
            <button class="btn btn-g" type="button" data-guide-close autofocus>Chiudi</button>
        </header>
        <div class="page-guide-content" data-guide-content></div>
    </dialog>
    <span class="sr-only" role="status" aria-live="polite" data-guide-status></span>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/page-guide.blade.php ENDPATH**/ ?>