import {parsePolicyContract, contractLabels} from './policy-contract-parser.js';

const clean=value=>String(value ?? '').normalize('NFKC').trim();
const key=value=>clean(value).toLocaleUpperCase('it').replace(/[^A-ZÀ-Ü0-9]/g,'');
const companyKey=value=>key(clean(value).replace(/\b(?:compagnia\s+di\s+assicurazioni|assicurazioni|insurance|company|S\.?\s*P\.?\s*A\.?|S\.?\s*A\.?|SE|PLC)\b/gi,''));
const fieldLabels={...contractLabels,'client.name':'Nome del contraente','client.client_type':'Tipo di cliente','client.tax_code':'Codice fiscale del contraente','client.vat_number':'Partita IVA del contraente','client.address':'Indirizzo del contraente','client.city':'Città del contraente','client.postal_code':'CAP del contraente','client.province':'Provincia del contraente','client.email':'Email del contraente','client.phone':'Telefono del contraente','vehicle.plate':'Targa','vehicle.make':'Marca','vehicle.model':'Modello del veicolo','vehicle.vin':'Telaio','vehicle.registered_on':'Prima immatricolazione','agency.name':'Nome dell’agenzia','agency.code':'Codice dell’agenzia','annual_premium':'Premio annuo','distributor':'Distributore'};

