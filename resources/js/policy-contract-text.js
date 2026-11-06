const header = /^(?:numero (?:di )?(?:polizza|contratto)|n[.°º]?\s*polizza|decorrenza|effetto|scadenza(?: polizza)?|premio (?:totale|annuo|annuale|rata)|frazionamento|compagnia|ramo|sottoramo)\s*:?[ \t]*$/i;

export function policyContractPageText(items) {
    const tokens = items.filter(item => typeof item.str === 'string' && item.str.trim());
    if (tokens.some(item => !Array.isArray(item.transform) || !Number.isFinite(item.transform[4]) || !Number.isFinite(item.transform[5]))) return tokens.map(item => item.str).join('\n');
    const rows = [];
    for (const item of [...tokens].sort((a,b)=>b.transform[5]-a.transform[5] || a.transform[4]-b.transform[4])) {
        const row = rows.at(-1);
        if (row && Math.abs(row.y-item.transform[5]) <= 3) row.items.push(item);
        else rows.push({y:item.transform[5],items:[item]});
    }
    for (const row of rows) row.items.sort((a,b)=>a.transform[4]-b.transform[4]);
    const text = [];
    /** Pair aligned table headers only when every column has exactly one value on the immediately following row. */
    for (let index=0; index<rows.length; index++) {
        const labels=rows[index].items, values=rows[index+1]?.items;
        const headerRow=labels.filter(item=>header.test(item.str.trim())).length >= 2 && !labels.some(item=>/\d/.test(item.str));
        if (headerRow && values && labels.length === values.length && labels.every(item=>header.test(item.str.trim())) && rows[index].y-rows[index+1].y <= 40
            && labels.every((label,column)=>Math.abs(label.transform[4]-values[column].transform[4]) <= 12)) {
            text.push(...labels.map((label,column)=>`${label.str.replace(/:\s*$/,'').trim()}: ${values[column].str.trim()}`));
            index++;
        } else text.push(labels.map(item=>item.str.trim()).join(headerRow ? ' | ' : ' ') + (headerRow ? ' |' : ''));
    }
    return text.join('\n');
}
