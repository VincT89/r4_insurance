const normalized = value => String(value ?? '').replace(/\s+/g, ' ').trim();

export function exportColumnType(header) {
    const name = normalized(header).toLocaleLowerCase('it');
    if (/^(data(?:\s|$)|scadenza|decorrenza|dal$|al$|da ricevere entro$|caricato il$|creato il$|inserita il$|registrata il$|ultimo rinnovo$|preparata il$|inviata il$|ricevuta il$|validità$)/.test(name)) return 'date';
    if (/^(polizze|appendici|clienti distinti|premio non indicato|non pagate|righe|gruppi interessati|quantità)$/.test(name)) return 'integer';
    if (/^(premi?(?:o)?(?:\s|$)|importo|totale(?:\s|$)|residuo|incassato|costo|valore economico)/.test(name)) return 'currency';
    if (/^(aliquota|percentuale)(?:\s|$)/.test(name)) return 'percent';
    return 'text';
}

function italianNumber(value) {
    const raw = normalized(value).replace(/\s/g, '');
    if (!/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d+)?$/.test(raw)) return null;
    const number = Number(raw.replaceAll('.', '').replace(',', '.'));
    return Number.isFinite(number) && Math.abs(number) <= Number.MAX_SAFE_INTEGER ? number : null;
}

export function excelCell(value, type = 'text') {
    const text = String(value ?? '');
    const raw = normalized(text);
    const fallback = { value: text, numFmt: '@' };
    if (!raw || /^[—–-]$/.test(raw)) return { value: null, numFmt: '@' };
    if (type === 'date') {
        const it = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        const parts = it ? [+it[3], +it[2], +it[1], +(it[4] || 0), +(it[5] || 0), +(it[6] || 0)] : iso ? iso.slice(1).map(v => +(v || 0)) : null;
        if (!parts) return fallback;
        const [year, month, day, hour, minute, second] = parts;
        const date = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
        if (year < 1900 || year > 9999 || date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day || hour > 23 || minute > 59 || second > 59) return fallback;
        return { value: date, numFmt: (it?.[4] || iso?.[4]) ? 'dd/mm/yyyy hh:mm' + ((it?.[6] || iso?.[6]) ? ':ss' : '') : 'dd/mm/yyyy' };
    }
    /** Only explicit amounts/percentages or typed columns become numbers; identifiers and formula-like strings stay literal text. */
    const currency = raw.match(/^(?:€\s*(-?[\d.,\s]+)|(-?[\d.,\s]+)\s*€)$/);
    const percentage = raw.match(/^(-?[\d.,\s]+)\s*%$/);
    if (currency || percentage || ['currency', 'percent', 'integer', 'number'].includes(type)) {
        const number = italianNumber(currency ? (currency[1] || currency[2]) : percentage ? percentage[1] : raw);
        if (number === null || (type === 'integer' && !Number.isInteger(number))) return fallback;
        if (percentage || type === 'percent') return { value: number / 100, numFmt: '0.00%' };
        return { value: number, numFmt: currency || type === 'currency' ? '#,##0.00 "€"' : type === 'integer' ? '0' : '#,##0.########' };
    }
    return fallback;
}
