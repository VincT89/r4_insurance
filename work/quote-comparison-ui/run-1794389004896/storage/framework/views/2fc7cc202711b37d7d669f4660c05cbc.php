<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['source','page'=>1]));

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

foreach (array_filter((['source','page'=>1]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<section class="document-preview" data-document-preview data-source="<?php echo e($source); ?>" data-initial-page="<?php echo e((int)$page); ?>" data-assets="<?php echo e(asset('vendor/pdfjs')); ?>/" data-state="loading" aria-label="Lettura del documento">
    <div class="document-preview-toolbar">
        <div class="document-preview-pages">
            <button type="button" class="btn btn-g btn-sm" data-previous-page aria-label="Pagina precedente" disabled>Precedente</button>
            <label for="pdf-page">Pagina</label><input id="pdf-page" class="form-in" type="number" min="1" value="1" data-page-number disabled><span data-page-count></span>
            <button type="button" class="btn btn-g btn-sm" data-next-page aria-label="Pagina successiva" disabled>Successiva</button>
        </div>
        <div class="document-preview-zoom"><label for="pdf-zoom">Zoom</label><select id="pdf-zoom" class="form-sel" data-pdf-zoom disabled><option value="page-width">Adatta alla larghezza</option><option value="page-fit">Pagina intera</option><option value="1">100%</option><option value="1.5">150%</option><option value="2">200%</option></select></div>
        <form class="document-preview-search" data-pdf-search role="search" aria-label="Cerca nel documento">
            <label class="sr-only" for="pdf-query">Cerca nel documento</label><input id="pdf-query" class="form-in" type="search" placeholder="Cerca nel documento" maxlength="200" data-pdf-query disabled>
            <button class="btn btn-g btn-sm" disabled>Cerca</button><button type="button" class="btn btn-g btn-sm" data-previous-match disabled>Risultato precedente</button>
            <span data-search-status role="status" aria-live="polite"></span>
        </form>
    </div>
    <p class="document-preview-status" data-preview-status role="status" aria-live="polite">Caricamento del documento…</p>
    <noscript><p>Per leggere qui il documento attiva JavaScript, oppure usa Scarica PDF.</p></noscript>
    <div class="document-preview-stage"><div class="document-preview-scroll" data-pdf-container tabindex="0" role="region" aria-label="Pagine del documento"><div class="pdfViewer" data-pdf-viewer></div></div></div>
</section>
<?php /**PATH C:\Users\vince\OneDrive\Desktop\SODANO\R4_INSURANCE\gestionale\resources\views/components/pdf-reader.blade.php ENDPATH**/ ?>