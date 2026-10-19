const unknownExpiry = 'Da verificare, data di scadenza non leggibile';
const identityTypes = [['', 'Non specificato'], ['electronic', 'Carta d’identità elettronica'], ['paper', 'Carta d’identità cartacea'], ['driving_licence', 'Patente']];
const element = (tag, className, text) => { const node=document.createElement(tag); if(className)node.className=className; if(text)node.textContent=text; return node; };

function initDocumentExpiry() {
    document.querySelectorAll('[data-document-expiry]').forEach(form => {
        if (form.dataset.expiryBound) return;
        form.dataset.expiryBound='true';
        const category=form.dataset.documentCategory ? {value:form.dataset.documentCategory} : form.elements.document_category, files=form.querySelector('input[type=file]'), list=form.querySelector('[data-file-details]');
        const detailsName=form.dataset.detailsName || 'file_details', fieldPrefix=form.dataset.expiryKey || 'file';
        let generation=0, busy=false, refreshers=[];
        const bind = (expiry, status, subtype) => {
            const refresh=()=>{
                status.hidden=category.value!=='identity' && !expiry.value;
                status.dataset.state=!expiry.value?'unknown':expiry.value<form.dataset.today?'expired':'valid';
                status.textContent=!expiry.value?unknownExpiry:expiry.value<form.dataset.today?`Documento non valido — scaduto il ${expiry.value.split('-').reverse().join('/')}`:'Documento valido';
                if(subtype){subtype.closest('.form-g').hidden=category.value!=='identity'; subtype.disabled=category.value!=='identity';}
            };
            expiry.addEventListener('input',refresh); refreshers.push(refresh); refresh(); return refresh;
        };
        if(form.elements?.expires_on)bind(form.elements.expires_on,form.querySelector('[data-expiry-status]'),form.querySelector('[data-identity-type]'));
        category.addEventListener?.('change',()=>refreshers.forEach(refresh=>refresh()));
        files?.addEventListener('change',()=>{
            generation++; list.replaceChildren(); refreshers=[];
            if(files.files.length>10){list.append(element('p','invalid-feedback','Seleziona al massimo 10 file.'));return;}
            [...files.files].forEach((file,index)=>{
                const current=generation, row=element('section','document-file-row'), heading=element('h3','',file.name), grid=element('div','form-row');
                const typeGroup=element('div','form-g'), typeLabel=element('label','form-lbl','Tipo di documento d’identità'), subtype=element('select','form-sel');
                subtype.name=`${detailsName}[${index}][identity_type]`; subtype.id=`${fieldPrefix}-identity-${index}`; typeLabel.htmlFor=subtype.id;
                identityTypes.forEach(([value,label])=>subtype.add(new Option(label,value))); typeGroup.append(typeLabel,subtype);
                const dateGroup=element('div','form-g'), dateLabel=element('label','form-lbl','Scadenza del documento'), expiry=element('input','form-in');
                expiry.type='date'; expiry.name=`${detailsName}[${index}][expires_on]`; expiry.id=`${fieldPrefix}-expiry-${index}`; dateLabel.htmlFor=expiry.id; dateGroup.append(dateLabel,expiry);grid.append(typeGroup,dateGroup);
                const read=element('button','btn btn-g','Leggi scadenza dal documento'); read.type='button'; read.dataset.readExpiry='';
                const result=element('div','u-mt-md'), status=element('p','document-expiry-status'); result.setAttribute('role','status'); status.setAttribute('role','status'); status.dataset.expiryStatus='';
                row.append(heading,grid,read,result,status);list.append(row);const refresh=bind(expiry,status,subtype);
                read.addEventListener('click',async()=>{
                    if(busy){result.textContent='Attendi il termine della lettura in corso.';return;}
                    if(file.size>10*1024*1024 || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type)){result.textContent='Per la lettura usa PDF, JPG, PNG o WEBP, massimo 10 MB.';return;}
                    busy=true;read.disabled=true;result.textContent='Lettura locale in corso…'; const dateBefore=expiry.value;
                    try{
                        const {readDocumentExpiry}=await import('./document-ocr.js');
                        const dates=await readDocumentExpiry(file,form.dataset.ocrBase,message=>{if(current===generation)result.textContent=message;});
                        if(current!==generation)return;
                        result.replaceChildren();
                        if(dates.length!==1){result.textContent=dates.length?'Rilevate più scadenze: verifica il documento e inserisci la data corretta.':unknownExpiry;return;}
                        const description=element('p','',`Scadenza rilevata: ${dates[0].split('-').reverse().join('/')}. Controlla che corrisponda al documento.`), confirm=element('button','btn btn-g','Conferma la data rilevata');confirm.type='button';
                        confirm.addEventListener('click',()=>{if(current!==generation)return;expiry.value=dates[0];refresh();result.replaceChildren();});
                        if(expiry.value!==dateBefore)description.textContent+=' La data inserita manualmente resta conservata fino alla conferma.';
                        result.append(description,confirm);
                    }catch{if(current===generation)result.textContent=unknownExpiry+'. Inserisci la data manualmente oppure riprova.';}
                    finally{busy=false;read.disabled=false;}
                });
            });
        });
    });
}
document.addEventListener('DOMContentLoaded',initDocumentExpiry);
document.addEventListener('livewire:navigated',initDocumentExpiry);
