import '../css/sodano/_tablet-signing.css';
import { hasInk } from './signature-ink.js';

export function mount(root) {
    const pad = root.querySelector('[data-signing-pad]'), context = pad.getContext('2d');
    const form = root.querySelector('[data-signing-form]'), reader = root.querySelector('[data-document-preview]');
    const error = root.querySelector('[data-signing-error]'), save = root.querySelector('[data-save-signature]');
    const placeToggle = root.querySelector('[data-place-toggle]');
    let strokes = [], placements = [], current = null, pointer = null, placing = false, busy = false, keyboard = false, cursor = [.12,.6], pages = 0;
    pages = Number(reader.querySelector('[data-page-number]')?.max || 0);
    const clamp = value => Math.max(0, Math.min(1, value));
    const showError = message => { error.hidden = false; error.textContent = message; };
    const changed = () => { form.elements.consent.checked = false; error.hidden = true; };
    const draw = () => {
        context.clearRect(0, 0, pad.width, pad.height);
        context.lineWidth = 3; context.strokeStyle = '#172333'; context.lineCap = 'round'; context.lineJoin = 'round';
        for (const stroke of strokes) {
            if (stroke.length < 2) continue;
            context.beginPath(); context.moveTo(stroke[0][0] * pad.width, stroke[0][1] * pad.height);
            for (const point of stroke.slice(1)) context.lineTo(point[0] * pad.width, point[1] * pad.height);
            context.stroke();
        }
        if (document.activeElement === pad && keyboard) { context.beginPath(); context.arc(cursor[0]*pad.width,cursor[1]*pad.height,5,0,Math.PI*2); context.fillStyle='#0b498c'; context.fill(); }
        paintPlacements();
    };
    const end = () => {
        if (current && current.length < 2) strokes = strokes.filter(stroke => stroke !== current);
        current = null; pointer = null; keyboard = false; draw();
    };
    const point = event => { const box = pad.getBoundingClientRect(); return [clamp((event.clientX-box.left)/box.width), clamp((event.clientY-box.top)/box.height)]; };
    pad.addEventListener('pointerdown', event => {
        if (busy || pointer !== null || event.button !== 0) return;
        event.preventDefault(); changed(); keyboard = false; pointer = event.pointerId;
        pad.setPointerCapture(event.pointerId); current = [point(event)]; strokes.push(current);
    });
    pad.addEventListener('pointermove', event => {
        if (event.pointerId !== pointer || !current || busy) return;
        const value = point(event), last = current.at(-1);
        if (Math.hypot(value[0]-last[0], value[1]-last[1]) < .003) return;
        if (strokes.flat().length >= 3000) { end(); showError('La firma è molto lunga. Cancella e riprova con un tratto più breve.'); return; }
        current.push(value); draw();
    });
    pad.addEventListener('pointerup', event => { if (event.pointerId === pointer) end(); });
    pad.addEventListener('pointercancel', end);
    pad.addEventListener('lostpointercapture', () => { if (pointer !== null) end(); });
    pad.addEventListener('keydown', event => {
        if (busy) return;
        if (event.key === 'Escape') { event.preventDefault(); end(); return; }
        if (event.code === 'Space') {
            event.preventDefault(); changed(); keyboard = true;
            if (current) { current = null; } else { current = [[...cursor]]; strokes.push(current); }
            draw(); return;
        }
        const move = { ArrowLeft:[-.025,0], ArrowRight:[.025,0], ArrowUp:[0,-.06], ArrowDown:[0,.06] }[event.key];
        if (!move) return;
        event.preventDefault(); keyboard = true; cursor = [clamp(cursor[0]+move[0]),clamp(cursor[1]+move[1])];
        if (current) { if (strokes.flat().length >= 3000) { end(); return; } current.push([...cursor]); changed(); }
        draw();
    });
    pad.addEventListener('blur', end);
    root.querySelector('[data-clear-ink]').addEventListener('click', () => { if (!busy) { strokes=[]; current=null; changed(); draw(); } });
    root.querySelector('[data-undo-ink]').addEventListener('click', () => { if (!busy) { strokes.pop(); current=null; changed(); draw(); } });
    function paintPlacements() {
        reader.querySelectorAll('[data-signing-placement]').forEach(element => element.remove());
        placements.forEach((placement, index) => {
            const page = reader.querySelector(`.page[data-page-number="${placement.page}"]`);
            if (!page) return;
            const marker = document.createElement('div'); marker.dataset.signingPlacement = String(index);
            marker.className = 'signing-page-marker'; marker.style.left = `${placement.x*100}%`; marker.style.top = `${placement.y*100}%`;
            marker.style.width = `${placement.width*100}%`; marker.style.height = `${placement.height*100}%`; marker.setAttribute('aria-label', `Punto firma ${index+1}`);
            const label = document.createElement('span'); label.textContent = `Firma ${index+1}`; marker.append(label);
            if (hasInk(strokes)) {
                const ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns,'svg');
                svg.setAttribute('viewBox','0 0 1 1'); svg.setAttribute('preserveAspectRatio','none'); svg.setAttribute('aria-hidden','true');
                for (const stroke of strokes) { const line=document.createElementNS(ns,'polyline'); line.setAttribute('points',stroke.map(p=>p.join(',')).join(' ')); line.setAttribute('fill','none'); line.setAttribute('stroke','#172333'); line.setAttribute('stroke-width','.005'); svg.append(line); }
                marker.append(svg);
            }
            page.append(marker);
        });
    }
    function listPlacements() {
        root.querySelector('[data-placement-status]').textContent = placements.length ? `Punti firma: ${placements.length}` : 'Nessun punto firma indicato.';
        const list = root.querySelector('[data-placement-list]'); list.replaceChildren();
        placements.forEach((placement,index) => {
            const row = document.createElement('div'), label = document.createElement('span'), remove = document.createElement('button');
            label.textContent = `Firma ${index+1} · pagina ${placement.page}`;
            remove.type='button'; remove.className='btn btn-g'; remove.textContent=`Rimuovi punto ${index+1}`;
            remove.addEventListener('click',()=>{ if (!busy) { placements.splice(index,1); changed(); listPlacements(); paintPlacements(); } });
            row.append(label,remove); list.append(row);
        });
    }
    const addPlacement = (page, x, y, ratio) => {
        if (busy) return;
        if (placements.length >= 10) return showError('Puoi indicare fino a dieci punti firma nello stesso documento.');
        const width=.30, height=Math.max(.025,Math.min(.45,width*ratio/2));
        placements.push({page,x:Math.max(0,Math.min(1-width,x-width/2)),y:Math.max(0,Math.min(1-height,y-height/2)),width,height});
        changed(); listPlacements(); paintPlacements(); placing=false; placeToggle.setAttribute('aria-pressed','false'); reader.classList.remove('is-placing');
        root.querySelector('[data-placement-help]').textContent = 'Punto aggiunto. Controlla che non copra il testo, poi disegna la firma e conferma.';
    };
    placeToggle.addEventListener('click',()=>{
        if (busy) return; placing=!placing; placeToggle.setAttribute('aria-pressed',String(placing)); reader.classList.toggle('is-placing',placing);
        root.querySelector('[data-placement-help]').textContent = placing ? 'Tocca nel documento il centro dello spazio dove deve comparire la firma.' : 'Leggi il documento e scegli dove inserire la firma.';
    });
    reader.addEventListener('click', event => {
        if (!placing) return;
        const page = event.target.closest('.page[data-page-number]'); if (!page) return;
        event.preventDefault(); event.stopPropagation();
        const box=page.getBoundingClientRect(); addPlacement(Number(page.dataset.pageNumber), (event.clientX-box.left)/box.width,(event.clientY-box.top)/box.height,box.width/box.height);
    },true);
    root.querySelector('[data-place-bottom]').addEventListener('click',()=>{
        if (!pages) return showError('Attendi il caricamento del documento.');
        const page=reader.querySelector(`.page[data-page-number="${pages}"]`);
        const box=page?.getBoundingClientRect();
        if (!box?.width || !box.height) return showError('Apri l’ultima pagina del documento e riprova.');
        addPlacement(pages,.70,.88,box.width/box.height);
        reader.querySelector('[data-page-number]').value=String(pages);
        reader.querySelector('[data-page-number]').dispatchEvent(new Event('change'));
        /** Mostra il punto scelto nel lettore, senza spostare il riquadro firma. */
        requestAnimationFrame(() => {
            const marker=reader.querySelector(`[data-signing-placement="${placements.length-1}"]`), container=reader.querySelector('[data-pdf-container]');
            if (!marker || !container) return;
            const target=marker.getBoundingClientRect(), viewport=container.getBoundingClientRect();
            container.scrollTop += target.top-viewport.top-(container.clientHeight-target.height)/2;
        });
    });
    reader.addEventListener('document:ready',event=>{ pages=event.detail.pages; });
    reader.addEventListener('document:page-rendered',paintPlacements);
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        end(); error.hidden=true;
        if (!placements.length) { showError('Indica dove inserire la firma nel documento.'); placeToggle.focus(); return; }
        if (!hasInk(strokes)) { showError('Disegna una firma completa nel riquadro prima di confermare.'); pad.focus(); return; }
        const data=Object.fromEntries(new FormData(form)); data.strokes=strokes; data.placements=placements;
        busy=true; save.disabled=true; save.textContent='Salvataggio della firma…'; form.setAttribute('aria-busy','true');
        try {
            const response = await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':data._token},body:JSON.stringify(data)});
            let result; try { result=await response.json(); } catch { throw new Error(response.status===419 ? 'La sessione è scaduta. Riapri il documento dalla polizza.' : 'La firma non è stata salvata. Riprova; il documento originale è conservato.'); }
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'La firma non è stata salvata. Riprova.');
            const destination = new URL(result.url,location.href);
            if (destination.origin !== location.origin) throw new Error('Destinazione non valida. Torna alla polizza.');
            location.assign(destination.href);
        } catch (failure) { busy=false; save.disabled=false; save.textContent='Conferma e salva firma'; form.removeAttribute('aria-busy'); showError(failure.message); error.scrollIntoView({block:'nearest'}); }
    });
}
