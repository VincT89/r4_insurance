const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), net = require('node:net'), assert = require('node:assert/strict');
const root = path.resolve(__dirname,'../..'), php = 'C:/PHP 8.3.1/php.exe';
const env = {...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`quote_mail_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',QUOTE_EMAIL_QA:'1',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report = {flows:[],layouts:[],errors:[],externalRequests:[],labelIssues:[],realEmailsSent:0};
const delay = ms => new Promise(r=>setTimeout(r,ms));

(async()=>{
    const reservation = net.createServer();
    await new Promise(r=>reservation.listen(0,'127.0.0.1',r));
    const port = reservation.address().port;
    await new Promise(r=>reservation.close(r));
    const base = `http://127.0.0.1:${port}`; env.APP_URL = base;
    const seed = JSON.parse(execFileSync(php,[path.join(__dirname,'prepare.php')],{cwd:root,env,windowsHide:true,encoding:'utf8'}));
    const server = spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser, page;
    try {
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context = await browser.newContext({viewport:{width:1440,height:1050}});
        await context.route('**/*',r=>{
            const url=r.request().url();
            if(url.startsWith(base+'/')||url.startsWith('blob:')||url.startsWith('data:'))return r.continue();
            report.externalRequests.push(url);return r.abort();
        });
        page = await context.newPage();page.setDefaultTimeout(15000);
        page.on('pageerror',e=>report.errors.push(e.message));
        page.on('response',r=>{if(r.status()>=500)report.errors.push(`${r.status()} ${r.url()}`);});
        const ready = async()=>{
            await page.waitForLoadState('load');
            await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));
            await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));});
        };
        const go = async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const submit = async(label,scope=page)=>{await Promise.all([page.waitForNavigation(),scope.getByRole('button',{name:label,exact:true}).click()]);await ready();};
        const screenshot = async(name,bottom=false)=>{
            await page.keyboard.press('Escape');
            await page.evaluate(async bottom=>{
                await window.Alpine?.nextTick();document.activeElement?.blur();
                for(const el of document.querySelectorAll('main,.content'))el.scrollTo({top:bottom?el.scrollHeight:0,behavior:'instant'});
                window.scrollTo(0,bottom?document.body.scrollHeight:0);
                await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
                await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));
            },bottom);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`overflow ${name}`);
            const missing = await page.locator('main input:not([type=hidden]),main select,main textarea').evaluateAll(els=>els.filter(e=>e.offsetParent&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.name));
            if(missing.length)report.labelIssues.push({name,missing});
            await page.screenshot({path:path.join(__dirname,`quote-email-${name}.png`),fullPage:true});report.layouts.push(name);
        };
        const layouts = async name=>{
            await page.setViewportSize({width:1440,height:1050});await screenshot(name+'-desktop');await screenshot(name+'-desktop-bottom',true);
            await page.setViewportSize({width:390,height:844});await screenshot(name+'-mobile');await screenshot(name+'-mobile-bottom',true);
            await page.setViewportSize({width:1440,height:1050});
        };
        const {jsPDF} = require('../../node_modules/jspdf');const pdf = new jsPDF();
        pdf.text('Documento riservato al collaudo delle richieste di preventivo',10,20);
        const buffer = Buffer.from(pdf.output('arraybuffer'));
        const file = name=>({name,mimeType:'application/pdf',buffer});
        await go('/login');await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');await submit('Entra');
        await go('/quotes/create');await page.locator('[data-client-search]').fill('Officine Valdoro');await page.locator('.client-picker-result').first().click();
        await page.waitForFunction(()=>!!document.querySelector('[name=client_id]').value);
        await page.locator('[name=insurance_branch_id]').selectOption(String(seed.branch_id));
        await page.locator('[name=subject]').fill('Assicurazione autovettura');await page.locator('[name=notes]').fill('Nota interna che non deve essere spedita');
        await submit('Apri pratica preventivi');const caseUrl = new URL(page.url()).pathname;
        for(const [index,id] of [seed.company_id,seed.europe_id,seed.other_id].entries()){
            await go(caseUrl+'/emails/create');
            assert.equal(await page.locator(`[name=supplier_company_id] option[value="${seed.other_id}"]`).isDisabled(),index<2);
            await page.locator('[name=supplier_company_id]').selectOption(String(id));
            assert.equal(await page.locator('#field-recipient-preview').inputValue(),['nobis@example.test','europe@example.test','valdoro@example.test'][index]);
            assert.ok(!(await page.locator('[name=body]').inputValue()).includes('Nota interna'));
            await page.locator('[name=body]').fill((await page.locator('[name=body]').inputValue())+'\nTarga del veicolo: AB123CD.');
            await page.locator('[name="files[]"]').setInputFiles([file('richiesta-firmata.pdf'),file('veicolo.pdf')]);
            if(index===0)await layouts('compose');
            await submit('Controlla richiesta');
            assert.equal(await page.getByRole('button',{name:'Conferma e invia richiesta',exact:true}).isDisabled(),true);
            assert.match(await page.locator('main').innerText(),/richiesta-firmata.pdf/);
            assert.match(await page.locator('main').innerText(),/veicolo.pdf/);
            assert.match(await page.locator('main').innerText(),/AB123CD/);
            if(index===0)await layouts('review');
            await page.locator('[name=reviewed]').check();await submit('Conferma e invia richiesta');
            assert.match(await page.locator('main').innerText(),/Inviata al server di posta/);
            assert.equal(await page.locator('[name=reviewed]').count(),0);
            if(index===0)await layouts('sent');
        }
        report.flows.push('Nobis ed Europe Assistance prima delle altre compagnie; anteprima e conferma, allegati multipli e invio simulato con messaggio reale.');
        await go(caseUrl);await layouts('history');
        await page.locator('[name=email_company]').selectOption(String(seed.company_id));
        await page.locator('[name=email_state]').selectOption('sent');
        await page.locator('[name=email_month]').fill(new Date().toLocaleDateString('sv-SE',{timeZone:'Europe/Rome'}).slice(0,7));
        await submit('Applica filtri');
        const history = page.locator('[aria-label="Richieste di preventivo inviate"]');
        assert.equal(await history.locator('tbody tr').count(),1);assert.match(await history.innerText(),/Nobis/);
        await layouts('history-filtered');
        for(const format of ['Excel','PDF']){
            const download = page.waitForEvent('download');
            await history.locator('xpath=ancestor::div[contains(concat(" ",normalize-space(@class)," ")," panel ")][1]').getByRole('button',{name:'Esporta '+format,exact:true}).click();
            const result = await download;assert.equal(await result.failure(),null);
            await result.saveAs(path.join(__dirname,'quote-email-history.'+(format==='Excel'?'xlsx':'pdf')));
        }
        report.flows.push('Storico filtrato per compagnia, stato e mese con esportazioni Excel e PDF.');
        await go(caseUrl);
        const receive = page.locator('form[action$="/receive"]').first();
        const quoteId = (await receive.getAttribute('action')).match(/quote-proposals\/(\d+)/)[1];
        const day = new Date().toLocaleDateString('sv-SE',{timeZone:'Europe/Rome'});
        const nextYear = (Number(day.slice(0,4))+1)+day.slice(4);
        for(const [key,value] of Object.entries({reference:'N-2026-88',product:'Auto completa',premium:'1200.01',received_on:day,valid_until:day,starts_on:day,expires_on:nextYear}))await receive.locator(`[name=${key}]`).fill(value);
        await receive.locator('[name=supplier_agency_id]').selectOption(String(seed.agency_id));
        await receive.locator('[name=payment_frequency]').selectOption('Semestrale');
        await receive.locator('[name="files[]"]').setInputFiles([file('preventivo.pdf')]);
        await submit('Registra preventivo ricevuto',receive);
        const proposal = page.locator(`#proposal-${quoteId}`);
        await proposal.locator('form[action$="/submit"] [name=confirmed]').check();await submit('Metti in approvazione cliente',proposal);
        const approve = proposal.locator('form[action$="/approve"]');
        await approve.locator('[name="files[]"]').setInputFiles([file('approvazione-cliente.pdf')]);await approve.locator('[name=confirmed]').check();await submit('Registra approvazione',approve);
        await page.getByRole('link',{name:'Crea polizza da questo preventivo'}).click();await ready();
        assert.equal(await page.locator('#field-premium').inputValue(),'1200.01');
        await page.locator('[name=number]').fill('SA-2026-088');await submit('Salva polizza');
        assert.match(await page.locator('main').innerText(),/richiesta-firmata.pdf/);
        assert.match(await page.locator('main').innerText(),/approvazione-cliente.pdf/);
        report.flows.push('Richiesta inviata, preventivo ricevuto, approvazione documentata e polizza con premio e storico allegati conservati.');
        assert.deepEqual(report.errors,[]);assert.deepEqual(report.externalRequests,[]);assert.deepEqual(report.labelIssues,[]);report.ok=true;
    }catch(e){report.ok=false;report.failure=e.stack;if(page)await page.screenshot({path:path.join(__dirname,'quote-email-failure.png'),fullPage:true}).catch(()=>{});throw e;}
    finally{if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'quote-email-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
