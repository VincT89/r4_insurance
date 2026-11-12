const normalize=value=>String(value || '').trim().toLocaleUpperCase('it');

export function matchingContractClient(result,read,current) {
    if(result?.status!=='duplicate' || Object.keys(result.errors || {}).length || result.matches?.length!==1) return null;
    const candidate=result.matches[0], identifiers=['tax_code','vat_number'].filter(key=>read[key]);
    if(!identifiers.length || normalize(read.name)!==normalize(candidate.name) || normalize(current.name)!==normalize(read.name)) return null;
    return identifiers.every(key=>candidate.fields.includes(key) && normalize(current[key]).replace(/\s/g,'')===normalize(read[key]).replace(/\s/g,'')) ? candidate : null;
}

export function contractTemplateApplies(template,client,branch,subbranch) {
    if(!client?.client_type || !['client','policy'].includes(template.scope)) return false;
    if(template.client_type && template.client_type!=='all' && template.client_type!==client.client_type) return false;
    return template.scope==='client' || ((!template.branch || normalize(template.branch)===normalize(branch)) && (!template.subbranch || normalize(template.subbranch)===normalize(subbranch)));
}

export function initContractPreparation(form) {
    const root=form.querySelector('[data-contract-preparation]');
    if(!root) return;
    const templates=JSON.parse(root.querySelector('[data-contract-templates]').textContent);
    const choices=root.querySelector('[data-contract-form-choices]'), mode=root.querySelector('[data-contract-forms-mode]');
    if(!choices || !mode) return;
    let client=JSON.parse(root.querySelector('[data-contract-client-context]').textContent);
    const restored=JSON.parse(root.querySelector('[data-contract-form-restore]').textContent);
    const touched=new Set(), edited=new Set(), automatic={};
    const rows=new Map();
    const primary=document.createElement('div'), others=document.createElement('details'), summary=document.createElement('summary');
    primary.className='contract-module-list'; others.className='contract-extra'; others.append(summary); choices.append(primary,others);
    if(restored?.mode) mode.value=restored.mode;
    for(const template of templates) {
        const row=document.createElement('div'), label=document.createElement('label'), box=document.createElement('input'), text=document.createElement('span'), small=document.createElement('small'), title=document.createElement('strong'), preview=document.createElement('a'), revision=document.createElement('input');
        row.className='module-choice'; label.className='contract-module-choice'; box.type='checkbox'; box.name='contract_forms[templates][]'; box.value=String(template.id);
        title.textContent=template.title; text.append(title,small); label.append(box,text); row.append(label);
        preview.href=template.preview; preview.target='_blank'; preview.rel='noopener'; preview.className='module-preview'; preview.textContent='Anteprima'; preview.setAttribute('aria-label','Anteprima del modello '+template.title); row.append(preview);
        revision.type='hidden'; revision.name=`contract_forms[revisions][${template.id}]`; revision.value=template.revision; row.append(revision);
        if(restored) { box.checked=(Array.isArray(restored.templates) ? restored.templates : []).map(String).includes(String(template.id)); touched.add(template.id); }
        box.addEventListener('change',()=>{ touched.add(template.id); refresh(); });
        rows.set(template.id,{row,box,small,revision});
    }
    const signer=root.querySelector('[data-contract-form-signer]'), role=root.querySelector('[data-contract-form-role]'), place=root.querySelector('[data-contract-form-place]'), date=root.querySelector('[data-contract-form-date]');
    for(const field of [signer,role]) field.addEventListener('input',()=>edited.add(field.name));
    function prefill(field,value) {
        if(!edited.has(field.name) && (!field.value || field.value===automatic[field.name])) { field.value=value || ''; automatic[field.name]=field.value; }
    }
    function context() {
        const picker=form.querySelector('[data-policy-client-picker]');
        if(picker?.dataset.creatingClient==='true') return Object.fromEntries([...picker.querySelectorAll('[data-quick-field]')].map(field=>[field.dataset.quickField,field.value]));
        return client;
    }
    function setField(field,show,required=false) {
        field.disabled=!show; field.required=show && required; field.closest('.form-g').hidden=!show;
        const label=field.closest('.form-g').querySelector('.form-lbl');
        let mark=label.querySelector('[data-contract-form-required]');
        if(!mark) { mark=document.createElement('span'); mark.dataset.contractFormRequired='true'; mark.textContent=' *'; mark.setAttribute('aria-hidden','true'); label.append(mark); }
        mark.hidden=!field.required;
    }
    function refresh() {
        const current=context(), branch=form.elements.category?.value, subbranch=form.elements.subcategory?.value;
        let extra=0; const selected=[];
        for(const template of templates) {
            const {row,box,small,revision}=rows.get(template.id);
            const prepared=template.scope==='client' && (current?.prepared_templates || []).includes(template.id);
            const applies=contractTemplateApplies(template,current,branch,subbranch);
            if(!touched.has(template.id)) box.checked=Boolean(template.required && applies && !prepared);
            revision.disabled=!box.checked;
            small.textContent=(template.scope==='client' ? 'Cliente' : 'Polizza')+(prepared ? ' / Già preparato con questi dati' : applies ? ' / Adatto alla pratica' : ' / Verifica se necessario');
            if(applies && !prepared || box.checked) primary.append(row); else { others.append(row); extra++; }
            if(box.checked) selected.push(template);
        }
        summary.textContent=`Altri moduli disponibili (${extra})`; others.hidden=extra===0;
        choices.hidden=mode.value==='later';
        root.querySelector('[data-contract-form-tools]').hidden=choices.hidden;
        const preparing=mode.value==='prepare' && selected.length>0;
        const hasSigner=preparing && selected.some(template=>template.signer), hasRole=hasSigner && (current?.client_type==='company' || selected.some(template=>template.role)), hasPlace=preparing && selected.some(template=>template.place);
        prefill(signer,current?.client_type==='person' ? current.name : current?.reference_person);
        prefill(role,current?.client_type==='company' ? current.reference_role : '');
        root.querySelector('[data-contract-form-details]').hidden=!preparing;
        setField(signer,hasSigner,hasSigner); setField(role,hasRole,current?.client_type==='company'); setField(place,hasPlace,hasPlace); setField(date,preparing,preparing);
        root.querySelector('[data-contract-form-status]').textContent=preparing ? `${selected.length} ${selected.length===1 ? 'modulo sarà preparato' : 'moduli saranno preparati'} alla conferma. Le firme saranno raccolte successivamente.` : 'Il promemoria dei moduli resterà disponibile nella polizza.';
    }
    mode.addEventListener('change',refresh);
    root.querySelector('[data-contract-forms-select]').addEventListener('click',()=>{
        const current=context();
        for(const template of templates) { const row=rows.get(template.id); touched.add(template.id); row.box.checked=contractTemplateApplies(template,current,form.elements.category?.value,form.elements.subcategory?.value) && !(template.scope==='client' && (current?.prepared_templates || []).includes(template.id)); }
        refresh(); form.elements.contract_reviewed.checked=false;
    });
    root.querySelector('[data-contract-forms-clear]').addEventListener('click',()=>{ for(const template of templates) { touched.add(template.id); rows.get(template.id).box.checked=false; } refresh(); form.elements.contract_reviewed.checked=false; });
    form.addEventListener('input',event=>{ if(event.target.matches('[data-quick-field]') || ['category','subcategory'].includes(event.target.name)) refresh(); });
    form.addEventListener('change',event=>{ if(['category','subcategory'].includes(event.target.name)) refresh(); });
    form.addEventListener('client-context-loaded',event=>{ if(event.detail?.client) client=event.detail.client; refresh(); });
    form.addEventListener('client-context-cleared',()=>{ client={}; refresh(); });
    form.addEventListener('contract-data-read',refresh);
    refresh();
}
