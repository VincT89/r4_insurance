<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Preventivi'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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
 <?php $__env->slot('title', null, []); ?> Preventivi <?php $__env->endSlot(); ?> <?php $__env->slot('meta', null, []); ?> <?php echo e($case ? $case->subject.' · '.$case->client->name : 'Riepilogo delle offerte ricevute e delle risposte attese.'); ?> <?php $__env->endSlot(); ?> <?php $__env->slot('actions', null, []); ?> <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create',\App\Models\Client::class)): ?><a class="btn btn-p" href="<?php echo e(route('quotes.offers.upload', $case ? ['case'=>$case->id] : [])); ?>">Carica offerte</a><?php endif; ?><a class="btn btn-g" href="<?php echo e(route('quotes.index')); ?>">Pratiche preventivo</a> <?php $__env->endSlot(); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($filters['client']??null) && !$case): ?><p>Elenco limitato al cliente selezionato. <a href="<?php echo e(route('quotes.offers')); ?>">Mostra tutti i preventivi</a></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="GET" action="<?php echo e(route('quotes.offers')); ?>" class="client-search-form" aria-label="Filtra offerte" data-list-filters>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case): ?><input type="hidden" name="case" value="<?php echo e($case->id); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filters['client']??null): ?><input type="hidden" name="client" value="<?php echo e($filters['client']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['name' => 'search','label' => 'Cliente, veicolo, pratica o compagnia']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','label' => 'Cliente, veicolo, pratica o compagnia']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<input id="field-search" class="form-in" name="search" value="<?php echo e($filters['search']??''); ?>" maxlength="255"> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['name' => 'verification','label' => 'Verifica dei dati']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'verification','label' => 'Verifica dei dati']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<select id="field-verification" class="form-sel" name="verification"><option value="">Tutti</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['pending'=>'Da verificare','verified'=>'Verificati','missing'=>'PDF mancante']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($key); ?>" <?php if(($filters['verification']??'')===$key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['name' => 'company','label' => 'Compagnia']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'company','label' => 'Compagnia']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<select id="field-company" class="form-sel" name="company"><option value="">Tutte</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($company->id); ?>" <?php if(($filters['company']??'')==$company->id): echo 'selected'; endif; ?>><?php echo e($company->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
        <div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="<?php echo e(route('quotes.offers', \Illuminate\Support\Arr::only($filters,['client','case']))); ?>">Azzera filtri</a></div>
        <details class="search-more offer-filter-options"><summary class="btn btn-g">Altri filtri</summary><div class="search-more-fields"></div></details>
    </form>
    <form method="GET" action="<?php echo e(route('quotes.compare')); ?>" data-quote-offer-selection>
        <div class="quote-list-toolbar">
            <p data-quote-selection-status aria-live="polite">Seleziona da due a sei offerte della stessa pratica.</p>
            <button class="btn btn-p" type="submit" data-quote-compare>Confronta offerte</button>
            <p data-quote-selection-error role="alert" class="u-alert-error" hidden></p>
        </div>
        <table class="quote-offers-table"><caption class="sr-only">Riepilogo dei preventivi</caption><thead><tr><th scope="col" aria-label="Selezione"></th><th scope="col">Cliente e pratica</th><th scope="col">Compagnia e agenzia</th><th scope="col">Premio lordo</th><th scope="col">Copertura e validità</th><th scope="col">Verifica</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $offers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $offer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php ($cells=$comparison->cells($offer)); ?>
            <tr>
                <td class="quote-select-cell"><input type="checkbox" name="quotes[]" value="<?php echo e($offer->id); ?>" data-case="<?php echo e($offer->insurance_quote_case_id); ?>" aria-label="Seleziona <?php echo e($offer->company->name); ?> tramite <?php echo e($offer->agency?->name ?: 'rapporto diretto'); ?>, <?php echo e($offer->quoteCase->subject); ?>" <?php if(!$offer->offer_documents_count): echo 'disabled'; endif; ?>></td>
                <td class="quote-client-cell"><a href="<?php echo e(route('clients.show',$offer->quoteCase->client)); ?>"><?php echo e($offer->quoteCase->client->name); ?></a><small><?php echo e($offer->quoteCase->vehicle_plate ?: $offer->quoteCase->branch->name); ?></small><a class="quote-small-link" href="<?php echo e(route('quotes.show',$offer->insurance_quote_case_id)); ?>"><?php echo e($offer->quoteCase->subject); ?></a></td>
                <td data-label="Compagnia e agenzia"><strong><?php echo e($offer->company->name); ?></strong><small><?php echo e($offer->agency?->name ?: 'Rapporto diretto'); ?></small></td>
                <td data-label="Premio lordo"><strong class="quote-money"><?php echo e($cells['premium']['display']); ?></strong><small>Totale del periodo</small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cells['premium']['uncertain']): ?><small class="quote-warning">Importo da chiarire</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                <td data-label="Copertura e validità"><?php echo e($cells['starts_on']['display']); ?><small>al <?php echo e($cells['expires_on']['display']); ?></small><small>Valida fino al <?php echo e($cells['valid_until']['display']); ?></small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->valid_until?->lt(today())): ?><small class="quote-warning">Offerta scaduta</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                <td data-label="Verifica"><strong><?php echo e(!$offer->offer_documents_count ? 'PDF mancante' : ($offer->comparisonReview?->verified_at ? 'Verificata' : 'Da verificare')); ?></strong><small><?php echo e(\App\Models\InsuranceQuote::STATUSES[$offer->status]); ?></small><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int)$offer->quoteCase->preferred_quote_id===$offer->id): ?><small>Preferita dall’operatore</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($offer->offer_documents_count): ?><a class="quote-small-link" href="<?php echo e(route('quotes.compare',['quotes'=>[$offer->id]])); ?>">Dati e PDF</a><?php else: ?><a class="quote-small-link" href="<?php echo e(route('quotes.offers.upload',['case'=>$offer->insurance_quote_case_id])); ?>">Carica PDF</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
            </tr>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><tr><td colspan="6">Nessun preventivo corrisponde ai filtri. Le offerte restano collegate alle pratiche preventivo.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody></table>
    </form>
    <div class="quote-list-footer"><span><?php echo e($offers->total()); ?> <?php echo e($offers->total() === 1 ? 'preventivo' : 'preventivi'); ?> · Pagina <?php echo e($offers->currentPage()); ?> di <?php echo e($offers->lastPage()); ?></span><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?><a href="<?php echo e(route('ai.index')); ?>">AI e consumi</a><?php endif; ?></div>
    <?php echo e($offers->links()); ?>

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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/quotes/offers.blade.php ENDPATH**/ ?>