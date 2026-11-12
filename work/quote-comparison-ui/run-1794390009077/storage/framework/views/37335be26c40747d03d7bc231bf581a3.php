<?php if (isset($component)) { $__componentOriginal2facb2b4ecce8cf6716e9417e2d0fc54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2facb2b4ecce8cf6716e9417e2d0fc54 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-section','data' => ['title' => 'Attività sinistri','empty' => $claimActivities->isEmpty(),'message' => 'Nessuna attività in arretrato o da svolgere entro 3 giorni.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Attività sinistri','empty' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($claimActivities->isEmpty()),'message' => 'Nessuna attività in arretrato o da svolgere entro 3 giorni.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

 <?php $__env->slot('actions', null, []); ?> <a class="btn btn-g btn-sm" href="<?php echo e(route('claims.index',['reminder'=>'all'])); ?>">Tutte le attività aperte</a> <?php $__env->endSlot(); ?>
<div class="client-table-scroll" role="region" aria-label="Attività sinistri in scadenza" tabindex="0"><table class="t-table"><thead><tr><th>Data attività</th><th>Cliente / sinistro</th><th>Attività</th><th>Operatore</th><th>Stato</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $claimActivities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><tr><td><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $activity->status === 'pending' && $activity->due_on->lt(today())]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($activity->status === 'pending' && $activity->due_on->lt(today()))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($activity->due_on->format('d/m/Y')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></td><td><?php echo e($activity->claim->client->name); ?><div><a href="<?php echo e(route('claims.show',$activity->claim).'#activities'); ?>"><?php echo e($activity->claim->reference); ?></a></div></td><td><?php echo e($activity->label); ?></td><td><?php echo e($activity->claim->assignee?->name?:'Da riassegnare'); ?></td><td><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $activity->status === 'pending' && $activity->due_on->lt(today())]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($activity->status === 'pending' && $activity->due_on->lt(today()))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($activity->state_label); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></td></tr><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="5">Nessuna attività in arretrato o da svolgere entro 3 giorni.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div><?php echo e($claimActivities->links()); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2facb2b4ecce8cf6716e9417e2d0fc54)): ?>
<?php $attributes = $__attributesOriginal2facb2b4ecce8cf6716e9417e2d0fc54; ?>
<?php unset($__attributesOriginal2facb2b4ecce8cf6716e9417e2d0fc54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2facb2b4ecce8cf6716e9417e2d0fc54)): ?>
<?php $component = $__componentOriginal2facb2b4ecce8cf6716e9417e2d0fc54; ?>
<?php unset($__componentOriginal2facb2b4ecce8cf6716e9417e2d0fc54); ?>
<?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/claims/dashboard.blade.php ENDPATH**/ ?>