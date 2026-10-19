const { chromium } = require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { jsPDF } = require('../../node_modules/jspdf');
const root = path.resolve(__dirname, '../..'), php = 'C:/PHP 8.3.1/php.exe';
const env = { ...process.env, APP_ENV:'testing', APP_DEBUG:'false', DB_CONNECTION:'sqlite', DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`), DB_URL:'', CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:`workspace_qa_${Date.now()}`, QUEUE_CONNECTION:'sync', MAIL_MAILER:'array', POLICY_IMPORT_QA:'0', EQUIVALENT_IMPORT_QA:'0', CATALOG_MERGE_QA:'0', WORKSPACE_QA:'1' };
const report = {flows:[],layouts:[],errors:[],externalRequests:[]};
const delay = ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
    const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));
    const base=`http://127.0.0.1:${port}`;env.APP_URL=base;
    const seeded=JSON.parse(execFileSync(php,[path.join(__dirname,'prepare.php')],{cwd:root,env,windowsHide:true,encoding:'utf8'}));
    const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser,page;
    try{
        for(let i=0;i<60;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await delay(250);}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context=await browser.newContext({viewport:{width:1440,height:1050}});
        await context.route('**/*',route=>{const url=route.request().url();if(url.startsWith(base+'/')||url.startsWith('blob:')||url.startsWith('data:'))return route.continue();report.externalRequests.push(url);return route.abort();});
        page=await context.newPage();page.setDefaultTimeout(20000);
        page.on('pageerror',e=>report.errors.push(e.message));page.on('response',r=>{if(r.status()>=400)report.errors.push(`${r.status()} ${r.url()}`);});
        const settled=async()=>page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));await Promise.all(document.getAnimations().filter(a=>a.effect?.getComputedTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})));});
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await settled();};
        const go=async url=>{assert.equal((await page.goto(base+url)).status(),200,url);await ready();};
        const shot=async name=>{await page.keyboard.press('Escape');await settled();await page.evaluate(()=>{document.activeElement?.blur();for(const el of document.querySelectorAll('main, main *, .content, .main'))if(el.scrollHeight>el.clientHeight)el.scrollTo({top:0,behavior:'instant'});window.scrollTo({top:0,behavior:'instant'});});await settled();assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`overflow ${name}`);await page.screenshot({path:path.join(__dirname,`workspace-${name}.png`),fullPage:true});report.layouts.push(name);};
        await go('/login');await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');
        await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);await ready();
        await shot('dashboard-desktop');
        await go('/policies/create');
        await page.locator('[data-client-search]').fill('Azienda dimostrativa');await page.locator('.client-picker-result').first().click();
        await page.waitForFunction(()=>!!document.querySelector('[name=client_id]').value);
        assert.ok((await page.locator('[name=vehicle_id]').innerText()).includes('TEST01'));
        await page.locator('[name=category]').selectOption('RC auto');await page.locator('[name=subcategory]').selectOption('Autovetture');
        await page.locator('[name=company]').selectOption('Compagnia dimostrativa');await page.locator('[name=supplier_agency_id]').selectOption(String(seeded.agency_id));
        await page.locator('[name=number]').fill('VISUAL-POL-001');await page.locator('[name=starts_on]').fill('2026-10-16');await page.locator('[name=expires_on]').fill('2027-10-16');
        await page.locator('[name=merit_class_cu]').selectOption('3');await page.locator('[name=merit_class_internal]').fill('3A');
        await page.locator('[name=payment_frequency]').selectOption('Semestrale');
        const pdf=new jsPDF();pdf.text('DOCUMENTO DIMOSTRATIVO - SCADENZA 31/12/2030',10,20);const pdfBytes=Buffer.from(pdf.output('arraybuffer'));
        await page.locator('[name="documents[]"]').setInputFiles([{name:'polizza-demo.pdf',mimeType:'application/pdf',buffer:pdfBytes},{name:'allegato-demo.pdf',mimeType:'application/pdf',buffer:pdfBytes}]);
        const height=await page.locator('[name=number]').evaluate(el=>el.getBoundingClientRect().height);assert.ok(height>=28&&height<=36,`input height ${height}`);
        await shot('polizza-desktop');await page.setViewportSize({width:390,height:844});await shot('polizza-mobile');await page.setViewportSize({width:1440,height:1050});
        await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Salva polizza',exact:true}).click()]);await ready();assert.match(await page.locator('main').innerText(),/VISUAL-POL-001/);assert.match(await page.locator('main').innerText(),/polizza-demo.pdf/);
        report.flows.push('Nuova polizza: ricerca cliente, collegamenti, classi di merito e due allegati PDF');
        await go('/policies/create');await page.locator('[name=number]').fill('DA-CONSERVARE');await page.locator('[data-new-client]').click();await page.locator('[data-quick-field=name]').fill('Cliente rapido dimostrativo');await page.locator('[data-save-client]').click();await page.waitForFunction(()=>!!document.querySelector('[name=client_id]').value);assert.equal(await page.locator('[name=number]').inputValue(),'DA-CONSERVARE');report.flows.push('Creazione rapida cliente senza perdita dei dati della polizza');
        await go(`/clients/${seeded.client_id}/documents/create`);await page.locator('[name=document_category]').selectOption('identity');
        const png=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=1500;c.height=450;const x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.fillStyle='#000';x.font='bold 52px Arial';x.fillText('DOCUMENTO DIMOSTRATIVO',60,100);x.fillText('SCADENZA 15/10/2025',60,230);return c.toDataURL('image/png').split(',')[1];});
        await page.locator('[name="files[]"]').setInputFiles([{name:'carta-demo.pdf',mimeType:'application/pdf',buffer:pdfBytes},{name:'patente-demo.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')}]);
        const rows=page.locator('.document-file-row');assert.equal(await rows.count(),2);
        await rows.nth(0).locator('select').selectOption('electronic');await rows.nth(1).locator('select').selectOption('driving_licence');
        const manifest=JSON.parse(fs.readFileSync(path.join(root,'public/build/manifest.json'),'utf8'));
        const ocrAsset=Object.values(manifest).find(item=>item.file.includes('document-ocr-')).file;
        const diagnostic=await page.evaluate(async asset=>{try{const module=await import('/build/'+asset);return await module.readDocumentExpiry(document.querySelector('[name="files[]"]').files[0],document.querySelector('[data-document-expiry]').dataset.ocrBase,()=>{});}catch(e){return {error:String(e),stack:e.stack};}},ocrAsset);
        assert.ok(Array.isArray(diagnostic),JSON.stringify(diagnostic));
        await rows.nth(0).getByRole('button',{name:'Leggi scadenza dal documento'}).click();await rows.nth(0).getByRole('button',{name:'Conferma la data rilevata'}).click({timeout:60000});
        assert.equal(await rows.nth(0).locator('input[type=date]').inputValue(),'2030-12-31');assert.equal(await rows.nth(0).locator('[data-expiry-status]').innerText(),'Documento valido');
        await rows.nth(1).getByRole('button',{name:'Leggi scadenza dal documento'}).click();await rows.nth(1).getByRole('button',{name:'Conferma la data rilevata'}).click({timeout:60000});
        assert.equal(await rows.nth(1).locator('input[type=date]').inputValue(),'2025-10-15');assert.match(await rows.nth(1).locator('[data-expiry-status]').innerText(),/Documento non valido/);
        await shot('documenti-desktop');await page.setViewportSize({width:390,height:844});await shot('documenti-mobile');await page.setViewportSize({width:1440,height:1050});
        await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Carica documento',exact:true}).click()]);await ready();assert.match(await page.locator('main').innerText(),/Carta d’identità elettronica/);assert.match(await page.locator('main').innerText(),/Patente/);report.flows.push('Lettura locale PDF e immagine, conferma separata delle date, documenti valido e scaduto');
        await go('/agency-communications?period=15');await page.locator('input[name="policies[]"]').check({trial:false}).catch(async()=>page.locator('input[name="policies[]"]').first().check());await page.locator('[name=reviewed]').check();
        await shot('comunicazioni-desktop');const download=page.waitForEvent('download');await page.getByRole('button',{name:'Scarica email cumulativa'}).click();const eml=await download;const stream=await eml.createReadStream();const chunks=[];for await(const chunk of stream)chunks.push(chunk);assert.match(Buffer.concat(chunks).toString(),/X-Unsent: 1/);report.flows.push('Filtri comunicazioni e download bozza cumulativa dopo controllo');
        for(const [name,url] of [['clienti','/clients/create'],['agenzie','/suppliers/create'],['report','/reports'],['scadenze','/deadlines']]){
            await go(url);await shot(name+'-desktop');await page.setViewportSize({width:390,height:844});await shot(name+'-mobile');await page.setViewportSize({width:1440,height:1050});
        }
        await go('/reports');const excel=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta Excel',exact:true}).click();await excel;
        const pdfDownload=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta PDF',exact:true}).click();await pdfDownload;report.flows.push('Esportazione report Excel e PDF');
        assert.deepEqual(report.externalRequests,[]);assert.deepEqual(report.errors,[]);report.ok=true;
    }catch(error){report.ok=false;report.failure=error.stack;if(page)await page.screenshot({path:path.join(__dirname,'workspace-failure.png'),fullPage:true}).catch(()=>{});throw error;}
    finally{if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'workspace-ui-report.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
