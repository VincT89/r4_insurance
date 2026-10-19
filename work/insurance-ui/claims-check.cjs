const { chromium }=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe';
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`claims_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',COMMISSION_QA:'1',CLAIMS_QA:'1',SIGNING_QA:'0',QUOTE_EMAIL_QA:'0',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report={flows:[],layouts:[],errors:[],externalRequests:[],labelIssues:[],exports:[]};const delay=ms=>new Promise(r=>setTimeout(r,ms));
const date=offset=>{const d=new Date();d.setDate(d.getDate()+offset);return d.toLocaleDateString('sv-SE',{timeZone:'Europe/Rome'});};
(async()=>{
    const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base=`http://127.0.0.1:${port}`;env.APP_URL=base;
    const seed=JSON.parse(execFileSync(php,[path.join(__dirname,'prepare.php')],{cwd:root,env,windowsHide:true,encoding:'utf8'}));
    const serverLog=fs.openSync(path.join(__dirname,'claims-server.log'),'w');
    const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:['ignore',serverLog,serverLog]});
    fs.writeFileSync(path.join(__dirname,'claims-run.json'),JSON.stringify({pid:process.pid,server:server.pid,port,db:env.DB_DATABASE}));
    let browser,page;
    try {
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});console.log('Ready '+new URL(page.url()).pathname);};
        const go=async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const submit=async(label,scope=page)=>{await Promise.all([page.waitForNavigation(),scope.getByRole('button',{name:label,exact:true}).click()]);await ready();assert.equal(await page.locator('main .alert-error, main .alert-danger').count(),0);};
        const login=async email=>{
            const context=await browser.newContext({viewport:{width:1440,height:1050}});
            await context.route('**/*',r=>{const u=r.request().url();if(u.startsWith(base+'/')||u.startsWith('blob:')||u.startsWith('data:'))return r.continue();report.externalRequests.push(u);return r.abort();});
            page=await context.newPage();page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(20000);page.on('pageerror',e=>{report.errors.push(e.message);console.log('Browser error: '+e.message);});page.on('response',r=>{if(r.status()>=500){report.errors.push(`${r.status()} ${r.url()}`);console.log('HTTP error: '+r.status()+' '+r.url());}});page.on('request',r=>{if(r.method()==='POST')console.log('POST '+r.url());});
            await go('/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('password-testing-only');await submit('Entra');
        };
        const screenshot=async(name,bottom=false)=>{
            await page.keyboard.press('Escape');await page.evaluate(async bottom=>{await window.Alpine?.nextTick();document.activeElement?.blur();for(const e of document.querySelectorAll('main,.content'))e.scrollTo({top:bottom?e.scrollHeight:0,behavior:'instant'});window.scrollTo(0,bottom?document.body.scrollHeight:0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));},bottom);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`overflow ${name}`);
            const missing=await page.locator('main input:not([type=hidden]),main select,main textarea').evaluateAll(es=>es.filter(e=>e.offsetParent&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.name));if(missing.length)report.labelIssues.push({name,missing});
            await page.screenshot({path:path.join(__dirname,`claims-${name}.png`),fullPage:true,timeout:10000,animations:'disabled'});report.layouts.push(name);console.log('Layout '+name);
        };
        const layouts=async name=>{await page.setViewportSize({width:1440,height:1050});await screenshot(name+'-desktop');await screenshot(name+'-desktop-bottom',true);await page.setViewportSize({width:390,height:844});await screenshot(name+'-mobile');await screenshot(name+'-mobile-bottom',true);await page.setViewportSize({width:1440,height:1050});};
        const exportTable=async(scope,label)=>{
            for(const format of ['Excel','PDF']){const waiting=page.waitForEvent('download');await scope.getByRole('button',{name:'Esporta '+format,exact:true}).click();const download=await waiting;assert.equal(await download.failure(),null);const file=path.join(__dirname,'claims-'+label+'.'+(format==='Excel'?'xlsx':'pdf'));await download.saveAs(file);report.exports.push(file);}
        };
        await login('operator@example.test');await go('/claims');await layouts('empty');await page.getByRole('link',{name:'Nuovo sinistro',exact:true}).click();await ready();
        await page.locator('input[name=q]').fill('Officine Valdoro');await submit('Cerca polizza');await layouts('picker');await page.getByRole('link',{name:'Seleziona polizza',exact:true}).last().click();await ready();
        await page.locator('[name=number]').fill('SN-2026-1042');await page.locator('[name=event_on]').fill(date(-1));await page.locator('[name=reported_on]').fill(date(0));await page.locator('[name=description]').fill('Urto al veicolo parcheggiato. Verbale e fotografie da acquisire.');await page.locator('[name=activity_label]').fill('Richiedere il verbale e le fotografie');await page.locator('[name=activity_due_on]').fill(date(3));
        const pdf=Buffer.from('%PDF-1.4\n1 0 obj <</Type/Catalog>> endobj\ntrailer <</Root 1 0 R>>\n%%EOF');
        await page.locator('input[type=file]').setInputFiles([{name:'segnalazione.pdf',mimeType:'application/pdf',buffer:pdf},{name:'constatazione.pdf',mimeType:'application/pdf',buffer:pdf}]);await layouts('create');await submit('Apri sinistro');
        const claimUrl=new URL(page.url()).pathname;assert.match(claimUrl,/^\/claims\/\d+$/);assert.match(await page.locator('main').innerText(),/SN-2026-1042/);await layouts('show');
        report.flows.push('Operatore: ricerca polizza, apertura con due allegati, prossima attività e collegamenti a cliente/polizza.');
        await page.getByRole('link',{name:'Gestisci attività',exact:true}).click();await ready();await page.locator('[name=operation]').selectOption('activity_rescheduled');await page.locator('[name=due_on]').fill(date(0));await page.locator('[name=reason]').fill('Il cliente passa oggi in agenzia');await layouts('reschedule');await submit('Conferma attività');
        assert.match(await page.locator('main').innerText(),/Da svolgere oggi/);
        await page.getByText('Aggiungi nota o allegati',{exact:true}).click();await page.locator('textarea[name=note]').fill('Ricevuta una prima copia della documentazione.');await page.locator('input[type=file]').setInputFiles([{name:'integrazione.pdf',mimeType:'application/pdf',buffer:pdf},{name:'verbale.pdf',mimeType:'application/pdf',buffer:pdf}]);await submit('Registra nota e allegati');
        await page.getByRole('link',{name:'Modifica dati',exact:true}).click();await ready();await page.locator('[name=description]').fill('Urto al veicolo parcheggiato. Documentazione ricevuta dal contraente.');await page.locator('[name=reason]').fill('Aggiornamento dopo ricezione documenti');await layouts('edit');await submit('Salva modifiche');
        await go('/dashboard');assert.match(await page.locator('main').innerText(),/Richiedere il verbale/);await layouts('dashboard');
        await go('/claims?reminder=today');assert.match(await page.locator('tbody').innerText(),/SN-2026-1042/);await page.locator('select[name=branch]').selectOption(String(seed.branch_id));await ready();await submit('Applica filtri');await layouts('filters');await exportTable(page.locator('main'),'list');
        await go(claimUrl);await exportTable(page.locator('#claim-history'),'history');
        const doc=page.locator('#claim-files').getByRole('link',{name:'segnalazione.pdf',exact:true});const href=await doc.getAttribute('href');const response=await page.request.get(href);assert.equal(response.status(),200);assert.equal(response.headers()['x-content-type-options'],'nosniff');
        await page.getByRole('link',{name:'Gestisci attività',exact:true}).click();await ready();await page.locator('[name=reason]').fill('Verbale e fotografie acquisiti');await submit('Conferma attività');assert.match(await page.locator('#activities').innerText(),/Completata/);
        await page.getByText('Cambia stato',{exact:true}).click();await page.locator('#claim-status').selectOption('closed_unpaid');await page.locator('#claim-status-reason').fill('Pratica chiusa senza liquidazione, come da comunicazione ricevuta.');await submit('Conferma stato');assert.equal(await page.getByRole('link',{name:'Modifica dati',exact:true}).count(),0);await layouts('closed-operator');
        report.flows.push('Riprogrammazione, avviso del giorno, integrazione multipla, modifica motivata, completamento e chiusura; export Excel/PDF elenco e storico.');
        await login('ui-test@example.test');await go(claimUrl);await page.getByText('Cambia stato',{exact:true}).click();await page.locator('#claim-status').selectOption('open');await page.locator('#claim-status-reason').fill('Documentazione integrativa da trattare');await submit('Conferma stato');
        await page.getByText('Programma attività',{exact:true}).click();await page.locator('#new-activity-label').fill('Contattare il perito');await page.locator('#new-activity-due').fill(date(-2));await submit('Salva attività');await layouts('reopened');
        await go('/claims?reminder=overdue');assert.match(await page.locator('tbody').innerText(),/Contattare il perito/);await layouts('overdue');
        await go('/audit-logs?auditable_type=claims&action=claims');assert.match(await page.locator('main').innerText(),/Sinistri/);await layouts('audit');
        await go(`/clients/${seed.client_id}?tab=documents`);assert.equal(await page.getByRole('link',{name:/Sinistro #/}).count(),4);
        report.flows.push('Admin: riapertura motivata, arretrato persistente e registro attività; quattro allegati nella scheda cliente.');
        await login('reader@example.test');await go(claimUrl);assert.equal(await page.getByRole('link',{name:'Modifica dati',exact:true}).count(),0);await layouts('readonly');assert.equal((await page.request.get(base+'/claims/create')).status(),403);assert.equal((await page.request.get(href)).status(),200);
        assert.deepEqual(report.errors,[]);assert.deepEqual(report.externalRequests,[]);assert.deepEqual(report.labelIssues,[]);report.ok=true;
    } catch(e) {report.ok=false;report.failure=e.stack;console.log('Failure: '+e.stack);if(page)await page.screenshot({path:path.join(__dirname,'claims-failure.png'),fullPage:true,timeout:5000}).catch(()=>{});throw e;}
    finally {if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'claims-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
