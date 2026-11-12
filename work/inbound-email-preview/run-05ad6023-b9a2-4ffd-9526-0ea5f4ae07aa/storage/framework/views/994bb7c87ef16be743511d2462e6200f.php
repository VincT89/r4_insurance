<?php
    $paymentOld = fn($key, $fallback = '') => is_scalar(old($key, $fallback)) ? (string) old($key, $fallback) : (string) $fallback;
    $defaultTitle = isset($selectedPaymentTitle) ? (string) $selectedPaymentTitle : ($collectionTitles->count() === 1 ? (string) $collectionTitles->first()->id : '');
    $selectedTitle = $chooseTitle ? $paymentOld('title_id', $defaultTitle) : $defaultTitle;
    $selected = $collectionTitles->first(fn($row) => (string) $row->id === $selectedTitle);
    $selectedTitle = $selected ? $selectedTitle : '';
    $titleOptions = $collectionTitles->mapWithKeys(fn($row) => [(string) $row->id => ['amount'=>\App\Support\Money::decimal($row->balance_cents), 'method'=>$row->payment_method, 'direction'=>$row->direction, 'agency'=>(bool)$row->supplier_agency_id,'coverage'=>'Periodo '.$row->coverage_from->format('d/m/Y').' – '.$row->coverage_to->format('d/m/Y')]])->all();
    $initialMethod = $paymentOld('payment_method', $selected?->payment_method ?? '');
    if ($selected?->direction === 'refund' && !in_array($initialMethod, \App\Models\InsuranceCollection::REFUND_METHODS,true)) $initialMethod = '';
    $initialAmount = $selected ? \App\Support\Money::decimal($selected->balance_cents) : '';
    $initialRecipient = $paymentOld('recipient', str_contains($initialMethod,'a Compagnia') ? 'company' : 'our_agency');
    $paymentKey = $paymentOld('idempotency_key');
    if (!\Illuminate\Support\Str::isUuid($paymentKey)) $paymentKey = (string) \Illuminate\Support\Str::uuid();
