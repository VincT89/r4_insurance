<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'href' => '#',
    'icon' => '',
    'label' => '',
    'active' => false,
    'badge' => null,
    'badgeClass' => '',
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
    'href' => '#',
    'icon' => '',
    'label' => '',
    'active' => false,
    'badge' => null,
    'badgeClass' => '',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<a href="<?php echo e($href); ?>" <?php echo e($attributes->merge(['class' => 'nav-item ' . ($active ? 'active' : '')])); ?> x-bind:title="!sidebarOpen ? '<?php echo e($label); ?>' : ''">
    <div class="nav-icon"><i data-lucide="<?php echo e($icon); ?>" width="16" height="16" stroke-width="1.8"></i></div>
    <span class="nav-label"><?php echo e($label); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($badge): ?>
        <span class="badge-mini"></span>
        <span class="nav-badge <?php echo e($badgeClass); ?>"><?php echo e($badge); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="nav-tooltip"><?php echo e($label); ?><?php echo e($badge ? " ($badge)" : ''); ?></div>
</a><?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/nav-item.blade.php ENDPATH**/ ?>