export function interpretAiContract(extraction,catalog={}) {
    const warnings=[], notes=Object.create(null), found=Object.create(null), blocked=new Set();
    if(!['policy','certificate'].includes(extraction?.document_kind)) {
        const reason={multiple:'Il PDF contiene più polizze: carica un contratto alla volta per evitare di mescolare i dati.',conditions:'Il PDF contiene condizioni generali: serve il contratto compilato con i dati della polizza.',quotation:'Il PDF è un preventivo o un facsimile: carica il contratto definitivo.'}[extraction?.document_kind] || 'Il documento AI non è riconosciuto come una singola polizza o un certificato compilato. Controlla il contratto originale.';
        return {values:{},client:{},vehicles:[],notes,warnings:[reason,...(extraction?.warnings || []).map(value=>clean(value).slice(0,500))]};
    }
    for(const field of extraction.fields || []) {
        if(!field) continue;
        const name=field.key, value=clean(field.value);
        if(field.certainty!=='certain' || !Number.isInteger(field.page) || field.page<1 || !clean(field.quote)) { blocked.add(name); if(value)warnings.push(`${fieldLabels[name] || name}: dato incerto, da controllare nel PDF.`); continue; }
        if(!value) continue;
        (found[name] ||= []).push(value);
        notes[name]=`Pagina ${field.page}: ${clean(field.quote).slice(0,350)}`;
    }
    const fields=Object.create(null);
    for(const [name,values] of Object.entries(found)) {
        const unique=[...new Set(values)];
        if(unique.length===1 && !blocked.has(name)) fields[name]=unique[0]; else { blocked.add(name); warnings.push(`${fieldLabels[name] || name}: nel PDF sono presenti valori diversi o incerti. Conferma quello corretto.`); }
    }
    const companyMatches=value=>(catalog.companies || []).filter(company=>key(company)===key(value) || companyKey(company) && companyKey(company)===companyKey(value));
    let companies=fields.company ? companyMatches(fields.company) : [];
    if(!companies.length && fields.distributor) {
        companies=companyMatches(fields.distributor);
        if(companies.length===1 && fields.company) notes.company=`Impresa assicuratrice: ${fields.company}. Distributore: ${fields.distributor}. Verifica l’abbinamento alla voce ${companies[0]} del catalogo.`;
    }
    if(companies.length===1) fields.company=companies[0];
    else { if(fields.company) warnings.push(`Compagnia: “${fields.company}” non ha un abbinamento univoco nel catalogo.`); delete fields.company; }
    if(fields.category) {
        const branch=(catalog.categories || []).filter(category=>key(category)===key(fields.category) || key(category)==='RCAUTO' && ['RCA','RESPONSABILITACIVILEAUTO'].includes(key(fields.category)));
        if(branch.length===1) fields.category=branch[0]; else { warnings.push(`Ramo: “${fields.category}” da collegare al catalogo.`); delete fields.category; }
    }
    if(fields.subcategory && fields.category) {
        const subs=(catalog.subcategories?.[fields.category] || []).filter(sub=>key(sub)===key(fields.subcategory));
        if(subs.length===1) fields.subcategory=subs[0]; else delete fields.subcategory;
    }
    const labels={number:'Numero polizza',company:'Compagnia',category:'Ramo',subcategory:'Sottoramo',product:'Prodotto',starts_on:'Decorrenza',expires_on:'Scadenza',premium:'Premio di polizza',annual_premium:'Premio annuo',first_installment_premium:'Premio prima rata',installment_premium:'Premio rata',payment_frequency:'Frazionamento',payment_method:'Metodo di pagamento',tacit_renewal:'Tacito rinnovo',merit_class_cu:'Classe CU',merit_class_internal:'Classe interna',insured_subject:'Soggetto assicurato'};
    const lines=[];
    for(const [name,label] of Object.entries(labels)) if(fields[name]) {
        let value=fields[name];
        if(['premium','annual_premium','first_installment_premium','installment_premium'].includes(name)) {
            if(!/^\d{1,10}(?:\.\d{1,2})?$/.test(value) || Number(value)<=0) { warnings.push(label+': importo da controllare.'); continue; }
            value=Number(value).toFixed(2).replace('.',',');
        }
        if(name==='tacit_renewal') value=value==='1' ? 'Sì' : value==='0' ? 'No' : '';
        if(value) lines.push(label+': '+value.replace(/[\n\r;|]/g,' '));
    }
    if(fields['client.name']) {
        lines.push('Contraente:',(fields['client.client_type']==='company' ? 'Ragione sociale: ' : 'Nome e cognome: ')+fields['client.name'].replace(/[\n\r;|]/g,' '));
        const clientLabels={tax_code:'Codice fiscale',vat_number:'Partita IVA',address:'Indirizzo',city:'Città',postal_code:'CAP',province:'Provincia',email:'Email',phone:'Telefono'};
        for(const [name,label] of Object.entries(clientLabels)) if(fields['client.'+name]) lines.push(label+': '+fields['client.'+name].replace(/[\n\r;|]/g,' '));
        lines.push('Veicolo:');
    }
    const parsed=parsePolicyContract(lines.join('\n'),catalog);
    /** Conflicting contract terms must not be reintroduced by arithmetic or first-installment fallbacks. */
    if(['premium','annual_premium','first_installment_premium','installment_premium','payment_frequency'].some(name=>blocked.has(name))) {
        for(const name of ['premium','installment_premium']) if(!fields[name]) delete parsed.values[name];
    }
    for(const name of blocked) if(Object.hasOwn(contractLabels,name)) delete parsed.values[name];
    /** One uncertain name must not discard independently evidenced fiscal identifiers or address fields. */
    for(const name of ['name','tax_code','vat_number','address','city','postal_code','province','email','phone']) if(fields['client.'+name]) parsed.client[name]=fields['client.'+name];
    if(['person','company'].includes(fields['client.client_type'])) parsed.client.client_type=fields['client.client_type'];
    const agencyName=fields['agency.name'], agencyCode=fields['agency.code']?.replace(/^0+/,'');
    const agencies=catalog.agencies?.[clean(parsed.values.company).toLocaleUpperCase('it')] || [];
    const matched=agencies.filter(agency=>agencyName && key(agency.name)===key(agencyName) || agencyCode && agency.code && String(agency.code).replace(/^0+/,'')===agencyCode);
    if(matched.length===1) parsed.values.supplier_agency_id=String(matched[0].id);
    else if(agencyName || agencyCode) warnings.push('Agenzia letta nel PDF, da collegare a un’agenzia della compagnia presente nel catalogo.');
    const plate=fields['vehicle.plate']?.replace(/[\s-]/g,'').toUpperCase();
    parsed.vehicles=plate && /^[A-Z0-9]{3,20}$/.test(plate) ? [{plate,...Object.fromEntries(['make','model','vin','registered_on'].filter(name=>fields['vehicle.'+name]).map(name=>[name,fields['vehicle.'+name]]))}] : [];
    if(plate && !parsed.values.insured_subject) parsed.values.insured_subject=[fields['vehicle.make'],fields['vehicle.model'],'Targa '+plate].filter(Boolean).join(' / ').slice(0,255);
    for(const [name,note] of Object.entries(notes)) if(parsed.values[name]!==undefined) parsed.notes[name]=note;
    parsed.warnings.push(...warnings,...(extraction.warnings || []).map(value=>clean(value).slice(0,500)));
    return parsed;
}
