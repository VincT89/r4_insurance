export const contractLabels = {
    number: 'Numero polizza', company: 'Compagnia', supplier_agency_id: 'Agenzia', category: 'Ramo', subcategory: 'Sottoramo',
    product: 'Prodotto', starts_on: 'Decorrenza', expires_on: 'Scadenza', premium: 'Premio di polizza',
    installment_premium: 'Premio rata', payment_frequency: 'Frazionamento', payment_method: 'Metodo di pagamento',
    first_installment_premium: 'Premio prima rata', tacit_renewal: 'Tacito rinnovo', vehicle_id: 'Veicolo / targa',
    merit_class_cu: 'Classe universale CU', merit_class_internal: 'Classe della compagnia', insured_subject: 'Soggetto assicurato',
};

const normalize = value => String(value ?? '').normalize('NFKC').replace(/[\u00a0\t]/g, ' ').replace(/ +/g, ' ').trim();
const key = value => normalize(value).toLocaleUpperCase('it');
const unique = values => [...new Set(values.filter(value => value !== null && value !== ''))];
const escape = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const datePattern = '(?:\\d{1,2}[/. \\-]\\d{1,2}[/. \\-]\\d{4}|\\d{4}-\\d{2}-\\d{2})';
const moneyPattern = '(?:\\d{1,3}(?:[. ]\\d{3})+(?:,\\d{2})?|\\d+(?:[,.]\\d{2})?)';
const timePrefix = '(?:(?:(?:dalle|alle) )?ore \\d{1,2}(?:[:.]\\d{2})?(?: del(?: giorno)?|,)?[ \\t]*)?';
const numberLabel = 'numero (?:di )?polizza(?: unico)?|n[.°º]?\\s*(?:di )?polizza|(?:la tua )?polizza\\s*(?:n[.°º]?|numero)\\s*(?:[:.]?\\s*/)?|polizza (?:auto|moto)\\b|polizza:|numero (?:di )?contratto|contratto\\s*n[.°º]?';
const policyNumbers = source => unique([...source.matchAll(new RegExp(`(?:^|\\n|\\||dal[ \\t]+)[ \\t]*(?:${numberLabel})[ \\t]*[:：#.]?[ \\t]*\\n?[ \\t]*([A-Z0-9][A-Z0-9/_.\\-]{1,99})`, 'gim'))]
    .map(match=>/\d/.test(match[1]) && !new RegExp(`^${datePattern}$`).test(match[1]) ? match[1].toUpperCase() : null));

