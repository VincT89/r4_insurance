const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const ExcelJS = require('../../node_modules/exceljs');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV:'testing', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`), DB_URL:'', CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:`policy_qa_${Date.now()}`, QUEUE_CONNECTION:'sync', MAIL_MAILER:'array', POLICY_IMPORT_QA:'1', CATALOG_MERGE_QA:'0' };
const report = { flows:[], layouts:[], errors:[] };
const delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
(async()=>{
    const reservation=net.createServer(); await new Promise(r=>reservation.listen(0,'127.0.0.1',r)); const port=reservation.address().port; await new Promise(r=>reservation.close(r));
    const base=`http://127.0.0.1:${port}`; env.APP_URL=base;
    const seeded=JSON.parse(execFileSync(php,[path.join(__dirname,'prepare.php')],{cwd:root,env,windowsHide:true,encoding:'utf8'}));
    const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser, page;
    try {
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context=await browser.newContext({viewport:{width:1440,height:1050},acceptDownloads:true});
        await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
        page=await context.newPage(); page.setDefaultTimeout(15000);
        page.on('pageerror',e=>report.errors.push(e.message));page.on('response',r=>{if(r.status()>=500)report.errors.push(`${r.status()} ${r.url()}`);});
        const settled=async()=>{await page.evaluate(async()=>{await window.Alpine?.nextTick();await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));});};
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await settled();};
        const goto=async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const submit=async name=>{await Promise.all([page.waitForNavigation(),page.getByRole('button',{name,exact:true}).click()]);await ready();};
        const layout=async(label,mobile=false)=>{await page.setViewportSize(mobile?{width:390,height:844}:{width:1440,height:1050});if(mobile)await page.keyboard.press('Escape');await settled();assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),label);report.layouts.push(label);};
        await goto('/login');await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');await submit('Entra');
        await goto('/settings/insurance-import?section=policies&result=imported');assert.equal(await page.locator('table.t-table tbody tr').count(),2);
        await page.locator('tr').filter({hasText:'000012'}).getByRole('link',{name:'Apri polizza',exact:true}).click();await ready();const policyPath=new URL(page.url()).pathname;
        assert.ok((await page.locator('main').innerText()).includes('Da verificare'));assert.ok((await page.locator('main').innerText()).includes('Archivio'));
        for(const name of ['AGENZIA DIMOSTRATIVA','COMPAGNIA DIMOSTRATIVA']){await page.getByRole('link',{name,exact:true}).click();await ready();assert.ok((await page.getByRole('heading',{level:1}).innerText()).includes(name));await goto(policyPath);}
        await layout('Polizza importata desktop');await page.screenshot({path:path.join(__dirname,'imported-policy-desktop.png')});await layout('Polizza importata mobile',true);await page.screenshot({path:path.join(__dirname,'imported-policy-mobile.png')});
        await page.getByRole('link',{name:'Modifica polizza',exact:true}).click();await ready();assert.equal(await page.locator('[name=supplier_agency_id] option:checked').innerText(),'AGENZIA DIMOSTRATIVA');
        await page.locator('[name=company]').fill('Compagnia non presente dimostrativa');assert.equal(await page.locator('[name=supplier_agency_id]').inputValue(),'');
        await page.locator('[name=company]').fill('COMPAGNIA DIMOSTRATIVA');await page.locator('[name=supplier_agency_id]').selectOption({label:'AGENZIA DIMOSTRATIVA'});await page.locator('[name=product]').fill('Prodotto dimostrativo verificato');await submit('Salva polizza');
        assert.ok((await page.locator('main').innerText()).includes('Prodotto dimostrativo verificato'));assert.ok((await page.locator('main').innerText()).includes('Da verificare'));
        report.flows.push('Polizza importata con stato incerto, origini Excel, collegamenti compagnia/agenzia; modifica con agenzie dipendenti dalla compagnia');
        await goto(`/clients/${seeded.client_id}?tab=policies&category=RC%20auto&subcategory=AUTO`);assert.ok((await page.locator('table.t-table').innerText()).includes('AGENZIA DIMOSTRATIVA'));assert.ok((await page.locator('table.t-table').innerText()).includes('Prodotto dimostrativo verificato'));
        await goto('/settings/insurance-import?section=policies&result=imported');await page.getByRole('button',{name:'Filtra tabella',exact:true}).click();await page.locator('.table-filter-picker').getByRole('button',{name:'Numero polizza',exact:true}).click();
        await page.getByRole('textbox',{name:'Valore per Numero polizza',exact:true}).fill('000012');await page.getByRole('button',{name:'Applica filtri',exact:true}).click();await page.waitForFunction(()=>document.querySelector('.table-export-status')?.textContent.startsWith('1 risultato'));
        const [excel]=await Promise.all([page.waitForEvent('download'),page.getByRole('button',{name:'Esporta Excel',exact:true}).click()]);await excel.saveAs(path.join(__dirname,'policy-import-filtered.xlsx'));
        const workbook=new ExcelJS.Workbook();await workbook.xlsx.readFile(path.join(__dirname,'policy-import-filtered.xlsx'));assert.equal(workbook.getWorksheet('Dati').getCell('A6').value,'000012');assert.equal(workbook.getWorksheet('Dati').getCell('F6').value,'Da verificare');
        const [pdf]=await Promise.all([page.waitForEvent('download'),page.getByRole('button',{name:'Esporta PDF',exact:true}).click()]);await pdf.saveAs(path.join(__dirname,'policy-import-filtered.pdf'));assert.equal(fs.readFileSync(path.join(__dirname,'policy-import-filtered.pdf')).subarray(0,5).toString(),'%PDF-');
        await layout('Esito polizze desktop');await page.screenshot({path:path.join(__dirname,'policy-import-results-desktop.png')});await layout('Esito polizze mobile',true);
        report.flows.push('Tab polizze ramo/sottoramo, filtro per numero, Excel con zeri iniziali conservati e PDF filtrato');
        await goto('/settings/insurance-import?section=policies&result=held&search=REPEAT-QA');assert.equal(await page.locator('table.t-table tbody tr').count(),1);assert.ok((await page.locator('main').innerText()).includes('rinnovi e storico'));
        await page.getByRole('link',{name:'Dati originali',exact:true}).click();await ready();assert.ok((await page.locator('main').innerText()).includes('31/12/2020'));assert.ok((await page.locator('main').innerText()).includes('31/12/2021'));assert.equal(await page.getByRole('link',{name:'Apri polizza',exact:true}).count(),0);
        await layout('Righe sospese desktop');await page.screenshot({path:path.join(__dirname,'policy-import-source-desktop.png')});await layout('Righe sospese mobile',true);
        await goto('/deadlines?period=all&search=000012');assert.equal(await page.locator('table.t-table tbody tr').count(),1);assert.ok((await page.locator('table.t-table').innerText()).includes('Stato polizza da verificare'));assert.equal(await page.locator('table.t-table .client-overdue').count(),0);
        await layout('Scadenze con stato incerto desktop');await layout('Scadenze con stato incerto mobile',true);
        report.flows.push('Gruppi sospesi consultabili senza scegliere una riga; scadenze originali distinte dallo stato della copertura');
        assert.deepEqual(report.errors,[]);report.ok=true;
    }catch(error){report.ok=false;report.failure=error.stack;if(page){report.page=page.url();report.text=(await page.locator('main').innerText().catch(()=>'' )).slice(0,3000);await page.screenshot({path:path.join(__dirname,'policy-import-failure.png')}).catch(()=>{});}throw error;}
    finally{if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'policy-import-ui-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
