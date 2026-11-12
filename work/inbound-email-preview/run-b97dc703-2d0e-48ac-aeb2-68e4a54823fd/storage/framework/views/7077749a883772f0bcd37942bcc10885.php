<?php ($documentation = app(\App\Services\InsuranceDocumentation::class)->summary($client, $policy)); ?>
<section id="policy-documentation" aria-label="Documentazione della polizza">
    <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Documenti richiesti e firme']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Documenti richiesti e firme']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('headerActions', null, []); ?> <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry', $client)): ?><a class="btn btn-p btn-sm" href="<?php echo e(route('clients.document-requests.create', [$client, 'policy'=>$policy->id])); ?>">Checklist e richieste</a><?php endif; ?> <?php $__env->endSlot(); ?>
        <div class="document-checklist-intro">
            <p><?php echo e($client->client_type === 'company' ? 'Azienda' : 'Privato'); ?> · <?php echo e($policy->category); ?><?php echo e($policy->subcategory ? ' / '.$policy->subcategory : ''); ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$documentation['categories']): ?><p class="u-text-meta">Checklist per questo ramo e tipo di cliente non ancora configurata. Le richieste manuali restano disponibili.</p>
            <?php elseif($documentation['missing']): ?><p>Documenti configurati da richiedere: <?php echo e(collect($documentation['missing'])->map(fn($category)=>\App\Models\Attachment::DOCUMENT_CATEGORIES[$category])->join(', ')); ?>.</p>
            <?php else: ?><p class="u-text-meta">Le voci configurate sono presenti nelle richieste. Controlla lo stato dei file e le firme prima di completare la lavorazione.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php echo $__env->make('clients.documents.requests-table', ['documentRequests'=>$documentation['requests']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="document-forms-grid">
            <?php echo $__env->make('insurance-forms.status', ['formPolicy'=>null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('insurance-forms.status', ['formPolicy'=>$policy], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="panel-footer-link"><a href="<?php echo e(route('clients.show', [$client, 'tab'=>'documents'])); ?>">Documenti e richieste dell’anagrafica cliente</a></div>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/insurance-forms/documentation.blade.php ENDPATH**/ ?>