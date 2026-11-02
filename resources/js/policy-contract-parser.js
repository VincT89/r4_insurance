export const contractLabels = {
    number: 'Numero polizza', company: 'Compagnia', supplier_agency_id: 'Agenzia', category: 'Ramo', subcategory: 'Sottoramo',
    product: 'Prodotto', starts_on: 'Decorrenza', expires_on: 'Scadenza', premium: 'Premio di polizza',
    installment_premium: 'Premio rata', payment_frequency: 'Frazionamento', payment_method: 'Metodo di pagamento',
    merit_class_cu: 'Classe universale CU', merit_class_internal: 'Classe della compagnia', insured_subject: 'Soggetto assicurato',
};

const normalize = value => String(value ?? '').normalize('NFKC').replace(/[\u00a0\t]/g, ' ').replace(/ +/g, ' ').trim();
const key = value => normalize(value).toLocaleUpperCase('it');
const unique = values => [...new Set(values.filter(value => value !== null && value !== ''))];
const escape = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const datePattern = '(?:\\d{1,2}[/.\\-]\\d{1,2}[/.\\-]\\d{4}|\\d{4}-\\d{2}-\\d{2})';
const moneyPattern = '(?:\\d{1,3}(?:[. ]\\d{3})+(?:,\\d{2})?|\\d+(?:[,.]\\d{2})?)';

