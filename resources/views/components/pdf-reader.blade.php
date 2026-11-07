@props(['source'])
<section class="document-preview" data-document-preview data-source="{{ $source }}" data-assets="{{ asset('vendor/pdfjs') }}/" data-state="loading" aria-label="Lettura del documento">
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
