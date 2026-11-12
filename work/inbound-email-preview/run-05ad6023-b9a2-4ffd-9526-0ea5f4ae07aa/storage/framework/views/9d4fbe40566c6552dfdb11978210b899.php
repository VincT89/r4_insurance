<?php
    $visibleSignatures = $openSignatures->whereIn('package.insurance_policy_id',$policies->modelKeys());
    $signaturesByPolicy = $visibleSignatures->groupBy('package.insurance_policy_id');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleSignatures->isNotEmpty()): ?>
    <div class="client-mobile-signatures"><?php echo $__env->make('insurance-forms.open-signatures',['openSignatures'=>$visibleSignatures], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Polizze del cliente']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Polizze del cliente']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('headerActions', null, []); ?> 
<nav class="client-section-nav client-policy-nav" aria-label="Tipologie di polizza"><span class="branch-filter-label">Ramo:</span>
    <a href="<?php echo e(route('clients.show', [$client, 'tab' => 'policies'])); ?>" <?php if(!request('category')): ?> aria-current="page" <?php endif; ?>>Tutte le polizze</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $policyCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policyCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <a href="<?php echo e(route('clients.show', [$client, 'tab' => 'policies', 'category' => $policyCategory])); ?>" <?php if(request('category') === $policyCategory): ?> aria-current="page" <?php endif; ?>><?php echo e($policyCategory); ?></a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</nav>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('category') && $policySubcategories->isNotEmpty()): ?>
    <nav class="client-section-nav client-policy-nav" aria-label="Sottorami del ramo selezionato"><a href="<?php echo e(route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category')])); ?>" <?php if(!request('subcategory')): ?> aria-current="page" <?php endif; ?>>Tutti i sottorami</a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $policySubcategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subcategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><a href="<?php echo e(route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category'), 'subcategory' => $subcategory])); ?>" <?php if(request('subcategory') === $subcategory): ?> aria-current="page" <?php endif; ?>><?php echo e($subcategory); ?></a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></nav>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
     <?php $__env->endSlot(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policies->isNotEmpty()): ?>
        <div class="client-table-scroll policy-list-scroll" role="region" aria-label="Polizze del cliente" tabindex="0">
            <table class="t-table client-policy-table">
                <thead><tr><th>Polizza e bene assicurato</th><th>Compagnia e agenzia</th><th>Scadenza</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr>
                        <td data-label="Polizza"><a class="policy-number" href="<?php echo e(route('clients.policies.show', [$client, $policy])); ?>"><?php echo e($policy->number); ?></a><div><?php echo e($policy->category); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->subcategory): ?> / <?php echo e($policy->subcategory); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->product): ?><div class="u-text-meta"><?php echo e($policy->product); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><div><?php echo e($policy->vehicle?->plate ?? $policy->asset?->name ?? $policy->insured_subject ?? 'Bene assicurato non indicato'); ?></div></td>
                        <td data-label="Compagnia e agenzia"><?php echo e($policy->company); ?><div class="u-text-meta"><?php echo e($policy->supplierAgency?->name ?: 'Agenzia non collegata'); ?></div></td>
                        <td data-label="Scadenza" data-export-type="date"><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $policy->is_expired]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policy->is_expired)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($policy->expires_on->format('d/m/Y')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?><div class="u-text-meta">Dal <?php echo e($policy->starts_on->format('d/m/Y')); ?></div></td>
                        <td data-label="Stato"><?php if (isset($component)) { $__componentOriginal9ad048417fbcc5773ae6cb577d774a1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ad048417fbcc5773ae6cb577d774a1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.policy-status','data' => ['policy' => $policy]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('policy-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['policy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policy)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ad048417fbcc5773ae6cb577d774a1b)): ?>
<?php $attributes = $__attributesOriginal9ad048417fbcc5773ae6cb577d774a1b; ?>
<?php unset($__attributesOriginal9ad048417fbcc5773ae6cb577d774a1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ad048417fbcc5773ae6cb577d774a1b)): ?>
<?php $component = $__componentOriginal9ad048417fbcc5773ae6cb577d774a1b; ?>
<?php unset($__componentOriginal9ad048417fbcc5773ae6cb577d774a1b); ?>
<?php endif; ?></td>
                        <td data-label="Azioni"><div class="policy-row-actions"><a href="<?php echo e(route('clients.policies.show', [$client, $policy])); ?>">Apri polizza</a><?php if (isset($component)) { $__componentOriginal38cc9becf2286c48e96bdd0a061c14cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.policy-contract-link','data' => ['policy' => $policy]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('policy-contract-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['policy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policy)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf)): ?>
<?php $attributes = $__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf; ?>
<?php unset($__attributesOriginal38cc9becf2286c48e96bdd0a061c14cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38cc9becf2286c48e96bdd0a061c14cf)): ?>
<?php $component = $__componentOriginal38cc9becf2286c48e96bdd0a061c14cf; ?>
<?php unset($__componentOriginal38cc9becf2286c48e96bdd0a061c14cf); ?>
<?php endif; ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->has_payable_titles): ?><a href="<?php echo e(route('clients.policies.show', [$client, $policy, 'tab'=>'payments']).'#payment-entry'); ?>">Registra pagamento</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php endif; ?>
                        <?php endif; ?></div>
                            <?php ($pending = $signaturesByPolicy->get($policy->id, collect())); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pending->count() === 1): ?>
                                <a class="client-policy-signature" href="<?php echo e($pending->first()->signatureActionUrl()); ?>"><strong><?php echo e($pending->first()->signatureActionLabel()); ?></strong><span><?php echo e($pending->first()->title); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($period = $pending->first()->package->policy_period): ?> · <?php echo e($period); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span></a>
                            <?php elseif($pending->isNotEmpty()): ?>
                                <details class="client-policy-signatures"><summary>Moduli da firmare o controllare (<?php echo e($pending->count()); ?>)</summary>
                                    <?php echo $__env->renderEach('insurance-forms.open-signature',$pending,'document'); ?>
                                </details>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="policy-empty"><?php echo e(request('category') ? 'Nessuna polizza corrisponde al ramo e al sottoramo selezionati.' : 'Nessuna polizza inserita. Usa Nuova polizza per iniziare.'); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/partials/policies.blade.php ENDPATH**/ ?>