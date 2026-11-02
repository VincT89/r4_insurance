import 'pdfjs-dist/web/pdf_viewer.css';
import '../css/sodano/_document-preview.css';

export async function mount(root) {
    const status = root.querySelector('[data-preview-status]');
    const source = new URL(root.dataset.source, location.href);
    const assets = new URL(root.dataset.assets, location.href);
    if (source.origin !== location.origin || assets.origin !== location.origin) throw new Error('Documento non locale.');
    const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
    const { default: workerUrl } = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
    const { PDFViewer, EventBus, PDFLinkService, PDFFindController, GenericL10n } = await import('pdfjs-dist/web/pdf_viewer.mjs');
    if (!root.isConnected) return;
    pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
    const abort = new AbortController();
    const eventBus = new EventBus();
    const linkService = new PDFLinkService({ eventBus });
    linkService.externalLinkEnabled = false;
    const findController = new PDFFindController({ eventBus, linkService });
    const container = root.querySelector('[data-pdf-container]');
    const viewer = new PDFViewer({ container, viewer: root.querySelector('[data-pdf-viewer]'), eventBus, linkService, findController,
        annotationMode: pdfjs.AnnotationMode.ENABLE, enableAutoLinking: false, maxCanvasPixels: 8 * 1024 * 1024,
        imageResourcesPath: new URL('images/', assets).href, l10n: new GenericL10n('it'), abortSignal: abort.signal });
    linkService.setViewer(viewer);
    const pageNumber = root.querySelector('[data-page-number]');
    const previous = root.querySelector('[data-previous-page]');
    const next = root.querySelector('[data-next-page]');
    const zoom = root.querySelector('[data-pdf-zoom]');
    const query = root.querySelector('[data-pdf-query]');
    const searchStatus = root.querySelector('[data-search-status]');
    const on = (element, event, handler) => element.addEventListener(event, handler, { signal: abort.signal });
    let documentPdf, timer, disposed = false;
    /** Non viene attivato alcun gestore degli script del PDF; il file resta di sola lettura. */
    const loadingTask = pdfjs.getDocument({ url: source.href, isEvalSupported: false, enableXfa: false,
        cMapUrl: new URL('cmaps/', assets).href, cMapPacked: true, standardFontDataUrl: new URL('standard_fonts/', assets).href,
        wasmUrl: new URL('wasm/', assets).href, useSystemFonts: true });
    const fail = message => {
        if (disposed) return;
        clearTimeout(timer);
        root.dataset.state = 'error';
        status.textContent = message;
    };
    const cleanup = () => { disposed = true; clearTimeout(timer); observer.disconnect(); abort.abort(); void loadingTask.destroy(); };
    on(document, 'livewire:navigating', cleanup);
    on(window, 'pagehide', cleanup);
    const syncPage = () => { pageNumber.value = viewer.currentPageNumber; previous.disabled = viewer.currentPageNumber <= 1; next.disabled = viewer.currentPageNumber >= viewer.pagesCount; };
    eventBus.on('pagesinit', () => {
        viewer.currentScaleValue = zoom.value;
        pageNumber.max = documentPdf.numPages;
        root.querySelector('[data-page-count]').textContent = `di ${documentPdf.numPages}`;
        root.querySelectorAll('.document-preview-toolbar button, .document-preview-toolbar input, .document-preview-toolbar select').forEach(control => { control.disabled = false; });
        syncPage();
    });
    eventBus.on('pagechanging', syncPage);
    eventBus.on('pagerendered', event => {
        if (event.error) return fail('Una pagina non è leggibile nel lettore. Usa Scarica PDF per consultare il file originale.');
        if (root.dataset.state === 'loading') { clearTimeout(timer); root.dataset.state = 'ready'; status.textContent = ''; }
    });
    eventBus.on('updatefindmatchescount', ({ matchesCount }) => { searchStatus.textContent = matchesCount.total ? `${matchesCount.current} di ${matchesCount.total} risultati` : 'Nessun risultato nel testo. Il documento potrebbe essere una scansione.'; });
    eventBus.on('updatefindcontrolstate', ({ state }) => { if (state === 1) searchStatus.textContent = 'Nessun risultato nel testo. Il documento potrebbe essere una scansione.'; });
    on(previous, 'click', () => { if (viewer.currentPageNumber > 1) viewer.currentPageNumber--; });
    on(next, 'click', () => { if (viewer.currentPageNumber < viewer.pagesCount) viewer.currentPageNumber++; });
    on(pageNumber, 'change', () => { const value = Number(pageNumber.value); if (Number.isInteger(value) && value >= 1 && value <= viewer.pagesCount) viewer.currentPageNumber = value; syncPage(); });
    on(zoom, 'change', () => { viewer.currentScaleValue = zoom.value; });
    const search = backwards => { if (!query.value.trim()) { searchStatus.textContent = ''; return; } eventBus.dispatch('find', { source: root, type: 'again', query: query.value, phraseSearch: true, caseSensitive: false, entireWord: false, highlightAll: true, findPrevious: backwards, matchDiacritics: false }); };
    on(root.querySelector('[data-pdf-search]'), 'submit', event => { event.preventDefault(); search(false); });
    on(root.querySelector('[data-previous-match]'), 'click', () => search(true));
    on(query, 'search', () => { if (!query.value) { eventBus.dispatch('find', { source: root, type: '', query: '' }); searchStatus.textContent = ''; } });
    const observer = new ResizeObserver(() => { if (documentPdf && ['page-width', 'page-fit'].includes(zoom.value)) viewer.currentScaleValue = zoom.value; });
    observer.observe(container);
    timer = setTimeout(() => { fail('Il documento impiega troppo tempo a caricarsi. Ricarica la pagina oppure usa Scarica PDF.'); void loadingTask.destroy(); }, 45000);
    try {
        documentPdf = await loadingTask.promise;
        if (disposed) return;
        viewer.setDocument(documentPdf);
        linkService.setDocument(documentPdf);
        findController.setDocument(documentPdf);
    } catch (error) {
        fail(error.name === 'PasswordException' ? 'Il PDF è protetto da password. Usa Scarica PDF per aprirlo con la password del documento.' : 'Non è possibile mostrare questo PDF. Usa Scarica PDF per consultare il file originale.');
    }
}
