const {chromium}=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {spawn}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),prior=JSON.parse(fs.readFileSync(path.join(__dirname,'claims-run.json'),'utf8'));
assert.equal(path.dirname(prior.db),__dirname);assert.match(path.basename(prior.db),/^ui-\d+\.sqlite$/);
const report={screens:[],exports:[],errors:[],external:[]};
(async()=>{
    const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base=`http://127.0.0.1:${port}`;
    const env={...process.env,APP_ENV:'testing',APP_URL:base,DB_CONNECTION:'sqlite',DB_DATABASE:prior.db,DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`claims_final_${Date.now()}`,MAIL_MAILER:'array',QUEUE_CONNECTION:'sync',WORKFLOW_QA:'0',QUOTE_EMAIL_QA:'0'};
    const server=spawn('C:/PHP 8.3.1/php.exe',['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:'ignore'});
    let browser;
    try {
        for(let i=0;i<50;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await new Promise(r=>setTimeout(r,200));}
        browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
        const context=await browser.newContext({viewport:{width:1440,height:1050}});await context.route('**/*',r=>{const u=r.request().url();if(u.startsWith(base+'/')||u.startsWith('blob:')||u.startsWith('data:'))return r.continue();report.external.push(u);return r.abort();});
        const page=await context.newPage();page.setDefaultTimeout(15000);page.on('pageerror',e=>report.errors.push(e.message));
        const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});};
        const go=async u=>{assert.equal((await page.goto(base+u)).status(),200);await ready();};
        const screen=async name=>{assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.screenshot({path:path.join(__dirname,`claims-${name}.png`),fullPage:true,animations:'disabled',timeout:10000});report.screens.push(name);};
        const exports=async(scope,name)=>{for(const format of ['Excel','PDF']){const waiting=page.waitForEvent('download');await scope.getByRole('button',{name:'Esporta '+format,exact:true}).click();const download=await waiting;assert.equal(await download.failure(),null);await download.saveAs(path.join(__dirname,`claims-${name}.${format==='PDF'?'pdf':'xlsx'}`));report.exports.push(name+' '+format);}};
        await go('/login');await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);await ready();
        await page.locator('#notifications-trigger').click();await page.locator('#notifications-panel').waitFor({state:'visible'});await page.getByText('Attività sinistro in arretrato',{exact:true}).waitFor({state:'visible'});await screen('notifications-desktop');await page.setViewportSize({width:390,height:844});await screen('notifications-mobile');await page.keyboard.press('Escape');
        await go('/claims/1');await page.locator('#claim-history').scrollIntoViewIfNeeded();await screen('history-mobile-compact');
        const history=page.locator('#claim-history');const width=await history.locator('table').evaluate(e=>e.getBoundingClientRect().width);assert.ok(width>330 && width<390);
        await history.getByText('Mostra dettagli',{exact:true}).first().click();await screen('history-mobile-details');
        await page.evaluate(()=>{document.querySelector('main').scrollTop=document.querySelector('main').scrollHeight;document.querySelector('.content')?.scrollTo(0,999999);});await screen('reopened-mobile-bottom');
        await page.setViewportSize({width:1440,height:1050});await exports(history,'history');await screen('history-desktop-final');
        await go('/claims?reminder=overdue');await exports(page.locator('main'),'list');await screen('list-desktop-final');
        await page.getByRole('button',{name:'Filtra tabella',exact:true}).click();await screen('table-filter-final');
        assert.deepEqual(report.errors,[]);assert.deepEqual(report.external,[]);report.ok=true;
    } catch(e) {report.ok=false;report.failure=e.stack;throw e;}
    finally {if(browser)await browser.close();server.kill();fs.writeFileSync(path.join(__dirname,'claims-layout-final.json'),JSON.stringify(report,null,2));}
    console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
