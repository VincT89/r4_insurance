<aside class="quote-comparison-source" data-comparison-source <?php if(!$source): ?> hidden <?php endif; ?> aria-label="Fonte del dato nel PDF">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source): ?>
        <div class="quote-source-heading"><div><h2><?php echo e($source['evidence']['document_name']); ?></h2><p><?php echo e($source['cell']['label']); ?> · Pagina <?php echo e($source['evidence']['page']); ?></p></div><a href="<?php echo e(route('quotes.compare',['quotes'=>$ids])); ?>" data-close-quote-source>Chiudi fonte</a></div>
        <blockquote><?php echo e($source['evidence']['quote']); ?></blockquote>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source['evidence']['coverage_name']): ?><p class="u-text-meta">Nome nel documento: <?php echo e($source['evidence']['coverage_name']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="u-text-meta">Il PDF riporta: <?php echo e($comparison->display($source['evidence']['value'],$source['cell']['type'])); ?>. Nel confronto: <?php echo e($source['cell']['display']); ?>.</p>
        <?php if (isset($component)) { $__componentOriginal73d10cdc2ef696ba2d007a7d0556003c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal73d10cdc2ef696ba2d007a7d0556003c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pdf-reader','data' => ['source' => $source['evidence']['download_url'],'page' => $source['evidence']['page']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pdf-reader'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['source' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($source['evidence']['download_url']),'page' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($source['evidence']['page'])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal73d10cdc2ef696ba2d007a7d0556003c)): ?>
<?php $attributes = $__attributesOriginal73d10cdc2ef696ba2d007a7d0556003c; ?>
<?php unset($__attributesOriginal73d10cdc2ef696ba2d007a7d0556003c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal73d10cdc2ef696ba2d007a7d0556003c)): ?>
<?php $component = $__componentOriginal73d10cdc2ef696ba2d007a7d0556003c; ?>
<?php unset($__componentOriginal73d10cdc2ef696ba2d007a7d0556003c); ?>
<?php endif; ?>
        <a href="<?php echo e($source['evidence']['url']); ?>" target="_blank" rel="noopener">Apri PDF in una nuova scheda</a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($source['editable'] && auth()->user()->can('updateRegistry',$case->client)): ?><?php echo $__env->make('quotes.comparison-edit',['offer'=>$source['offer'],'cell'=>$source['cell']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</aside>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/quotes/comparison-source.blade.php ENDPATH**/ ?>