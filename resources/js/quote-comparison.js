export function selectionProblem(selected) {
    if (selected.length < 2) return 'Seleziona almeno due offerte.';
    if (selected.length > 6) return 'Confronta al massimo sei offerte per volta.';
    if (new Set(selected.map(item => String(item.caseId))).size !== 1) return 'Le offerte appartengono a pratiche diverse. Seleziona offerte dello stesso rischio.';
    return '';
}

function initialize() {
    document.querySelectorAll('[data-quote-offer-selection]').forEach(form => {
        if (form.dataset.initialized) return;
        form.dataset.initialized = 'true';
        const checks = [...form.querySelectorAll('input[name="quotes[]"]')];
        const sync = () => {
            const selected = checks.filter(input => input.checked);
            const message = selectionProblem(selected.map(input => ({caseId:input.dataset.case})));
            let summary = 'Seleziona da due a sei offerte della stessa pratica.';
            if (selected.length === 1) summary = '1 offerta selezionata. Scegline un’altra della stessa pratica.';
            if (selected.length > 1) summary = `${selected.length} offerte selezionate`;
            form.querySelector('[data-quote-selection-status]').textContent = summary;
            const error = form.querySelector('[data-quote-selection-error]');
            error.textContent = message; error.hidden = selected.length < 2 || !message;
            form.querySelector('[data-quote-compare]').disabled = Boolean(message);
            checks.forEach(input => input.closest('tr').classList.toggle('is-selected',input.checked));
            return message;
        };
        form.addEventListener('change',sync);
        form.addEventListener('submit',event => { if (sync()) event.preventDefault(); });
        sync();
    });
    document.querySelectorAll('[data-quote-upload]').forEach(form => {
        if (form.dataset.initialized) return;
        form.dataset.initialized = 'true';
        form.addEventListener('submit',event => {
            const inputs = [...form.querySelectorAll('input[type="file"]')];
            const files = inputs.flatMap(input => [...input.files]);
            if (!files.length || files.some(file => file.size > 10*1024*1024) || files.reduce((size,file)=>size+file.size,0)>50*1024*1024 || inputs.some(input=>input.files.length>10)) {
                event.preventDefault();
                let error=form.querySelector('[data-upload-error]');
                if (!error) { error=document.createElement('p'); error.dataset.uploadError=''; error.className='u-alert-error'; error.setAttribute('role','alert'); form.prepend(error); }
                error.textContent='Seleziona almeno un PDF. Massimo 10 file per offerta, 10 MB per file e 50 MB complessivi.';
                return;
            }
            inputs.filter(input=>!input.files.length).forEach(input=>{input.disabled=true;});
        });
    });
    document.querySelectorAll('[data-quote-comparison]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized='true';
        const status=root.querySelector('[data-quote-comparison-status]');
        const difference=root.querySelector('[data-quote-differences]');
        difference.addEventListener('change',()=>{
            root.querySelectorAll('[data-comparison-row]').forEach(row=>{row.hidden=difference.checked&&row.dataset.different!=='true';});
            root.querySelectorAll('[data-comparison-group]').forEach(group=>{group.hidden=![...group.querySelectorAll('[data-comparison-row]')].some(row=>!row.hidden);});
        });
        const selectors=[...root.querySelectorAll('[data-mobile-offer]')];
        const mobile=()=>{
            const selected=new Set(selectors.map(select=>select.value));
            root.querySelectorAll('[data-offer-column]').forEach(cell=>cell.classList.toggle('quote-mobile-hidden',selectors.length>0&&!selected.has(cell.dataset.offerColumn)));
        };
        selectors.forEach(select=>select.addEventListener('change',()=>{
            const other=selectors.find(item=>item!==select);
            if(other.value===select.value) other.value=[...other.options].find(option=>option.value!==select.value).value;
            mobile();
        }));
        mobile();
        let sourceRequest = null;
        let sourceTrigger = null;
        root.addEventListener('click',async event=>{
            const close=event.target.closest('[data-close-quote-source]');
            if(close) {
                event.preventDefault();
                sourceRequest?.abort();
                sourceRequest = null;
                const pane=root.querySelector('[data-comparison-source]');
                pane.querySelector('[data-document-preview]')?.dispatchEvent(new Event('document:dispose'));
                pane.replaceChildren(); pane.hidden=true;
                root.querySelector('[data-comparison-workspace]').classList.remove('has-source');
                status.textContent = '';
                sourceTrigger?.focus(); return;
            }
            const link=event.target.closest('[data-quote-source]'); if(!link) return;
            const url=new URL(link.href,location.href); if(url.origin!==location.origin) return;
            event.preventDefault();
            sourceRequest?.abort();
            const request = new AbortController();
            sourceRequest = request;
            sourceTrigger = link;
            status.textContent='Apertura della pagina del PDF…';
            try {
                const response = await fetch(url, {
                    headers: {Accept: 'application/json'},
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: request.signal,
                });
                if(!response.ok) throw new Error('La fonte non è disponibile. Riapri il confronto e verifica l’accesso al documento.');
                const data = await response.json();
                if (request.signal.aborted || !root.isConnected) return;
                /** Il frammento proviene esclusivamente dalla rotta autenticata e usa l’escaping di Blade. */
                const parsed=new DOMParser().parseFromString(data.html,'text/html'); const pane=parsed.querySelector('[data-comparison-source]');
                if(!pane) throw new Error('Anteprima non disponibile. Usa il collegamento al PDF.');
                const previous=root.querySelector('[data-comparison-source]');
                previous.querySelector('[data-document-preview]')?.dispatchEvent(new Event('document:dispose'));
                previous.replaceWith(pane); pane.hidden=false;
                root.querySelector('[data-comparison-workspace]').classList.add('has-source');
                pane.querySelector('[data-close-quote-source]').focus();
                const reader=pane.querySelector('[data-document-preview]'); reader.dataset.initialized='true';
                const {mount}=await import('./document-preview.js');
                if (request.signal.aborted || !reader.isConnected) return;
                await mount(reader);
                if (!request.signal.aborted && reader.isConnected) {
                    status.textContent = reader.dataset.state === 'error' ? '' : 'Fonte del dato aperta.';
                }
            } catch (error) {
                if (!request.signal.aborted && root.isConnected) {
                    status.textContent = error.message || 'Impossibile aprire il PDF.';
                }
            }
        });
        root.querySelectorAll('[data-quote-ai-read]').forEach(form=>{
            form.addEventListener('submit',async event=>{
                event.preventDefault(); if(!form.reportValidity()||form.dataset.busy) return;
                const button=form.querySelector('button'); const output=form.querySelector('[data-ai-status]');
                const documents=[...form.querySelectorAll('[data-ai-document]')];
                form.dataset.busy='true'; button.disabled=true; output.textContent='Lettura dei PDF in corso…';
                try {
                    for(const document of documents) {
                        const url=new URL(document.dataset.url,location.href);
                        if(url.origin!==location.origin) throw new Error('Indirizzo di lettura non valido.');
                        const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':window.document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({document_id:Number(document.value),ai_processing:true,retry:document.dataset.retry==='true'})});
                        const result=await response.json().catch(()=>({}));
                        if(!response.ok) {
                            document.dataset.retry=result.ai_retry_allowed===true?'true':'false';
                            button.textContent=result.ai_retry_allowed===true?'Riprova lettura':'Leggi PDF con AI';
                            throw new Error(Object.values(result.errors||{}).flat().join(' ')||'Lettura non riuscita. Ricarica il confronto per controllare i documenti già elaborati.');
                        }
                        document.dataset.retry='false';
                    }
                    location.reload();
                } catch(error) { output.textContent=error.message; output.setAttribute('role','alert'); }
                finally { delete form.dataset.busy; button.disabled=false; }
            });
            form.hidden=false;
        });
    });
}

if(typeof document!=='undefined') {
    document.addEventListener('DOMContentLoaded',initialize);
    document.addEventListener('livewire:navigated',initialize);
}
