const header = /^(?:numero (?:di )?(?:polizza|contratto)|n[.°º]?\s*polizza|decorrenza|effetto(?: polizza)?|scadenza(?: polizza| prima rata)?|premio (?:totale|annuo|annuale|rata|netto|tassabile)|totale premio (?:prima rata|rate successive)|accessori|tasse|imposte|imponibile|totale(?: eur totale)?|(?:codice )?rateazione|frazionamento|compagnia|ramo|sottoramo|prodotto|contraente(?:\s*\/\s*assicurato)?|codice fiscale\s*\/\s*partita iva|dalle ore 24 del|alle ore 24 del)\s*:?[ \t]*$/i;

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
    const partyRow=rows.findIndex(row=>row.items.some(item=>/^Contraente$/i.test(item.str.trim())) && row.items.some(item=>/^Proprietario$/i.test(item.str.trim())));
    if(partyRow>=0) {
        const roles=rows[partyRow].items, contractor=roles.find(item=>/^Contraente$/i.test(item.str.trim()));
        const next=roles.filter(item=>item.transform[4]>contractor.transform[4]).sort((a,b)=>a.transform[4]-b.transform[4])[0];
        const names={'nome':'Nome e cognome','comune di residenza':'Comune','indirizzo domicilio/sede':'Indirizzo','comune del domicilio':'Comune','codice fiscale/p.iva':'Codice fiscale'};
        const personal=[];
        for(const row of rows.slice(partyRow+1,partyRow+11)) {
            const label=row.items.find(item=>names[item.str.trim().toLowerCase()]);
            if(!label || !next) continue;
            const value=row.items.filter(item=>item.transform[4]>=contractor.transform[4]-3 && item.transform[4]<next.transform[4]-3).map(item=>item.str.trim()).join(' ');
            if(value) personal.push(names[label.str.trim().toLowerCase()]+': '+value);
        }
        if(personal.some(line=>line.startsWith('Nome e cognome:'))) text.push('Contraente:',...personal,'Veicolo:');
    }
    for(let index=0;index<rows.length-1;index++) {
        const labels=rows[index].items;
        if(!labels.some(item=>/^DETTAGLIO PREMIO ALLA FIRMA$/i.test(item.str.trim()))) continue;
        const total=labels.find(item=>/^Totale$/i.test(item.str.trim()));
        if(!total) continue;
        const previous=labels.filter(item=>item.transform[4]<total.transform[4]).at(-1);
        if(!previous || rows[index].y-rows[index+1].y>30) continue;
        const from=(previous.transform[4]+total.transform[4])/2;
        const amount=rows[index+1].items.filter(item=>item.transform[4]>=from).map(item=>item.str.trim()).join(' ');
        if(/^(?:€\s*)?\d[\d., ]*$/.test(amount)) text.push('Totale premio prima rata: '+amount);
    }
    const certificate=tokens.find(item=>/^CERTIFICATO DI ASSICURAZIONE$/i.test(item.str.trim()));
    const contacts=tokens.find(item=>/^NUMERI UTILI$/i.test(item.str.trim()));
    if(certificate && contacts && contacts.transform[4]>certificate.transform[4]) {
        /** Keep the policyholder column separate from customer-service contacts printed beside it. */
        for(const row of rows) {
            if(row.y>=certificate.transform[5]) continue;
            const label=row.items.find(item=>/^(?:Impresa|Polizza N[.]?|Contraente|Comune|Targa veicolo|Tipo veicolo|Validit[aà])$/i.test(item.str.trim()));
            if(!label) continue;
            const value=row.items.filter(item=>item.transform[4]>label.transform[4] && item.transform[4]<contacts.transform[4]-5).map(item=>item.str.trim()).join(' ');
            if(value) text.push(label.str.trim()+': '+value);
        }
    }
    /** Column positions preserve empty cells: a missing value must never borrow a date or amount from its neighbour. */
    for (let index=0; index<rows.length; index++) {
        const labels=rows[index].items, values=rows[index+1]?.items;
        const headerRow=labels.filter(item=>header.test(item.str.trim())).length >= 2
            && labels.every(item=>header.test(item.str.trim()) || (/^[A-ZÀ-Ü0-9 .’'/%()_-]{1,55}$/.test(item.str.trim()) && !/^\d/.test(item.str.trim())));
        if (headerRow && values && rows[index].y-rows[index+1].y <= 40) {
            const partyTable=labels.some(label=>/^contraente/i.test(label.str));
            let consumed=1;
            const paired = labels.map((label,column)=> {
                const left=label.transform[4], right=labels[column+1]?.transform[4] ?? Infinity;
                let cell=values.filter(value=>value.transform[4]>=left-2 && value.transform[4]<right-2);
                if (!cell.length && partyTable && rows[index+2] && rows[index].y-rows[index+2].y<=30 && !rows[index+2].items.some(item=>header.test(item.str))) {
                    cell=rows[index+2].items.filter(value=>value.transform[4]>=left-2 && value.transform[4]<right-2);
                    if(cell.length) consumed=2;
                }
                const value=cell.map(item=>item.str.trim()).join(' ');
                const amountColumn=/^(?:premio (?:netto|tassabile)|totale|accessori|tasse|imposte|imponibile)/i.test(label.str) && /^[€EUR \d.,]+$/.test(value) && /\d/.test(value);
                if(!cell.length || (!amountColumn && Math.abs(cell[0].transform[4]-left)>12)) return null;
                let name=label.str.replace(/:\s*$/,'').trim();
                const qualifier=values.filter(item=>item.transform[4]<labels[0].transform[4]-2).map(item=>item.str).join(' ');
                if(/^TOTALE EUR$/i.test(name) && /premio sino al/i.test(qualifier)) {
                    const context=rows.slice(Math.max(0,index-2),index).flatMap(row=>row.items).map(item=>item.str).join(' ');
                    name=/AL 100%/i.test(context) ? 'Premio totale del periodo' : 'Premio della quota';
                }
                return `${name}: ${value}`;
            });
            if (paired.filter(Boolean).length >= 2) { text.push(...paired.filter(Boolean)); index+=consumed; }
            else text.push(labels.map(item=>item.str.trim()).join(' | ')+' |');
        } else text.push(labels.map(item=>item.str.trim()).join(headerRow ? ' | ' : ' ') + (headerRow ? ' |' : ''));
    }
    return text.join('\n');
}
