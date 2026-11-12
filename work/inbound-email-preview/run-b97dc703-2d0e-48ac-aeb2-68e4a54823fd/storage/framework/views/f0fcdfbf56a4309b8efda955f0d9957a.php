<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Polizza '.$policy->number] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="policy-desk" data-policy-record data-list-user="<?php echo e(auth()->id()); ?>" data-policy-list-url="<?php echo e(route('policies.index')); ?>">
    <?php
        $canTransition = !$policy->companyChangeTo && !$policy->replacementTo && !$policy->hasPendingReplacement();
        $receiptDue = $policyTitles->where('direction','receipt')->reject(fn ($title) => $title->cancelled_at)->sum('balance_cents');
        $refundDue = $policyTitles->where('direction','refund')->reject(fn ($title) => $title->cancelled_at)->sum('balance_cents');
        $policySections = ['overview'=>'Dati polizza', 'payments'=>'Titoli e pagamenti', 'documents'=>'Contratto e documenti', 'forms'=>'Moduli e firme', 'history'=>'Storico e lavorazione'];
        if (auth()->user()->can('system.admin')) $policySections['commissions'] = 'Provvigioni';
    ?>
    <div class="policy-breadcrumbs"><a href="<?php echo e(route('policies.index')); ?>" data-policy-list-return>Elenco polizze</a><span>/</span><a href="<?php echo e(route('clients.show',[$client,'tab'=>'policies'])); ?>">Cliente: <?php echo e($client->name); ?></a></div>
    <header class="policy-file-heading">
        <div><p class="policy-file-client"><?php echo e($client->name); ?></p><h1>Polizza <?php echo e($policy->number); ?></h1></div>
        <div class="policy-file-actions">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payableTitles->isNotEmpty()): ?><a class="btn btn-p" data-policy-primary-payment data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'payments']).'#payment-entry'); ?>" <?php if($policyTab==='payments'): ?> hidden <?php endif; ?>><?php echo e($receiptDue>0 ? 'Incassa' : 'Registra rimborso'); ?></a>
                <?php elseif(!$hasTitles && in_array($policy->status,['active','renewed'],true)): ?><a class="btn btn-p" href="<?php echo e(route('titles.schedule',$policy)); ?>">Prepara titoli</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canTransition && in_array($policy->status,['active','renewed','expired'],true)): ?><a class="btn btn-g" href="<?php echo e(route('titles.renewal',$policy)); ?>">Rinnova o cambia compagnia</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?> <?php endif; ?>
            <details class="policy-other-actions"><summary>Altre operazioni</summary><div>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->editableBy(auth()->user())): ?><a href="<?php echo e(route('clients.policies.edit',[$client,$policy])); ?>">Modifica polizza</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canTransition && in_array($policy->status,['active','renewed','suspended'],true)): ?><a href="<?php echo e(route('policy-transitions.create',[$policy,'replacement'])); ?>">Sostituisci</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endif; ?>
                    <a href="<?php echo e(route('claims.create',['policy'=>$policy->id])); ?>">Nuovo sinistro</a>
                <?php endif; ?>
                <a data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'history'])); ?>">Lavorazione e sostituzioni</a>
            </div></details>
        </div>
    </header>
    <div class="policy-file-context">
        <span><?php echo e($policy->company); ?> · <?php echo e($policy->category); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->vehicle): ?> · <?php echo e($policy->vehicle->plate); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['policy-file-state','is-alert'=>$policy->is_expired]); ?>"><?php echo e($policy->status_label); ?></span>
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
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->contracts->count()===1 && $policy->contracts->first()->mime_type==='application/pdf' && !$policy->contracts->first()->latestTabletSignature): ?><a href="<?php echo e(route('station-signing.create',$policy->contracts->first())); ?>">Firma contratto sul tablet</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->contracts->isEmpty()): ?><a href="<?php echo e(route('clients.documents.create',[$client,'policy'=>$policy->id,'category'=>'policy'])); ?>">Carica contratto</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php echo $__env->make('clients.policies.contract-lineage', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="policy-desk-workspace" data-policy-workspace data-current-section="<?php echo e($policyTab); ?>" data-guide-payments="<?php echo e($payableTitles->isNotEmpty() ? 'open' : ($hasTitles ? 'settled' : 'unprepared')); ?>">
    <nav class="policy-section-nav" aria-label="Sezioni della polizza">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $policySections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><a data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>$key])); ?>" <?php if($policyTab===$key): ?> aria-current="page" <?php endif; ?>><?php echo e($label); ?></a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </nav>
    <div class="policy-desk-body">
    <section data-policy-section="overview" aria-label="Dati polizza" <?php if($policyTab!=='overview'): ?> hidden <?php endif; ?>>
        <section class="practice-actions" aria-label="Attività da completare">
            <h2>Da completare</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $workTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="practice-action"><p><?php echo e($task['detail']); ?></p><a class="btn btn-g btn-sm" data-policy-section-link href="<?php echo e($task['url']); ?>"><?php echo e($task['label']); ?></a></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><p class="u-text-meta">Nessuna attività documentale aperta. Puoi consultare dati, pagamenti e storico dalle sezioni della polizza.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <div class="policy-facts" id="policy-overview">
            <h2>Il contratto</h2>
            <dl class="policy-key-facts">
                <div><dt>Decorrenza</dt><dd><?php echo e($policy->starts_on->format('d/m/Y')); ?></dd></div>
                <div><dt>Scadenza</dt><dd><?php if (isset($component)) { $__componentOriginal056eb8c62f283da78c4e1c4a0ef8e3a7 = $component; } ?>
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
<?php endif; ?></dd></div>
                <div><dt>Premio di polizza</dt><dd><?php echo e($policy->premium !== null ? number_format((float)$policy->premium,2,',','.').' €' : 'Non indicato'); ?></dd></div>
                <div><dt>Frazionamento</dt><dd><?php echo e($policy->payment_frequency ?: 'Non indicato'); ?></dd></div>
                <div><dt>Compagnia</dt><dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->supplierCompany && auth()->user()->can('view',$policy->supplierCompany)): ?><a href="<?php echo e(route('supplier-companies.show',$policy->supplierCompany)); ?>"><?php echo e($policy->company); ?></a><?php else: ?> <?php echo e($policy->company); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                <div><dt>Agenzia</dt><dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->supplierAgency && auth()->user()->can('view',$policy->supplierAgency)): ?><a href="<?php echo e(route('suppliers.show',$policy->supplierAgency)); ?>"><?php echo e($policy->supplierAgency->name); ?></a><?php else: ?> <?php echo e($policy->supplierAgency?->name ?: 'Non collegata'); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                <div><dt>Ramo</dt><dd><?php echo e($policy->category); ?></dd></div>
                <div><dt>Sottoramo</dt><dd><?php echo e($policy->subcategory ?: 'Da specificare'); ?></dd></div>
            </dl>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->vehicle || $policy->asset || $policy->insured_subject): ?>
        <div class="policy-facts">
            <h2>Cosa è assicurato</h2>
            <dl class="policy-key-facts">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->vehicle): ?><div><dt>Veicolo / targa</dt><dd><?php echo e($policy->vehicle->plate); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->vehicle->make || $policy->vehicle->model): ?><small><?php echo e($policy->vehicle->make); ?> <?php echo e($policy->vehicle->model); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->asset): ?><div><dt>Immobile o altro bene collegato</dt><dd><a href="<?php echo e(route('clients.show',[$client,'tab'=>'assets']).'#assets-'.$policy->asset->id); ?>"><?php echo e($policy->asset->name); ?></a></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->insured_subject): ?><div><dt>Bene o soggetto assicurato</dt><dd><?php echo e($policy->insured_subject); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->merit_class_cu!==null): ?><div><dt>Classe universale CU</dt><dd><?php echo e($policy->merit_class_cu); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </dl>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <details class="policy-extra-facts">
            <summary>Altri dati del contratto</summary>
            <dl class="policy-key-facts">
                <div><dt>Nome del prodotto</dt><dd><?php echo e($policy->product ?: 'Non indicato'); ?></dd></div>
                <div><dt>Classe universale CU</dt><dd><?php echo e($policy->merit_class_cu ?? 'Non indicata / non applicabile'); ?></dd></div>
                <div><dt>Classe della compagnia</dt><dd><?php echo e($policy->merit_class_internal ?: 'Non indicata / non applicabile'); ?></dd></div>
                <div><dt>Tacito rinnovo</dt><dd><?php echo e($policy->tacit_renewal === null ? 'Non indicato' : ($policy->tacit_renewal ? 'Sì' : 'No')); ?></dd></div>
                <div><dt>Metodo di pagamento abituale</dt><dd><?php echo e($policy->payment_method ?: 'Non indicato'); ?></dd></div>
                <div><dt>Premio rata</dt><dd><?php echo e($policy->installment_premium !== null ? number_format((float)$policy->installment_premium,2,',','.').' €' : 'Non indicato'); ?></dd></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->last_renewed_at): ?><div><dt>Ultimo rinnovo registrato</dt><dd><?php echo e($policy->last_renewed_at->format('d/m/Y H:i')); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div><dt>Stato pagamento</dt><dd><?php echo e($policy->payment_status_label); ?></dd></div>
            </dl>
        </details>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->notes): ?><div class="policy-facts"><h2>Note</h2><p class="u-whitespace-pre-wrap"><?php echo e($policy->notes); ?></p></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="policy-related-links">
            <a href="<?php echo e(route('claims.index',['policy'=>$policy->id])); ?>">Consulta sinistri</a>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?><a href="<?php echo e(route('claims.create',['policy'=>$policy->id])); ?>">Nuovo sinistro</a><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->insurance_quote_id): ?><a href="<?php echo e(route('quotes.show',[$policy->quote->insurance_quote_case_id,'proposal'=>$policy->insurance_quote_id])); ?>#proposal-<?php echo e($policy->insurance_quote_id); ?>">Preventivo e approvazione del cliente</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry=$policy->importEntry): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?><a href="<?php echo e(route('insurance-import.policy-source',$entry)); ?>">Origine dati</a><?php endif; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </section>
    <section data-policy-section="documents" aria-label="Contratto e documenti" <?php if($policyTab!=='documents'): ?> hidden <?php endif; ?>>
        <div class="policy-section-actions">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?>
                <?php ($openForms = $signatures['toSign']->concat($signatures['toReview'])->concat($signatures['previous'])); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openForms->count() === 1): ?>
                    <a class="btn btn-p" href="<?php echo e($openForms->first()->signatureActionUrl()); ?>"><?php echo e($openForms->first()->signatureActionLabel()); ?></a>
                <?php elseif($openForms->isNotEmpty()): ?>
                    <a class="btn btn-p" data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'forms']).'#policy-signatures'); ?>">Moduli da firmare o controllare</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a class="btn <?php echo e($openForms->isEmpty() ? 'btn-p' : 'btn-g'); ?>" href="<?php echo e(route('clients.documents.create', [$client, 'policy'=>$policy->id])); ?>"><?php echo e($openForms->isEmpty() ? 'Carica documento' : 'Carica altro documento'); ?></a>
            <?php endif; ?>
            <a class="btn btn-g" href="<?php echo e(route('clients.show', [$client, 'tab'=>'documents'])); ?>">Tutti i documenti del cliente</a>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($policy->contracts->isNotEmpty()): ?>
            <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Contratti e firme','padded' => true,'id' => 'policy-contracts']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Contratti e firme','padded' => true,'id' => 'policy-contracts']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo $__env->make('document-signing.contract-list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->renderComponent(); ?>
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
        <?php if (isset($component)) { $__componentOriginal36665f0dc0e45320e21db1e20a989acf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36665f0dc0e45320e21db1e20a989acf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.panel','data' => ['title' => 'Documenti della polizza','id' => 'policy-documents']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Documenti della polizza','id' => 'policy-documents']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo $__env->make('clients.documents.table', ['documents'=>$policy->documents, 'documentContextPolicy'=>$policy], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $attributes = $__attributesOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__attributesOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36665f0dc0e45320e21db1e20a989acf)): ?>
