<?php
    $allowanceOld = fn($key, $fallback = '') => is_scalar(old($key, $fallback)) ? (string)old($key, $fallback) : (string)$fallback;
    $allowanceTitles = $allowanceTitles->where('direction','receipt')->values();
    $defaultAllowanceTitle = isset($selectedAllowanceTitle) ? (string)$selectedAllowanceTitle : ($allowanceTitles->count() === 1 ? (string)$allowanceTitles->first()->id : '');
    $selectedAllowance = $allowanceTitles->firstWhere('id', $allowanceOld('allowance_title_id', $defaultAllowanceTitle));
    $allowanceOptions = $allowanceTitles->mapWithKeys(fn($row) => [(string)$row->id=>['balance'=>$row->balance_cents, 'company'=>$row->contract->company]])->all();
    $allowanceKey = $allowanceOld('allowance_key');
    if (!\Illuminate\Support\Str::isUuid($allowanceKey)) $allowanceKey = (string)\Illuminate\Support\Str::uuid();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowanceTitles->isNotEmpty()): ?>
<details id="allowance-entry" class="allowance-entry" <?php if(old('allowance_key')): ?> open <?php endif; ?>>
    <summary class="btn btn-g">Registra abbuono</summary>
    <form method="POST" action="<?php echo e($allowanceAction); ?>" class="u-mt-md" data-allowance-form
        x-data="{titles:<?php echo \Illuminate\Support\Js::from($allowanceOptions)->toHtml() ?>,title:<?php echo \Illuminate\Support\Js::from((string)($selectedAllowance?->id ?? ''))->toHtml() ?>,amount:<?php echo \Illuminate\Support\Js::from($allowanceOld('allowance_amount'))->toHtml() ?>,bearer:<?php echo \Illuminate\Support\Js::from($allowanceOld('allowance_borne_by'))->toHtml() ?>,
            remaining(){const value=this.amount.replace(',','.');if(!/^\d+(\.\d{1,2})?$/.test(value))return null;const cents=Math.round(Number(value)*100);return this.titles[this.title] ? this.titles[this.title].balance-cents : null},
            money(cents){return new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(cents/100)}}">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="allowance_key" value="<?php echo e($allowanceKey); ?>">
        <input type="hidden" name="allowance_balance" :value="titles[title]?.balance || ''" value="<?php echo e($selectedAllowance?->balance_cents); ?>">
        <p class="u-text-meta u-mb-md">L’abbuono riduce il residuo senza registrare un incasso e non conferma la copertura della polizza. Registralo prima del pagamento: l’importo da incassare si aggiorna automaticamente.</p>
        <div class="form-row">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($chooseAllowanceTitle): ?>
                <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Titolo dell’abbuono','name' => 'allowance_title_id','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Titolo dell’abbuono','name' => 'allowance_title_id','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <select class="form-sel" name="allowance_title_id" x-model="title" @change="amount=''" required>
                        <option value="">Scegli il titolo</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allowanceTitles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($row->id); ?>" <?php if($selectedAllowance?->id === $row->id): echo 'selected'; endif; ?>>#<?php echo e($row->id); ?> / <?php echo e($row->due_on->format('d/m/Y')); ?> / <?php echo e($row->reference ?: \App\Models\InsuranceTitle::KINDS[$row->kind]); ?> / Residuo <?php echo e(\App\Support\Money::format($row->balance_cents)); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Importo dell’abbuono (€)','name' => 'allowance_amount','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Importo dell’abbuono (€)','name' => 'allowance_amount','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <input class="form-in" name="allowance_amount" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,2})?" maxlength="13" x-model="amount" value="<?php echo e($allowanceOld('allowance_amount')); ?>" required aria-describedby="allowance-balance-help">
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Data dell’abbuono','name' => 'allowance_on','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Data dell’abbuono','name' => 'allowance_on','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<input class="form-in" type="date" name="allowance_on" value="<?php echo e($allowanceOld('allowance_on',today()->toDateString())); ?>" max="<?php echo e(today()->toDateString()); ?>" required> <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Chi sostiene l’abbuono','name' => 'allowance_borne_by','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Chi sostiene l’abbuono','name' => 'allowance_borne_by','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <select class="form-sel" name="allowance_borne_by" x-model="bearer" required><option value="">Scegli chi lo sostiene</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\InsuranceAllowance::BEARERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($value); ?>" <?php if($allowanceOld('allowance_borne_by') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                <p class="u-text-meta u-mt-sm" x-show="bearer==='company' &amp;&amp; title" x-cloak x-text="titles[title]?.company"></p>
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
            <div x-show="bearer==='agency'" x-cloak>
                <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Agenzia che sostiene l’abbuono','name' => 'allowance_agency_id','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Agenzia che sostiene l’abbuono','name' => 'allowance_agency_id','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<select class="form-sel" name="allowance_agency_id" :disabled="bearer!=='agency'" :required="bearer==='agency'"><option value="">Scegli l’agenzia</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allowanceAgencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($agency->id); ?>" <?php if($allowanceOld('allowance_agency_id') === (string)$agency->id): echo 'selected'; endif; ?>><?php echo e($agency->name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select> <?php echo $__env->renderComponent(); ?>
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
        </div>
        <p id="allowance-balance-help" class="u-text-meta u-mb-md" aria-live="polite" x-text="!title ? 'Scegli il titolo per vedere il residuo.' : (remaining() === null ? 'Residuo del titolo: '+money(titles[title].balance) : (remaining() &lt; 0 ? 'L’abbuono supera il residuo del titolo.' : 'Dopo l’abbuono restano '+money(remaining())+' da incassare.'))"></p>
        <div class="form-row full">
            <?php if (isset($component)) { $__componentOriginal9855f61cf324bb44a86bed9db080852c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9855f61cf324bb44a86bed9db080852c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Motivo dell’abbuono','name' => 'allowance_reason','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Motivo dell’abbuono','name' => 'allowance_reason','required' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<textarea class="form-ta" name="allowance_reason" rows="2" minlength="5" maxlength="1000" required><?php echo e($allowanceOld('allowance_reason')); ?></textarea> <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-group','data' => ['label' => 'Riferimento della comunicazione (facoltativo)','name' => 'allowance_reference']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Riferimento della comunicazione (facoltativo)','name' => 'allowance_reference']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<input class="form-in" name="allowance_reference" maxlength="150" value="<?php echo e($allowanceOld('allowance_reference')); ?>"> <?php echo $__env->renderComponent(); ?>
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
        <div class="insurance-form-actions"><button class="btn btn-p" :disabled="!title || remaining() === null || remaining() &lt; 0 || remaining() === titles[title]?.balance">Registra abbuono</button></div>
    </form>
</details>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/allowance-form.blade.php ENDPATH**/ ?>