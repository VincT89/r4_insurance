export function expiryCandidates(text) {
    const normalized = String(text).slice(0, 100000).replace(/\r/g, '\n');
    const label = /(?:scadenza|scade\s+il|valido\s+fino\s+al|date\s+of\s+expiry|expiry|expiration|4\s*[bB]\s*[.:])/gi;
    const candidates = new Set();
    for (const match of normalized.matchAll(label)) {
        const following = normalized.slice(match.index + match[0].length, match.index + match[0].length + 100);
        /** Never infer expiry from birth, issue dates or an arbitrary date elsewhere. */
        const window = following.split(/(?:nascita|birth|rilascio|issue|emissione)/i)[0];
        const date = window.match(/\b(\d{1,2})\s*[./-]\s*(\d{1,2})\s*[./-]\s*(\d{4})\b/);
        if (!date) continue;
        const day = +date[1], month = +date[2], year = +date[3];
        const value = new Date(Date.UTC(year, month - 1, day));
        if (year < 1950 || year > 2100 || value.getUTCFullYear() !== year || value.getUTCMonth() !== month-1 || value.getUTCDate() !== day) continue;
        candidates.add(`${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`);
    }
    return [...candidates].sort();
}
