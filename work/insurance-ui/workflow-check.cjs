const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), net = require('node:net'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..'), php = 'C:/PHP 8.3.1/php.exe';
const database = path.join(__dirname, `ui-${Date.now()}.sqlite`);
const env = {...process.env, APP_ENV:'testing', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:database, DB_URL:'', CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:`work_${Date.now()}`, QUEUE_CONNECTION:'sync', MAIL_MAILER:'array', WORKSPACE_QA:'1', AGENCY_EMAIL_QA:'1', OPERATIONS_QA:'1', WORKFLOW_QA:'1'};
/** The isolated session must survive the simulated jump to midnight while file timestamps use real time. */
env.SESSION_LIFETIME = '1440';
const report = {flows:[], layouts:[], errors:[], externalRequests:[], labelIssues:[]};
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
(async () => {
    const reservation = net.createServer(); await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
    const port = reservation.address().port; await new Promise(resolve => reservation.close(resolve));
    const base = `http://127.0.0.1:${port}`; env.APP_URL = base;
    const seed = JSON.parse(execFileSync(php, [path.join(__dirname,'prepare.php')], {cwd:root, env, windowsHide:true, encoding:'utf8'}));
    fs.writeFileSync(database+'.clock', '2026-10-16 23:59:50');
    const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', path.join(root,'public'), path.join(__dirname,'router.php')], {cwd:root, env, windowsHide:true, stdio:'ignore'});
    let browser, page;
    try {
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const ready = async () => { await page.waitForLoadState('load'); await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length)); await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));}); };
        const go = async url => { assert.equal((await page.goto(base+url)).status(),200,url); await ready(); };
        const submit = async (label,scope=page) => { await Promise.all([page.waitForNavigation(),scope.getByRole('button',{name:label,exact:true}).click()]); await ready(); };
        const login = async email => {
            const context = await browser.newContext({viewport:{width:1440,height:1050}});
            await context.route('**/*',route=>{const url=route.request().url();if(url.startsWith(base+'/')||url.startsWith('blob:')||url.startsWith('data:'))return route.continue();report.externalRequests.push(url);return route.abort();});
            page = await context.newPage(); page.setDefaultTimeout(15000);
            page.on('pageerror',error=>report.errors.push(error.message)); page.on('response',response=>{if(response.status()>=500)report.errors.push(response.status()+' '+response.url());});
            await go('/login'); await page.locator('[name=email]').fill(email); await page.locator('[name=password]').fill('password-testing-only'); await submit('Entra');
            return page;
        };
        const capture = async (name,target) => {
            for(const [size,viewport] of [['desktop',{width:1440,height:1050}],['mobile',{width:390,height:844}]]) {
                await page.setViewportSize(viewport); await page.keyboard.press('Escape'); await ready();
                if(target)await page.locator(target).scrollIntoViewIfNeeded(); else await page.evaluate(()=>{for(const el of document.querySelectorAll('main,.content'))el.scrollTo(0,0);window.scrollTo(0,0);});
                await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
                assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'page overflow '+name);
                const missing=await page.locator('main input:not([type=hidden]),main select,main textarea').evaluateAll(els=>els.filter(e=>e.offsetParent&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.name));
                if(missing.length)report.labelIssues.push({name,missing});
                await page.screenshot({path:path.join(__dirname,`workflow-${name}-${size}.png`),fullPage:true}); report.layouts.push(name+'-'+size);
            }
            await page.setViewportSize({width:1440,height:1050});
        };
        const policyPath=`/clients/${seed.client_id}/policies/${seed.policy_id}`;
        const operator = await login('operator@example.test'); await go(policyPath); await capture('open');
        const close=page.locator('form[action$="/close-work"]'); await close.locator('[name=confirmed]').check(); await submit('Chiudi lavorazione',close);
        assert.equal(await page.getByRole('link',{name:'Modifica polizza',exact:true}).count(),1); await capture('closed-today');
        fs.writeFileSync(database+'.clock', '2026-10-17 00:00:00'); await go(policyPath);
        assert.equal(await page.getByRole('link',{name:'Modifica polizza',exact:true}).count(),0); assert.match(await page.locator('main').innerText(),/solo|riservate all.admin/); await capture('locked-next-day');
        assert.equal((await page.goto(base+policyPath+'/edit')).status(),403); await go(policyPath);
        const reportForm=page.locator('form[action$="/corrections"]'); await reportForm.locator('[name=message]').fill('Il soggetto assicurato deve essere Officine Valdoro, come indicato nel contratto.'); await capture('report-form','#policy-corrections'); await submit('Invia segnalazione',reportForm);
        report.flows.push('Chiusura esplicita su polizza attiva, modifica consentita nello stesso giorno e blocco alla mezzanotte successiva; segnalazione disponibile');
        const admin=await login('ui-test@example.test'); await page.getByRole('button',{name:/^Notifiche/}).click(); assert.match(await page.locator('#notifications-panel').innerText(),/Errore segnalato su una polizza/); await page.keyboard.press('Escape');
        await go(policyPath); await capture('admin-report','#policy-corrections'); await page.getByRole('link',{name:'Correggi polizza',exact:true}).click(); await ready();
        await page.locator('[name=insured_subject]').fill('Officine Valdoro'); await page.locator('[name=correction_reason]').fill('Corretto il soggetto assicurato dopo controllo della segnalazione e del contratto.'); await capture('admin-reason','#field-correction-reason'); await submit('Salva polizza');
        const resolution=page.locator('form[action$="/resolve"]').first(); await resolution.locator('[name=status]').selectOption('corrected'); await resolution.locator('[name=resolution]').fill('Soggetto assicurato corretto come da contratto della compagnia.'); await submit('Registra esito',resolution); await capture('resolved','#policy-corrections');
        page=operator; await go(policyPath); assert.equal(await page.getByRole('link',{name:'Modifica polizza',exact:true}).count(),0); assert.match(await page.locator('#policy-corrections').innerText(),/Correzione effettuata/);
        await page.getByRole('button',{name:/^Notifiche/}).click(); assert.match(await page.locator('#notifications-panel').innerText(),/Esito della segnalazione/);
        report.flows.push('Notifica admin, correzione motivata, esito conservato e notificato all’operatore senza riaprire la lavorazione');
        assert.deepEqual(report.errors,[]); assert.deepEqual(report.externalRequests,[]); assert.deepEqual(report.labelIssues,[]); report.ok=true;
    } catch(error) { report.ok=false;report.failure=error.stack;if(page)await page.screenshot({path:path.join(__dirname,'workflow-failure.png'),fullPage:true}).catch(()=>{});throw error; }
    finally { if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'workflow-report.json'),JSON.stringify(report,null,2)); }
    console.log(JSON.stringify(report,null,2));
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
