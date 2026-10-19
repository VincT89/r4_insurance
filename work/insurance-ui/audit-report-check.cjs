const {chromium}=require('C:/Users/vince/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),path=require('node:path'),{pathToFileURL,fileURLToPath}=require('node:url');
(async()=>{
 const target=path.resolve(__dirname,'../full-audit-2026-10-18/report.html');
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const page=await browser.newPage();await page.goto(pathToFileURL(target).href);
  const links=await page.locator('a[href]').evaluateAll(as=>as.map(a=>a.href));const missing=links.filter(s=>s.startsWith('file:')&&!s.includes('#')).filter(s=>!fs.existsSync(fileURLToPath(s)));
  const result={missing,layouts:[]};
  for(const width of [1440,390]){await page.setViewportSize({width,height:1000});await page.screenshot({path:path.join(path.dirname(target),`report-preview-${width}.png`)});result.layouts.push({width,overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)});}
  fs.writeFileSync(path.join(path.dirname(target),'report-validation.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
