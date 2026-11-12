<div class="communication-batches" data-guide-batches="<?php echo e(($batchLimited??false) ? 'limited' : (filled(request('_table_filters')) ? 'filtered' : (count($batches) ? 'ready' : 'empty'))); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batchLimited??false): ?>
        <p>Più di 500 operazioni nel periodo. Usa «Filtra elenco» per scegliere un’agenzia o restringere le date.</p>
    <?php elseif(filled(request('_table_filters'))): ?>
        <p>È attivo un filtro sulle singole righe. Usa la selezione nell’elenco sottostante.</p>
    <?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $batches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php ($batchAgency=$batch['agency']); ?>
        <form method="POST" action="<?php echo e(route($batchRoute,$batchAgency)); ?>" x-data="{busy:false}" @submit="busy=true">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="all_matching" value="1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div>
                <strong><?php echo e($batchAgency->name); ?></strong>
                <p class="form-help"><?php echo e($batch['count']); ?> <?php echo e($batch['count']===1 ? $batchSingular : $batchPlural); ?> · <?php echo e($batchAgency->email ?: 'Email agenzia da completare'); ?></p>
            </div>
            <button class="btn btn-p" type="submit" aria-label="Prepara email per <?php echo e($batchAgency->name); ?>" <?php if($batchAgency->status!=='active'): echo 'disabled'; endif; ?> :disabled="busy || <?php echo e($batchAgency->status==='active' ? 'false' : 'true'); ?>" :aria-busy="busy" x-text="busy ? 'Preparo…' : 'Prepara email'">Prepara email</button>
        </form>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <p>Nessuna comunicazione da preparare nel periodo e con i filtri scelti.</p>
        <a class="btn btn-g" href="#communication-search">Cambia periodo o filtri</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/agency-communications/batches.blade.php ENDPATH**/ ?>