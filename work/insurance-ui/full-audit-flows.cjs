const {spawnSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'work/full-audit-2026-10-18');
fs.mkdirSync(out,{recursive:true});
const results=[];
for(const name of ['operations','workflow','commission']){
 const file=path.join(__dirname,name+'-check.cjs');const log=fs.openSync(path.join(out,name+'-flow.log'),'w');
 const started=Date.now();const result=spawnSync(process.execPath,[file],{cwd:root,env:{...process.env,AUDIT_FIXED_DATE:'2026-10-16 12:00:00',SESSION_EXPIRE_ON_CLOSE:'true'},windowsHide:true,stdio:['ignore',log,log],timeout:240000});fs.closeSync(log);
 results.push({name,status:result.status,error:result.error?.message,seconds:Math.round((Date.now()-started)/1000)});console.log(JSON.stringify(results.at(-1)));
}
fs.writeFileSync(path.join(out,'flows-summary.json'),JSON.stringify(results,null,2));
