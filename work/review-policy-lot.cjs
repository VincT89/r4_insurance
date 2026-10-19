const fs = require('node:fs');
const path = require('node:path');
const dir=path.join(__dirname,'../storage/app/private/insurance-import');
const policies=JSON.parse(fs.readFileSync(path.join(dir,'policies.json'),'utf8'));
const clients=JSON.parse(fs.readFileSync(path.join(dir,'clients.json'),'utf8'));
const issues={}, combinations={}, conflicts={}, statuses={};
for(const p of policies){for(const k of p.issues)issues[k]=(issues[k]||0)+1;const key=p.issues.join('|');combinations[key]=(combinations[key]||0)+1;for(const k of p.conflicting_fields)conflicts[k]=(conflicts[k]||0)+1;statuses[p.data.source_status]=(statuses[p.data.source_status]||0)+1;}
const source=JSON.parse(fs.readFileSync(path.join(dir,'source-rows.jsonl'),'utf8').split('\n').find(line=>line&&JSON.parse(line).source==='policies'));
console.log(JSON.stringify({policyGroups:policies.length,clientCandidates:clients.length,candidateFields:Object.keys(policies[0]),policyFields:Object.keys(policies[0].data),rawFields:Object.keys(source.values),issues,conflicts,statuses,issueCombinations:Object.entries(combinations).sort((a,b)=>b[1]-a[1])},null,2));
