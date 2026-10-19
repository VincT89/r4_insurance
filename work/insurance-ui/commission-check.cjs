const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe';
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`commission_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',COMMISSION_QA:'1',QUOTE_EMAIL_QA:'0',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report={flows:[],layouts:[],errors:[],externalRequests:[],labelIssues:[]};const delay=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
    const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base=`http://127.0.0.1:${port}`;env.APP_URL=base;
    const seed=JSON.parse(execFileSync(php,[path.join(__dirname,'prepare.php')],{cwd:root,env,windowsHide:true,encoding:'utf8'}));
    const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser,page;
    try {
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));});};
        const go=async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const submit=async(label,scope=page)=>{await Promise.all([page.waitForNavigation(),scope.getByRole('button',{name:label,exact:true}).click()]);await ready();};
        const login=async email=>{
            const context=await browser.newContext({viewport:{width:1440,height:1050}});
            await context.route('**/*',r=>{const url=r.request().url();if(url.startsWith(base+'/')||url.startsWith('blob:')||url.startsWith('data:'))return r.continue();report.externalRequests.push(url);return r.abort();});
            page=await context.newPage();page.setDefaultTimeout(15000);page.on('pageerror',e=>report.errors.push(e.message));page.on('response',r=>{if(r.status()>=500)report.errors.push(`${r.status()} ${r.url()}`);});
            await go('/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('password-testing-only');await submit('Entra');
        };
        const screenshot=async(name,bottom=false)=>{
            await page.keyboard.press('Escape');await page.evaluate(async bottom=>{await window.Alpine?.nextTick();document.activeElement?.blur();for(const el of document.querySelectorAll('main,.content'))el.scrollTo({top:bottom?el.scrollHeight:0,behavior:'instant'});window.scrollTo(0,bottom?document.body.scrollHeight:0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));},bottom);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`overflow ${name}`);
            const missing=await page.locator('main input:not([type=hidden]),main select,main textarea').evaluateAll(els=>els.filter(e=>e.offsetParent&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.name));if(missing.length)report.labelIssues.push({name,missing});
            await page.screenshot({path:path.join(__dirname,`commission-${name}.png`),fullPage:true});report.layouts.push(name);
        };
        const layouts=async name=>{await page.setViewportSize({width:1440,height:1050});await screenshot(name+'-desktop');await screenshot(name+'-desktop-bottom',true);await page.setViewportSize({width:390,height:844});await screenshot(name+'-mobile');await screenshot(name+'-mobile-bottom',true);await page.setViewportSize({width:1440,height:1050});};
        await login('ui-test@example.test');
        const index=`/suppliers/${seed.agency_id}/commissions`;
        await go(`/suppliers/${seed.agency_id}`);await page.getByRole('link',{name:'Provvigioni',exact:true}).click();await ready();
        await page.getByRole('link',{name:'Configura aliquote',exact:true}).click();await ready();
        await page.locator('[name=supplier_company_id]').selectOption(String(seed.company_id));
        await page.locator('[name=valid_from]').fill('2026-10-01');await page.locator('[name=valid_to]').fill('2026-12-31');
        assert.equal(await page.locator('[name=accrual_event]').inputValue(),'');
        const branchRows=()=>page.locator('.commission-option').filter({has:page.locator(`input[name$="[branch_id]"][value="${seed.branch_id}"]`)});
        const rootRow=()=>branchRows().filter({has:page.locator('input[name$="[subbranch_id]"][value=""]')});
        const subRow=()=>branchRows().filter({has:page.locator('input[name$="[subbranch_id]"]:not([value=""])')});
        await rootRow().locator('input[type=checkbox]').check();await rootRow().locator('select').selectOption('net_premium');await rootRow().locator('input[name$="[issue_rate]"]').fill('12,3456');
        await subRow().locator('input[type=checkbox]').check();await subRow().locator('select').selectOption('gross_premium');await subRow().locator('input[name$="[issue_rate]"]').fill('20');await subRow().locator('input[name$="[renewal_rate]"]').fill('8');
        await page.getByRole('button',{name:'Mostra solo selezionate',exact:true}).click();await page.waitForFunction(()=>[...document.querySelectorAll('.commission-option')].filter(e=>e.getClientRects().length).length===2);assert.equal(await page.locator('.commission-option:visible').count(),2);
        await page.locator('#field-catalog-search').fill('RC auto');await layouts('form');await submit('Salva condizioni');
        const original=new URL(page.url()).pathname;
        assert.match(await page.locator('main').innerText(),/12,3456 %/);assert.match(await page.locator('main').innerText(),/Da definire con il mandante/);await layouts('saved');
        report.flows.push('Admin configura mandato, rami e sottorami con basi e aliquote distinte; maturazione e percentuali mancanti restano da definire.');
        await page.getByRole('link',{name:'Modifica condizioni',exact:true}).click();await ready();
        assert.equal(await rootRow().locator('input[name$="[issue_rate]"]').inputValue(),'12.3456');
        await rootRow().locator('input[name$="[issue_rate]"]').fill('13');await page.locator('[name=revision_reason]').fill('Condizioni aggiornate sulla base del mandato');
        await page.getByRole('button',{name:'Mostra solo selezionate',exact:true}).click();await layouts('revision');await submit('Salva nuova versione');
        const current=new URL(page.url()).pathname;assert.notEqual(original,current);
        await go(original);assert.match(await page.locator('main').innerText(),/12,3456 %/);assert.equal(await page.getByRole('link',{name:'Modifica condizioni',exact:true}).count(),0);
        await go(index);await page.locator('[name=company]').selectOption(String(seed.company_id));await page.locator('[name=branch]').selectOption(String(seed.branch_id));await page.locator('[name=on]').fill('2026-10-16');await page.locator('[name=basis]').selectOption('net_premium');await submit('Applica filtri');
        assert.equal(await page.locator('tbody tr').count(),1);assert.match(await page.locator('tbody').innerText(),/13 %/);await layouts('filtered');
        for(const format of ['Excel','PDF']){const download=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta '+format,exact:true}).click();const result=await download;assert.equal(await result.failure(),null);await result.saveAs(path.join(__dirname,'commission-filtered.'+(format==='Excel'?'xlsx':'pdf')));}
        await page.locator('[name=state]').selectOption('history');await submit('Applica filtri');assert.match(await page.locator('tbody').innerText(),/12,3456 %/);
        report.flows.push('Revisione motivata con storico conservato, filtri per mandato, ramo, data e base; esportazioni Excel e PDF.');
        await go(current);await page.getByText('Annulla questa configurazione',{exact:true}).click();await page.locator('[name=reason]').fill('Configurazione da sostituire dopo verifica del mandato');await page.locator('[name=confirmed]').check();await submit('Annulla configurazione');assert.match(await page.locator('main').innerText(),/Annullata/);
        await login('reader@example.test');await go(index);assert.equal(await page.getByRole('link',{name:'Configura aliquote',exact:true}).count(),0);await go(current);assert.equal(await page.getByRole('link',{name:'Modifica condizioni',exact:true}).count(),0);await layouts('readonly-history');
        assert.equal((await page.request.get(base+index+'/create')).status(),403);
        report.flows.push('Annullamento confermato con motivazione e storico; utente amministrazione in sola lettura, modifica riservata all’admin.');
        assert.deepEqual(report.errors,[]);assert.deepEqual(report.externalRequests,[]);assert.deepEqual(report.labelIssues,[]);report.ok=true;
    }catch(e){report.ok=false;report.failure=e.stack;if(page)await page.screenshot({path:path.join(__dirname,'commission-failure.png'),fullPage:true}).catch(()=>{});throw e;}
    finally{if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'commission-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
