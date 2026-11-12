<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['client', 'quoteCase'=>null, 'policy'=>null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['client', 'quoteCase'=>null, 'policy'=>null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $receivedEmails = collect();
    if (auth()->user()?->can('communications.manage') && auth()->user()->can('view',$client) && \Illuminate\Support\Facades\Schema::hasTable('inbound_emails')) {
        $receivedEmails = \App\Models\InboundEmail::where('client_id',$client->id)->where('status','assigned')
            ->when($quoteCase, fn($q)=>$q->where('insurance_quote_case_id',$quoteCase->id))
            ->when($policy, fn($q)=>$q->where('insurance_policy_id',$policy->id))
            ->withCount('files')->orderByDesc('received_at')->orderByDesc('id')->limit(5)->get();
    }
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($receivedEmails->isNotEmpty()): ?>
    <section class="inbound-email-section" aria-label="Email e allegati ricevuti">
        <h2>Email e allegati ricevuti</h2>
        <ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $receivedEmails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receivedEmail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><a href="<?php echo e(route('inbound-emails.show',$receivedEmail)); ?>"><?php echo e($receivedEmail->payload['subject'] ?: 'Senza oggetto'); ?></a> · <?php echo e($receivedEmail->received_at->format('d/m/Y')); ?> · <?php echo e($receivedEmail->files_count); ?> <?php echo e($receivedEmail->files_count===1 ? 'allegato' : 'allegati'); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
        <a href="<?php echo e(route('inbound-emails.index',array_filter(['status'=>'all','client'=>$client->id,'case'=>$quoteCase?->id,'policy'=>$policy?->id]))); ?>">Tutte le email ricevute</a>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/inbound-emails.blade.php ENDPATH**/ ?>