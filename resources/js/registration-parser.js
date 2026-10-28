const fields = {
    plate: ['A', 'TARGA', 'NUMERO DI IMMATRICOLAZIONE'],
    make: ['D\\.1', 'MARCA'],
    model: ['D\\.3', 'MODELLO', 'DENOMINAZIONE COMMERCIALE'],
    vin: ['E', 'TELAIO', 'NUMERO DI TELAIO', 'NUMERO DI IDENTIFICAZIONE DEL VEICOLO'],
    registered_on: ['B', 'PRIMA IMMATRICOLAZIONE', 'DATA DELLA PRIMA IMMATRICOLAZIONE'],
};

function normalize(field, value) {
    const clean = value.replace(/\s+/g, ' ').trim();
    if (field === 'plate') {
        const plate = clean.replace(/\s/g, '');
        return /^[A-Z0-9-]{5,12}$/.test(plate) && /[A-Z]/.test(plate) && /\d/.test(plate) ? plate : null;
    }
    if (field === 'vin') return /^[A-HJ-NPR-Z0-9]{17}$/.test(clean) ? clean : null;
    if (field === 'registered_on') {
        const match = clean.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$/);
        if (!match) return null;
        const [,day,month,year] = match;
        const date = new Date(Date.UTC(+year, +month-1, +day));
        if (+year < 1900 || date.getUTCFullYear() !== +year || date.getUTCMonth() !== +month-1 || date.getUTCDate() !== +day) return null;
        return `${year}-${month.padStart(2,'0')}-${day.padStart(2,'0')}`;
    }
    return clean.length >= 2 && clean.length <= 100 && !/NON LEGGIBILE|ILLEGIBILE|DA VERIFICARE|SCADENZA/.test(clean) ? clean : null;
}

export function registrationCandidates(input) {
    /** Read explicit field labels only; never turn a guessed letter into a digit in a plate or VIN. */
    const text = String(input).toUpperCase().replace(/\r/g,'\n')
        .replace(/[([]\s*(A|B|D\s*\.\s*[123]|E|F\s*\.\s*[123]|I|J|P\s*\.\s*[123])\s*[)\]]\s*/g, (_,code) => `\n${code.replace(/\s/g,'')}: `);
    return Object.fromEntries(Object.entries(fields).map(([field, labels]) => {
        const regex = new RegExp(`(?:^|\\n)\\s*(?:${labels.join('|')})\\s*(?::|\\.(?=\\s)|\\s)\\s*([^\\n]+)`, 'g');
        const values = [...text.matchAll(regex)].map(match => normalize(field, match[1])).filter(Boolean);
        return [field, [...new Set(values)]];
    }));
}

export function mergeRegistrationCandidates(results) {
    return Object.fromEntries(Object.keys(fields).map(field => [field, [...new Set(results.flatMap(result => result[field] || []))]]));
}
