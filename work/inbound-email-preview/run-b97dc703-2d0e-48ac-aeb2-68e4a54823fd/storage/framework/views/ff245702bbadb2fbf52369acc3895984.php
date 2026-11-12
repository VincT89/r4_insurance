<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policyTitles->isNotEmpty()): ?>
    <div class="policy-title-list">
        <h2>Titoli della polizza</h2>
        <div class="client-table-scroll policy-list-scroll" role="region" aria-label="Titoli della polizza" tabindex="0">
            <table class="t-table policy-titles-table" data-list-details>
                <thead><tr><th>Titolo</th><th>Importo</th><th>Azioni</th><th data-list-detail>Scadenza</th><th data-list-detail>Stato</th></tr></thead>
                <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $policyTitles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policyTitle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr data-no-list-details>
                        <td data-label="Titolo"><a href="<?php echo e(route('titles.show',$policyTitle)); ?>"><?php echo e($policyTitle->direction === 'refund' ? 'Rimborso' : \App\Models\InsuranceTitle::KINDS[$policyTitle->kind]); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policyTitle->reference): ?> / <?php echo e($policyTitle->reference); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a><div class="u-text-meta"><?php echo e($policyTitle->coverage_from->format('d/m/Y')); ?> – <?php echo e($policyTitle->coverage_to->format('d/m/Y')); ?></div><div class="policy-title-due" data-table-export-ignore>Scadenza <?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-text','data' => ['alert' => $policyTitle->state==='overdue']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-text'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['alert' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policyTitle->state==='overdue')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($policyTitle->due_on->format('d/m/Y')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $attributes = $__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__attributesOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7)): ?>
<?php $component = $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7; ?>
<?php unset($__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7); ?>
<?php endif; ?></div></td>
                        <td data-label="Importo"><strong><?php echo e(\App\Support\Money::format($policyTitle->amount_cents)); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policyTitle->allowance_cents>0): ?><div class="u-text-meta">Abbuoni: <?php echo e(\App\Support\Money::format($policyTitle->allowance_cents)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($policyTitle->collected_cents>0 || $policyTitle->allowance_cents>0) && $policyTitle->balance_cents>0 && !$policyTitle->cancelled_at): ?><div class="u-text-meta">Da saldare: <?php echo e(\App\Support\Money::format($policyTitle->balance_cents)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><div class="policy-title-state" data-table-export-ignore><?php if (isset($component)) { $__componentOriginal123acc38556ac823498710676f866859 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal123acc38556ac823498710676f866859 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.title-status','data' => ['title' => $policyTitle]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('title-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policyTitle)]); ?>
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
<?php endif; ?></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policyTitle->allowance_cents>0 && $policyTitle->affects_coverage && !$policyTitle->cancelled_at): ?><div class="u-text-meta"><?php echo e($policyTitle->coverage_confirmation_valid ? 'Copertura confermata' : ($policyTitle->balance_cents>0 ? 'Copertura da confermare dopo saldo' : 'Copertura da confermare')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                        <td data-label="Azioni"><div class="policy-row-actions">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payableTitles->contains('id',$policyTitle->id)): ?>
                                <a class="btn btn-g btn-sm" href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'payments','title'=>$policyTitle->id]).'#payment-entry'); ?>"><?php echo e($policyTitle->direction === 'refund' ? 'Registra rimborso' : 'Incassa'); ?></a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policyTitle->direction === 'receipt'): ?><a href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'payments','title'=>$policyTitle->id]).'#allowance-entry'); ?>">Registra abbuono</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($policyTitle->allowance_cents>0 && $policyTitle->affects_coverage && !$policyTitle->cancelled_at && !$policyTitle->coverage_confirmation_valid): ?><a href="#allowance-coverage-<?php echo e($policyTitle->id); ?>">Conferma copertura</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <a href="<?php echo e(route('titles.show',$policyTitle)); ?>">Dettaglio e movimenti</a>
                        </div></td>
                        <td><?php echo e($policyTitle->due_on->format('d/m/Y')); ?></td><td><?php echo e($policyTitle->state_label); ?></td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <p class="u-text-meta">Non sono ancora presenti titoli. Prepara i titoli dal frazionamento della polizza oppure inserisci un titolo manuale.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/policies/titles.blade.php ENDPATH**/ ?>