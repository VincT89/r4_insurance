const {chromium}=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const ExcelJS=require('../../node_modules/exceljs');
const root=path.resolve(__dirname,'../..'),php='C:/PHP 8.3.1/php.exe',out=path.join(root,'work/audit-fixes-2026-10-19');
fs.mkdirSync(out,{recursive:true});
const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(__dirname,'ui-'+Date.now()+'.sqlite'),DB_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'file',SESSION_EXPIRE_ON_CLOSE:'true',SESSION_COOKIE:'audit_fixes_'+Date.now(),QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',WORKSPACE_QA:'1',AGENCY_EMAIL_QA:'1',OPERATIONS_QA:'1',CLAIMS_QA:'1',COMMISSION_QA:'0',SIGNING_QA:'0',QUOTE_EMAIL_QA:'0',WORKFLOW_QA:'0',POLICY_IMPORT_QA:'0',EQUIVALENT_IMPORT_QA:'0',CATALOG_MERGE_QA:'0'};
const report={started:new Date().toISOString(),checks:[],layouts:[],errors:[],external:[]};
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const reservation=net.createServer();await new Promise(r=>reservation.listen(0,'127.0.0.1',r));const port=reservation.address().port;await new Promise(r=>reservation.close(r));const base='http://127.0.0.1:'+port;env.APP_URL=base;
 const seed={};for(const f of ['prepare.php','full-audit-fixtures.php','audit-fixes-fixtures.php'])Object.assign(seed,JSON.parse(execFileSync(php,[path.join(__dirname,f)],{cwd:root,env,windowsHide:true,encoding:'utf8'})));
 const log=fs.openSync(path.join(out,'server.log'),'w');const server=spawn(php,['-S','127.0.0.1:'+port,'-t',path.join(root,'public'),path.join(__dirname,'router.php')],{cwd:root,env,windowsHide:true,stdio:['ignore',log,log]});let browser;
 try {
  for(let i=0;i<100;i++){try{if((await fetch(base+'/login')).ok)break;}catch{}await sleep(100);}
  browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const makeContext=async()=>{
   const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'it-IT',timezoneId:'Europe/Rome'});
   await context.route('**/*',r=>{const u=r.request().url();if(u.startsWith(base+'/')||u.startsWith('blob:')||u.startsWith('data:'))return r.continue();report.external.push(u);return r.abort();});
   context.on('page',p=>{p.setDefaultTimeout(20000);p.on('pageerror',e=>report.errors.push(e.message));});return context;
  };
  const go=async(page,url)=>{const res=await page.goto(base+url);assert.equal(res.status(),200,url);await page.waitForLoadState('load');};
  const login=async(page,email)=>{await go(page,'/login');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('password-testing-only');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Entra',exact:true}).click()]);assert(!page.url().endsWith('/login'));};
  const layout=async(page,name)=>{
   await page.waitForFunction(() => document.querySelector('#shell')?.classList.contains('expanded') === (innerWidth >= 1024));
   await page.evaluate(async () => { await document.fonts.ready; await Promise.allSettled(document.getAnimations().filter(animation => Number.isFinite(animation.effect.getComputedTiming().endTime)).map(animation => animation.finished)); });
   const width=await page.evaluate(()=>({viewport:innerWidth,body:document.documentElement.scrollWidth}));
   assert(width.body<=width.viewport+2,'Overflow '+name+' '+JSON.stringify(width));
   report.layouts.push({name,...width});await page.screenshot({path:path.join(out,name+'.png')});
  };
  const context=await makeContext(),page=await context.newPage();await login(page,'ui-test@example.test');
  const exportFile=async(scope,name,format,count)=>{
   const event=page.waitForEvent('download');await scope.getByRole('button',{name:'Esporta '+format,exact:true}).click();const d=await event;
   const file=path.join(out,name+'.'+(format==='Excel'?'xlsx':'pdf'));await d.saveAs(file);
   await scope.getByText('Esportazione completata: '+count+' righe.',{exact:true}).waitFor();
   if(format==='Excel'){const workbook=new ExcelJS.Workbook();await workbook.xlsx.readFile(file);assert.equal(workbook.worksheets[0].rowCount-5,count);}
   else assert(fs.statSync(file).size>1000);
   report.checks.push({name,format,rows:count});
  };
  const claimUrl='/claims/'+seed.claim_id+'?history_page=2&activities_page=2&files_page=2';
  await go(page,claimUrl);await page.locator('#claim-history [data-table-tools]').waitFor();
  assert.equal(await page.locator('#claim-history nav').getAttribute('data-page-name'),'history_page');
  await exportFile(page.locator('#claim-history'),'history-all-from-page-2','Excel',seed.event_count);
  await exportFile(page.locator('#claim-history'),'history-all-from-page-2','PDF',seed.event_count);
  await exportFile(page.locator('#activities'),'activities-from-page-2','Excel',seed.activities_count);
  await exportFile(page.locator('#claim-files'),'files-from-page-2','Excel',seed.files_count);
  const history=page.locator('#claim-history');
  await history.getByRole('button',{name:'Filtra tabella',exact:true}).click();
  await history.locator('.table-filter-picker button').filter({hasText:'Operazione'}).click();
  await history.getByRole('textbox',{name:'Valore per Operazione',exact:true}).fill('Nota');
  await history.getByRole('button',{name:'Applica filtri',exact:true}).click();
  await history.getByRole('button',{name:'Filtri applicati (1)',exact:true}).waitFor();
  await exportFile(history,'filtered-history','Excel',511);
  await page.locator('#claim-history').scrollIntoViewIfNeeded();await layout(page,'filters-desktop');
  await page.setViewportSize({width:390,height:844});await layout(page,'filters-mobile');
  await page.setViewportSize({width:1440,height:1000});
  await go(page,'/clients?page=2');await page.locator('[data-table-tools]').first().waitFor();
  await exportFile(page.locator('.panel').filter({has:page.locator('table.t-table')}).first(),'clients-from-page-2','Excel',seed.clients_count);
  const edit='/clients/'+seed.client_id+'/policies/'+seed.edit_policy_id+'/edit';
  const second=await context.newPage();await go(page,edit);await go(second,edit);
  const stale=await page.locator('[name=policy_revision]').inputValue();
  await page.locator('[name=notes]').fill('Testo da recuperare dopo il confronto.');
  await second.locator('[name=notes]').fill('Aggiornamento già salvato dalla seconda scheda.');
  await Promise.all([second.waitForNavigation(),second.getByRole('button',{name:'Salva polizza',exact:true}).click()]);
  assert(!second.url().endsWith('/edit'),await second.locator('body').innerText());
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Salva polizza',exact:true}).click()]);
  await page.getByText('Controlla gli aggiornamenti prima di salvare',{exact:true}).waitFor();
  assert.equal(await page.locator('[name=notes]').inputValue(),'Testo da recuperare dopo il confronto.');
  assert.equal(await page.locator('[name=policy_revision]').inputValue(),stale);
  await page.evaluate(()=>scrollTo(0,0));await layout(page,'policy-conflict-desktop');
  await page.setViewportSize({width:390,height:844});await layout(page,'policy-conflict-mobile');
  const popupEvent=page.waitForEvent('popup');await page.getByRole('link',{name:'Apri polizza aggiornata in un’altra scheda',exact:true}).click();const popup=await popupEvent;await popup.waitForLoadState('load');
  assert((await popup.locator('body').innerText()).includes('Aggiornamento già salvato dalla seconda scheda.'));await popup.close();
  await Promise.all([page.waitForNavigation(),page.getByRole('link',{name:'Riapri modulo aggiornato',exact:true}).click()]);
  assert.notEqual(await page.locator('[name=policy_revision]').inputValue(),stale);
  assert.equal(await page.locator('[name=notes]').inputValue(),'Aggiornamento già salvato dalla seconda scheda.');
  await page.locator('[name=notes]').fill('Confronto completato: modifica concordata.');
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Salva polizza',exact:true}).click()]);
  assert((await page.locator('body').innerText()).includes('Confronto completato: modifica concordata.'));
  report.checks.push({name:'simultaneous-policy-edit',staleSaveBlocked:true,inputPreserved:true,reviewAndResave:true});
  const employeeContext=await makeContext(),employee=await employeeContext.newPage();await login(employee,'operator@example.test');
  await go(employee,'/clients/'+seed.client_id+'?tab=network');
  assert.equal(await employee.getByRole('button',{name:'Elimina',exact:true}).count(),0);
  const csrf=await employee.locator('meta[name=csrf-token]').getAttribute('content');
  const deletion=await employeeContext.request.post(base+'/clients/'+seed.client_id+'/records/contacts/'+seed.contact_id,{form:{_method:'DELETE',_token:csrf}});
  assert.equal(deletion.status(),403);
  await go(employee,'/clients/'+seed.client_id+'/vehicles/'+seed.unused_vehicle_id+'/edit');
  assert.equal(await employee.getByRole('button',{name:'Elimina veicolo',exact:true}).count(),0);
  await go(employee,'/profile');assert.equal(await employee.getByRole('button',{name:'Elimina account',exact:true}).count(),0);
  assert((await employee.locator('body').innerText()).includes('Questo account ha attività o dati collegati.'));
  await layout(employee,'profile-history-desktop');await employee.setViewportSize({width:390,height:844});await layout(employee,'profile-history-mobile');
  report.checks.push({name:'employee-permissions-and-retained-history',deleteHttpStatus:403,deleteControlsHidden:true,profileExplainsRetention:true});
  assert.deepEqual(report.errors,[]);assert.deepEqual(report.external,[]);
 }catch(e){report.fatal=e.stack;console.error(e.stack);}finally{if(browser)await browser.close();server.kill();fs.closeSync(log);report.finished=new Date().toISOString();fs.writeFileSync(path.join(out,'browser-report.json'),JSON.stringify(report,null,2));}
 console.log(JSON.stringify(report,null,2));if(report.fatal)process.exitCode=1;
})().catch(e=>{console.error(e.stack);process.exitCode=1;});

