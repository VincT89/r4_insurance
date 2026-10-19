import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { Workbook, SpreadsheetFile } from '@oai/artifact-tool';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const input = path.join(root, 'storage/app/private/insurance-import');
const output = path.join(root, 'outputs/01a13a0a-2993-7ac0-944b-064563a2c934');
const read = async name => JSON.parse(await fs.readFile(path.join(input, name), 'utf8'));
const summary = await read('summary.json');
const issues = await read('issues.json');
const repeated = await read('repeated-policies.json');
let imported;
try { imported = JSON.parse(await fs.readFile(path.join(root, 'work/registry-import-report.json'), 'utf8')); } catch (error) { if (error.code !== 'ENOENT') throw error; }
const wb = Workbook.create();
const labels = value => (value ?? '').replaceAll('_', ' ');
const safe = value => typeof value === 'string' && /^[=+@-]/.test(value) ? "'" + value : value;
const col = number => { let out = ''; for (let n = number; n > 0; n = Math.floor((n - 1) / 26)) out = String.fromCharCode(65 + (n - 1) % 26) + out; return out; };
const sheet = (name, title, subtitle, headers, rows, widths) => {
    const ws = wb.worksheets.add(name);
    ws.showGridLines = false;
    const end = col(headers.length);
    ws.getRange(`A1:${end}1`).merge(); ws.getRange('A1').values = [[title]];
    ws.getRange(`A1:${end}1`).format = { fill: '#0B498C', font: { bold: true, color: '#FFFFFF', size: 18 }, rowHeight: 36 };
    ws.getRange(`A2:${end}3`).merge(); ws.getRange('A2').values = [[subtitle]];
    ws.getRange(`A2:${end}3`).format = { wrapText: true, verticalAlignment: 'center', font: { size: 11, color: '#334155' }, rowHeight: 24 };
    ws.getRange(`A5:${end}${rows.length + 5}`).values = [headers, ...rows.map(r => r.map(safe))];
    ws.getRange(`A5:${end}${rows.length + 5}`).format.font.size = 11;
    ws.getRange(`A5:${end}${rows.length + 5}`).format.verticalAlignment = 'center';
    ws.getRange(`A5:${end}${rows.length + 5}`).format.wrapText = true;
    const table = ws.tables.add(`A5:${end}${rows.length + 5}`, true, 'Review' + wb.worksheets.items.length);
    table.style = 'TableStyleMedium2';
    table.showFilterButton = true;
    ws.getRange(`A5:${end}5`).format = { fill: '#0B498C', font: { bold: true, color: '#FFFFFF' }, rowHeight: 36 };
    widths.forEach((width, i) => ws.getRange(`${col(i+1)}1:${col(i+1)}${rows.length+5}`).format.columnWidth = width);
    rows.forEach((r, i) => {
        const lines = Math.max(...r.map((v, j) => Math.ceil(String(v ?? '').length / Math.max(10, widths[j] - 3))));
        ws.getRange(`A${i+6}:${end}${i+6}`).format.rowHeight = Math.min(150, Math.max(30, lines * 16 + 10));
    });
    ws.freezePanes.freezeRows(5);
    return ws;
};

