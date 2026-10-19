const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(__dirname, `ui-${Date.now()}.sqlite`), DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: `appearance_qa_${Date.now()}`, QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };
const report = { flows: [], layouts: [], javascriptErrors: [], failedResponses: [] };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const chosen = { sidebar: '#f8fafc', sidebar_active: '#ddeeff', topbar: '#ffffff', page: '#e8f1f5', panel: '#ffffff', field: '#f3f4f6', table_header: '#245178', table_body: '#ffffff', table_hover: '#dbe9f3', primary: '#1c6292', heading: '#1c6292', text: '#203040', sidebar_hover: '#c8dceb', primary_hover: '#124469', secondary_hover: '#cadce8', link_hover: '#163da8' };

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
        for (let i = 0; i < 40; i++) {
            try { if ((await fetch(base + '/login')).ok) break; } catch {}
            await delay(250);
        }
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
        const goto = async url => { assert.equal((await page.goto(base + url)).status(), 200, url); await ready(); };
        const check = label => { report.flows.push(label); console.log('Verificato: ' + label); };
        const save = async label => { await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.getByRole('button', { name: label, exact: true }).click()]); await ready(); assert.equal(await page.locator('.flash-error').count(), 0); };
        const vars = () => page.evaluate(() => Object.fromEntries([...document.body.style].filter(key => key.startsWith('--ap-')).map(key => [key, document.body.style.getPropertyValue(key).trim()])));
        const bg = selector => page.locator(selector).first().evaluate(el => getComputedStyle(el).backgroundColor);
        const set = async colors => { for (const [key, value] of Object.entries(colors)) await page.locator(`[data-color-value="${key}"]`).fill(value); await ready(); };
        const layout = async name => {
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Nessun overflow ' + name);
            const missing = await page.locator('main input:not([type=hidden]), main select, main textarea').evaluateAll(elements => elements.filter(el => el.offsetParent && !el.labels?.length && !el.getAttribute('aria-label')).map(el => el.name));
            assert.deepEqual(missing, []);
            report.layouts.push({ name, viewport: page.viewportSize() });
        };
        await goto('/login');
        assert.equal(await page.getByText('Sodano Consulting').count(), 0);
        assert.equal(await page.locator('.login-logo img').evaluate(el => el.complete && el.naturalWidth > 0), true);
        assert.ok((await page.locator('link[rel=icon][sizes=\"32x32\"]').getAttribute('href')).includes('/favicon-32x32.png?v='));
        await page.screenshot({ path: path.join(__dirname, 'appearance-login-desktop.png') });
        await page.setViewportSize({ width: 390, height: 844 }); await ready();
        assert.equal(await page.locator('.login-mobile-brand').isVisible(), true);
        await layout('Login mobile'); await page.screenshot({ path: path.join(__dirname, 'appearance-login-mobile.png') });
        await page.setViewportSize({ width: 1440, height: 1050 });
        await page.locator('[name=email]').fill('ui-test@example.test'); await page.locator('[name=password]').fill('password-testing-only');
        await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]); await ready();
        check('Logo originale e favicon su accesso desktop e mobile');
        await goto('/profile');
        await page.getByRole('navigation', { name: 'Impostazioni personali' }).getByRole('link', { name: 'Aspetto', exact: true }).click(); await ready();
        assert.equal(await page.locator('[data-color-value]').count(), 18);
        await layout('Impostazioni desktop');
        await page.screenshot({ path: path.join(__dirname, 'appearance-settings-desktop.png') });
        const initial = await vars();
        await set(chosen);
        assert.equal(await bg('body'), 'rgb(232, 241, 245)');
        assert.equal(await bg('.sidebar'), 'rgb(248, 250, 252)');
        assert.equal(await bg('.topbar'), 'rgb(255, 255, 255)');
        assert.equal(await bg('.appearance-group'), 'rgb(255, 255, 255)');
        assert.equal(await bg('.t-table th'), 'rgb(36, 81, 120)');
        assert.equal(await bg('.t-table tbody'), 'rgb(255, 255, 255)');
        assert.equal(await bg('[data-color-value=page]'), 'rgb(243, 244, 246)');
        await page.locator('.sidebar a').first().hover(); await ready();
        assert.equal(await bg('.sidebar a'), 'rgb(200, 220, 235)');
        await page.getByRole('button', { name: 'Salva aspetto', exact: true }).hover(); await ready();
        assert.equal(await bg('[data-appearance-editor] .btn-p'), 'rgb(18, 68, 105)');
        await page.getByRole('button', { name: 'Annulla modifiche', exact: true }).hover(); await ready();
        assert.equal(await bg('[data-appearance-cancel]'), 'rgb(202, 220, 232)');
        await page.locator('.t-table tbody tr').hover(); await ready();
        assert.equal(await bg('.t-table tbody tr'), 'rgb(219, 233, 243)');
        await page.getByRole('link', { name: 'Profilo e sicurezza', exact: true }).hover(); await ready();
        assert.equal(await page.getByRole('link', { name: 'Profilo e sicurezza', exact: true }).evaluate(el => getComputedStyle(el).color), 'rgb(22, 61, 168)');
        await page.mouse.move(1200, 60);
        const preview = await vars();
        await save('Salva aspetto');
        assert.deepEqual(await vars(), preview, 'Anteprima uguale al tema salvato sul server');
        await goto('/clients'); assert.equal(await bg('.t-table th'), 'rgb(36, 81, 120)');
        await page.getByRole('link', { name: /Blocco Note/ }).first().click(); await ready();
        assert.equal(await bg('.sidebar'), 'rgb(248, 250, 252)', 'Tema conservato nella navigazione Livewire');
        await goto('/settings/appearance');
        await set({ page: '#000000' });
        await page.getByRole('button', { name: 'Annulla modifiche', exact: true }).click();
        assert.deepEqual(await vars(), preview);
        await page.getByRole('button', { name: 'Prova colori iniziali', exact: true }).click();
        assert.deepEqual(await vars(), initial);
        await page.reload(); await ready(); assert.deepEqual(await vars(), preview, 'Anteprima non salvata non persiste');
        check('Colori e hover: anteprima fedele, salvataggio, navigazione e annullamento');

        const second = await browser.newContext({ viewport: { width: 390, height: 844 } });
        await second.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const secondPage = await second.newPage();
        await secondPage.goto(base + '/login');
        await secondPage.locator('[name=email]').fill('ui-test@example.test'); await secondPage.locator('[name=password]').fill('password-testing-only');
        await Promise.all([secondPage.waitForURL('**/dashboard'), secondPage.locator('button[type=submit]').click()]);
        assert.equal(await secondPage.locator('body').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(232, 241, 245)');
        await second.close(); check('Preferenze personali disponibili in una nuova sessione');

        await set({ page: '#101820', panel: '#17232e', field: '#0b141d', sidebar: '#0a1118', sidebar_active: '#233a4e', topbar: '#0a1118', table_header: '#233a4e', table_body: '#17232e', table_hover: '#2a4053', primary: '#f4c84b', heading: '#a8d8ff', text: '#000000' });
        await save('Salva aspetto');
        await Promise.all([page.waitForURL('**/login'), page.locator('.sidebar-logout-form button').click()]); await ready();
        assert.equal(await bg('.login-shell'), 'rgb(16, 24, 32)');
        assert.equal(await bg('.login-right'), 'rgb(0, 0, 0)');
        assert.equal(await bg('[name=email]'), 'rgb(11, 20, 29)');
        await page.screenshot({ path: path.join(__dirname, 'appearance-login-themed-desktop.png') });
        await page.setViewportSize({ width: 390, height: 844 }); await ready(); await layout('Login con tema personale');
        await page.screenshot({ path: path.join(__dirname, 'appearance-login-themed-mobile.png') });
        await page.locator('[name=email]').fill('ui-test@example.test'); await page.locator('[name=password]').fill('password-testing-only');
        await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]); await ready();
        check('Logout e login conservano il tema nel browser, compresi campi e pulsanti');
        for (const viewport of [{ width: 1440, height: 1050 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            for (const url of ['/settings/appearance', '/clients', '/clients/1', '/clients/1/edit', '/suppliers', '/profile']) {
                await goto(url); await layout(url);
                if (url === '/settings/appearance' || url === '/clients/1') await page.screenshot({ path: path.join(__dirname, `appearance-dark-${viewport.width}-${url.includes('appearance') ? 'settings' : 'client'}.png`) });
            }
        }
        await goto('/settings/appearance');
        await page.locator('[data-color-value=primary]').fill('#ffffff');
        await page.getByRole('button', { name: 'Salva aspetto', exact: true }).scrollIntoViewIfNeeded();
        await page.screenshot({ path: path.join(__dirname, 'appearance-mobile-actions.png') });
        await save('Salva aspetto');
        assert.equal((await vars())['--ap-primary-text'], '#000000');
        await save('Ripristina e salva i colori iniziali'); assert.deepEqual(await vars(), initial);
        check('Tema scuro, testo con contrasto automatico e ripristino su smartphone');
        const imagePage = await context.newPage();
        await imagePage.goto(base + '/favicon.svg');
        await imagePage.locator('svg').first().screenshot({ path: path.join(__dirname, 'appearance-favicon.png') });
        await imagePage.close();
        assert.deepEqual(report.javascriptErrors, []); assert.deepEqual(report.failedResponses, []);
        report.ok = true; console.log(JSON.stringify({ ok: true, flows: report.flows.length, layouts: report.layouts.length }));
    } catch (error) {
        report.ok = false; report.error = error.stack;
        if (page) { report.failureUrl = page.url(); await page.screenshot({ path: path.join(__dirname, 'appearance-failure.png'), fullPage: true }).catch(() => {}); }
        throw error;
    } finally {
        fs.writeFileSync(path.join(__dirname, 'appearance-report.json'), JSON.stringify(report, null, 2));
        if (browser) await browser.close();
        server.kill();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
