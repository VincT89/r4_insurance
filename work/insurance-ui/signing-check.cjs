const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe';
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`signing_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',COMMISSION_QA:'1',SIGNING_QA:'1',QUOTE_EMAIL_QA:'0',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
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
            await page.screenshot({path:path.join(__dirname,`signing-${name}.png`),fullPage:true});report.layouts.push(name);
        };
        const layouts=async name=>{await page.setViewportSize({width:1440,height:1050});await screenshot(name+'-desktop');await screenshot(name+'-desktop-bottom',true);await page.setViewportSize({width:390,height:844});await screenshot(name+'-mobile');await screenshot(name+'-mobile-bottom',true);await page.setViewportSize({width:1440,height:1050});};
        const download=async(label,name)=>{const pending=page.waitForEvent('download');await page.getByRole('link',{name:label,exact:true}).click();const item=await pending;assert.equal(await item.failure(),null);await item.saveAs(path.join(__dirname,name));};
        await login('ui-test@example.test');
        await go('/settings/forms');await layouts('models');
        for(const id of seed.source_template_ids){const response=await page.request.get(base+`/settings/forms/${id}/preview`);assert.equal(response.status(),200);const bytes=await response.body();assert.equal(bytes.subarray(0,5).toString(),'%PDF-');fs.writeFileSync(path.join(__dirname,`signing-source-${id}.pdf`),bytes);}
        await page.getByRole('link',{name:'Nuovo modello',exact:true}).click();await ready();
        await page.locator('[name=title]').fill('Ricevuta di collaudo');await page.locator('[name=category]').selectOption('delivery_receipt');await page.locator('[name=scope]').selectOption('client');
        await page.locator('[name=body]').fill('Documento per il solo collaudo.\n\nContraente: {{contraente}}\n\nFirmatario: {{firmatario}}\n\nQualifica: {{qualifica_firmatario}}\n\nLuogo: {{luogo}}\n\nData: {{data_documento}}\n\nFirma: __________________');await submit('Salva bozza');await layouts('model-edit');
        await download('Anteprima PDF','signing-preview.pdf');await page.locator('[name=confirmed]').check();await submit('Approva versione');
        assert.match(await page.locator('main').innerText(),/Utilizzabile/);
        report.flows.push('Admin crea e approva un modello; anteprime dei sette fogli originali rimangono bozze.');
        const client=`/clients/${seed.client_id}`,policy=`${client}/policies/${seed.policy_id}`;
        await go(client);await page.getByRole('link',{name:'Moduli e firme',exact:true}).click();await ready();await page.getByRole('link',{name:'Prepara moduli',exact:true}).click();await ready();
        await page.locator('[name=signer_name]').fill('Alessandro Varesi');await page.locator('[name=signer_role]').fill('Legale rappresentante');await page.locator('[name=place]').fill('Napoli');await page.locator('input[name="templates[]"]').check();await page.locator('[name=confirmed]').check();await layouts('prepare-client');await submit('Genera moduli PDF');
        const clientPackage=new URL(page.url()).pathname;await download('Scarica modulo PDF','signing-client.pdf');assert.match(await page.locator('main').innerText(),/Firma mancante/);
        await go(policy);await page.getByRole('link',{name:'Prepara moduli',exact:true}).click();await ready();
        await page.locator('[name=signer_name]').fill('Alessandro Varesi');await page.locator('[name=signer_role]').fill('Legale rappresentante');await page.locator('[name=place]').fill('Napoli');await page.locator('input[name="templates[]"]').check();await page.locator('[name=confirmed]').check();await submit('Genera moduli PDF');
        const policyPackage=new URL(page.url()).pathname;await download('Scarica modulo PDF','signing-policy.pdf');
        await page.getByText('Carica documenti firmati',{exact:true}).click();
        const upload=page.locator('form').filter({has:page.locator('input[type=file]')});
        await upload.locator('input[type=file]').setInputFiles([path.join(__dirname,'signing-client.pdf'),path.join(__dirname,'signing-policy.pdf')]);await upload.locator('[name=signer_name]').fill('Alessandro Varesi');await upload.locator('[name=signer_role]').fill('Legale rappresentante');await upload.locator('[name=signed_on]').fill(new Date().toLocaleDateString('sv-SE',{timeZone:'Europe/Rome'}));await layouts('signatures-upload');await submit('Registra file firmati');
        assert.match(await page.locator('main').innerText(),/Firma da controllare/);await page.locator('[name=outcome]').selectOption('validated');await page.locator('[name=review_note]').fill('Controllo funzionale effettuato su file di prova');await page.locator('[name=confirmed]').check();await layouts('review');await submit('Salva controllo');assert.match(await page.locator('main').innerText(),/Documentazione completata/);await layouts('signed-history');
        report.flows.push('Fascicolo cliente e polizza, PDF precompilati, due allegati firmati in un caricamento e controllo manuale con storico.');
        await go(`${policy}/coverage-checks`);const href=await page.getByRole('link',{name:'Apri verifica RCA ufficiale',exact:true}).getAttribute('href');assert.equal(href,'https://www.ilportaledellautomobilista.it/web/portale-automobilista/ext/verifica-copertura-rc');
        await page.locator('[name=outcome]').selectOption('covered');await page.locator('input[type=file]').setInputFiles([path.join(__dirname,'signing-client.pdf'),path.join(__dirname,'signing-policy.pdf')]);await page.locator('[name=note]').fill('Esito simulato esclusivamente nel database di collaudo');await page.locator('[name=confirmed]').check();await submit('Registra controllo RCA');assert.match(await page.locator('tbody').innerText(),/Copertura presente/);await layouts('coverage');
        for(const format of ['Excel','PDF']){const pending=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta '+format,exact:true}).click();const result=await pending;assert.equal(await result.failure(),null);await result.saveAs(path.join(__dirname,'signing-coverage.'+(format==='Excel'?'xlsx':'pdf')));}
        await go(client+'/forms');await page.locator('[name=reason]').selectOption('initial');await submit('Applica filtri');assert.equal(await page.locator('tbody tr').count(),1);await layouts('filtered');
        report.flows.push('Controllo RCA manuale con prove multiple, filtri fascicoli ed esportazioni Excel/PDF; nessuna richiesta esterna eseguita.');
        await login('reader@example.test');await go(policyPackage);assert.equal(await page.getByRole('button',{name:'Registra file firmati'}).count(),0);await layouts('readonly');assert.equal((await page.request.get(base+'/settings/forms')).status(),403);await go(clientPackage);
        report.flows.push('Utente amministrazione legge fascicoli e allegati; configurazione e registrazioni protette dai permessi.');
        assert.deepEqual(report.errors,[]);assert.deepEqual(report.externalRequests,[]);assert.deepEqual(report.labelIssues,[]);report.ok=true;
    }catch(e){report.ok=false;report.failure=e.stack;if(page)await page.screenshot({path:path.join(__dirname,'signing-failure.png'),fullPage:true}).catch(()=>{});throw e;}
    finally{if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'signing-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
