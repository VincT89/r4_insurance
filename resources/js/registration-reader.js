import { registrationCandidates, mergeRegistrationCandidates } from './registration-parser.js';

const labels = {plate:'Targa',make:'Marca',model:'Modello',vin:'Numero di telaio',registered_on:'Prima immatricolazione'};
function initRegistrationReaders() {
    document.querySelectorAll('[data-registration-reader]').forEach(root => {
        if (root.dataset.bound) return;
        root.dataset.bound = 'true';
        const form = root.closest('form'), input = root.querySelector('[data-registration-files]'), button = root.querySelector('[data-read-registration]');
        const status = root.querySelector('[data-registration-status]'), review = root.querySelector('[data-registration-review]');
        let generation = 0;
        input.addEventListener('change', () => { generation++; review.replaceChildren(); review.hidden = true; status.textContent = ''; });
        button.addEventListener('click', async () => {
            const files = [...input.files], current = ++generation, client = form.elements.client_id?.value;
            if (!files.length || files.length > 10 || files.reduce((sum,file)=>sum+file.size,0)>20*1024*1024 || files.some(file => file.size > 10*1024*1024 || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type))) {
                status.textContent = 'Seleziona da 1 a 10 PDF o immagini, massimo 10 MB ciascuno e 20 MB complessivi.'; return;
            }
            button.disabled = true; review.replaceChildren(); review.hidden = true;
            try {
                const {readDocumentData} = await import('./document-ocr.js');
                const results = [];
                for (const file of files) {
                    if (current !== generation || !root.isConnected) return;
                    status.textContent = `Lettura locale: ${file.name}`;
                    results.push(await readDocumentData(file, root.dataset.ocrBase, message => { if (current===generation) status.textContent = `${file.name}: ${message}`; },registrationCandidates,{maxPages:4}));
                }
                if (current !== generation || !root.isConnected) return;
                const data = mergeRegistrationCandidates(results);
                if (data.plate.length > 1 || data.vin.length > 1) { status.textContent = 'Sono presenti targhe o telai diversi. Seleziona soltanto i file di un veicolo e ripeti la lettura.'; return; }
                if (!Object.values(data).some(values => values.length)) { status.textContent = 'Dati del libretto non leggibili. Puoi inserirli manualmente.'; return; }
                const heading = document.createElement('p'); heading.textContent = 'Controlla i dati letti dal libretto'; heading.className = 'registration-review-title'; review.append(heading);
                const grid = document.createElement('div'); grid.className = 'form-row';
                const controls = {};
                for (const [field,label] of Object.entries(labels)) {
                    const container = document.createElement('div'), labelNode = document.createElement('label');
                    const select = document.createElement('select'); select.className = 'form-sel'; select.id = `${input.id}-read-${field}`;
                    labelNode.htmlFor = select.id; labelNode.textContent = label;
                    select.add(new Option(data[field].length ? 'Non utilizzare questo dato' : 'Non leggibile: inserimento manuale',''));
                    for (const value of data[field]) select.add(new Option(field==='registered_on' ? value.split('-').reverse().join('/') : value,value));
                    if (data[field].length === 1) select.value = data[field][0];
                    controls[field] = select; container.append(labelNode,select); grid.append(container);
                }
                review.append(grid);
                const confirmLabel = document.createElement('label'), confirm = document.createElement('input');
                confirm.type = 'checkbox'; confirmLabel.className = 'email-review-confirm u-mt-md';
                confirmLabel.append(confirm,document.createTextNode(' Ho controllato i dati selezionati e confermo il loro utilizzo.'));
                const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'btn btn-p btn-sm u-mt-md'; apply.textContent = 'Usa i dati controllati'; apply.disabled = true;
                confirm.addEventListener('change',()=>apply.disabled=!confirm.checked);
                apply.addEventListener('click',()=>{
                    if (current!==generation || !confirm.checked) return;
                    if (form.elements.client_id?.value !== client) { status.textContent = 'Il cliente è cambiato. Ripeti la lettura per il cliente corretto.'; return; }
                    const values = Object.fromEntries(Object.entries(controls).filter(([,select])=>select.value).map(([field,select])=>[field,select.value]));
                    if (form.matches('[data-quote-form]')) {
                        const existing = values.plate && [...form.elements.vehicle_id.options].find(option=>option.dataset.plate===values.plate);
                        if (form.elements.purpose?.value === 'addition') {
                            if (!existing || existing.value !== form.elements.vehicle_id.value) { status.textContent='Il libretto non corrisponde al veicolo della polizza. Controlla gli allegati prima di proseguire.'; return; }
                            status.textContent='Libretto del veicolo della polizza: sarà allegato al salvataggio.';
                        }
                        else if (existing) { form.elements.vehicle_id.value=existing.value; form.elements.vehicle_id.dispatchEvent(new Event('change',{bubbles:true})); status.textContent='Veicolo già presente: selezionato nella richiesta. Il libretto sarà allegato al salvataggio.'; }
                        else {
                            for (const [field,value] of Object.entries(values)) form.querySelector(`[data-quick-vehicle-field="${field}"]`).value=value;
                            const quick=form.querySelector('[data-quick-vehicle]'); quick.open=true; quick.scrollIntoView({block:'center'});
                            status.textContent='Dati riportati nel nuovo veicolo. Premi “Salva e seleziona veicolo” per conservarlo.';
                        }
                    } else {
                        for (const [field,value] of Object.entries(values)) { form.elements[field].value=value; form.elements[field].dispatchEvent(new Event('change',{bubbles:true})); }
                        status.textContent='Dati riportati nella scheda. Controlla i campi e salva il veicolo.';
                    }
                    review.hidden=true;
                });
                review.append(confirmLabel,apply); review.hidden=false;
                status.textContent='Lettura completata. I campi illeggibili restano da verificare.';
            } catch (error) { if (current===generation) status.textContent=`Lettura non completata. ${error.message || 'Puoi inserire i dati manualmente.'}`; }
            finally { button.disabled=false; }
        });
    });
}
document.addEventListener('DOMContentLoaded',initRegistrationReaders);
document.addEventListener('livewire:navigated',initRegistrationReaders);
