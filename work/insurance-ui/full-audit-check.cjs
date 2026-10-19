const {chromium}=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe',out=path.join(root,'work/full-audit-2026-10-18');
fs.mkdirSync(out,{recursive:true});
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`full_audit_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',COMMISSION_QA:'1',CLAIMS_QA:'1',SIGNING_QA:'1',QUOTE_EMAIL_QA:'1',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report={started:new Date().toISOString(),pages:[],links:[],interactions:[],errors:[],external:[],failures:[],exports:[]};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base=`http://127.0.0.1:${port}`;env.APP_URL=base;
 const runSeed=file=>{const data=execFileSync(php,[path.join(__dirname,file)],{cwd:root,env,windowsHide:true,encoding:'utf8'});try{return JSON.parse(data);}catch{throw new Error(file+': '+data);}};
 const seed={...runSeed('prepare.php'),...runSeed('full-audit-fixtures.php')};
 const log=fs.openSync(path.join(out,'server.log'),'w');const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:['ignore',log,log]});
 let browser,page;
 try{
  for(let i=0;i<100;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await sleep(150);}
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'it-IT',timezoneId:'Europe/Rome',reducedMotion:'reduce'});
  await context.route('**/*',r=>{const u=r.request().url();if(u.startsWith(base+'/')||u.startsWith('blob:')||u.startsWith('data:'))return r.continue();report.external.push(u);return r.abort();});
  page=await context.newPage();page.setDefaultTimeout(12000);page.on('pageerror',e=>report.errors.push({url:page.url().replace(base,''),error:e.message}));
  page.on('response',r=>{if(r.status()>=500)report.errors.push({url:r.url().replace(base,''),status:r.status()});});
  const ready=async()=>{await page.waitForLoadState('load');await page.waitForFunction(()=>[...document.querySelectorAll('[x-data]')].every(e=>e._x_dataStack?.length));await page.evaluate(async()=>{await window.Alpine?.nextTick();await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});};
  const go=async url=>{const response=await page.goto(base+url);if(response.status()!==200)throw new Error(`HTTP ${response.status()} ${url}`);await ready();};
  const links=new Set();
  const inspect=async(name,width,bottom=false)=>{
   await page.setViewportSize({width,height:width<500?800:1000});await page.keyboard.press('Escape');
   await page.evaluate(async bottom=>{for(const e of document.querySelectorAll('main,.content'))e.scrollTo({top:bottom?e.scrollHeight:0,behavior:'instant'});document.activeElement?.blur();await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));},bottom);
   const result=await page.evaluate(()=>{
    const visible=e=>!!e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';
    const main=document.querySelector('main')||document.body;
    const fields=[...main.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(visible);
    const labelIssues=fields.filter(e=>!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>({tag:e.tagName,name:e.name,type:e.type}));
    const ids=[...document.querySelectorAll('[id]')].map(e=>e.id);const duplicateIds=[...new Set(ids.filter((id,i)=>ids.indexOf(id)!==i))];
    const controls=[...main.querySelectorAll('input:not([type=hidden]),select,textarea,button,a.btn')].filter(visible).map(e=>({tag:e.tagName,name:e.name||e.textContent.trim().slice(0,55),width:Math.round(e.getBoundingClientRect().width),height:Math.round(e.getBoundingClientRect().height),font:parseFloat(getComputedStyle(e).fontSize)}));
    const uploads=[...main.querySelectorAll('input[type=file]')].map(e=>({name:e.name,multiple:e.multiple,accept:e.accept}));
    const tables=[...main.querySelectorAll('table')].map(e=>({class:e.className,headers:[...e.querySelectorAll('thead th')].map(t=>t.textContent.trim()),rows:e.tBodies[0]?.rows.length||0,scroll:e.parentElement.scrollWidth>e.parentElement.clientWidth+1,tools:!!(e.closest('.panel')||e.parentElement).querySelector('[data-table-tools]')}));
    const overflow=[...main.querySelectorAll('*')].filter(visible).filter(e=>{const r=e.getBoundingClientRect();return r.left< -1||r.right>innerWidth+1;}).slice(0,20).map(e=>({tag:e.tagName,class:e.className,text:e.textContent.trim().slice(0,60)}));
    const rgb=s=>(s.match(/[\d.]+/g)||[]).map(Number);const lum=a=>{const v=a.slice(0,3).map(n=>{n/=255;return n<=.04045?n/12.92:((n+.055)/1.055)**2.4;});return .2126*v[0]+.7152*v[1]+.0722*v[2];};
    const contrast=[];for(const e of [...main.querySelectorAll('label,.u-text-meta,.form-label,button.btn,a.btn,th')].filter(visible)){
     if(!e.textContent.trim())continue;const s=getComputedStyle(e),fg=rgb(s.color);let bg=[255,255,255];for(let p=e;p;p=p.parentElement){const b=rgb(getComputedStyle(p).backgroundColor);if(b.length===3||b[3]===1){bg=b;break;}}
     const ratio=(Math.max(lum(fg),lum(bg))+.05)/(Math.min(lum(fg),lum(bg))+.05);if(ratio<4.5&&parseFloat(s.fontSize)<18)contrast.push({text:e.textContent.trim().slice(0,80),ratio:+ratio.toFixed(2),color:s.color,background:bg.slice(0,3),font:s.fontSize});
    }
    return {url:location.pathname+location.search,h1:[...document.querySelectorAll('h1')].map(e=>e.textContent.trim()),pageOverflow:document.documentElement.scrollWidth>innerWidth+1,labelIssues,duplicateIds,controls,uploads,tables,overflowCandidates:overflow,contrastCandidates:contrast.slice(0,30),links:[...document.querySelectorAll('a[href]')].filter(visible).map(a=>a.getAttribute('href')).filter(Boolean),bodyText:main.innerText.slice(0,25000)};
   });
   for(const href of result.links){try{const u=new URL(href,base);if(u.origin===base&&!u.hash&&!u.pathname.startsWith('/attachments/')&&!u.pathname.includes('/preview'))links.add(u.pathname+u.search);}catch{}}
   result.name=name;result.width=width;result.bottom=bottom;result.screenshot=`${name}-${width}${bottom?'-bottom':''}.png`;delete result.links;
   await page.screenshot({path:path.join(out,result.screenshot),fullPage:true,animations:'disabled'});report.pages.push(result);console.log(`Checked ${name} ${width}${bottom?' bottom':''}`);
  };
  await go('/login');await inspect('login',1440);await inspect('login',360);
  await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);await ready();
  const c=seed.client_id,p=seed.policy_id,a=seed.agency_id;
  const pages=[['dashboard','/dashboard'],['clients','/clients'],['client-create','/clients/create'],['client-edit',`/clients/${c}/edit`],...['policies','registry','network','vehicles','assets','documents','deadlines','history','consents'].map(t=>['client-'+t,`/clients/${c}?tab=${t}`]),['vehicle-create',`/clients/${c}/vehicles/create`],['contact-create',`/clients/${c}/records/contacts/create`],['asset-create',`/clients/${c}/records/assets/create`],['consent-create',`/clients/${c}/records/consents/create`],['document-create',`/clients/${c}/documents/create`],['policies','/policies'],['policy-create','/policies/create'],['policy-show',`/clients/${c}/policies/${p}`],['policy-edit',`/clients/${c}/policies/${p}/edit`],['quotes','/quotes'],['quote-create','/quotes/create'],['quote-show',`/quotes/${seed.case_id}`],['quote-email',`/quotes/${seed.case_id}/emails/create?company=${seed.company_id}`],['titles','/titles'],['title-show',`/titles/${seed.title_id}`],['title-create',`/policies/${p}/titles/create`],['renewal',`/policies/${p}/titles/renewal`],['collections','/collections'],['claims','/claims'],['claim-create',`/claims/create?policy=${p}`],['agencies','/suppliers'],['agency-create','/suppliers/create'],['agency-show',`/suppliers/${a}`],['agency-edit',`/suppliers/${a}/edit`],['companies','/supplier-companies'],['company-create','/supplier-companies/create'],['reports','/reports'],['deadlines','/deadlines'],['communications','/agency-communications'],['communications-collections','/agency-communications/collections'],['communications-history','/agency-communications/history'],['forms',`/clients/${c}/forms`],['forms-create',`/clients/${c}/forms/create?policy=${p}`],['rca',`/clients/${c}/policies/${p}/coverage-checks`],['templates','/settings/forms'],['template-create','/settings/forms/create'],['catalog','/settings/insurance-catalog'],['users','/users'],['user-create','/users/create'],['user-edit',`/users/${seed.operator_id}/edit`],['notes','/daily-notes'],['profile','/profile'],['appearance','/settings/appearance'],['email-settings','/settings/email'],['audit','/audit-logs']];
  for(const [name,url] of pages){try{await go(url);for(const width of [1440,768,360])await inspect(name,width);if(['policy-create','policy-show','client-create','quote-show','title-show','claim-create','appearance'].includes(name))await inspect(name,360,true);}catch(e){report.failures.push({name,url,error:e.message});console.log('Page failure '+name+' '+e.message);}}
  await page.setViewportSize({width:1440,height:1000});await go('/clients');
  for(const format of ['Excel','PDF']){try{const wait=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta '+format,exact:true}).first().click();const d=await wait;const file='clients-all.'+(format==='Excel'?'xlsx':'pdf');await d.saveAs(path.join(out,file));report.exports.push(file);}catch(e){report.failures.push({name:'export-'+format,error:e.message});}}
  await page.getByRole('button',{name:'Filtra tabella',exact:true}).first().click();const filter=page.locator('.table-filter-panel').first();const choices=await filter.locator('.table-filter-picker button').allTextContents();
  if(choices.length){await filter.locator('.table-filter-picker button').first().click();await filter.locator('input:not([disabled])').first().fill('inesistente');await filter.getByRole('button',{name:'Applica filtri',exact:true}).click();await page.waitForFunction(()=>!document.querySelector('.table-tools[aria-busy=true]'));report.interactions.push({name:'generic-table-filter',text:await page.locator('main').innerText()});await inspect('clients-filter-empty',360);}
  await go('/dashboard');await page.setViewportSize({width:360,height:800});await page.getByRole('button',{name:'Apri menu di navigazione',exact:true}).click();await page.screenshot({path:path.join(out,'mobile-menu-open.png'),fullPage:true});report.interactions.push({name:'mobile-menu',visible:await page.locator('#app-sidebar').isVisible()});await page.keyboard.press('Escape');
  await page.locator('#notifications-trigger').click();await page.locator('#notifications-panel').waitFor({state:'visible'});await page.screenshot({path:path.join(out,'mobile-notifications.png'),fullPage:true});const bounds=await page.locator('#notifications-panel').boundingBox();report.interactions.push({name:'notifications-bounds',bounds});await page.keyboard.press('Escape');
  for(const url of [...links].filter(u=>!u.includes('_table_all')).slice(0,220)){const response=await page.request.get(base+url);report.links.push({url,status:response.status()});}
  await context.close();
  for(const email of ['operator@example.test','reader@example.test']){
   const ctx=await browser.newContext({viewport:{width:360,height:800},locale:'it-IT'});await ctx.route('**/*',r=>r.request().url().startsWith(base+'/')?r.continue():r.abort());page=await ctx.newPage();page.setDefaultTimeout(12000);await go('/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('password-testing-only');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);await ready();
   for(const [name,url] of [['dashboard','/dashboard'],['clients','/clients'],['client',`/clients/${c}`],['new-policy','/policies/create'],['new-quote','/quotes/create'],['policy',`/clients/${c}/policies/${p}`],['titles','/titles']]){try{await go(url);await inspect((email.startsWith('operator')?'operator-':'reader-')+name,360);}catch(e){report.failures.push({name:email+' '+name,url,error:e.message});}}
   await ctx.close();
  }
 }catch(e){report.fatal=e.stack;console.error(e.stack);}finally{if(browser)await browser.close();server.kill();report.finished=new Date().toISOString();fs.writeFileSync(path.join(out,'browser-report.json'),JSON.stringify(report,null,2));}
 console.log(JSON.stringify({pages:report.pages.length,links:report.links.length,failures:report.failures,errors:report.errors,external:report.external,fatal:report.fatal},null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