const totals = [
    ['Righe anagrafica sorgente', summary.counts.registry_rows, 'Comprende righe ripetute e identificativi assenti.'],
    ['Anagrafiche candidate', summary.counts.client_candidates, imported ? 'Gruppi esaminati. Per l’esito del caricamento vedere Importazione eseguita.' : 'Gruppi anagrafici più clienti presenti solo nelle polizze; nessuna importazione eseguita.'],
    ['Anagrafiche senza anomalie rilevate', summary.counts.clients_without_record_issues, 'Controlli formali; non attestano identità o correttezza presso registri esterni.'],
    ['Anagrafiche da verificare', summary.counts.clients_to_review, 'Identificativi, duplicati, dati discordanti o insufficienti.'],
    ['Clienti presenti solo nelle polizze', summary.counts.clients_only_in_policies, 'Contatti e dati completi da integrare.'],
    ['Righe assicurative', summary.counts.policy_rows, 'Escluse spese, fatture, quote associative e riepilogo finale.'],
    ['Righe amministrative separate', summary.counts.financial_rows_excluded, 'Conservate nella fonte privata; non trasformate in polizze.'],
    ['Gruppi di polizze', summary.counts.policy_groups, 'Chiave: identificativo fiscale + compagnia + numero; non sono polizze definitive.'],
    ['Gruppi con numero ripetuto', summary.counts.policy_repeated_groups, 'Non scegliere automaticamente la riga più recente: possono esserci rinnovi, rate o modifiche.'],
    ['Gruppi senza altre anomalie rilevate', summary.counts.policy_candidates_without_record_issues, 'Anche questi richiedono la verifica dello stato Archivio/OLD.'],
    ['Gruppi con ulteriori verifiche', summary.counts.policy_groups_to_review, 'Classificazioni proposte, clienti o righe discordanti e altri controlli.'],
    ['Gruppi con stato da verificare', summary.counts.policies_needing_status, 'Archivio e OLD conservati. Nessuno stato in vigore dedotto.'],
    ['Denominazioni compagnia conservate', summary.counts.companies_after_expenses, 'SPESE escluso; nessuna fusione di denominazioni ambigue.'],
    ['Etichette agenzia normalizzate', summary.counts.agencies_after_formatting_and_expenses, 'Uniformato CAMPA/Campa; DI EMISSIONE escluso. Recapiti e identità delle agenzie da completare.'],
];
sheet('Riepilogo', 'Importazione R4 Insurance', imported ? 'Clienti e agenzie caricati. Le polizze restano escluse. Il foglio Importazione eseguita riporta l’esito; gli altri fogli conservano analisi e decisioni da completare.' : 'Prospetto di lavoro. Le proposte non sono ancora applicate alle anagrafiche. Consultare Decisioni prima del caricamento definitivo.', ['Contenuto', 'Quantità', 'Significato'], totals, [44, 14, 82]);
if (imported?.ok) {
    sheet('Importazione eseguita', 'Esito caricamento anagrafiche', 'Verificato sul database r4_insurance. Le polizze non sono state importate. Le fonti originali e una copia di sicurezza sono conservate in area privata.', ['Voce', 'Quantità', 'Nota'], [
        ['Clienti importati', imported.created.client, 'Righe discordanti escluse. Email condivise conservate senza unificare le persone.'],
        ['Agenzie importate', imported.created.agency, 'Una sola agenzia con più compagnie; nessuna gerarchia di subagenzia dedotta.'],
        ['Compagnie collegate', imported.created.company, 'Denominazioni originali conservate; nomi e ruoli ambigui da verificare.'],
        ['Collegamenti agenzia / compagnia', imported.database_counts.supplier_agency_company, 'Relazioni effettivamente registrate.'],
        ['Anagrafiche in sospeso', imported.held.client, '7 gruppi discordanti e 64 presenti solo nel file polizze. Consultabili in Impostazioni → Importazione Excel → Esito anagrafiche.'],
        ['Polizze importate', 0, 'Importazione esclusa su richiesta. Stato Archivio/OLD e duplicati ancora da risolvere.'],
        ['Celle confrontate con due lettori', 512948, '112.700 celle Anagrafica e 400.248 celle Gestionale: nessuna differenza.'],
        ['Account admin', 'Invariato', 'Password e utenti conservati.'],
    ], [44, 16, 86]);
    wb.worksheets.getItem('Importazione eseguita').getRange('B6:B13').format.horizontalAlignment = 'center';
}
sheet('Decisioni', 'Decisioni per il caricamento', 'I valori originali rimangono conservati. Compilare una scelta nelle colonne di revisione; le modifiche al prospetto non vengono eseguite automaticamente.', ['Argomento', 'Stato', 'Verifica necessaria', 'Decisione del cliente'], summary.decisions.map(d => [d.topic, d.state, d.text, '']), [30, 16, 82, 45]);
const taxonomy = sheet('Rami e sottorami', 'Rami e sottorami', 'Sorgente: Gestionale, Dati!Q:R, intestazioni alla riga 7. Classificazione operativa dell’agenzia: non equivale automaticamente ai rami normativi IVASS.', ['Ramo Excel', 'Sottoramo Excel', 'Ramo proposto', 'Sottoramo proposto', 'Righe', 'Esito', 'Criterio', 'Conferma / correzione'], summary.taxonomy.map(r => [r.source_branch, r.source_subbranch, r.branch, r.subbranch, r.rows, labels(r.result), r.reason, '']), [25, 22, 25, 23, 10, 20, 64, 36]);
const providers = sheet('Compagnie e agenzie', 'Compagnie e agenzie', 'Sorgente: Gestionale, Dati!M:N. Una stessa agenzia può lavorare con più compagnie. Il file non dimostra una gerarchia di agenzie e subagenzie.', ['Compagnia Excel', 'Agenzia Excel', 'Compagnia proposta', 'Agenzia proposta', 'Righe', 'Esito', 'Criterio', 'Conferma / correzione'], summary.company_agencies.map(r => [r.source_company, r.source_agency, r.company, r.agency, r.rows, labels(r.result), r.reason, '']), [37, 29, 37, 29, 10, 20, 58, 36]);
sheet('Anomalie', 'Anomalie e riferimenti', 'Ogni riga identifica un controllo su un gruppo. Più controlli possono riferirsi allo stesso gruppo. I riferimenti sono numeri di riga del foglio Dati originale.', ['File sorgente', 'Righe Dati', 'Controllo', 'Priorità', 'Esito della verifica'], issues.map(r => [r.area === 'clients' ? 'Anagrafica' : 'Gestionale', r.rows.join(', '), labels(r.code), 'Da verificare', '']), [20, 30, 62, 18, 55]);
sheet('Polizze ripetute', 'Numeri polizza ripetuti', 'Chiave di raggruppamento: cliente, compagnia e numero. Tutte le righe sono conservate; le differenze non vengono sovrascritte scegliendo una riga arbitraria.', ['Righe Gestionale!Dati', 'Righe nel gruppo', 'Campi discordanti', 'Date rata differenti', 'Esito della verifica'], repeated.map(r => [r.rows.join(', '), r.count, r.conflicting_fields.join(', ') || 'Nessuno tra i campi confrontati', r.different_installment_dates ? 'Sì' : 'No', '']), [30, 18, 76, 22, 55]);
sheet('Campi', 'Collegamento dei campi', 'Anagrafica!Dati, intestazioni A7:W7; Gestionale!Dati, intestazioni A7:BB7. La fonte integrale resta nel lotto privato.', ['Campo Excel', 'Destinazione', 'Regola'], summary.mapping.map(r => [r.source, r.target, r.rule]), [40, 42, 95]);
for (const ws of [taxonomy, providers]) {
    ws.getRange(`F6:F${ws.getUsedRange().values.length}`).conditionalFormats.add('containsText', { text: 'da confermare', format: { fill: '#FFF1CE', font: { color: '#704900' } } });
}
const overview = wb.worksheets.getItem('Riepilogo');
overview.getRange('B7').formulas = [['=B8+B9']];
overview.getRange('B13').formulas = [['=B15+B16']];
overview.getRange('B6:B19').setNumberFormat('#,##0');
overview.getRange('B6:B19').format.horizontalAlignment = 'center';
await wb.recalculate();
await fs.mkdir(output, { recursive: true });
const check = await wb.inspect({ kind: 'region', sheetId: 'Riepilogo', range: 'A5:C19', maxChars: 2000, tableMaxRows: 15 });
console.log(check.ndjson ?? JSON.stringify(check));
const xlsx = await SpreadsheetFile.exportXlsx(wb);
const outputFile = path.join(output, 'Preparazione-importazione-R4.xlsx');
await xlsx.save(outputFile);
const bytes = await fs.readFile(outputFile);
await fs.writeFile(path.join(input, 'review.xlsx'), bytes);
const manifest = await read('manifest.json');
manifest.files['review.xlsx'] = crypto.createHash('sha256').update(bytes).digest('hex');
await fs.writeFile(path.join(input, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
for (const [name, range, png] of [['Riepilogo', 'A1:C19', 'summary'], ['Rami e sottorami', 'A1:H14', 'taxonomy'], ['Compagnie e agenzie', 'A1:H12', 'providers'], ['Anomalie', 'A1:E11', 'issues'], ...(imported?.ok ? [['Importazione eseguita','A1:C13','imported']] : [])]) {
    const preview = await wb.render({ sheetName: name, range, scale: 1.3, format: 'png' });
    await fs.writeFile(path.join(root, `work/import-workbook/${png}.png`), new Uint8Array(await preview.arrayBuffer()));
}
console.log(JSON.stringify({ workbook: outputFile, bytes: bytes.length, sheets: wb.worksheets.items.length }));
