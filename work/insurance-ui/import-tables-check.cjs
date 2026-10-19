const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const ExcelJS = require('../../node_modules/exceljs');
const root = path.resolve(__dirname, '../..');
const php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV:'testing', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`), DB_URL:'', CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:`import_qa_${Date.now()}`, QUEUE_CONNECTION:'sync', MAIL_MAILER:'array' };
const report = { flows:[], layouts:[], javascriptErrors:[], failedResponses:[] };
const delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
(async()=>{
    const reservation=net.createServer(); await new Promise(r=>reservation.listen(0,'127.0.0.1',r)); const port=reservation.address().port; await new Promise(r=>reservation.close(r));
    const base=`http://127.0.0.1:${port}`; env.APP_URL=base;
    for (const script of ['prepare.php','prepare-tables.php']) execFileSync(php,[path.join(__dirname,script)],{cwd:root,env,windowsHide:true,stdio:'pipe'});
    const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser;
    try {
        for(let i=0;i<60;i++){try {if((await fetch(base+'/login')).ok)break;}catch{} await delay(250);}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context=await browser.newContext({viewport:{width:1440,height:1050},acceptDownloads:true});
        await context.route('**/*',route=>new URL(route.request().url()).origin===base ? route.continue() : route.abort());
        const page=await context.newPage(); page.setDefaultTimeout(20000);
        page.on('pageerror',e=>report.javascriptErrors.push(e.message));
        page.on('response',r=>{if(r.status()>=500) report.failedResponses.push({url:r.url(),status:r.status()});});
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await page.evaluate(()=>window.Alpine?.nextTick());await page.evaluate(()=>Promise.all(document.getAnimations().filter(a=>a.effect?.getTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{}))));};
        const goto=async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const check=label=>{report.flows.push(label);console.log('Verificato: '+label);};
        const layout=async name=>{assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' senza overflow');report.layouts.push({name,viewport:page.viewportSize()});};
        const download=async(label,file)=>{const [d]=await Promise.all([page.waitForEvent('download'),page.getByRole('button',{name:label,exact:true}).first().click()]);await d.saveAs(path.join(__dirname,file));};
        await goto('/login');
        const icon=await page.locator('link[rel=icon][sizes="32x32"]').getAttribute('href');
        const iconResponse=await context.request.get(icon);assert.equal(iconResponse.status(),200);assert.ok((await iconResponse.body()).length>100);
        assert.ok((await (await context.request.get(base+'/favicon.ico')).body()).length>100);
        await page.screenshot({path:path.join(__dirname,'import-login-desktop.png')});
        await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');
        await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);await ready();check('Favicon PNG e ICO non vuote e collegate al login');
        await goto('/clients');
        assert.equal(await page.locator('table tbody tr').count(),20);
        await page.getByRole('button',{name:'Filtra tabella',exact:true}).click();
        await page.locator('.table-filter-picker').getByRole('button',{name:'Contraente',exact:true}).click();
        await page.getByRole('textbox',{name:'Valore per Contraente',exact:true}).fill('Cliente DEMO');
        await page.getByRole('button',{name:'Applica filtri',exact:true}).click();
        try { await page.waitForFunction(()=>document.querySelector('.table-export-status')?.textContent.startsWith('520 risultati')); } catch(e) { console.error(await page.locator('.table-tools').innerText()); await page.screenshot({path:path.join(__dirname,'import-failure.png')}); throw e; }
        assert.equal(await page.locator('table tbody tr').count(),50);
        await layout('Filtri clienti desktop');await page.screenshot({path:path.join(__dirname,'import-filters-desktop.png')});
        await download('Esporta Excel','table-all-clients.xlsx');
        const all=new ExcelJS.Workbook();await all.xlsx.readFile(path.join(__dirname,'table-all-clients.xlsx'));assert.equal(all.getWorksheet('Dati').rowCount-5,520);
        assert.ok(all.getWorksheet('Dati').getRow(6).getCell(3).value.startsWith('000'));
        await page.locator('.table-filter-picker').getByRole('button',{name:'Stato',exact:true}).click();
        const second=page.locator('.table-filter-rule').nth(1);await second.getByRole('button',{name:'È uguale a',exact:true}).click();await second.getByRole('textbox',{name:'Valore per Stato',exact:true}).fill('Attivo');
        await page.getByRole('button',{name:'Applica filtri',exact:true}).click();await page.waitForFunction(()=>document.querySelector('.table-export-status')?.textContent.startsWith('260 risultati'));
        await download('Esporta Excel','table-filtered-clients.xlsx');
        const filtered=new ExcelJS.Workbook();await filtered.xlsx.readFile(path.join(__dirname,'table-filtered-clients.xlsx'));assert.equal(filtered.getWorksheet('Dati').rowCount-5,260);
        await download('Esporta PDF','table-filtered-clients.pdf');assert.ok(fs.readFileSync(path.join(__dirname,'table-filtered-clients.pdf')).subarray(0,5).toString()==='%PDF-');
        check('Filtri combinati ed export completo di 520/260 righe su più pagine; zeri iniziali conservati');
        await page.setViewportSize({width:390,height:844});await ready();await layout('Filtri clienti smartphone');await page.screenshot({path:path.join(__dirname,'import-filters-mobile.png')});
        await page.getByRole('button',{name:'Almeno un criterio',exact:true}).click();
        await page.getByRole('button',{name:'Applica filtri',exact:true}).click();
        try { await page.waitForFunction(()=>document.querySelector('.table-export-status')?.textContent.startsWith('521 risultati')); }
        catch(e) { console.error(await page.locator('.table-tools').innerText()); await page.screenshot({path:path.join(__dirname,'import-filter-or-failure.png')}); throw e; }
        await page.getByRole('button',{name:'Rimuovi filtro Stato',exact:true}).click();assert.equal(await page.locator('.table-filter-rule').count(),1);
        await page.getByRole('button',{name:'Azzera filtri',exact:true}).click();assert.equal(await page.locator('table tbody tr').count(),20);
        assert.equal(await page.locator('.table-filter-rule').count(),0);
        await page.setViewportSize({width:1440,height:1050});
        await goto('/suppliers/create');
        await page.getByLabel('Compagnia DEMO A',{exact:true}).check();await page.getByLabel('Compagnia DEMO B',{exact:true}).check();await page.locator('[name=name]').fill('Agenzia DEMO plurimandataria');
        await Promise.all([page.waitForURL('**/suppliers/*'),page.getByRole('button',{name:'Salva agenzia'}).click()]);await ready();
        assert.equal(await page.getByRole('link',{name:'Compagnia DEMO A',exact:true}).count(),1);assert.equal(await page.getByRole('link',{name:'Compagnia DEMO B',exact:true}).count(),1);
        await page.getByRole('link',{name:'Modifica agenzia',exact:true}).click();await ready();assert.equal(await page.locator('[type=checkbox]:checked').count(),2);
        await layout('Agenzia plurimandataria desktop');await page.screenshot({path:path.join(__dirname,'import-agency-desktop.png')});
        await page.setViewportSize({width:390,height:844});await ready();await layout('Agenzia plurimandataria smartphone');
        check('Salvataggio e modifica di un’unica agenzia con due compagnie');
        for(const section of ['overview','registry','branches','providers','issues','mapping']){
            await goto('/settings/insurance-import?section='+section);await layout('Importazione '+section+' smartphone');
            if(section!=='overview') assert.equal(await page.getByRole('button',{name:'Esporta Excel',exact:true}).count(),1);
        }
        await goto('/settings/insurance-import?section=branches');await page.setViewportSize({width:1440,height:1050});await ready();await layout('Catalogo rami desktop');await page.screenshot({path:path.join(__dirname,'import-catalog-desktop.png')});
        await download('Esporta PDF','table-catalog.pdf');
        const [review]=await Promise.all([page.waitForEvent('download'),page.getByRole('link',{name:'Scarica prospetto di verifica'}).click()]);await review.saveAs(path.join(__dirname,'import-review.xlsx'));
        check('Anteprima importazione e download del prospetto; esportazione PDF del catalogo');
        for(const url of ['/clients/1?tab=policies','/clients/1?tab=vehicles','/clients/1?tab=documents','/clients/1?tab=deadlines','/suppliers','/supplier-companies','/users']){
            await goto(url);
            const tables=await page.locator('table.t-table').count();assert.equal(await page.locator('.table-tools').count(),tables,url);
            await page.setViewportSize({width:390,height:844});await ready();await layout(url+' smartphone');await page.setViewportSize({width:1440,height:1050});
        }
        check('Controlli presenti nelle tabelle clienti, polizze, targhe, documenti, scadenze, fornitori e utenti');
        await goto('/clients/1?tab=policies');
        await page.evaluate(()=>{document.querySelector('table tbody tr td').textContent='=1+1';});
        await download('Esporta Excel','table-literal-text.xlsx');
        const literal=new ExcelJS.Workbook();await literal.xlsx.readFile(path.join(__dirname,'table-literal-text.xlsx'));
        assert.equal(literal.getWorksheet('Dati').getCell('A6').value,'=1+1');
        assert.equal(literal.getWorksheet('Dati').getCell('A6').type,ExcelJS.ValueType.String);
        check('I testi esportati non diventano formule Excel');
        assert.deepEqual(report.javascriptErrors,[]);assert.deepEqual(report.failedResponses,[]);report.ok=true;
    } finally { if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'import-tables-report.json'),JSON.stringify(report,null,2)); }
})().catch(e=>{console.error(e);process.exitCode=1;});
