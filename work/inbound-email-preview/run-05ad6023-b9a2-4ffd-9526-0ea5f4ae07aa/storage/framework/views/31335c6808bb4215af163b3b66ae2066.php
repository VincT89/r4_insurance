<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Titoli'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
 <?php $__env->slot('title', null, []); ?> Titoli<?php echo e($contextPolicy ? ' / '.$contextPolicy->number : ($contextClient ? ' / '.$contextClient->name : '')); ?> <?php $__env->endSlot(); ?> <?php $__env->slot('actions', null, []); ?> <a class="btn btn-g" href="<?php echo e(route('collections.index',$contextParameters+['month'=>''])); ?>">Contabilità</a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contextPolicy): ?><a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$contextClient,$contextPolicy,'tab'=>'payments'])); ?>">Scheda polizza</a><?php elseif($contextClient): ?><a class="btn btn-g" href="<?php echo e(route('clients.show',$contextClient)); ?>">Scheda cliente</a><?php else: ?><a class="btn btn-g" href="<?php echo e(route('policies.index')); ?>">Polizze</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contextParameters): ?><a class="btn btn-g" href="<?php echo e(route('titles.index')); ?>">Tutti i titoli</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php $__env->endSlot(); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php echo $__env->make('ledger.filters',['isCollections'=>false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($filters['direction'] ?? '') !== 'refund'): ?><details class="list-summary"><summary>Totali dei titoli filtrati</summary><?php if (isset($component)) { $__componentOriginalac6a82c2aa70a83e93a753250e7bbcbf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalac6a82c2aa70a83e93a753250e7bbcbf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.payment-summary','data' => ['summary' => $paymentSummary,'filters' => $filters,'filtered' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('payment-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['summary' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paymentSummary),'filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'filtered' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalac6a82c2aa70a83e93a753250e7bbcbf)): ?>
<?php $attributes = $__attributesOriginalac6a82c2aa70a83e93a753250e7bbcbf; ?>
<?php unset($__attributesOriginalac6a82c2aa70a83e93a753250e7bbcbf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalac6a82c2aa70a83e93a753250e7bbcbf)): ?>
<?php $component = $__componentOriginalac6a82c2aa70a83e93a753250e7bbcbf; ?>
<?php unset($__componentOriginalac6a82c2aa70a83e93a753250e7bbcbf); ?>
<?php endif; ?></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <p class="list-results" data-table-page-summary><?php echo e($titles->total()); ?> <?php echo e($titles->total() === 1 ? 'titolo' : 'titoli'); ?> · Pagina <?php echo e($titles->currentPage()); ?> di <?php echo e($titles->lastPage()); ?></p>
    <div class="client-table-scroll" role="region" aria-label="Titoli" tabindex="0">
        <table class="t-table" data-title-table data-list-details data-filter-form="ledger-filters">
            <thead><tr><th>Titolo</th><th>Polizza</th><th>Contraente</th><th data-list-detail>Agenzia</th><th data-list-detail>Compagnia</th><th>Scadenza</th><th data-list-detail>Scadenza +15 giorni</th><th data-list-detail>Periodo</th><th data-list-detail>Frazionamento</th><th data-list-detail>Importo</th><th data-list-detail>Abbuoni</th><th>Residuo</th><th data-list-detail>Metodo previsto</th><th>Stato</th></tr></thead>
            <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $titles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <tr data-title-summary-group="<?php echo e($title->summary_state); ?>" data-title-balance-cents="<?php echo e($title->balance_cents); ?>">
                    <td>
                        <a href="<?php echo e(route('titles.show',$title)); ?>"><?php echo e(\App\Models\InsuranceTitle::KINDS[$title->kind]); ?> <?php echo e($title->reference ?: '#'.$title->id); ?></a>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$title->policy->client)): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$title->cancelled_at && $title->balance_cents > 0): ?>
                                    <div><a class="btn btn-g btn-sm" href="<?php echo e(route('titles.show',$title)); ?>#payment-entry" aria-label="<?php echo e($title->direction==='refund' ? 'Registra rimborso per' : 'Incassa'); ?> <?php echo e($title->reference ?: 'titolo '.$title->id); ?>"><?php echo e($title->direction==='refund' ? 'Registra rimborso' : 'Incassa'); ?></a></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?php echo e(route('clients.policies.show',[$title->policy->client_id,$title->policy])); ?>"><?php echo e($title->contract->number); ?></a></td>
                    <td><a href="<?php echo e(route('clients.show',$title->policy->client)); ?>"><?php echo e($title->policy->client->name); ?></a></td>
                    <td><?php echo e($title->contract->supplierAgency?->name ?: 'Rapporto diretto'); ?></td><td><?php echo e($title->contract->company); ?></td>
                    <td><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $title->state === 'overdue']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title->state === 'overdue')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($title->due_on->format('d/m/Y')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></td>
                    <td><?php echo e($title->follow_up_on->format('d/m/Y')); ?></td><td><?php echo e($title->coverage_from->format('d/m/Y')); ?> – <?php echo e($title->coverage_to->format('d/m/Y')); ?></td>
                    <td><?php echo e($title->payment_frequency); ?></td><td><?php echo e(\App\Support\Money::format($title->amount_cents)); ?></td>
                    <td><?php echo e(\App\Support\Money::format($title->allowance_cents)); ?></td>
                    <td><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => in_array($title->state, ['due_today', 'partial', 'overdue'], true)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(in_array($title->state, ['due_today', 'partial', 'overdue'], true))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(\App\Support\Money::format($title->balance_cents)); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></td>
                    <td><?php echo e($title->payment_method); ?></td><td><?php if (isset($component)) { $__componentOriginal123acc38556ac823498710676f866859 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal123acc38556ac823498710676f866859 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.title-status','data' => ['title' => $title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('title-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal123acc38556ac823498710676f866859)): ?>
<?php $attributes = $__attributesOriginal123acc38556ac823498710676f866859; ?>
<?php unset($__attributesOriginal123acc38556ac823498710676f866859); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal123acc38556ac823498710676f866859)): ?>
<?php $component = $__componentOriginal123acc38556ac823498710676f866859; ?>
<?php unset($__componentOriginal123acc38556ac823498710676f866859); ?>
<?php endif; ?></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="14">Nessun titolo corrisponde ai filtri. I titoli si creano dalla scheda della polizza.</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo e($titles->links()); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/titles.blade.php ENDPATH**/ ?>