<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previous=$policy->companyChangeFrom): ?>
    <div class="policy-contract-lineage">
        <div><strong>Polizza precedente</strong><p><?php echo e($previous->sourcePolicy->company); ?> / <?php echo e($previous->sourcePolicy->number); ?></p></div>
        <a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$client,$previous->sourcePolicy])); ?>">Apri polizza precedente</a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacement=$policy->replacementFrom): ?>
    <div class="policy-contract-lineage">
        <div><strong>Polizza precedente</strong><p><?php echo e($replacement->sourcePolicy->company); ?> / <?php echo e($replacement->sourcePolicy->number); ?></p><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacement->replacementSchedule?->state==='pending'): ?><p>Il cambio è programmato dal <?php echo e($replacement->effective_on->format('d/m/Y')); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$client,$replacement->sourcePolicy])); ?>">Apri polizza precedente</a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacement=$policy->replacementTo): ?>
    <div class="policy-contract-lineage">
        <div><strong><?php echo e($replacement->replacementSchedule?->state==='pending' ? 'Sostituzione programmata' : 'Polizza sostitutiva'); ?></strong><p><?php echo e($replacement->resultPolicy->company); ?> / <?php echo e($replacement->resultPolicy->number); ?> / Dal <?php echo e($replacement->effective_on->format('d/m/Y')); ?></p></div>
        <div class="insurance-form-actions"><a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$client,$replacement->resultPolicy])); ?>">Apri nuova polizza</a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacement->replacementSchedule?->state==='pending'): ?><a class="btn btn-g" href="<?php echo e(route('clients.policy-changes.show',[$client,$replacement])); ?>">Gestisci programmazione</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($following=$policy->companyChangeTo): ?>
    <div class="policy-contract-lineage">
        <div><strong>Nuova polizza alla scadenza</strong><p><?php echo e($following->resultPolicy->company); ?> / <?php echo e($following->resultPolicy->number); ?> / Dal <?php echo e($following->effective_on->format('d/m/Y')); ?></p></div>
        <a class="btn btn-g" href="<?php echo e(route('clients.policies.show',[$client,$following->resultPolicy])); ?>">Apri nuova polizza</a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/clients/policies/contract-lineage.blade.php ENDPATH**/ ?>