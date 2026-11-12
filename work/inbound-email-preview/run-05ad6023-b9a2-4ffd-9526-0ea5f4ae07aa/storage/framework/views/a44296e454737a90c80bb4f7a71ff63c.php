<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['policy', 'button' => false]));

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

foreach (array_filter((['policy', 'button' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->contracts->count() === 1): ?>
    <?php ($contract = $policy->contracts->first()); ?>
    <a data-table-export-ignore href="<?php echo e($contract->openUrl()); ?>" <?php if($contract->canPreview()): ?> target="_blank" rel="noopener" <?php endif; ?> class="<?php echo \Illuminate\Support\Arr::toCssClasses(['btn btn-g' => $button, 'policy-contract-link' => !$button]); ?>"><?php echo e($contract->canPreview() ? 'Apri contratto' : 'Scarica contratto'); ?></a>
<?php elseif($policy->contracts->isNotEmpty()): ?>
    <a data-policy-section-link data-table-export-ignore href="<?php echo e(route('clients.policies.show', [$policy->client_id, $policy, 'tab'=>'documents']).'#policy-contracts'); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['btn btn-g' => $button, 'policy-contract-link' => !$button]); ?>">Apri contratti (<?php echo e($policy->contracts->count()); ?>)</a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/policy-contract-link.blade.php ENDPATH**/ ?>