<?php $component = $__componentOriginal36665f0dc0e45320e21db1e20a989acf; ?>
<?php unset($__componentOriginal36665f0dc0e45320e21db1e20a989acf); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginala36f48b8cf8f1fc0a479a5f5d71b8681 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala36f48b8cf8f1fc0a479a5f5d71b8681 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.inbound-emails','data' => ['client' => $client,'policy' => $policy]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('inbound-emails'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['client' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($client),'policy' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($policy)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala36f48b8cf8f1fc0a479a5f5d71b8681)): ?>
<?php $attributes = $__attributesOriginala36f48b8cf8f1fc0a479a5f5d71b8681; ?>
<?php unset($__attributesOriginala36f48b8cf8f1fc0a479a5f5d71b8681); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala36f48b8cf8f1fc0a479a5f5d71b8681)): ?>
<?php $component = $__componentOriginala36f48b8cf8f1fc0a479a5f5d71b8681; ?>
<?php unset($__componentOriginala36f48b8cf8f1fc0a479a5f5d71b8681); ?>
<?php endif; ?>
    </section>
    <section data-policy-section="payments" aria-label="Titoli e pagamenti" <?php if($policyTab!=='payments'): ?> hidden <?php endif; ?>>
        <?php echo $__env->make('ledger.recorded-payment', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('clients.policies.accounting-review', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $settledAllowanceTitles->filter(fn($row) => !$row->coverage_confirmation_valid); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $coverageTitle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php echo $__env->make('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['policy-payment-layout','is-settled'=>$payableTitles->isEmpty()]); ?>" id="payments">
            <div class="policy-entry-column">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payableTitles->isNotEmpty()): ?>
                    <section id="payment-entry" class="policy-money-entry" aria-label="Registra pagamento">
                        <h2>Registra pagamento</h2>
                        <p class="policy-money-context"><?php echo e($client->name); ?> · <?php echo e($policy->number); ?></p>
                        <?php echo $__env->make('ledger.collection-form',['collectionPolicy'=>$policy,'collectionTitles'=>$payableTitles,'chooseTitle'=>true,'collectionAction'=>route('policies.collect',$policy),'selectedPaymentTitle'=>$selectedPaymentTitle,'compactCollection'=>true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </section>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('updateRegistry',$client)): ?>
                        <?php echo $__env->make('ledger.allowance-form',['allowanceTitles'=>$payableTitles,'chooseAllowanceTitle'=>true,'allowanceAction'=>route('policies.allowances',$policy),'selectedAllowanceTitle'=>$selectedPaymentTitle], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?> <?php endif; ?>
                <?php else: ?>
                    <div class="policy-payment-empty"><h2>Pagamenti</h2><p>Non ci sono titoli con un residuo da registrare.</p></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="policy-payment-ledger">
                <?php echo $__env->make('clients.policies.titles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <div class="policy-related-links">
                    <a href="<?php echo e(route('titles.index',['policy'=>$policy->id])); ?>">Elenco completo titoli</a>
                    <a href="<?php echo e(route('collections.index',['policy'=>$policy->id,'month'=>''])); ?>">Contabilità della polizza</a>
                    <a href="<?php echo e(route('allowances.index',['policy'=>$policy->id,'month'=>''])); ?>">Abbuoni della polizza</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('ledger.manage')): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$hasTitles && in_array($policy->status,['active','renewed'],true)): ?><a href="<?php echo e(route('titles.schedule',$policy)); ?>">Prepara titoli</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!in_array($policy->status,\App\Models\InsurancePolicy::CLOSED_STATUSES,true)): ?><a href="<?php echo e(route('titles.create',$policy)); ?>">Titolo manuale</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?>
                </div>
                <details class="policy-payment-coverage"><summary>Copertura risultante dai titoli</summary>
                    <dl class="client-registry-details"><div><dt>Copertura risultante fino al</dt><dd><?php echo e($policy->coverage_until?->format('d/m/Y') ?: 'Non determinata dai titoli'); ?></dd></div></dl>
                    <p class="u-text-meta">La data deriva da periodi consecutivi interamente incassati oppure saldati con abbuono e copertura confermata separatamente. Per l’effettiva operatività valgono lo stato e le condizioni del contratto.</p>
                </details>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settledAllowanceTitles->contains(fn($row) => $row->coverage_confirmation_valid)): ?>
                    <details class="policy-payment-coverage"><summary>Coperture confermate con abbuono</summary>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $settledAllowanceTitles->filter(fn($row) => $row->coverage_confirmation_valid); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $coverageTitle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php echo $__env->make('ledger.allowance-coverage',['coverageTitle'=>$coverageTitle,'allowanceOrigin'=>'policy'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <p class="policy-payment-reference">Frazionamento: <?php echo e($policy->payment_frequency ?: 'Non indicato'); ?> · Metodo abituale: <?php echo e($policy->payment_method ?: 'Non indicato'); ?></p>
            </div>
        </div>
    </section>
    <section data-policy-section="forms" id="policy-forms" aria-label="Moduli e firme" <?php if($policyTab!=='forms'): ?> hidden <?php endif; ?>><?php echo $__env->make('insurance-forms.policy-summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></section>
    <section data-policy-section="history" id="policy-history" aria-label="Storico e lavorazione" <?php if($policyTab!=='history'): ?> hidden <?php endif; ?>>
        <?php echo $__env->make('clients.policies.workflow', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('policy-changes.history', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('clients.policies.corrections', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?>
        <?php if (isset($component)) { $__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.delete-modal','data' => ['action' => ''.e(route('clients.policies.destroy', [$client, $policy])).'','title' => 'Elimina polizza','message' => 'La polizza può essere eliminata solo se priva di lavorazioni chiuse, segnalazioni, titoli, preventivi, documenti e altri collegamenti storici. Per conservarla, impostala come cessata.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('delete-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => ''.e(route('clients.policies.destroy', [$client, $policy])).'','title' => 'Elimina polizza','message' => 'La polizza può essere eliminata solo se priva di lavorazioni chiuse, segnalazioni, titoli, preventivi, documenti e altri collegamenti storici. Per conservarla, impostala come cessata.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<button type="button" class="btn btn-g btn-danger-outline">Elimina polizza</button> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd)): ?>
<?php $attributes = $__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd; ?>
<?php unset($__attributesOriginalb7eac87efb73c0c2c26fe03ec80faafd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd)): ?>
<?php $component = $__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd; ?>
<?php unset($__componentOriginalb7eac87efb73c0c2c26fe03ec80faafd); ?>
<?php endif; ?>
    <?php endif; ?>
    </section>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?><section data-policy-section="commissions" aria-label="Provvigioni" <?php if($policyTab!=='commissions'): ?> hidden <?php endif; ?>><?php echo $__env->make('commissions.summary', ['commissionPolicy'=>$policy], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></section><?php endif; ?>
    <aside class="policy-work-rail" data-policy-work-rail <?php if(!in_array($policyTab,['overview','forms'],true)): ?> hidden <?php endif; ?> aria-label="Attività della polizza">
        <div data-policy-overview-only <?php if($policyTab!=='overview'): ?> hidden <?php endif; ?>>
            <div class="policy-balance">
                <h2>Pagamenti</h2>
                <dl><div><dt>Da incassare</dt><dd><?php echo e($hasTitles ? \App\Support\Money::format($receiptDue) : 'Non determinato'); ?></dd></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundDue>0): ?><div><dt>Da rimborsare</dt><dd><?php echo e(\App\Support\Money::format($refundDue)); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dl>
                <p><?php echo e($hasTitles ? 'Residui dei titoli aperti, comprese le scadenze future.' : 'Non sono presenti titoli per questa polizza.'); ?></p>
                <a class="policy-rail-link" data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'payments'])); ?>">Vedi titoli e movimenti</a>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settledAllowanceTitles->contains(fn($title) => !$title->coverage_confirmation_valid)): ?><p class="policy-coverage-notice">Ci sono titoli saldati con abbuono: <a data-policy-section-link href="<?php echo e(route('clients.policies.show',[$client,$policy,'tab'=>'payments'])); ?>">controlla la copertura da confermare</a>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formReminder): ?><div class="policy-module-choice"><h2>Scelta dei moduli</h2><p>La scelta dei moduli è ancora da completare.</p><a class="btn btn-g" href="<?php echo e(\App\Support\NotificationDestination::local($formReminder->data['url'])); ?>">Scegli i moduli</a></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php echo $__env->make('insurance-forms.policy-signatures', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </aside>
    </div>
    </div>
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
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/policies/show.blade.php ENDPATH**/ ?>