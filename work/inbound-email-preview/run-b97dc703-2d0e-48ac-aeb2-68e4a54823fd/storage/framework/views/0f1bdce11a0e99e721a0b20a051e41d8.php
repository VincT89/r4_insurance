<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Lavorazione','padded' => true,'id' => 'policy-work']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Lavorazione','padded' => true,'id' => 'policy-work']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->work_closed_at): ?>
        <p>Completata il <?php echo e($policy->work_closed_at->format('d/m/Y H:i')); ?> da <?php echo e($policy->workClosedBy?->name ?: 'Utente non disponibile'); ?>.</p>
        <p class="u-text-meta">Dal <?php echo e($policy->work_closed_at->copy()->addDay()->format('d/m/Y')); ?> le modifiche sono riservate all’admin. Lo stato del contratto resta <?php echo e(mb_strtolower($policy->status_label)); ?>.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->workLocked() && !auth()->user()->canManageSystem()): ?><p>Per correggere un errore, invia una segnalazione all’admin.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
        <p>Lavorazione aperta. Chiudila quando hai completato e controllato i dati della polizza.</p>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry', $client)): ?>
            <form method="POST" action="<?php echo e(route('clients.policies.close-work', [$client, $policy])); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="fingerprint" value="<?php echo e($policy->workFingerprint()); ?>">
                <label class="u-flex-center u-gap-sm"><input type="checkbox" name="confirmed" value="1" required> Ho completato la lavorazione. Dal giorno successivo le modifiche saranno riservate all’admin.</label>
                <div class="insurance-form-actions"><button type="submit" class="btn btn-p">Chiudi lavorazione</button></div>
            </form>
        <?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry', $client)): ?><a class="btn btn-g" href="#policy-corrections">Segnala un errore all’admin</a><?php endif; ?>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/policies/workflow.blade.php ENDPATH**/ ?>