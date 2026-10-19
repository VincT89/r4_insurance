const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(__dirname, `ui-${Date.now()}.sqlite`), DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: `registry_qa_${Date.now()}`, QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };
const report = { flows: [], layouts: [], links: [], javascriptErrors: [], failedResponses: [] };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 16);

(async () => {
    const reservation = net.createServer();
    await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
    const port = reservation.address().port;
    await new Promise(resolve => reservation.close(resolve));
    const base = `http://127.0.0.1:${port}`;
    env.APP_URL = base;
    execFileSync(php, [path.join(__dirname, 'prepare.php')], { cwd: root, env, windowsHide: true, stdio: 'pipe' });
    const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'public'), path.join(__dirname, 'router.php')], { cwd: root, env, windowsHide: true, stdio: 'ignore' });
    let browser, page;
    try {
        let available = false;
        for (let i = 0; i < 40; i++) {
            try { if ((await fetch(base + '/login')).ok) { available = true; break; } } catch {}
            await delay(250);
        }
        assert.ok(available, 'Server isolato disponibile');
        browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        page = await context.newPage();
        page.setDefaultTimeout(12000);
        page.on('pageerror', error => report.javascriptErrors.push(error.message));
        page.on('response', response => { if (response.status() >= 500) report.failedResponses.push({ url: response.url(), status: response.status() }); });
        const ready = async () => {
            await page.waitForLoadState('load');
            await page.waitForFunction(() => [...document.querySelectorAll('[x-data]')].every(el => el._x_dataStack?.length));
            await page.evaluate(() => window.Alpine?.nextTick());
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => a.effect?.getTiming().iterations !== Infinity).map(a => a.finished.catch(() => {}))));
        };
        const goto = async url => { assert.equal((await page.goto(url.startsWith('http') ? url : base + url)).status(), 200, url); await ready(); };
        const fill = async fields => { for (const [key, value] of Object.entries(fields)) await page.locator(`[name="${key}"]`).fill(value); };
        const select = async (key, value) => { await page.locator(`[name="${key}"]`).selectOption(value); await page.evaluate(() => window.Alpine.nextTick()); };
        const submit = async (label, url) => { await Promise.all([page.waitForURL(url), page.getByRole('button', { name: label, exact: true }).click()]); await ready(); assert.equal(await page.locator('.flash-error').count(), 0, label); };
        const check = label => { report.flows.push(label); console.log('Verificato: ' + label); };
        const createClient = async fields => { await goto('/clients/create'); await fill(fields); await submit('Salva cliente', /\/clients\/\d+$/); return new URL(page.url()).pathname; };
        await goto('/login');
        await fill({ email: 'ui-test@example.test', password: 'password-testing-only' });
        await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
        const client = await createClient({ name: 'Cliente dimostrativo esteso', phone: '3201234567', preferred_contact_time: 'Pomeriggio', contact_preferences: 'Chiamata su appuntamento, dato dimostrativo.' });
        const family = await createClient({ name: 'Familiare dimostrativo' });
        const familyId = family.split('/').pop();
        const clientId = client.split('/').pop();
        await goto(client + '/edit'); await select('preferred_contact_channel', 'phone'); await submit('Aggiorna cliente', new RegExp(client + '$'));
        await goto(client + '/records/contacts/create');
        await fill({ name: 'Referente aggiuntivo dimostrativo', role: 'Delegato dimostrativo', email: 'referente@example.test' });
        await submit('Salva registrazione', '**tab=network');
        const editContact = await page.locator('section[id^="contacts-"]').first().getByRole('link', { name: 'Modifica', exact: true }).getAttribute('href');
        await goto(editContact); await fill({ phone: '0100000000' }); await submit('Salva registrazione', '**tab=network');
        assert.ok(await page.getByText('0100000000', { exact: true }).count());
        await goto(client + '/relationships/create');
        await select('related_client_id', familyId); await select('relationship_type', 'family'); await fill({ client_role: 'Genitore', related_role: 'Figlio' }); await submit('Salva relazione', '**tab=network');
        const relationshipEdit = await page.getByRole('link', { name: 'Modifica relazione', exact: true }).getAttribute('href');
        await goto(family + '?tab=network'); assert.ok(await page.getByText('Genitore', { exact: true }).count());
        await goto(client + '/households/create'); await fill({ name: 'Nucleo dimostrativo', [`roles[${clientId}]`]: 'Genitore', [`roles[${familyId}]`]: 'Figlio' });
        await page.locator(`[name="members[]"][value="${familyId}"]`).check(); await submit('Salva nucleo', '**tab=network');
        const householdEdit = await page.getByRole('link', { name: 'Modifica nucleo', exact: true }).getAttribute('href');
        check('Preferenze, referenti aggiuntivi, relazione reciproca e nucleo familiare');

        await goto(client + '/records/assets/create'); await select('asset_type', 'property');
        await fill({ name: 'Immobile dimostrativo', category: 'Abitazione', address: 'Indirizzo dimostrativo', city: 'Roma' }); await submit('Salva registrazione', '**tab=assets');
        const assetEdit = await page.getByRole('link', { name: 'Modifica', exact: true }).getAttribute('href');
        const assetId = assetEdit.match(/assets\/(\d+)\/edit/)[1];
        const policyPaths = [];
        for (const [i, subcategory] of ['Sottoramo A dimostrativo', 'Sottoramo B dimostrativo'].entries()) {
            await goto(client + '/policies/create');
            await fill({ category: 'Ramo dimostrativo', subcategory, company: 'Compagnia dimostrativa', number: `QA-EST-${i}`, starts_on: '2026-01-01', expires_on: '2026-12-31' });
            if (i === 0) await select('client_asset_id', assetId);
            await submit('Salva polizza', /\/policies\/\d+$/); policyPaths.push(new URL(page.url()).pathname);
        }
        await goto(client + '?category=Ramo%20dimostrativo');
        await Promise.all([page.waitForURL('**subcategory=Sottoramo%20A%20dimostrativo'), page.getByRole('navigation', { name: 'Sottorami del ramo selezionato' }).getByRole('link', { name: 'Sottoramo A dimostrativo', exact: true }).click()]); await ready();
        assert.equal(await page.locator('table tbody tr').count(), 1);
        assert.equal(await page.locator('table').getByText('Immobile dimostrativo', { exact: true }).count(), 1);
        check('Ramo e sottoramo: salvataggio, tab dipendenti, filtro e bene collegato');

        await goto(client + '/documents/create');
        await page.locator('[name=file]').setInputFiles({ name: 'consenso-dimostrativo.txt', mimeType: 'text/plain', buffer: Buffer.from('Prova dimostrativa senza valore reale.') });
        await select('document_category', 'consent'); await submit('Carica documento', '**tab=documents');
        const docLink = await page.getByRole('link', { name: 'consenso-dimostrativo.txt', exact: true }).getAttribute('href');
        const docId = docLink.match(/attachments\/(\d+)\/download/)[1];
        const policyId = policyPaths[0].split('/').pop();
        for (const status of ['given', 'revoked']) {
            await goto(client + '/records/consents/create');
            assert.equal(await page.locator('[name=status]').inputValue(), '');
            assert.equal(await page.locator('[name=insurance_policy_id]').isDisabled(), true);
            await select('kind', 'electronic_delivery'); await select('scope', 'policy'); await select('status', status);
            await select('insurance_policy_id', policyId); await select('attachment_id', docId);
            await fill({ event_at: yesterday, signer_name: 'Firmatario dimostrativo', notice_reference: 'Modulo dimostrativo, versione test' });
            await submit('Salva registrazione', '**tab=consents');
        }
        assert.ok(await page.getByText('Prestato', { exact: true }).count()); assert.ok(await page.getByText('Revocato', { exact: true }).count());
        assert.equal(await page.locator('section[id^="consents-"] button').count(), 0, 'Consensi senza cancellazione');
        const download = await context.request.get(docLink); assert.equal(await download.text(), 'Prova dimostrativa senza valore reale.');
        check('Consenso elettronico per polizza: nessuna preselezione, prova scaricabile e revoca nello storico');

        await goto(client + '/records/interactions/create'); await select('channel', 'phone'); await fill({ event_at: yesterday, subject: 'Chiamata dimostrativa', notes: 'Dettaglio dimostrativo del contatto', outcome: 'Esito dimostrativo' }); await submit('Salva registrazione', '**tab=history');
        const historyEdit = await page.getByRole('link', { name: 'Modifica', exact: true }).getAttribute('href');
        await goto(historyEdit); await fill({ outcome: 'Esito dimostrativo aggiornato' }); await submit('Salva registrazione', '**tab=history');
        assert.ok(await page.getByText('Esito dimostrativo aggiornato', { exact: true }).count());
        check('Contatto manuale, esito e modifica nello storico');

        const duplicate = await createClient({ name: 'Duplicato dimostrativo', phone: '3201234567', salient_notes: 'Nota dimostrativa da trasferire' });
        await goto(client + '/duplicates');
        const mergePreview = await page.getByRole('link', { name: 'Confronta e unifica', exact: true }).getAttribute('href');
        await goto(mergePreview);
        const snapshotPreview = page.url();
        await page.screenshot({ path: path.join(__dirname, 'registry-merge-desktop.png'), fullPage: true, animations: 'disabled' });
        for (const radio of await page.locator('input[type=radio][value=target]').all()) await radio.check();
        await page.locator('[name=confirm]').check(); await submit('Conferma unificazione', '**tab=registry');
        assert.ok(await page.getByText('Nota dimostrativa da trasferire', { exact: true }).count());
        assert.ok(await page.getByText('Storico unificazioni', { exact: true }).count());
        await goto(duplicate); assert.equal(new URL(page.url()).pathname, client);
        check('Duplicati: confronto esplicito, trasferimento, storico e reindirizzamento della vecchia scheda');

        const pages = [client + '?tab=registry', client + '?tab=network', client + '?tab=assets', client + '?tab=consents', client + '?tab=history', client + '?tab=policies&category=Ramo%20dimostrativo', client + '/records/contacts/create', client + '/records/assets/create', client + '/records/interactions/create', client + '/records/consents/create', relationshipEdit, householdEdit, policyPaths[0] + '/edit', policyPaths[0], client + '/duplicates'];
        const links = new Set();
        for (const viewport of [{ width: 1440, height: 1050 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            for (const [i, url] of pages.entries()) {
                await goto(url);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Larghezza pagina ' + url);
                const unlabelled = await page.locator('main input:not([type=hidden]):not([type=submit]), main select, main textarea').evaluateAll(elements => elements.filter(el => el.offsetParent && !el.labels?.length && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby')).map(el => el.name));
                assert.deepEqual(unlabelled, [], 'Etichette ' + url);
                for (const href of await page.locator('main a[href]').evaluateAll(elements => elements.map(el => el.href))) { const link = new URL(href); if (link.origin === base) links.add(href); }
                report.layouts.push({ path: new URL(page.url()).pathname + new URL(page.url()).search, ...viewport });
                if ([1, 3, 5, 9, 12].includes(i)) await page.screenshot({ path: path.join(__dirname, `registry-${viewport.width}-${i}.png`), fullPage: true, animations: 'disabled' });
            }
        }
        for (const href of links) { const response = await context.request.get(href); assert.equal(response.status(), 200, href); report.links.push(new URL(href).pathname + new URL(href).search); }
        check('Desktop e smartphone: moduli, etichette, tab e collegamenti');
        await goto(client + '?category=Ramo%20dimostrativo');
        await Promise.all([page.waitForURL('**subcategory=Sottoramo%20B%20dimostrativo'), page.getByRole('navigation', { name: 'Sottorami del ramo selezionato' }).getByRole('link', { name: 'Sottoramo B dimostrativo', exact: true }).click()]); await ready();
        assert.equal(await page.locator('table tbody tr').count(), 1);
        await goto(client + '/records/consents/create');
        await select('kind', 'other'); await page.locator('[name=purpose]').waitFor({ state: 'visible' });
        await fill({ purpose: 'Finalità esclusivamente dimostrativa', event_at: yesterday, signer_name: 'Firmatario dimostrativo', notice_reference: 'Documento dimostrativo', evidence_reference: 'Archivio di prova dimostrativo' });
        await select('scope', 'all'); await select('status', 'refused');
        assert.equal(await page.locator('[name=insurance_policy_id]').isDisabled(), true);
        const save = page.getByRole('button', { name: 'Salva registrazione', exact: true });
        await save.scrollIntoViewIfNeeded();
        const saveBox = await save.boundingBox();
        assert.ok(saveBox.x >= 0 && saveBox.x + saveBox.width <= 390 && saveBox.y >= 52 && saveBox.y + saveBox.height <= 844);
        await page.screenshot({ path: path.join(__dirname, 'registry-mobile-form-bottom.png'), animations: 'disabled' });
        await submit('Salva registrazione', '**tab=consents');
        assert.ok(await page.getByText('Negato', { exact: true }).count());
        check('Smartphone: cambio sottoramo, campi condizionali e salvataggio di un consenso negato');
        assert.deepEqual(report.javascriptErrors, []); assert.deepEqual(report.failedResponses, []);
        report.ok = true; report.database = 'SQLite isolato con soli dati dimostrativi';
        console.log(JSON.stringify({ ok: true, flows: report.flows.length, layouts: report.layouts.length, links: report.links.length }, null, 2));
    } catch (error) {
        report.ok = false; report.error = error.stack;
        if (page) { report.failureUrl = page.url(); await page.screenshot({ path: path.join(__dirname, 'registry-failure.png'), fullPage: true }).catch(() => {}); }
        throw error;
    } finally {
        fs.writeFileSync(path.join(__dirname, 'registry-report.json'), JSON.stringify(report, null, 2));
        if (browser) await browser.close();
        server.kill();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
