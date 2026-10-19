const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV:'testing', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`), DB_URL:'', CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:`equivalent_qa_${Date.now()}`, QUEUE_CONNECTION:'sync', MAIL_MAILER:'array', POLICY_IMPORT_QA:'0', EQUIVALENT_IMPORT_QA:'1', CATALOG_MERGE_QA:'0' };
const report = { flows:[], layouts:[], errors:[] };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    const reservation = net.createServer(); await new Promise(r => reservation.listen(0, '127.0.0.1', r));
    const port = reservation.address().port; await new Promise(r => reservation.close(r));
    const base = `http://127.0.0.1:${port}`; env.APP_URL = base;
    const seeded = JSON.parse(execFileSync(php, [path.join(__dirname, 'prepare.php')], {cwd:root, env, windowsHide:true, encoding:'utf8'}));
    const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root, 'public'), path.join(__dirname, 'router.php')], {cwd:root, env, windowsHide:true, stdio:'ignore'});
    let browser, page;
    try {
        for (let i=0; i<60; i++) { try { if ((await fetch(base+'/login')).ok) break; } catch {} await delay(250); }
        browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
        const context = await browser.newContext({viewport:{width:1440,height:1050}});
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        page = await context.newPage(); page.setDefaultTimeout(15000);
        page.on('pageerror', error => report.errors.push(error.message));
        page.on('response', response => { if (response.status() >= 500) report.errors.push(`${response.status()} ${response.url()}`); });
        const settled = async () => page.evaluate(async () => { await window.Alpine?.nextTick(); await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))); await Promise.all(document.getAnimations().filter(a => a.effect?.getComputedTiming().iterations !== Infinity).map(a => a.finished.catch(() => {}))); });
        const ready = async () => { await page.waitForLoadState('load'); await page.waitForFunction(() => [...document.querySelectorAll('[x-data]')].every(e => e._x_dataStack?.length)); await settled(); };
        const goto = async url => { assert.equal((await page.goto(base+url)).status(),200); await ready(); };
        const follow = async name => { await Promise.all([page.waitForNavigation(), page.getByRole('link',{name,exact:true}).click()]); await ready(); };
        await goto('/login'); await page.locator('[name=email]').fill('ui-test@example.test'); await page.locator('[name=password]').fill('password-testing-only');
        await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]); await ready();
        await goto('/settings/insurance-import?section=policies&result=imported&search=REPEAT-QA');
        assert.equal(await page.locator('table.t-table tbody tr').count(),1);
        await follow('Dati originali');
        assert.match(await page.locator('main').innerText(), /Accorpamento delle righe/i);
        assert.ok((await page.locator('main').innerText()).includes('2 righe con dati contrattuali e note identici'));
        const stateRow = page.locator('table.t-table tbody tr').filter({has:page.locator('th[scope=row]',{hasText:/^Polizze$/})});
        assert.deepEqual(await stateRow.locator('td').allTextContents(),['Archivio','OLD']);
        const dates = page.locator('table.t-table tbody tr').filter({has:page.locator('th[scope=row]',{hasText:/^Data Rata Attuale$/})});
        assert.deepEqual(await dates.locator('td').allTextContents(),['01/01/2020','01/02/2020']);
        for (const [name,size] of [['desktop',{width:1440,height:1050}],['mobile',{width:390,height:844}]]) {
            await page.setViewportSize(size); await page.keyboard.press('Escape');
            await settled();
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1));
            report.layouts.push(name);
            await page.screenshot({path:path.join(__dirname,`equivalent-policy-${name}.png`),fullPage:true});
        }
        await follow('Apri polizza');
        assert.equal(new URL(page.url()).pathname, `/clients/${seeded.client_id}/policies/${seeded.policy_id}`);
        assert.ok((await page.locator('main').innerText()).includes('Archivio / OLD'));
        assert.ok((await page.locator('main').innerText()).includes('Da verificare'));
        await follow('Consulta dati originali');
        assert.equal(new URL(page.url()).pathname, `/settings/insurance-import/policies/${seeded.entry_id}`);
        await goto(`/clients/${seeded.client_id}?tab=policies&category=RC%20auto&subcategory=AUTO`);
        assert.equal(await page.locator('table.t-table tbody tr').filter({hasText:'REPEAT-QA'}).count(),1);
        await goto('/deadlines?period=all&search=REPEAT-QA');
        assert.equal(await page.locator('table.t-table tbody tr').count(),1);
        assert.ok((await page.locator('table.t-table').innerText()).includes('Stato polizza da verificare'));
        report.flows.push('Una polizza per gruppo; collegamenti tra esito, fonte, scheda, tab e scadenze');
        report.flows.push('Accorpamento tracciato; entrambe le righe, etichette originali e date rata restano consultabili');
        assert.deepEqual(report.errors,[]); report.ok=true;
    } catch(error) {
        report.ok=false; report.failure=error.stack;
        if (page) await page.screenshot({path:path.join(__dirname,'equivalent-policy-failure.png')}).catch(()=>{});
        throw error;
    } finally {
        if (browser) await browser.close(); server.kill();
        fs.writeFileSync(path.join(__dirname,'equivalent-policy-ui-report.json'),JSON.stringify(report,null,2));
    }
    console.log(JSON.stringify(report,null,2));
})().catch(error => { console.error(error.stack); process.exitCode=1; });