?>
<form method="POST" enctype="multipart/form-data" action="<?php echo e($collectionAction); ?>" class="collection-entry" data-collection-entry
    @submit="busy=true" :aria-busy="busy.toString()"
    x-data="{busy:false,receiptMethods:<?php echo \Illuminate\Support\Js::from(\App\Models\InsurancePolicy::PAYMENT_METHODS)->toHtml() ?>,refundMethods:<?php echo \Illuminate\Support\Js::from(\App\Models\InsuranceCollection::REFUND_METHODS)->toHtml() ?>,titles:<?php echo \Illuminate\Support\Js::from($titleOptions)->toHtml() ?>,title:<?php echo \Illuminate\Support\Js::from($selectedTitle)->toHtml() ?>,amount:<?php echo \Illuminate\Support\Js::from($initialAmount)->toHtml() ?>,method:<?php echo \Illuminate\Support\Js::from($initialMethod)->toHtml() ?>,recipient:<?php echo \Illuminate\Support\Js::from($initialRecipient)->toHtml() ?>,
        selectTitle(){this.amount=this.titles[this.title]?.amount||'';this.method=this.titles[this.title]?.method||'';if(this.titles[this.title]?.direction==='refund' &amp;&amp; !this.refundMethods.includes(this.method))this.method='';this.selectMethod()},
        selectMethod(){if(this.method.includes('a Compagnia'))this.recipient='company';else if(this.method.includes('a Intermediario') &amp;&amp; this.recipient==='company')this.recipient='our_agency'}}">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="idempotency_key" value="<?php echo e($paymentKey); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($chooseTitle): ?>
    <div class="form-row full collection-title-row">
            <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Titolo da saldare','name' => 'title_id','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Titolo da saldare','name' => 'title_id','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <select class="form-sel" name="title_id" x-model="title" @change="selectTitle()" required>
                    <option value="">Scegli il titolo</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $collectionTitles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paymentTitle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($paymentTitle->id); ?>" <?php if($selectedTitle === (string) $paymentTitle->id): echo 'selected'; endif; ?>><?php echo e($paymentTitle->direction === 'refund' ? 'Rimborso / ' : ''); ?><?php echo e($paymentTitle->due_on->format('d/m/Y')); ?> / <?php echo e($paymentTitle->reference ?: \App\Models\InsuranceTitle::KINDS[$paymentTitle->kind]); ?> / <?php echo e(\App\Support\Money::format($paymentTitle->balance_cents)); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactCollection ?? false): ?><p class="collection-title-context" x-show="title" x-text="titles[title]?.coverage || ''"></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="form-row collection-amount-row">
        <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Importo del pagamento (€)','name' => 'amount','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Importo del pagamento (€)','name' => 'amount','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactCollection ?? false): ?><span class="collection-readable-amount" :class="{'is-empty': !amount}" aria-hidden="true" x-text="amount ? Number(amount).toLocaleString('it-IT',{style:'currency',currency:'EUR'}) : 'Scegli il titolo'"><?php echo e($selected ? \App\Support\Money::format($selected->balance_cents) : 'Scegli il titolo'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <input class="form-in <?php echo e(($compactCollection ?? false) ? 'collection-raw-amount' : ''); ?>" type="number" step="0.01" min="0.01" name="amount" x-model="amount" value="<?php echo e($initialAmount); ?>" inputmode="decimal" readonly required <?php if($compactCollection ?? false): ?> tabindex="-1" <?php endif; ?> aria-describedby="payment-amount-help">
         <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Data effettiva del pagamento','name' => 'received_on','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Data effettiva del pagamento','name' => 'received_on','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <input class="form-in" type="date" name="received_on" value="<?php echo e($paymentOld('received_on',today()->toDateString())); ?>" max="<?php echo e(today()->toDateString()); ?>" required>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
    </div>
    <div class="form-row collection-method-row">
        <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Modalità di pagamento','name' => 'payment_method','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Modalità di pagamento','name' => 'payment_method','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <select name="payment_method" class="form-sel" x-model="method" @change="selectMethod()" required>
                <option value="">Scegli la modalità</option>
                <template x-for="item in (titles[title]?.direction === 'refund' ? refundMethods : receiptMethods)" :key="item"><option :value="item" x-text="item" :selected="item === method"></option></template>
            </select>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
        <div class="form-g"><label class="form-lbl" for="payment-recipient" x-text="titles[title]?.direction === 'refund' ? 'Chi ha effettuato il rimborso al cliente' : 'Chi ha ricevuto il pagamento'"></label>
            <select id="payment-recipient" name="recipient" class="form-sel" x-model="recipient" required>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\InsuranceCollection::RECIPIENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option x-show="<?php echo \Illuminate\Support\Js::from($value)->toHtml() ?> !== 'agency' || titles[title]?.agency" value="<?php echo e($value); ?>" <?php if($initialRecipient === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>
    </div>
    <p class="u-text-meta" id="payment-amount-help"><span x-text="titles[title]?.direction === 'refund' ? 'Stai registrando l’intero rimborso al cliente.' : 'L’importo è compilato automaticamente e salda tutto il residuo del titolo scelto.'">L’importo è compilato automaticamente e salda tutto il residuo del titolo scelto.</span> Indica il giorno in cui è avvenuto il pagamento.</p>
    <details class="u-mt-md" <?php if(collect(['document_type','reference','notes','consultant_id','files'])->contains(fn($field) => $errors->has($field) || $errors->has($field.'.*'))): ?> open <?php endif; ?>>
        <summary>Riferimenti e documenti facoltativi</summary>
        <div class="form-row u-mt-md">
            <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Tipo di registrazione o documento','name' => 'document_type']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Tipo di registrazione o documento','name' => 'document_type']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <select name="document_type" class="form-sel">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\InsuranceCollection::DOCUMENT_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($value); ?>" <?php if($paymentOld('document_type','record') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
             <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Riferimento del pagamento','name' => 'reference']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Riferimento del pagamento','name' => 'reference']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<input class="form-in" name="reference" maxlength="150" value="<?php echo e($paymentOld('reference')); ?>"> <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Documenti di pagamento (PDF)','name' => 'files']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Documenti di pagamento (PDF)','name' => 'files']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<input class="form-in" type="file" name="files[]" multiple accept="application/pdf,.pdf"> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('system.admin')): ?>
                <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Consulente','name' => 'consultant_id']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Consulente','name' => 'consultant_id']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<select class="form-sel" name="consultant_id"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $consultants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($user->id); ?>" <?php if($paymentOld('consultant_id',auth()->id()) === (string) $user->id): echo 'selected'; endif; ?>><?php echo e($user->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
            <?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Note','name' => 'notes']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Note','name' => 'notes']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<textarea class="form-ta" name="notes" rows="2" maxlength="5000"><?php echo e($paymentOld('notes')); ?></textarea> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $attributes = $__attributesOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__attributesOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9855f61cf324bb44a86bed9db080852c)): ?>
<?php $component = $__componentOriginal9855f61cf324bb44a86bed9db080852c; ?>
<?php unset($__componentOriginal9855f61cf324bb44a86bed9db080852c); ?>
<?php endif; ?>
        </div>
    </details>
    <div class="insurance-form-actions"><button class="btn btn-p" :disabled="busy || !title || !amount" x-text="busy ? 'Registrazione…' : (titles[title]?.direction === 'refund' ? <?php echo \Illuminate\Support\Js::from(($compactCollection ?? false) ? 'Conferma rimborso' : 'Registra rimborso al cliente')->toHtml() ?> : <?php echo \Illuminate\Support\Js::from(($compactCollection ?? false) ? 'Conferma incasso' : 'Registra pagamento')->toHtml() ?>)"><?php echo e(($compactCollection ?? false) ? 'Conferma incasso' : 'Registra pagamento'); ?></button></div>
</form>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/collection-form.blade.php ENDPATH**/ ?>