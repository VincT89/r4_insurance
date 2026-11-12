<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title->allowances->isNotEmpty()): ?>
<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Abbuoni del titolo','id' => 'abbuoni-del-titolo']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Abbuoni del titolo','id' => 'abbuoni-del-titolo']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="client-table-scroll" role="region" aria-label="Abbuoni del titolo" tabindex="0"><table class="t-table" data-list-details>
        <thead><tr><th>Data</th><th>Importo</th><th>Chi lo sostiene</th><th>Stato</th><th data-list-detail>Operatore</th><th data-list-detail>Motivo</th><th data-list-detail>Riferimento</th><th data-list-detail>Dettagli storno</th><th>Azioni</th></tr></thead>
        <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $title->allowances->sortByDesc('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allowance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <tr data-list-label="Abbuono #<?php echo e($allowance->id); ?> del <?php echo e($allowance->effective_on->format('d/m/Y')); ?>">
                <td><?php echo e($allowance->effective_on->format('d/m/Y')); ?></td><td><?php echo e(\App\Support\Money::format($allowance->amount_cents)); ?></td><td><?php echo e($allowance->bearer_label); ?></td><td><?php echo e($allowance->voided_at ? 'Stornato' : 'Registrato'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowance->borne_by==='agency' && !$allowance->voided_at): ?><div><?php echo e($allowance->recovery_state_label); ?></div><div>Da recuperare <?php echo e(\App\Support\Money::format($allowance->recoverable_cents)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                <td><?php echo e($allowance->author?->name ?: 'Operatore nello storico'); ?> / <?php echo e($allowance->created_at->format('d/m/Y H:i')); ?></td><td class="u-whitespace-pre-wrap"><?php echo e($allowance->reason); ?></td><td><?php echo e($allowance->reference ?: 'Non indicato'); ?></td><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowance->voided_at): ?><?php echo e($allowance->voided_at->format('d/m/Y H:i')); ?> / <?php echo e($allowance->voidedBy?->name ?: 'Operatore nello storico'); ?> / <?php echo e($allowance->void_reason); ?><?php else: ?> Non stornato <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                <td><a href="<?php echo e(route('allowances.show',$allowance)); ?>">Apri abbuono</a><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$title->policy->client)): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$allowance->voided_at && !$title->cancelled_at && !$allowance->recovered_cents): ?>
                    <details><summary>Storna abbuono</summary><form method="POST" action="<?php echo e(route('allowances.void',$allowance)); ?>" class="u-mt-md"><?php echo csrf_field(); ?><input type="hidden" name="allowance_origin" value="title"><?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Motivo dello storno','name' => 'allowance_void_reason','for' => 'void-allowance-'.$allowance->id,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Motivo dello storno','name' => 'allowance_void_reason','for' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('void-allowance-'.$allowance->id),'required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<textarea class="form-ta" name="allowance_void_reason" rows="2" minlength="5" maxlength="1000" required></textarea> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?><div class="insurance-form-actions"><button class="btn btn-g">Conferma storno</button></div></form></details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endif; ?> <?php endif; ?></td>
            </tr>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></tbody>
    </table></div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/allowance-history.blade.php ENDPATH**/ ?>