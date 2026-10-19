const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(__dirname, `ui-${Date.now()}.sqlite`), DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: `insurance_navigation_${Date.now()}`, QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };
const report = { checks: [], javascriptErrors: [] };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

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
        page.setDefaultTimeout(10000);
        page.on('pageerror', error => report.javascriptErrors.push(error.message));
        const ready = async () => {
            await page.waitForLoadState('load');
            await page.waitForFunction(() => [...document.querySelectorAll('[x-data]')].every(el => el._x_dataStack?.length));
            await page.evaluate(() => window.Alpine?.nextTick());
            await page.evaluate(() => Promise.all(document.getAnimations().filter(animation => animation.effect?.getTiming().iterations !== Infinity).map(animation => animation.finished.catch(() => {}))));
        };
        const goto = async route => { assert.equal((await page.goto(base + route)).status(), 200); await ready(); };
        const follow = async (locator, route) => { await Promise.all([page.waitForURL(base + route), locator.click()]); await ready(); };
        const check = label => { report.checks.push(label); console.log('Verificato: ' + label); };
        const screenshot = name => page.screenshot({ path: path.join(__dirname, name + '.png'), animations: 'disabled' });
        await goto('/login');
        await page.locator('[name=email]').fill('ui-test@example.test');
        await page.locator('[name=password]').fill('password-testing-only');
        await follow(page.locator('button[type=submit]'), '/dashboard');

        await follow(page.locator('#app-sidebar a[href$="/clients"]'), '/clients');
        await follow(page.locator('#app-sidebar a[href$="/supplier-companies"]'), '/supplier-companies');
        await follow(page.locator('main a[href$="/suppliers"]').first(), '/suppliers');
        check('Navigazione desktop: Clienti, Fornitori, Agenzie e subagenzie');

        await page.setViewportSize({ width: 390, height: 844 });
        await goto('/clients/1');
        const menu = page.locator('.mobile-menu-toggle');
        assert.equal(await menu.getAttribute('aria-expanded'), 'false');
        assert.equal(await page.locator('#app-sidebar').evaluate(el => el.inert), true);
        await menu.click();
        await page.waitForFunction(() => document.querySelector('.mobile-menu-toggle').getAttribute('aria-expanded') === 'true');
        assert.equal(await page.locator('#app-sidebar').evaluate(el => el.inert), false);
        await screenshot('navigation-mobile-menu');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('.mobile-menu-toggle').getAttribute('aria-expanded') === 'false');
        await menu.click();
        await follow(page.locator('#app-sidebar a[href$="/supplier-companies"]'), '/supplier-companies');
        assert.equal(await menu.getAttribute('aria-expanded'), 'false');
        await menu.click();
        await follow(page.locator('#app-sidebar a[href$="/clients"]'), '/clients');
        assert.equal(await menu.getAttribute('aria-expanded'), 'false');
        check('Menu smartphone: apertura, Escape, collegamenti e chiusura dopo la navigazione');

        await goto('/clients/1?tab=policies');
        await follow(page.getByRole('navigation', { name: 'Tipologie di polizza' }).getByRole('link', { name: 'RC auto', exact: true }), '/clients/1?tab=policies&category=RC%20auto');
        assert.equal(await page.locator('table tbody tr').count(), 1);
        const scroll = page.locator('.client-table-scroll').first();
        const metrics = await scroll.evaluate(el => {
            el.scrollLeft = el.scrollWidth;
            return { width: el.clientWidth, total: el.scrollWidth, left: el.scrollLeft, overflow: getComputedStyle(el).overflowX };
        });
        assert.ok(metrics.total > metrics.width && metrics.left > 0 && metrics.overflow === 'auto');
        const lastColumn = await scroll.locator('th').last().boundingBox();
        assert.ok(lastColumn.x >= 0 && lastColumn.x + lastColumn.width <= 390);
        await screenshot('navigation-mobile-table');
        await follow(page.locator('.client-section-nav').first().getByRole('link', { name: 'Scadenze', exact: true }), '/clients/1?tab=deadlines');
        check('Tab polizze e scadenze su smartphone, colonne della tabella raggiungibili');

        for (const [route, buttonLabel, file] of [
            ['/clients/1/edit', 'Aggiorna cliente', 'navigation-mobile-client-bottom'],
            ['/suppliers/create', 'Salva agenzia', 'navigation-mobile-supplier-bottom'],
        ]) {
            await goto(route);
            const save = page.getByRole('button', { name: buttonLabel, exact: true });
            await save.scrollIntoViewIfNeeded();
            const box = await save.boundingBox();
            assert.ok(box.x >= 0 && box.x + box.width <= 390 && box.y >= 52 && box.y + box.height <= 844);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await screenshot(file);
        }
        check('Moduli lunghi su smartphone: campi finali e pulsanti di salvataggio raggiungibili');

        await goto('/clients/1/policies/1');
        const opener = page.getByRole('button', { name: 'Elimina polizza', exact: true });
        await opener.click();
        const dialog = page.getByRole('dialog', { name: 'Elimina polizza', exact: true });
        await dialog.waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement?.hasAttribute('data-dialog-initial-focus'));
        await page.keyboard.press('Shift+Tab');
        assert.equal(await dialog.getByRole('button', { name: 'Sì, elimina', exact: true }).evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Tab');
        assert.equal(await dialog.getByRole('button', { name: 'Annulla', exact: true }).evaluate(el => el === document.activeElement), true);
        await screenshot('navigation-mobile-dialog');
        await page.keyboard.press('Escape');
        await dialog.waitFor({ state: 'hidden' });
        await page.waitForFunction(() => document.activeElement?.textContent.trim() === 'Elimina polizza');
        assert.equal(await opener.evaluate(el => el === document.activeElement), true);
        await opener.click();
        await dialog.getByRole('button', { name: 'Annulla', exact: true }).click();
        await dialog.waitFor({ state: 'hidden' });
        assert.equal((await context.request.get(base + '/clients/1/policies/1')).status(), 200);
        check('Conferma eliminazione: tastiera, focus, Escape e annullamento senza perdita dati');
        assert.deepEqual(report.javascriptErrors, []);
        report.ok = true;
    } catch (error) {
        report.ok = false;
        report.error = error.stack;
        if (page) await page.screenshot({ path: path.join(__dirname, 'navigation-failure.png'), animations: 'disabled' }).catch(() => {});
        throw error;
    } finally {
        fs.writeFileSync(path.join(__dirname, 'navigation-report.json'), JSON.stringify(report, null, 2));
        if (browser) await browser.close();
        server.kill();
    }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
