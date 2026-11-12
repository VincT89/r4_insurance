<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Contabilità'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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

         <?php $__env->slot('title', null, []); ?> Contabilità<?php echo e($contextPolicy ? ' / '.$contextPolicy->number : ($contextClient ? ' / '.$contextClient->name : '')); ?> <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a class="btn btn-g" href="<?php echo e(route('allowances.index',$contextParameters+$ledgerPeriodParameters)); ?>">Abbuoni</a>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('communications.manage')): ?><a class="btn btn-g" href="<?php echo e(route('agency-communications.collections', $contextPolicy ? ['scope'=>'pending','agency'=>$contextPolicy->supplier_agency_id,'search'=>$contextPolicy->number] : [])); ?>">Comunica alle agenzie</a><?php endif; ?>
            <a class="btn btn-g" href="<?php echo e(route('titles.index',$contextParameters)); ?>">Titoli</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contextPolicy): ?><a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$contextClient,$contextPolicy,'tab'=>'payments'])); ?>">Scheda polizza</a><?php elseif($contextClient): ?><a class="btn btn-g" href="<?php echo e(route('clients.show',$contextClient)); ?>">Scheda cliente</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contextParameters): ?><a class="btn btn-g" href="<?php echo e(route('collections.index')); ?>">Tutti i movimenti</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
    <?php echo $__env->make('ledger.collection-filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <section data-collection-workspace aria-label="Risultati contabilità">
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['class' => 'collection-summary collection-overview','ariaLabel' => 'Riepilogo contabilità']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'collection-summary collection-overview','aria-label' => 'Riepilogo contabilità']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <dl class="collection-summary-values">
                <div><dt>Incassi</dt><dd data-collection-total="receipts"><?php echo e(\App\Support\Money::format((int)$totals->receipts)); ?></dd></div>
                <div><dt>Rimborsi</dt><dd data-collection-total="refunds"><?php echo e(\App\Support\Money::format((int)$totals->refunds)); ?></dd></div>
                <div><dt>Saldo<span class="sr-only"> incassi meno rimborsi</span></dt><dd data-collection-total="total"><?php echo e(\App\Support\Money::format((int)$totals->total)); ?></dd></div>
            </dl>
            <p class="collection-summary-note">Totali al netto degli storni.</p>
            <details class="collection-cash-details collection-provider-summary"><summary>Riepilogo per compagnia e agenzia</summary>
            <div class="client-table-scroll" role="region" aria-label="Totali per compagnia e agenzia" tabindex="0">
                <table class="collection-group-table"><thead><tr><th>Compagnia / agenzia</th><th>Incassi</th><th>Rimborsi</th><th>Saldo</th><th>Stampa</th></tr></thead>
                    <tbody data-collection-groups>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr><th scope="row"><?php echo e($group->company); ?><small><?php echo e($group->agency); ?></small></th>
                                <td data-label="Incassi"><?php echo e(\App\Support\Money::format((int)$group->receipts)); ?><small><?php echo e($group->receipt_count); ?> <?php echo e((int)$group->receipt_count===1 ? 'incasso' : 'incassi'); ?></small></td>
                                <td data-label="Rimborsi"><?php echo e(\App\Support\Money::format((int)$group->refunds)); ?><small><?php echo e($group->refund_count); ?> <?php echo e((int)$group->refund_count===1 ? 'rimborso' : 'rimborsi'); ?></small></td>
                                <td data-label="Saldo"><?php echo e(\App\Support\Money::format((int)$group->total)); ?></td>
                                <td><button type="button" class="btn btn-g btn-sm" data-collection-export="pdf" data-collection-group="<?php echo e($group->key); ?>" aria-label="Stampa <?php echo e($group->company); ?> / <?php echo e($group->agency); ?>">Stampa / PDF</button></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="5">Nessun movimento valido corrisponde ai filtri.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <details class="collection-cash-details"><summary>Ripartizione per chi ha gestito il pagamento</summary>
                <dl><div><dt>Saldo della nostra agenzia</dt><dd data-collection-total="ours"><?php echo e(\App\Support\Money::format((int)$totals->ours)); ?></dd></div><div><dt>Saldo compagnie e altre agenzie</dt><dd data-collection-total="direct"><?php echo e(\App\Support\Money::format((int)$totals->total-(int)$totals->ours)); ?></dd></div></dl>
            </details>
            </details>
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
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Movimenti','id' => 'movimenti']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Movimenti','id' => 'movimenti']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('headerActions', null, []); ?> <p class="list-results" data-table-page-summary><?php echo e($collections->total()); ?> <?php echo e($collections->total() === 1 ? 'movimento' : 'movimenti'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($collections->hasPages()): ?> · Pagina <?php echo e($collections->currentPage()); ?> di <?php echo e($collections->lastPage()); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p> <?php $__env->endSlot(); ?>
            <div class="collection-list-tools">
                <div data-collection-actions></div>
            </div>
            <div class="client-table-scroll" role="region" aria-label="Movimenti di contabilità" tabindex="0">
                <table class="t-table collection-movements" data-list-details="collections" data-collection-table data-filter-form="ledger-filters" data-table-query="<?php echo e(json_encode($filters)); ?>">
                    <thead><tr><th>Contraente</th><th>Polizza / titolo</th><th>Data</th><th>Importo</th><th>Tipo pagamento</th><th data-list-detail>Compagnia</th><th data-list-detail>Agenzia</th><th data-list-detail>Stato</th><th data-list-detail>Gestito da</th><th data-list-detail>Consulente</th><th data-list-detail>Tipo documento</th><th data-list-detail>Riferimento</th><th data-list-detail>Documenti</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $collections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $title = $collection->title;
                                $contract = $title->contract;
                                $companyName = $contract->company ?: 'Compagnia non indicata';
                                $agencyName = $title->supplierAgency?->name ?: 'Rapporto diretto';
                                $refund = $collection->direction === 'refund';
                                $movementLabel = $refund ? 'Rimborso' : 'Incasso';
                            ?>
                            <tr data-list-label="<?php echo e($movementLabel); ?> di <?php echo e($title->policy->client->name); ?>, <?php echo e($contract->number); ?>, movimento <?php echo e($collection->id); ?>" data-collection-cents="<?php echo e($collection->amount_cents); ?>" data-collection-direction="<?php echo e($collection->direction); ?>" data-collection-recipient="<?php echo e($collection->recipient); ?>" data-collection-voided="<?php echo e($collection->voided_at ? 1 : 0); ?>"
                                data-collection-group="<?php echo e(\App\Services\CollectionSummary::key($title->supplier_company_id,$title->supplier_agency_id,$companyName)); ?>" data-collection-company="<?php echo e($companyName); ?>" data-collection-agency="<?php echo e($agencyName); ?>">
                                <td><a href="<?php echo e(route('clients.show', $title->policy->client)); ?>"><?php echo e($title->policy->client->name); ?></a></td>
                                <td><a href="<?php echo e(route('clients.policies.show', [$title->policy->client_id, $title->policy, 'tab'=>'payments'])); ?>"><?php echo e($contract->number); ?></a><div><a href="<?php echo e(route('titles.show', $title)); ?>#movimenti-del-titolo">Titolo #<?php echo e($title->id); ?></a></div></td>
                                <td><?php echo e($collection->received_on->format('d/m/Y')); ?></td>
                                <td class="collection-amount"><?php echo e(\App\Support\Money::format($refund ? -$collection->amount_cents : $collection->amount_cents)); ?><small data-table-export-ignore><?php echo e($movementLabel); ?><?php echo e($collection->voided_at ? ' stornato' : ''); ?></small></td>
                                <td><?php echo e($collection->payment_method); ?></td>
                                <td><?php echo e($companyName); ?></td><td><?php echo e($agencyName); ?></td>
                                <td><?php echo e($collection->voided_at ? 'Stornato' : $movementLabel.' registrato'); ?></td>
                                <td><?php echo e(\App\Models\InsuranceCollection::RECIPIENTS[$collection->recipient]); ?></td>
                                <td><?php echo e($collection->consultant?->name ?: 'Non indicato'); ?></td>
                                <td><?php echo e(\App\Models\InsuranceCollection::DOCUMENT_TYPES[$collection->document_type]); ?></td>
                                <td><?php echo e($collection->reference ?: 'Non indicato'); ?></td>
                                <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $collection->documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><a href="<?php echo e($document->openUrl()); ?>"><?php echo e($document->original_name); ?></a><br><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><span data-table-export-ignore>Nessun allegato</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="13"><p>Nel periodo scelto non risultano incassi o rimborsi con questi filtri.</p><a class="btn btn-g u-mt-sm" href="<?php echo e(route('titles.index',['state'=>'open'])); ?>">Trova il titolo da incassare</a></td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                    <tfoot><tr><td colspan="13"><div class="collection-table-total"><strong>Saldo dei filtri, esclusi storni</strong><strong data-collection-total="total"><?php echo e(\App\Support\Money::format((int)$totals->total)); ?></strong></div></td></tr></tfoot>
                </table>
            </div>
            <?php echo e($collections->links()); ?>

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
    </section>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/collections.blade.php ENDPATH**/ ?>