function isoDate(raw) {
    let year, month, day;
    if (/^\d{4}-/.test(raw)) [year, month, day] = raw.split('-').map(Number);
    else [day, month, year] = raw.split(/[/. \-]/).map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (year < 1900 || year > 2200 || date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function money(raw) {
    let value = raw.replace(/ /g, '');
    if (value.includes(',')) value = value.replace(/\./g, '').replace(',', '.');
    else if (/^\d{1,3}(?:\.\d{3})+$/.test(value)) value = value.replace(/\./g, '');
    const number = Number(value);
    return Number.isFinite(number) && number > 0 && number <= 9999999999.99 ? number.toFixed(2) : null;
}

/** Only labelled values become candidates; conflicting occurrences remain visible for human review. */
export function parsePolicyContract(source, {companies = [], categories = [], subcategories = {}, agencies = {}} = {}) {
    const fullText = String(source).normalize('NFKC').replace(/\r/g, '').replace(/[\u00a0\t]/g, ' ');
    const pages = fullText.split('\f');
    /** Read the personalised schedule, not premiums or examples buried in annexes and general conditions. */
    const schedules = pages.filter(page => /(?:decorrenza|effetto|inizio contratto|validit[aà]|dalle ore \d{1,2}(?::\d{2})? del)[^\n]*\n?[^\n]*\d{4}/i.test(page)
        && /(?:scadenza(?: polizza)?|fine contratto|alle ore \d{1,2}(?::\d{2})? del)[^\n]*\n?[^\n]*\d{4}/i.test(page)
        && policyNumbers(page).length);
    const text = schedules.length ? schedules.join('\n\f\n') : fullText;
    const candidates = {}, warnings = [], notes = {};
    const capture = (label, pattern, convert = normalize, scope = text) => unique([...scope.matchAll(new RegExp(`(?:^|[\\s;|])(?:${label})[ \\t]*(?:[:：#\\[]|n[.°º]?(?![a-z])|numero\\b)?[ \\t]*\\n?[ \\t]*(${pattern})`, 'gim'))].map(match => convert(match[1])));
    candidates.number = policyNumbers(text);
    candidates.starts_on = capture('(?:data (?:di )?)?decorrenza(?: (?:della )?(?:polizza|contratto|copertura))?|(?:data (?:di )?)?effetto(?: (?:della )?polizza)?|inizio (?:copertura|assicurazione|contratto)|validit[aà]|dalle ore \\d{1,2}(?::\\d{2})? del', `${timePrefix}${datePattern}`, value => isoDate(value.match(new RegExp(datePattern))[0]));
    candidates.expires_on = capture('(?:data (?:di )?)?scadenza (?:della )?(?:polizza|contratto|copertura)|fine (?:copertura|assicurazione|contratto)|scadenza(?![ \\t]+(?:rata|premio|pagamento|titolo|prima|vincolo))|alle ore \\d{1,2}(?::\\d{2})? del', `${timePrefix}${datePattern}`, value => isoDate(value.match(new RegExp(datePattern))[0]));
    const amounts = label => capture(label, `(?:€|EUR)?[ \\t]*${moneyPattern}`, value => money(value.replace(/€|EUR/gi, '').trim()));
    const totalPremium = amounts('premio (?:totale(?: (?:di polizza|del contratto|del periodo))?|complessivo|di polizza)');
    const annualPremium = amounts('premio (?:annuo|annuale|totale annuo|totale annuale)(?: lordo| totale)?|premio lordo annuo(?: complessivo)?|importo annuo comprensivo delle imposte|totale premio (?:annuo|annuale)(?![ \\t]+tassabile)');
    candidates.premium = totalPremium.length ? totalPremium : annualPremium;
    const firstPremium = amounts('totale premio prima rata|premio prima rata|importo (?:lordo )?alla firma');
    const ordinaryPremium = amounts('totale premio rate successive|premio (?:di )?rata|importo (?:della )?rata|importo prossima rata');
    candidates.first_installment_premium = firstPremium;
    candidates.installment_premium = ordinaryPremium.length ? ordinaryPremium : firstPremium;
    candidates.payment_frequency = capture('frazionamento(?: (?:del )?premio)?|(?:codice )?rateazione|periodicit[aà](?: (?:del |di )?pagamento)?|pagamento', '(?:\\d+[ \\t]+)?(?:annuale|annuo|semestrale|quadrimestrale|trimestrale|mensile|unico|unica soluzione)\\b', value => { value=value.replace(/^\d+\s+/,''); return ({annuo:'Annuale', 'unica soluzione':'Unico'}[value.toLowerCase()] || value[0].toUpperCase() + value.slice(1).toLowerCase()); });
    candidates.merit_class_cu = capture('classe (?:di merito )?(?:universale|CU)(?: di assegnazione)?|CU di assegnazione|forma tariffaria bonus malus[ \\t]*[-–][ \\t]*CU', '\\d{1,2}\\b', value => +value >= 1 && +value <= 18 ? String(+value) : null);
    candidates.merit_class_internal = capture('classe (?:di merito )?(?:interna|compagnia|della compagnia|Prima)', '[A-Z0-9][A-Z0-9.\\-]{0,29}');
    const labelledLine = (label, scope = text) => capture(`(?:${label})[ \\t]*[:：]`, '[^\\n;|]{1,255}', normalize, scope);
    candidates.product = labelledLine('prodotto(?: assicurativo)?|nome (?:del )?prodotto', fullText);
    candidates.insured_subject = labelledLine('bene assicurato|soggetto assicurato');
    function catalogue(label, options, scope = text, firstPart = false) {
        const labelled = capture(label, '[^\\n;|]{1,150}', normalize, scope);
        return unique(options.filter(option => labelled.some(value => key(firstPart ? value.split('/')[0] : value) === key(option))));
    }
    candidates.category = catalogue('ramo(?: assicurativo)?', categories, text, true);
    if (!candidates.category.length) {
        const compact=value=>key(value).replace(/[^A-ZÀ-Ü0-9]/g,'');
        const title=text.split('\n').slice(0,22).join('\n')+'\n'+candidates.product.join('\n');
        const concepts=[];
        if ((/responsabilit[aà]['’]?\s+civile\s+auto|\bR[.]?C[.]?A[.]?\b/i.test(text) || /CERTIFICATO DI ASSICURAZIONE/i.test(text) && /Tipo veicolo[ \t:]+(?:Autovettura|Motociclo)/i.test(text)) && /veicol|targa|autovettur|libro matricola/i.test(text)) concepts.push('RCAUTO');
        else if (/responsabilit[aà]['’]?\s+civile\s+rischi\s+diversi|\bRCT\s*[/–-]\s*RCO\b/i.test(title)) concepts.push('RCGENERALE');
        else if (/infortuni (?:cumulativ|collettiv)|modello generico infortuni/i.test(title)) concepts.push('INFORTUNI');
        else if (/^\s*Incendio (?:Mutui|Rischi Nominati)\b/im.test(title)) concepts.push('INCENDIO');
        candidates.category=categories.filter(category=>concepts.includes(compact(category)));
        if(candidates.category.length===1) notes.category='Ramo collegato alla copertura indicata nel contratto.';
    }
    candidates.subcategory = catalogue('sottoramo', Object.values(subcategories).flat());
    if (!candidates.subcategory.length && candidates.category.length === 1) {
        const branch = candidates.category[0], available = subcategories[branch] || [];
        const combined = labelledLine('ramo(?: assicurativo)?').map(value => value.split('/')).filter(parts => key(parts[0]) === key(branch));
        candidates.subcategory = unique(available.filter(option => combined.some(parts => parts.slice(1).some(part => key(part) === key(option)))));
        if(!candidates.subcategory.length) {
            const classification=text.split('\n').filter(line=>!/(?:registro|sede legale|capitale sociale|albo imprese|partita iva|codice fiscale)/i.test(line)).join('\n');
            candidates.subcategory=available.filter(option=>new RegExp(`(?:^|[^A-ZÀ-Ü0-9])${escape(option)}(?:$|[^A-ZÀ-Ü0-9])`,'i').test(classification));
            if(!candidates.subcategory.length && key(branch).replace(/\s/g,'')==='RCAUTO' && /Tipo veicolo[ \t:]+Autovettura/i.test(text)) candidates.subcategory=available.filter(option=>key(option)==='AUTO');
        }
    }
    candidates.company = catalogue('compagnia(?: assicuratrice| di assicurazione)?|impresa(?: assicuratrice| di assicurazione)?', companies);
    if (!candidates.company.length) {
        const compact = value => key(value).replace(/[^A-ZÀ-Ü0-9]/g,'');
        candidates.company = companies.filter(company => {
            const normalized=key(company), literal=escape(company);
            if (['PRIMA','TUA'].includes(normalized)) return new RegExp(`(?:^|\\W)${literal}\\s+Assicurazioni\\b`,'i').test(fullText);
            if (normalized==='EUROPE') return fullText.split('\n').some(line=>key(line)===normalized);
            const companyScope=schedules.length ? text+'\n'+pages.slice(0,2).join('\n') : fullText;
            const stem=normalized.replace(/\s+S[.]?\s*P[.]?\s*A[.]?$/,'');
            if (stem.length>20 && companyScope.split('\n').some(line=>compact(line).includes(compact(stem)))) return true;
            const legal=companyScope.split('\n').some((line,index,lines)=> {
                if (normalized.length>20 && compact(line+' '+(lines[index+1] || '')).includes(compact(company))) return true;
                if (compact(line).includes(compact(company)) && /(?:S\.?\s*P\.?\s*A\.?|Insurance|Assicurazioni|Assistance|societ[aà]|impresa)/i.test(line)) return true;
                return key(line)===normalized;
            });
            if (!legal && ['PRIMA','TUA','EUROPE'].includes(normalized)) return false;
            return legal || new RegExp(`(?:^|\\W)${literal}(?:$|\\W)`, 'im').test(text);
        });
        /** A longer catalogue name takes precedence over the same brand prefix. */
        candidates.company=candidates.company.filter(company=>!candidates.company.some(other=>other!==company && key(other).startsWith(key(company)+' ')));
    }
    if (candidates.company.length === 1) {
        const issuer=labelledLine('impresa(?: assicuratrice)?').map(value=>value.split(/ - /)[0]);
        const distributed=fullText.match(/agenzia della compagnia[^\n]*\n([^\n]*?\b(?:SE|plc|S[.]?p[.]?A[.]?))(?:\s|$)/i)?.[1];
        if(distributed && !issuer.length) issuer.push(distributed);
        if(key(candidates.company[0])==='PRIMA' && issuer.length===1 && !/prima/i.test(issuer[0])) notes.company='Il PDF indica '+issuer[0]+' come impresa assicuratrice; Prima è l’agenzia di sottoscrizione. Verifica l’abbinamento alla voce PRIMA del catalogo.';
        const available = agencies[key(candidates.company[0])] || [];
        const names = catalogue('agenzia(?: intermediaria)?|intermediario', available.map(agency => agency.name));
        candidates.supplier_agency_id = available.filter(agency => names.includes(agency.name)).map(agency => String(agency.id));
        if (!candidates.supplier_agency_id.length) {
            const codes=capture('agenzia(?:/subagenzia)?|cod[.]? agenzia', '\\d{2,12}(?:/\\d+)?', value=>value.split('/')[0].replace(/^0+/,''));
            candidates.supplier_agency_id=available.filter(agency=>agency.code && codes.includes(String(agency.code).replace(/^0+/,''))).map(agency=>String(agency.id));
        }
    }
    candidates.payment_method = capture('modalit[aà] (?:di )?pagamento|metodo (?:di )?pagamento|metodo previsto|pagamento previsto', 'Bonifico a Compagnia|Bonifico a Intermediario|Assegno a Compagnia|Assegno a Intermediario|Contanti a Intermediario|Contanti a Compagnia|Carta di credito a Intermediario|Carta di credito a Compagnia|Finanziamento');
    candidates.tacit_renewal=capture('tacito rinnovo', 's[iì]|no',value=>/^s/i.test(value) ? '1' : '0');
    if (/deroga al tacito rinnovo|senza tacito rinnovo/i.test(text)) candidates.tacit_renewal=unique([...candidates.tacit_renewal,'0']);

    /** Personal identifiers are read only inside the contracting party block, never from the insurer or vehicle owner. */
    const clientBlocks = [];
    const lines=(text+'\n\f\n'+pages.slice(0,2).join('\n\f\n')).split('\n');
    for (let index=0;index<lines.length;index++) {
        if (!/^\s*(?:(?:dati (?:del )?|amministrazione )?contraente)(?:\s*[-/]\s*assicurato)?(?:\s*[:：]|\s*$|\s+principale\s*:)/i.test(lines[index])) continue;
        const block=[lines[index]];
        for (let next=index+1;next<Math.min(lines.length,index+16);next++) {
            if (/^\s*(?:\f|durata\b|i tuoi riferimenti|la societ|allianz\b|zurich\b|tua assicurazioni|unipol|groupama|nobis|(?:dati (?:dell[’']?\s*)?)?(?:assicurato|proprietario|veicolo|targa|tipo veicolo|compagnia|intermediario|agenzia|garanzie|premio|decorrenza|effetto|scadenza|condizioni)\b)/i.test(lines[next])) break;
            block.push(lines[next]);
        }
        clientBlocks.push(block.join('\n'));
    }
    const clientBlock = clientBlocks.filter(block=>/(?:codice fiscale|partita IVA|C[.]?\s*F[.]?)/i.test(block)).join('\n') || clientBlocks[0] || '';
    const client = {};
    const clientCapture = (field, label, pattern, convert = normalize) => {
        const found = capture(label, pattern, convert, clientBlock);
        if (found.length === 1) client[field] = found[0];
        else if (found.length > 1) warnings.push(`Dati del contraente: più valori per ${field === 'tax_code' ? 'codice fiscale' : field === 'vat_number' ? 'partita IVA' : field}.`);
    };
    const joinedName=match=> {
        const parts=[normalize(match[1])];
        for(const line of clientBlock.slice(match.index+match[0].length).split('\n').slice(1,3)) {
            if(!line.trim() || /\d|[:;|]|^(?:codice|partita|indirizzo|residenza|sede|C[.]?A[.]?P|citt|comune|prov|telefono|email|durata|premio|decorrenza|effetto|scadenza|data|via\b|firma)/i.test(line.trim())) break;
            parts.push(line.trim());
        }
        return normalize(parts.join(' ')).slice(0,255);
    };
    const partyNames=unique([...clientBlock.matchAll(/(?:^|\n)\s*(?:nome e cognome|ragione sociale)[ \t]*[:：]?[ \t]*([^\n;|:]{2,255})/gim)].map(joinedName));
    const directNames=unique([...clientBlock.matchAll(/(?:^|\n)\s*(?:amministrazione )?contraente(?: principale|\s*[-/]\s*assicurato)?[ \t]*[:：][ \t]*\n?[ \t]*([^\n;|:]{2,255})/gim)].map(joinedName));
    const names=partyNames.length ? partyNames : directNames;
    if (names.length===1) client.name=names[0];
    else if (names.length>1) warnings.push('Dati del contraente: più nomi presenti. Conferma il cliente corretto.');
    clientCapture('tax_code', 'codice fiscale(?: della contraente|\\s*[/]\\s*partita IVA)?|C[.]?\\s*F[.]?', '[A-Z0-9]{16}\\b|\\d{11}\\b|\\d{2}[.]\\d{3}[.]\\d{3}[.]\\d{3}\\b', value=>key(value).replace(/\./g,''));
    clientCapture('vat_number', 'partita IVA|P[.]?\\s*IVA', '(?:IT\\s*)?\\d{11}\\b', value => value.replace(/^IT\s*/i, ''));
    clientCapture('email', 'e[\\-]?mail', '[^\\s@]+@[^\\s@]+\\.[^\\s@;]+', value => value.toLowerCase());
    clientCapture('phone', 'telefono|cellulare', '[+\\d][\\d .()/-]{5,49}');
    clientCapture('address', 'indirizzo(?![ \\t]+PEC)|residenza|sede legale', '[^\\n;|]{1,255}');
    clientCapture('city', 'comune|citt[aà]', '[^\\n;|]{1,100}');
    clientCapture('postal_code', 'C[.]?\\s*A[.]?\\s*P[.]?', '\\d{5}\\b');
    clientCapture('province', 'provincia|prov[.]', '[A-Z]{2}\\b', key);
    if(client.name) {
        const inline=client.name.match(/^(.*?)\s+-\s+((?:VIA|PIAZZA|P[.]ZZA|CORSO|VIALE)\b.*?)\s+-\s+(\d{5})\s+(.+?)\s+([A-Z]{2})$/i);
        if(inline) { client.name=inline[1]; client.address ||= inline[2]; client.postal_code ||= inline[3]; client.city ||= inline[4]; client.province ||= inline[5].toUpperCase(); }
    }
    const cityProvince=client.city?.match(/^(.*?)\s+\(?([A-Z]{2})\)?$/);
    if(cityProvince) { client.city=cityProvince[1]; client.province ||= cityProvince[2]; }
    const postalCity=client.city?.match(/^(\d{5})\s+(.+)$/);
    if(postalCity) { client.postal_code ||= postalCity[1]; client.city=postalCity[2]; }
    if(client.name && !client.vat_number) {
        const parties=[...fullText.matchAll(/con:[ \t]*\n([^\n]{3,255})\nPartita I[.]?\s*V[.]?\s*A[.]?[ \t:]+(\d{11})\b/gi)]
            .filter(match=>key(client.name).startsWith(key(match[1])));
        if(parties.length===1) {
            client.vat_number=parties[0][2]; client.client_type='company';
            const address=fullText.slice(parties[0].index+parties[0][0].length).match(/^\s*\n((?:VIA|PIAZZA|CORSO|VIALE) [^\n]{1,200})\n([A-ZÀ-Ü ]{2,100})\n/);
            if(address) { client.address ||= address[1]; client.city ||= address[2]; }
        }
    }
    if (client.name && (/ragione sociale|sede legale/i.test(clientBlock) || /^\d{11}$/.test(client.tax_code || ''))) client.client_type = 'company';
    else if (client.name && /nome e cognome/i.test(clientBlock)) client.client_type = 'person';
    if(!candidates.insured_subject.length && client.name && /contraente\s*[-/]\s*assicurato/i.test(clientBlock)) candidates.insured_subject=[client.name];
    const plates=unique(fullText.split('\f').filter(page=>/targa|telaio|veicoli/i.test(page)).flatMap(page=>[...page.matchAll(/\b[A-Z]{2}\s*\d{3}\s*[A-Z]{2}\b/g)].map(match=>match[0].replace(/\s/g,''))));
    if(plates.length===1 && !candidates.insured_subject.length) {
        const type=labelledLine('tipo veicolo|dati del veicolo');
        candidates.insured_subject=[(type.length===1 ? type[0]+' / ' : '')+'Targa '+plates[0]].filter(value=>value.length<=255);
    }
    if(plates.length>1 && /libro matricola|flotta|parco veicoli/i.test(text)) candidates.insured_subject=['Veicoli: '+plates.join(', ')].filter(value=>value.length<=255);

    if (candidates.premium.length && !totalPremium.length && annualPremium.length && candidates.starts_on.length === 1 && candidates.expires_on.length === 1) {
        const start = new Date(candidates.starts_on[0] + 'T00:00:00Z');
        const end = new Date(candidates.expires_on[0] + 'T00:00:00Z');
        const days = (end - start) / 86400000;
        if (days < 365 || days > 366) {
            const years=end.getUTCFullYear()-start.getUTCFullYear();
            if(years>1 && years<=10 && start.getUTCMonth()===end.getUTCMonth() && start.getUTCDate()===end.getUTCDate() && annualPremium.length===1) {
                candidates.premium=[(Math.round(Number(annualPremium[0])*100)*years/100).toFixed(2)];
                notes.premium='Totale calcolato dal premio annuo del contratto per '+years+' annualità. Controlla eventuali regolazioni del premio.';
            } else { candidates.premium = []; warnings.push('Il contratto indica un premio annuo e una durata diversa da un anno: verifica il premio totale della copertura.'); }
        }
    }
    if (!candidates.premium.length && firstPremium.length===1 && ordinaryPremium.length<=1 && candidates.starts_on.length===1 && candidates.expires_on.length===1 && candidates.payment_frequency.length===1) {
        const months={Annuale:12,Semestrale:6,Quadrimestrale:4,Trimestrale:3,Mensile:1}[candidates.payment_frequency[0]];
        const start=new Date(candidates.starts_on[0]+'T00:00:00Z'), end=new Date(candidates.expires_on[0]+'T00:00:00Z');
        const count=months && ((end.getUTCFullYear()-start.getUTCFullYear())*12+end.getUTCMonth()-start.getUTCMonth())/months;
        if(Number.isInteger(count) && count>=1 && count<=120 && (count===1 || ordinaryPremium.length===1) && start.getUTCDate()===end.getUTCDate()) {
            candidates.premium=[((Math.round(Number(firstPremium[0])*100)+Math.round(Number(ordinaryPremium[0] || 0)*100)*(count-1))/100).toFixed(2)];
            notes.premium=count===1 ? 'Premio della prima rata per l’intera copertura.' : 'Totale calcolato dalla prima rata e '+(count===2 ? 'dalla rata successiva' : 'dalle '+(count-1)+' rate successive')+' indicate nel contratto. Controlla eventuali regolazioni del premio.';
        }
    }
    const periodPremium=amounts('premio totale del periodo');
    if(periodPremium.length===1 && !candidates.first_installment_premium.length) candidates.first_installment_premium=periodPremium;
    if (/(?:^|\n)\s*(?:documento di prova|bozza|preventivo\b|proposta\b)/im.test(text.slice(0, 800))) warnings.push('Il PDF è indicato come prova, bozza o proposta. Controlla il contratto definitivo prima di confermare la polizza.');
    const facsimile=/(?:fac[ -]?simile)/i.test(fullText.slice(0,1500)) || [...fullText.matchAll(/fac[ -]?simile/gi)].length>2;
    if (facsimile || (!candidates.number.length && /(?:condizioni (?:generali|di assicurazione)|documento informativo precontrattuale)/i.test(fullText.slice(0,4000)))) {
        for (const field of Object.keys(candidates)) if (field !== 'company') candidates[field]=[];
        for (const field of Object.keys(client)) delete client[field];
        warnings.push(facsimile ? 'Il PDF è un facsimile: i dati di esempio non vengono usati per creare la polizza.' : 'Il PDF contiene condizioni generali o un modulo non compilato: mancano i dati di una polizza individuale.');
    }
    if (pages.length>1 && !schedules.length) warnings.push('Non è stata individuata una scheda completa di polizza: controlla i dati sul documento originale.');
    const values = {};
    for (const [field, found] of Object.entries(candidates)) {
        if (found.length === 1) values[field] = found[0];
        else if (found.length > 1) warnings.push(`${contractLabels[field]}: trovati valori diversi (${found.join(', ')}). Scegli il dato corretto nel modulo.`);
    }
    return {values, candidates, client, warnings, notes, vehicles:facsimile ? [] : plates.map(plate=>({plate}))};
}
