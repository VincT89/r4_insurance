<?php ($recorded = session('recorded_payment')); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recorded && (int)$recorded['policy_id'] === (int)$policy->id): ?>
    <div class="recorded-payment">
        <span><?php echo e($recorded['refund'] ? 'Rimborso' : 'Incasso'); ?> del <?php echo e(\Illuminate\Support\Carbon::parse($recorded['date'])->format('d/m/Y')); ?> · <?php echo e($recorded['amount']); ?> · <?php echo e($recorded['method']); ?></span>
        <a class="btn btn-g" href="<?php echo e(route('collections.index', ['policy'=>$policy->id, 'from'=>$recorded['date'], 'to'=>$recorded['date']])); ?>#movimenti">Vedi in contabilità</a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/ledger/recorded-payment.blade.php ENDPATH**/ ?>