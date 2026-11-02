export async function readPolicyContract(file, base, progress, signal) {
    const origin = new URL(base, location.href);
    if (origin.origin !== location.origin) throw new Error('Lettura locale non disponibile.');
    let loading, worker, timer, stopped = false;
    const cancel = () => { stopped = true; void worker?.terminate(); void loading?.destroy(); };
    signal?.addEventListener('abort', cancel, {once:true});
    const check = () => { if (stopped || signal?.aborted) throw new Error('Lettura interrotta.'); };
    try {
        return await Promise.race([(async () => {
            const pdfjs = await import('pdfjs-dist/build/pdf.mjs');
            const {default: workerUrl} = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
            check();
            pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
            loading = pdfjs.getDocument({data:new Uint8Array(await file.arrayBuffer()), isEvalSupported:false, disableFontFace:true, useSystemFonts:true, maxImageSize:16000000});
            const pdf = await loading.promise;
            const pages = Math.min(pdf.numPages, 60), parts = [];
            let scanned = 0, skipped = pdf.numPages - pages;
            for (let index = 1; index <= pages; index++) {
                check(); progress(`Lettura del contratto: pagina ${index} di ${pdf.numPages}…`);
                const page = await pdf.getPage(index), content = await page.getTextContent();
                let text = '', previousY;
                for (const item of content.items) {
                    if (!('str' in item)) continue;
                    const y = item.transform?.[5];
                    if (previousY !== undefined && Math.abs(y - previousY) > 3) text += '\n';
                    text += item.str + (item.hasEOL ? '\n' : ' '); previousY = y;
                }
                if (text.replace(/\s/g, '').length < 60) {
                    if (scanned >= 8) { skipped++; parts.push(text); continue; }
                    if (!worker) {
                        progress('Preparazione della lettura delle pagine scansionate…');
                        const {createWorker} = await import('tesseract.js');
                        worker = await createWorker('ita', 1, {workerPath:new URL('worker.min.js', origin).href, corePath:new URL('core', origin).href, langPath:new URL('lang', origin).href, workerBlobURL:false, cacheMethod:'none'});
                        if (stopped || signal?.aborted) { await worker.terminate(); check(); }
                    }
                    check(); progress(`Lettura della pagina scansionata ${index} di ${pdf.numPages}…`);
                    const unit = page.getViewport({scale:1});
                    const viewport = page.getViewport({scale:Math.min(2.5, 2200 / Math.max(unit.width, unit.height))});
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
                    try {
                        await page.render({canvasContext:canvas.getContext('2d'), viewport}).promise;
                        const result = await worker.recognize(canvas); check(); text += '\n' + result.data.text; scanned++;
                    } finally { canvas.width = canvas.height = 0; }
                }
                parts.push(text); page.cleanup();
            }
            return {text:parts.join('\n').slice(0, 500000), skipped, scanned, pages:pdf.numPages};
        })(), new Promise((_,reject) => { timer = setTimeout(() => { cancel(); reject(new Error('La lettura richiede troppo tempo. Puoi completare i dati controllando il contratto.')); }, 90000); })]);
    } catch (error) {
        if (error.name === 'PasswordException') throw new Error('Il PDF è protetto da password. Carica una copia leggibile oppure compila i dati controllando il contratto.');
        throw error;
    } finally { stopped = true; clearTimeout(timer); signal?.removeEventListener('abort', cancel); await worker?.terminate(); await loading?.destroy(); }
}
