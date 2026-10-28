import { expiryCandidates } from './document-expiry-parser.js';

export function readDocumentExpiry(file, base, progress) {
    return readDocumentData(file, base, progress, expiryCandidates);
}

export async function readDocumentData(file, base, progress, parse, {maxPages = 2} = {}) {
    let worker, pdf, loadingTask, timer, cancelled = false;
    const origin = new URL(base, location.href);
    if (origin.origin !== location.origin) throw new Error('Percorso OCR non locale.');
    const read = async () => {
        const images = [];
        if (file.type === 'application/pdf') {
            const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
            const { default: workerUrl } = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
            pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
            loadingTask = pdfjs.getDocument({data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false, useSystemFonts: true, disableFontFace: true});
            pdf = await loadingTask.promise;
            let text = '';
            for (let index=1; index<=Math.min(pdf.numPages,maxPages); index++) {
                const page = await pdf.getPage(index);
                const content = await page.getTextContent();
                text += content.items.map(item => item.str + (item.hasEOL ? '\n' : ' ')).join('') + '\n';
            }
            const found = parse(text);
            if (Array.isArray(found) ? found.length : Object.values(found).some(values => values.length)) return found;
            for (let index=1; index<=Math.min(pdf.numPages,maxPages); index++) {
                const page = await pdf.getPage(index);
                const unit = page.getViewport({scale:1});
                const viewport = page.getViewport({scale: Math.min(2.5, 2200 / Math.max(unit.width,unit.height))});
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
                await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
                images.push(canvas);
            }
        } else {
            const bitmap = await createImageBitmap(file);
            const scale = Math.min(1, 2400/Math.max(bitmap.width,bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1,Math.round(bitmap.width*scale)); canvas.height = Math.max(1,Math.round(bitmap.height*scale));
            const ctx = canvas.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,canvas.width,canvas.height); ctx.drawImage(bitmap,0,0,canvas.width,canvas.height); bitmap.close();
            images.push(canvas);
        }
        if (cancelled) throw new Error('Lettura interrotta.');
        const { createWorker } = await import('tesseract.js');
        worker = await createWorker('ita', 1, {workerPath:new URL('worker.min.js',origin).href, corePath:new URL('core',origin).href, langPath:new URL('lang',origin).href, workerBlobURL:false, cacheMethod:'none', logger:message => { if (!cancelled && message.status==='recognizing text') progress('Lettura del documento in corso…'); }});
        if (cancelled) { await worker.terminate(); throw new Error('Lettura interrotta.'); }
        let text = '';
        for (const canvas of images) { const result = await worker.recognize(canvas); text += result.data.text+'\n'; canvas.width=canvas.height=0; }
        return parse(text);
    };
    try {
        return await Promise.race([read(), new Promise((_,reject) => { timer=setTimeout(() => { cancelled=true; void worker?.terminate(); void loadingTask?.destroy(); reject(new Error('Lettura troppo lenta. Riprova con un’immagine più nitida.')); },45000); })]);
    } finally { cancelled=true; clearTimeout(timer); await worker?.terminate(); await loadingTask?.destroy(); }
}