function isoDate(raw) {
    let year, month, day;
    if (/^\d{4}-/.test(raw)) [year, month, day] = raw.split('-').map(Number);
    else [day, month, year] = raw.split(/[/.\-]/).map(Number);
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
    const text = String(source).normalize('NFKC').replace(/\r/g, '').replace(/[\u00a0\t]/g, ' ');
    const candidates = {}, warnings = [];
    const capture = (label, pattern, convert = normalize, scope = text) => unique([...scope.matchAll(new RegExp(`(?:^|[\\s;|])(?:${label})[ \\t]*(?:[:：#]|n[.°º]?|numero)?[ \\t]*\\n?[ \\t]*(${pattern})`, 'gim'))].map(match => convert(match[1])));
    candidates.number = capture('numero (?:di )?polizza|n[.°º]?\\s*(?:di )?polizza|polizza\\s*(?:n[.°º]?|numero)|numero (?:di )?contratto|contratto\\s*n[.°º]?', '[A-Z0-9][A-Z0-9/_.\\-]{1,99}', value => /\d/.test(value) ? value.toUpperCase() : null);
    candidates.starts_on = capture('(?:data (?:di )?)?decorrenza(?: (?:della )?(?:polizza|contratto|copertura))?|inizio (?:copertura|assicurazione)', `(?:alle ore \\d{1,2}(?:[:.]\\d{2})? del )?${datePattern}`, value => isoDate(value.match(new RegExp(datePattern))[0]));
    candidates.expires_on = capture('(?:data (?:di )?)?scadenza (?:della )?(?:polizza|contratto|copertura)|fine (?:copertura|assicurazione)|scadenza(?![ \\t]+(?:rata|premio|pagamento|titolo))', datePattern, isoDate);
    candidates.premium = capture('premio (?:totale (?:di polizza|del contratto)|totale|complessivo|annuo(?: lordo| totale)?|annuale(?: lordo| totale)?)|totale premio (?:annuo|annuale)', `(?:€|EUR)?[ \\t]*${moneyPattern}`, value => money(value.replace(/€|EUR/gi, '').trim()));
    candidates.installment_premium = capture('premio (?:di )?rata|importo (?:della )?rata', `(?:€|EUR)?[ \\t]*${moneyPattern}`, value => money(value.replace(/€|EUR/gi, '').trim()));
    candidates.payment_frequency = capture('frazionamento(?: (?:del )?premio)?|periodicit[aà](?: (?:del )?pagamento)?', 'annuale|annuo|semestrale|quadrimestrale|trimestrale|mensile|unico|unica soluzione', value => ({annuo:'Annuale', 'unica soluzione':'Unico'}[value.toLowerCase()] || value[0].toUpperCase() + value.slice(1).toLowerCase()));
    candidates.merit_class_cu = capture('classe (?:di merito )?(?:universale|CU)(?: di assegnazione)?|CU di assegnazione', '\\d{1,2}\\b', value => +value >= 1 && +value <= 18 ? String(+value) : null);
    candidates.merit_class_internal = capture('classe (?:di merito )?(?:interna|compagnia|della compagnia)', '[A-Z0-9][A-Z0-9.\\-]{0,29}');
    const labelledLine = label => capture(label, '[^\\n;|]{1,255}');
    candidates.product = labelledLine('prodotto(?: assicurativo)?|nome (?:del )?prodotto');
    candidates.insured_subject = labelledLine('bene assicurato|soggetto assicurato');
    function catalogue(label, options, scope = text) {
        const labelled = capture(label, '[^\\n;|]{1,150}', normalize, scope);
        return unique(options.filter(option => labelled.some(value => key(value) === key(option))));
    }
    candidates.category = catalogue('ramo(?: assicurativo)?', categories);
    candidates.subcategory = catalogue('sottoramo', Object.values(subcategories).flat());
    candidates.company = catalogue('compagnia(?: assicuratrice| di assicurazione)?|impresa(?: assicuratrice| di assicurazione)?', companies);
    if (!candidates.company.length) candidates.company = companies.filter(company => new RegExp(`(?:^|\\W)${escape(company)}(?:$|\\W)`, 'im').test(text));
    if (candidates.company.length === 1) {
        const available = agencies[key(candidates.company[0])] || [];
        const names = catalogue('agenzia(?: intermediaria)?|intermediario', available.map(agency => agency.name));
        candidates.supplier_agency_id = available.filter(agency => names.includes(agency.name)).map(agency => String(agency.id));
    }
    candidates.payment_method = capture('modalit[aà] (?:di )?pagamento|metodo (?:di )?pagamento', 'Bonifico a Compagnia|Bonifico a Intermediario|Assegno a Compagnia|Assegno a Intermediario|Contanti a Intermediario|Contanti a Compagnia|Carta di credito a Intermediario|Carta di credito a Compagnia|Finanziamento');

    /** Personal identifiers are read only inside the contracting party block, never from the insurer or vehicle owner. */
    const clientBlock = text.match(/(?:^|\n)\s*(?:dati (?:del )?)?contraente\b[^\n]*\n?[\s\S]*?(?=\n\s*(?:dati (?:dell[’']?\s*)?)?(?:assicurato|proprietario|veicolo|compagnia|intermediario|agenzia|garanzie|premio|decorrenza|condizioni)\b|(?![\s\S]))/im)?.[0] || '';
    const client = {};
    const clientCapture = (field, label, pattern, convert = normalize) => {
        const found = capture(label, pattern, convert, clientBlock);
        if (found.length === 1) client[field] = found[0];
        else if (found.length > 1) warnings.push(`Dati del contraente: più valori per ${field === 'tax_code' ? 'codice fiscale' : field === 'vat_number' ? 'partita IVA' : field}.`);
    };
    clientCapture('name', 'contraente|nome e cognome|ragione sociale', '[^\\n;|:]{2,255}', value => /^(?:dati|nome|codice|ragione|partita)\b/i.test(value) ? null : normalize(value));
    clientCapture('tax_code', 'codice fiscale|C[.]?\\s*F[.]?', '[A-Z0-9]{16}\\b|\\d{11}\\b', key);
    clientCapture('vat_number', 'partita IVA|P[.]?\\s*IVA', '(?:IT\\s*)?\\d{11}\\b', value => value.replace(/^IT\s*/i, ''));
    clientCapture('email', 'e[\\-]?mail', '[^\\s@]+@[^\\s@]+\\.[^\\s@;]+', value => value.toLowerCase());
    clientCapture('phone', 'telefono|cellulare', '[+\\d][\\d .()/-]{5,49}');
    clientCapture('address', 'indirizzo|residenza|sede legale', '[^\\n;|]{1,255}');
    clientCapture('city', 'comune|citt[aà]', '[^\\n;|]{1,100}');
    clientCapture('postal_code', 'CAP', '\\d{5}\\b');
    clientCapture('province', 'provincia', '[A-Z]{2}\\b', key);
    if (client.name) client.client_type = /ragione sociale|sede legale/i.test(clientBlock) ? 'company' : 'person';

    if (candidates.premium.length && /premio annu(?:o|ale)/i.test(text) && candidates.starts_on.length === 1 && candidates.expires_on.length === 1) {
        const start = new Date(candidates.starts_on[0] + 'T00:00:00Z');
        const end = new Date(candidates.expires_on[0] + 'T00:00:00Z');
        const days = (end - start) / 86400000;
        if (days < 365 || days > 366) { candidates.premium = []; warnings.push('Il contratto indica un premio annuo e una durata diversa da un anno: verifica il premio totale della copertura.'); }
    }
    const values = {};
    for (const [field, found] of Object.entries(candidates)) {
        if (found.length === 1) values[field] = found[0];
        else if (found.length > 1) warnings.push(`${contractLabels[field]}: trovati valori diversi (${found.join(', ')}). Scegli il dato corretto nel modulo.`);
    }
    return {values, candidates, client, warnings};
}
