const {chromium}=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe',out=path.join(root,'work/full-audit-2026-10-18');
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,`ui-${Date.now()}.sqlite`),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_COOKIE:`audit_edges_${Date.now()}`,QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',COMMISSION_QA:'1',CLAIMS_QA:'1',SIGNING_QA:'1',QUOTE_EMAIL_QA:'1',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report={started:new Date().toISOString(),checks:[],errors:[],external:[]};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base=`http://127.0.0.1:${port}`;env.APP_URL=base;
 const seed={};for(const f of ['prepare.php','full-audit-fixtures.php','full-audit-edge-fixtures.php'])Object.assign(seed,JSON.parse(execFileSync(php,[path.join(__dirname,f)],{cwd:root,env,windowsHide:true,encoding:'utf8'})));
 const log=fs.openSync(path.join(out,'edges-server.log'),'w');const server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:['ignore',log,log]});let browser;
 try{
  for(let i=0;i<100;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await sleep(100);}
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'it-IT',timezoneId:'Europe/Rome'});
  await context.route('**/*',r=>{const u=r.request().url();if(u.startsWith(base+'/')||u.startsWith('blob:')||u.startsWith('data:'))return r.continue();report.external.push(u);return r.abort();});
  const page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>report.errors.push(e.message));
  const go=async url=>{const res=await page.goto(base+url);if(res.status()!==200)throw new Error(`${res.status()} ${url}`);await page.waitForLoadState('load');};
  for(const url of ['/privacy-policy','/terms-of-service']){await go(url);report.checks.push({name:'public-legal',url,text:(await page.locator('body').innerText()).slice(0,4500)});await page.screenshot({path:path.join(out,url.slice(1)+'.png')});}
  await go('/login');await page.locator('[name=email]').fill('ui-test@example.test');await page.locator('[name=password]').fill('password-testing-only');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);
  await go(`/claims/${seed.claim_id}?history_page=2#claim-history`);await page.locator('#claim-history [data-table-tools]').waitFor();
  const history=page.locator('#claim-history');const before=await history.locator('tbody tr').count();
  for(const format of ['Excel','PDF']){const done=page.waitForEvent('download');await history.getByRole('button',{name:'Esporta '+format,exact:true}).click();const d=await done;const file='history-from-page-2.'+(format==='Excel'?'xlsx':'pdf');await d.saveAs(path.join(out,file));report.checks.push({name:'named-pagination-export',format,total:seed.event_count,rowsOnPage:before,status:await history.locator('.table-tools').innerText(),file});}
  await page.screenshot({path:path.join(out,'history-page-2-export.png')});
  await history.getByRole('button',{name:'Filtra tabella',exact:true}).click();await history.locator('.table-filter-picker button').filter({hasText:'Operazione'}).click();await history.getByRole('textbox',{name:'Valore per Operazione',exact:true}).fill('Nota');await history.getByRole('button',{name:'Applica filtri',exact:true}).click();await history.getByRole('button',{name:'Filtri applicati (1)',exact:true}).waitFor();report.checks.push({name:'named-pagination-filter',status:await history.locator('.table-tools').innerText(),rows:await history.locator('tbody tr').count(),expected:20});
  await go('/clients');await page.getByRole('button',{name:'Filtra tabella',exact:true}).first().click();const filter=page.locator('.table-filter-panel').first();await filter.locator('.table-filter-picker button').first().click();await filter.locator('input:not([disabled])').first().fill('inesistente');await filter.getByRole('button',{name:'Applica filtri',exact:true}).click();await page.getByRole('button',{name:'Filtri applicati (1)',exact:true}).first().waitFor();report.checks.push({name:'normal-pagination-filter',status:await page.locator('.table-tools').first().innerText(),visibleRows:await page.locator('table.t-table tbody tr:visible').count()});
  await go('/titles');const wait=page.waitForEvent('download');await page.getByRole('button',{name:'Esporta Excel',exact:true}).first().click();const d=await wait;await d.saveAs(path.join(out,'titles-values.xlsx'));report.checks.push({name:'title-xlsx',file:'titles-values.xlsx'});
 }catch(e){report.fatal=e.stack;console.error(e.stack);}finally{if(browser)await browser.close();server.kill();report.finished=new Date().toISOString();fs.writeFileSync(path.join(out,'edges-report.json'),JSON.stringify(report,null,2));}
 console.log(JSON.stringify(report,null,2));if(report.fatal)process.exitCode=1;
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
