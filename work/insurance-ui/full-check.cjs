const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(__dirname, `ui-${Date.now()}.sqlite`), DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: `insurance_qa_${Date.now()}`, QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const date = days => { const d = new Date(); d.setUTCDate(d.getUTCDate() + days); return d.toISOString().slice(0, 10); };
const report = { flows: [], layouts: [], links: [], javascriptErrors: [], failedResponses: [] };

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
        let serverReady = false;
        for (let i = 0; i < 40; i++) {
            try { if ((await fetch(base + '/login')).ok) { serverReady = true; break; } } catch {}
            await delay(250);
        }
        assert.ok(serverReady, 'Server di prova disponibile');
        browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
        const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        page = await context.newPage();
        page.setDefaultTimeout(12000);
        page.on('pageerror', e => report.javascriptErrors.push(e.message));
        page.on('response', r => { if (r.status() >= 500) report.failedResponses.push({ url: r.url(), status: r.status() }); });
        const ready = async () => {
            await page.waitForLoadState('load');
            await page.waitForFunction(() => [...document.querySelectorAll('[x-data]')].every(el => el._x_dataStack?.length));
            await page.evaluate(() => window.Alpine?.nextTick());
            await page.evaluate(() => Promise.all(document.getAnimations().filter(animation => animation.effect?.getTiming().iterations !== Infinity).map(animation => animation.finished.catch(() => {}))));
        };
        const goto = async p => { const response = await page.goto(p.startsWith('http') ? p : base + p); assert.equal(response.status(), 200, p); await ready(); };
        const fill = async fields => { await ready(); for (const [name, value] of Object.entries(fields)) await page.locator(`[name="${name}"]`).fill(value); };
        const select = async (name, value) => { await ready(); await page.locator(`[name="${name}"]`).selectOption(String(value)); await page.evaluate(() => window.Alpine.nextTick()); };
        const submit = async (label, pattern) => {
            await Promise.all([page.waitForURL(pattern), page.getByRole('button', { name: label, exact: true }).click()]);
            await ready();
            assert.equal(await page.locator('.flash-error').count(), 0, `Salvataggio ${label}`);
        };
        const check = name => { report.flows.push(name); console.log(`Verificato: ${name}`); };
        const deleteFrom = async (trigger, title, blocked = false) => {
            await ready();
            await trigger.click();
            const dialog = page.getByRole('dialog', { name: title, exact: true });
            try { await dialog.waitFor({ state: 'visible' }); } catch (error) {
                report.dialogState = await page.evaluate(() => [...document.querySelectorAll('.confirm-modal-trigger, [data-dialog-overlay]')].map(el => ({
                    title: el.textContent.trim().slice(0, 200), state: el._x_dataStack, display: getComputedStyle(el).display, hidden: el.getAttribute('aria-hidden'), cloak: el.hasAttribute('x-cloak')
                })));
                throw error;
            }
            await Promise.all([page.waitForNavigation(), dialog.getByRole('button', { name: 'Sì, elimina', exact: true }).click()]);
            assert.equal(await page.locator('.flash-error').count(), blocked ? 1 : 0, `Eliminazione ${title}`);
        };
        await goto('/login');
        await fill({ email: 'ui-test@example.test', password: 'password-testing-only' });
        await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
        check('Accesso admin e dashboard');
        await goto('/clients/create');
        const personFields = { name: 'Persona dimostrativa collaudo', title: 'Sig.', tax_code: 'qa-persona', birth_date: '1980-01-02', birth_place: 'Luogo dimostrativo', phone: '0100000000', mobile: '3200000000', fax: '0100000001', email: 'persona-qa@example.test', address: 'Indirizzo dimostrativo', city: 'Roma', province: 'rm', postal_code: '00100', reference_person: 'Referente dimostrativo', reference_email: 'referente-qa@example.test', reference_role: 'Ruolo dimostrativo', reference_phone: '0100000002', target: 'Target dimostrativo', office: 'Ufficio dimostrativo', consultant: 'Consulente dimostrativo', collaborator: 'Collaboratore dimostrativo', operator: 'Operatore dimostrativo', salient_notes: 'Notizie dimostrative del collaudo' };
        await fill(personFields);
        await submit('Salva cliente', /\/clients\/\d+$/);
        const personPath = new URL(page.url()).pathname;
        await goto(personPath + '/edit');
        for (const [key, expected] of Object.entries(personFields)) assert.equal(await page.locator(`[name="${key}"]`).inputValue(), ['tax_code', 'province'].includes(key) ? expected.toUpperCase() : expected, `Persistenza ${key}`);
        await fill({ mobile: '3200000009' });
        await submit('Aggiorna cliente', new RegExp(personPath + '$'));
        await goto('/clients/create');
        await select('client_type', 'company');
        await page.locator('[name=birth_date]').waitFor({ state: 'hidden' });
        assert.equal(await page.locator('[name=birth_date]').isVisible(), false);
        assert.equal(await page.locator('[name=birth_date]').isDisabled(), true);
        await fill({ name: 'Azienda dimostrativa collaudo', company_name: 'Denominazione dimostrativa', vat_number: 'qa-azienda', email: 'azienda-qa@example.test' });
        await submit('Salva cliente', /\/clients\/\d+$/);
        const companyClientPath = new URL(page.url()).pathname;
        await goto(companyClientPath + '?tab=registry');
        assert.equal(await page.getByText('Data di nascita', { exact: true }).count(), 0);
        check('Creazione e modifica persone/aziende, persistenza campi Excel');

        const vehicleIds = [];
        for (let i = 1; i <= 2; i++) {
            await goto(personPath + '/vehicles/create');
            await fill({ plate: ` qa test0${i} `, make: 'Marca dimostrativa', model: 'Modello dimostrativo', vin: `TELAIO-DEMO-${i}` });
            await submit('Salva veicolo', '**tab=vehicles');
            const row = page.locator('tr').filter({ hasText: `QATEST0${i}` });
            const href = await row.getByRole('link', { name: /Modifica/ }).getAttribute('href');
            vehicleIds.push(href.match(/vehicles\/(\d+)\/edit/)[1]);
        }
        await goto(personPath + `/vehicles/${vehicleIds[0]}/edit`);
        await fill({ model: 'Modello aggiornato' });
        await submit('Salva veicolo', '**tab=vehicles');
        check('Due targhe per cliente, normalizzazione e modifica veicolo');

        const policies = [];
        const categories = ['RC auto', 'Infortuni', 'RC persone', 'Ramo dimostrativo'];
        for (const [i, category] of categories.entries()) {
            await goto(personPath + '?tab=policies' + (i < 3 ? '&category=' + encodeURIComponent(category) : ''));
            await page.getByRole('link', { name: 'Nuova polizza', exact: true }).click();
            if (i < 3) assert.equal(await page.locator('[name=category]').inputValue(), category);
            await fill({ category, company: 'Compagnia di polizza dimostrativa', number: `QA-POL-${i + 1}`, starts_on: date(-100), expires_on: date(i === 0 ? 7 : i === 1 ? 30 : i === 2 ? -1 : 60), premium: '123.45' });
            if (i === 0) await select('vehicle_id', vehicleIds[0]);
            assert.equal(await page.locator('[name=vehicle_id] option').filter({ hasText: /^TEST01 \/ Marca/ }).count(), 0, 'Veicolo dell’altro cliente escluso');
            await submit('Salva polizza', /\/policies\/\d+$/);
            policies.push(new URL(page.url()).pathname);
        }
        for (const [i, category] of categories.entries()) {
            await goto(personPath + '?tab=policies');
            await page.getByRole('navigation', { name: 'Tipologie di polizza' }).getByRole('link', { name: category, exact: true }).click();
            assert.equal(await page.locator('table').getByText(`QA-POL-${i + 1}`, { exact: true }).count(), 1);
            assert.equal(await page.locator('table tbody tr').count(), 1);
        }
        await page.getByRole('navigation', { name: 'Tipologie di polizza' }).getByRole('link', { name: 'Tutte le polizze', exact: true }).click();
        assert.equal(await page.locator('table tbody tr').count(), 4);
        check('Polizze multiple, tab per ramo, ramo personalizzato e targa corretta');

        await goto(policies[0]);
        await page.getByRole('link', { name: 'Carica documento', exact: true }).click();
        assert.equal(await page.locator('[name=insurance_policy_id]').inputValue(), policies[0].split('/').pop());
        await page.locator('[name=file]').setInputFiles({ name: 'documento-collaudo.txt', mimeType: 'text/plain', buffer: Buffer.from('Documento dimostrativo per collaudo riservato.') });
        await select('document_category', 'policy');
        await select('vehicle_id', vehicleIds[0]);
        await fill({ expires_on: date(7), description: 'Documento collegato alla polizza e al veicolo' });
        await submit('Carica documento', '**tab=documents');
        const docRow = page.locator('tr').filter({ hasText: 'documento-collaudo.txt' });
        const downloadUrl = await docRow.getByRole('link', { name: 'documento-collaudo.txt', exact: true }).getAttribute('href');
        const editDocUrl = await docRow.getByRole('link', { name: /Modifica dati/ }).getAttribute('href');
        const download = await context.request.get(downloadUrl);
        assert.equal(download.status(), 200);
        assert.match(download.headers()['content-disposition'], /attachment/);
        assert.equal(await download.text(), 'Documento dimostrativo per collaudo riservato.');
        await docRow.getByRole('link', { name: 'Polizza QA-POL-1', exact: true }).click();
        assert.equal(new URL(page.url()).pathname, policies[0]);
        await deleteFrom(page.getByRole('button', { name: 'Elimina polizza', exact: true }), 'Elimina polizza', true);
        await goto(personPath + `/vehicles/${vehicleIds[0]}/edit`);
        await deleteFrom(page.getByRole('button', { name: 'Elimina veicolo', exact: true }), 'Elimina veicolo', true);
        await goto(personPath + '?tab=registry');
        await deleteFrom(page.getByRole('button', { name: 'Elimina cliente', exact: true }), 'Elimina cliente', true);
        await goto(editDocUrl);
        await fill({ expires_on: date(15) });
        await submit('Salva dati', '**tab=documents');
        check('Upload, download riservato, modifica documento e cancellazioni protette');

        for (const [period, expectedCount] of [['7', 1], ['15', 2], ['30', 3], ['45', 3], ['60', 4], ['overdue', 1]]) {
            await goto(personPath + '?tab=deadlines');
            await select('period', period);
            await Promise.all([page.waitForURL('**period=' + period + '**'), page.getByRole('button', { name: 'Filtra', exact: true }).click()]);
            assert.equal(await page.locator('table tbody tr').count(), expectedCount, `Filtro scadenze ${period}`);
        }
        await goto(personPath + '?tab=deadlines');
        await select('period', 'custom');
        await fill({ from: date(15), to: date(15) });
        await Promise.all([page.waitForURL('**period=custom**'), page.getByRole('button', { name: 'Filtra', exact: true }).click()]);
        assert.equal(await page.locator('table').getByText('documento-collaudo.txt', { exact: true }).count(), 1);
        const customLink = await page.locator('table a').first().getAttribute('href');
        assert.equal(customLink, downloadUrl);
        await goto(policies[3] + '/edit');
        await select('status', 'ceased');
        await submit('Salva polizza', new RegExp(policies[3] + '$'));
        await goto(personPath + '?tab=deadlines');
        assert.equal(await page.locator('table').getByText(/QA-POL-4/).count(), 0);
        check('Scadenze 7/15/30/45/60 giorni, scadute, intervallo libero, esclusione cessate');

        await goto('/clients?search=qa%20test01');
        assert.equal(await page.locator('table').getByRole('link', { name: 'Persona dimostrativa collaudo', exact: true }).count(), 1);
        await goto('/clients?search=QA-POL-2');
        assert.equal(await page.locator('table').getByRole('link', { name: 'Persona dimostrativa collaudo', exact: true }).count(), 1);
        await goto('/clients?client_type=company');
        assert.equal(await page.locator('table').getByRole('link', { name: 'Persona dimostrativa collaudo', exact: true }).count(), 0);
        check('Ricerca per targa e polizza e filtro persone/aziende');

        const companyPaths = [];
        for (const suffix of ['A', 'B']) {
            await goto('/supplier-companies/create');
            await fill({ name: `Compagnia dimostrativa ${suffix}`, email: `compagnia-${suffix.toLowerCase()}@example.test`, reference_person: 'Referente dimostrativo' });
            await submit('Salva compagnia', /\/supplier-companies\/\d+$/);
            companyPaths.push(new URL(page.url()).pathname);
        }
        await goto(companyPaths[0]);
        await page.getByRole('link', { name: 'Nuova agenzia', exact: true }).click();
        assert.equal(await page.locator('[name=supplier_company_id]').inputValue(), companyPaths[0].split('/').pop());
        await fill({ name: 'Agenzia principale dimostrativa', company_name: 'Ragione sociale dimostrativa', email: 'agenzia-qa@example.test', agency_code: 'QA-01' });
        await submit('Salva agenzia', /\/suppliers\/\d+$/);
        const agencyPath = new URL(page.url()).pathname;
        await page.getByRole('link', { name: 'Nuova subagenzia', exact: true }).click();
        assert.equal(await page.locator('[name=parent_agency_id]').inputValue(), agencyPath.split('/').pop());
        await fill({ name: 'Subagenzia dimostrativa', reference_person: 'Referente subagenzia dimostrativo' });
        await submit('Salva agenzia', /\/suppliers\/\d+$/);
        const subagencyPath = new URL(page.url()).pathname;
        await goto(subagencyPath + '/edit');
        await select('supplier_company_id', companyPaths[1].split('/').pop());
        assert.equal(await page.locator('[name=parent_agency_id]').inputValue(), '');
        assert.equal(await page.locator(`[name=parent_agency_id] option[value="${agencyPath.split('/').pop()}"]`).isDisabled(), true);
        await select('supplier_company_id', companyPaths[0].split('/').pop());
        await select('parent_agency_id', agencyPath.split('/').pop());
        await fill({ phone: '0100000009' });
        await submit('Salva agenzia', new RegExp(subagencyPath + '$'));
        await goto(companyPaths[0]);
        assert.equal(await page.locator('table tbody tr').count(), 2);
        await deleteFrom(page.getByRole('button', { name: 'Elimina compagnia', exact: true }), 'Elimina compagnia', true);
        await goto(agencyPath);
        await deleteFrom(page.getByRole('button', { name: 'Elimina agenzia', exact: true }), 'Elimina agenzia', true);
        await goto(agencyPath + '/edit');
        assert.equal(await page.locator('[name=parent_agency_id]').isDisabled(), true);
        await goto('/suppliers?supplier_company_id=' + companyPaths[1].split('/').pop());
        assert.equal(await page.locator('table').getByText('Agenzia principale dimostrativa', { exact: true }).count(), 0);
        check('Compagnie, agenzie, subagenzie, selezioni dipendenti e protezione gerarchia');

        // Delete only disposable records, keeping the linked examples for the layout/link audit.
        await goto('/supplier-companies/create');
        await fill({ name: 'Compagnia temporanea dimostrativa' });
        await submit('Salva compagnia', /\/supplier-companies\/\d+$/);
        await deleteFrom(page.getByRole('button', { name: 'Elimina compagnia', exact: true }), 'Elimina compagnia');
        await goto(personPath + '/vehicles/create');
        await fill({ plate: 'QATEMP' });
        await submit('Salva veicolo', '**tab=vehicles');
        await page.locator('tr').filter({ hasText: 'QATEMP' }).getByRole('link', { name: /Modifica/ }).click();
        await deleteFrom(page.getByRole('button', { name: 'Elimina veicolo', exact: true }), 'Elimina veicolo');
        await goto(personPath + '?tab=documents');
        await deleteFrom(page.getByRole('button', { name: 'Elimina documento-collaudo.txt', exact: true }), 'Elimina documento');
        assert.equal(await page.getByRole('link', { name: 'documento-collaudo.txt', exact: true }).count(), 0);
        assert.equal((await context.request.get(downloadUrl)).status(), 404);
        await goto(personPath + '?tab=deadlines');
        assert.equal(await page.getByText('documento-collaudo.txt', { exact: true }).count(), 0);
        check('Eliminazioni consentite e rimozione coerente di documento e scadenza');

        const auditPaths = [personPath + '?tab=policies', personPath + '?tab=registry', personPath + '?tab=vehicles', personPath + '?tab=documents', personPath + '?tab=deadlines', personPath + '/edit', policies[0], '/supplier-companies', companyPaths[0], '/suppliers', agencyPath, subagencyPath + '/edit'];
        const links = new Set();
        for (const viewport of [{ width: 1440, height: 1050 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            for (const [i, p] of auditPaths.entries()) {
                await goto(p);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Pagina responsive ${viewport.width} ${p}`);
                for (const scroll of await page.locator('.client-table-scroll').all()) {
                    const lastColumnReachable = await scroll.evaluate(el => {
                        el.scrollLeft = el.scrollWidth;
                        const last = el.querySelector('thead th:last-child');
                        const reachable = !last || last.getBoundingClientRect().right <= el.getBoundingClientRect().right + 1;
                        el.scrollLeft = 0;
                        return reachable;
                    });
                    assert.ok(lastColumnReachable, `Ultima colonna raggiungibile ${viewport.width} ${p}`);
                }
                const inputsWithoutLabels = await page.locator('main input:not([type=hidden]):not([type=submit]), main select, main textarea').evaluateAll(elements => elements.filter(e => e.offsetParent && !e.labels?.length && !e.getAttribute('aria-label') && !e.getAttribute('aria-labelledby')).map(e => e.name));
                assert.deepEqual(inputsWithoutLabels, [], `Etichette campi ${p}`);
                report.layouts.push({ path: p, width: viewport.width, height: viewport.height });
                for (const href of await page.locator('main a[href]').evaluateAll(a => a.map(a => a.href))) {
                    const url = new URL(href);
                    if (url.origin === base && /^\/(clients|suppliers|supplier-companies|attachments)(\/|\?|$)/.test(url.pathname + url.search)) links.add(href);
                }
                if ([0, 1, 4, 8, 11].includes(i)) await page.screenshot({ path: path.join(__dirname, `qa-${viewport.width}-${i}.png`), fullPage: true, animations: 'disabled' });
            }
        }
        for (const href of links) {
            const response = await context.request.get(href);
            assert.equal(response.status(), 200, `Collegamento ${href}`);
            report.links.push(new URL(href).pathname + new URL(href).search);
        }
        // An unauthenticated session cannot read customer or supplier records.
        const guest = await browser.newContext();
        for (const p of [personPath, policies[0], companyPaths[0], agencyPath]) {
            const response = await guest.request.get(base + p, { maxRedirects: 0 });
            assert.equal(response.status(), 302);
            assert.match(response.headers().location, /\/login$/);
        }
        await guest.close();
        check('Desktop e smartphone, etichette accessibili, collegamenti e accesso anonimo');
        assert.deepEqual(report.javascriptErrors, [], 'Nessun errore JavaScript');
        assert.deepEqual(report.failedResponses, [], 'Nessun errore del server');
        report.ok = true;
        report.database = 'SQLite separato con dati dimostrativi';
        console.log(JSON.stringify({ ok: true, flows: report.flows.length, layouts: report.layouts.length, links: report.links.length, javascriptErrors: report.javascriptErrors, failedResponses: report.failedResponses }, null, 2));
    } catch (e) {
        report.ok = false;
        report.error = e.stack;
        if (page) { report.failureUrl = page.url(); await page.screenshot({ path: path.join(__dirname, 'qa-failure.png'), fullPage: true }).catch(() => {}); }
        throw e;
    } finally {
        fs.writeFileSync(path.join(__dirname, 'full-report.json'), JSON.stringify(report, null, 2));
        if (browser) await browser.close();
        server.kill();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
