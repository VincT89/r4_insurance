<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <title><?php echo e($title ?? config('branding.name')); ?></title>
  <?php if (isset($component)) { $__componentOriginal35deebe96ce645e6ad3278dca4897881 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal35deebe96ce645e6ad3278dca4897881 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-icons','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-icons'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal35deebe96ce645e6ad3278dca4897881)): ?>
<?php $attributes = $__attributesOriginal35deebe96ce645e6ad3278dca4897881; ?>
<?php unset($__attributesOriginal35deebe96ce645e6ad3278dca4897881); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal35deebe96ce645e6ad3278dca4897881)): ?>
<?php $component = $__componentOriginal35deebe96ce645e6ad3278dca4897881; ?>
<?php unset($__componentOriginal35deebe96ce645e6ad3278dca4897881); ?>
<?php endif; ?>
  <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="guest-body app-body r4-workbench" style="<?php echo e(\App\Support\Appearance::style(\App\Support\Appearance::forRequest(request()))); ?>">
  <?php if (isset($component)) { $__componentOriginalde5f0d4ecb9844db6eb1e6f82ca808bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde5f0d4ecb9844db6eb1e6f82ca808bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-guide','data' => ['floating' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-guide'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['floating' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde5f0d4ecb9844db6eb1e6f82ca808bc)): ?>
<?php $attributes = $__attributesOriginalde5f0d4ecb9844db6eb1e6f82ca808bc; ?>
<?php unset($__attributesOriginalde5f0d4ecb9844db6eb1e6f82ca808bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde5f0d4ecb9844db6eb1e6f82ca808bc)): ?>
<?php $component = $__componentOriginalde5f0d4ecb9844db6eb1e6f82ca808bc; ?>
<?php unset($__componentOriginalde5f0d4ecb9844db6eb1e6f82ca808bc); ?>
<?php endif; ?>
  <?php echo e($slot); ?>

</body>
</html>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/layouts/guest.blade.php ENDPATH**/ ?>