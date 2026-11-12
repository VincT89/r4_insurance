<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Polizze'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="policy-register-page" data-policy-register data-list-user="<?php echo e(auth()->id()); ?>" data-policy-list-url="<?php echo e(route('policies.index')); ?>">
        <header class="policy-page-heading">
            <div><h1>Polizze</h1><p>Cerca una polizza e scegli l’operazione.</p></div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', \App\Models\Client::class)): ?><a href="<?php echo e(route('policies.create')); ?>" class="btn btn-p">Nuova polizza</a><?php endif; ?>
        </header>
        <?php echo $__env->make('policies.register-filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <section class="policy-register panel" aria-label="Elenco polizze">
            <div class="policy-register-caption">
                <p data-table-page-summary><?php echo e($policies->total()); ?> <?php echo e($policies->total() === 1 ? 'polizza' : 'polizze'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policies->hasPages()): ?> · Pagina <?php echo e($policies->currentPage()); ?> di <?php echo e($policies->lastPage()); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                <div data-policy-export-actions></div>
            </div>
            <div class="client-table-scroll" role="region" aria-label="Portafoglio polizze" tabindex="0">
                <table class="t-table policy-register-table" data-list-details data-details-label="Altri dati" data-filter-form="policies-filters">
                    <thead><tr><th>Polizza</th><th>Cliente</th><th>Compagnia</th><th>Scadenza</th><th>Da incassare</th><th>Azioni</th><th data-list-detail>Ramo</th><th data-list-detail>Sottoramo</th><th data-list-detail>Agenzia</th><th data-list-detail>Stato</th><th data-list-detail>Pagamento</th><th data-list-detail>Metodo di pagamento</th><th data-list-detail>Premio di polizza</th><th data-list-detail>Da rimborsare</th></tr></thead>
                    <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr data-list-label="<?php echo e($policy->number); ?>" id="policy-row-<?php echo e($policy->id); ?>">
                            <td><a class="policy-register-number" data-policy-list-link href="<?php echo e(route('clients.policies.show', [$policy->client_id,$policy])); ?>"><?php echo e($policy->number); ?></a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->vehicle): ?><div class="policy-register-meta"><?php echo e($policy->vehicle->plate); ?></div><?php elseif($policy->product): ?><div class="policy-register-meta"><?php echo e($policy->product); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td><a href="<?php echo e(route('clients.show',$policy->client)); ?>"><?php echo e($policy->client->name); ?></a></td>
                            <td><?php echo e($policy->company); ?></td>
                            <td><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
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
<?php endif; ?><span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['policy-register-state','is-alert'=>$policy->is_expired,'is-review'=>$policy->status==='review']); ?>" data-table-export-ignore><?php echo e($policy->status_label); ?></span></td>
                            <td class="policy-register-money"><?php echo e($policy->titles_exists ? \App\Support\Money::format((int)$policy->receipt_due_cents) : 'Nessun titolo'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->refund_due_cents>0): ?><small data-table-export-ignore>Da rimborsare <?php echo e(\App\Support\Money::format((int)$policy->refund_due_cents)); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td><div class="policy-register-actions">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('work')==='open' && ($nextTask = $workTasks[$policy->id][0] ?? null)): ?><a class="btn btn-p btn-sm" data-policy-list-link href="<?php echo e($nextTask['url']); ?>"><?php echo e($nextTask['label']); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$policy->client)): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->receipt_due_cents>0): ?><a class="btn btn-p btn-sm" data-policy-list-link aria-label="Incassa per la polizza <?php echo e($policy->number); ?>" href="<?php echo e(route('clients.policies.show',[$policy->client_id,$policy,'tab'=>'payments']).'#payment-entry'); ?>">Incassa</a>
                                    <?php elseif($policy->refund_due_cents>0): ?><a class="btn btn-p btn-sm" data-policy-list-link href="<?php echo e(route('clients.policies.show',[$policy->client_id,$policy,'tab'=>'payments']).'#payment-entry'); ?>">Registra rimborso</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?> <?php endif; ?>
                                <a class="policy-register-open" data-policy-list-link aria-label="Apri polizza <?php echo e($policy->number); ?>" href="<?php echo e(route('clients.policies.show',[$policy->client_id,$policy])); ?>">Apri</a>
                                <?php if (isset($component)) { $__componentOriginal38cc9becf2286c48e96bdd0a061c14cf = $component; } ?>
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
<?php endif; ?>
                            </div></td>
                            <td><?php echo e($policy->category); ?></td><td><?php echo e($policy->subcategory ?: 'Non specificato'); ?></td><td><?php echo e($policy->supplierAgency?->name ?: 'Non assegnata'); ?></td>
                            <td><?php echo e($policy->status_label); ?></td><td><?php echo e($policy->payment_status_label); ?></td><td><?php echo e($policy->payment_method ?: 'Non indicato'); ?></td>
                            <td><?php echo e($policy->premium !== null ? number_format((float)$policy->premium,2,',','.').' €' : 'Non indicato'); ?></td><td><?php echo e(\App\Support\Money::format((int)$policy->refund_due_cents)); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="14" class="policy-register-empty">Nessuna polizza corrisponde ai filtri. Puoi cambiare la ricerca o <a href="<?php echo e(route('policies.index')); ?>">mostrare tutte le polizze</a>.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo e($policies->links()); ?>

        </section>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/policies/index.blade.php ENDPATH**/ ?>