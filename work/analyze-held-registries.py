"""Reconcile held identities against source rows and the read-only registry snapshot."""
import sys, json, pathlib, collections, re, unicodedata, difflib
root=pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0,str(root/'scripts'))
from prepare_insurance_import import fiscal_valid, fiscal, key, clean, CLIENT_FIELDS
private=root/'storage/app/private/insurance-import'
snapshot=json.loads((private/'held-review/database-snapshot.json').read_text(encoding='utf8'))
clients=json.loads((private/'clients.json').read_text(encoding='utf8'))
candidates={c['key']:c for c in clients}
sources=[json.loads(line) for line in (private/'source-rows.jsonl').read_text(encoding='utf8').splitlines()]
by_source={(row['source'],row['row']):row for row in sources}
def name_key(s):
    return re.sub(r'[^A-Z0-9]', '', unicodedata.normalize('NFKD',key(s)).encode('ascii','ignore').decode())
live=snapshot['clients']
imported={int(e['entity_id']):e for e in snapshot['imported']}
live_by_name=collections.defaultdict(list)
live_fiscals={}
for c in live:
    live_by_name[name_key(c['name'])].append(c)
    entry=imported.get(c['id'])
    source=candidates.get(entry['source_key']) if entry else None
    c['_fiscal']=fiscal((source or {}).get('fiscal_identifier') or c['tax_code'] or c['vat_number'])
    if c['_fiscal']: live_fiscals.setdefault(c['_fiscal'],[]).append(c)
def near_id(a,b):
    return bool(a and b and (len(a)==len(b) and sum(x!=y for x,y in zip(a,b))<=2 or abs(len(a)-len(b))==1 and difflib.SequenceMatcher(None,a,b).ratio()>=.90))
def summarize(c):
    return {'id':c['id'],'name':c['name'],'fiscal_identifier':c['_fiscal'],'fiscal_valid':fiscal_valid(c['_fiscal']), 'source_rows':json.loads(imported[c['id']]['source_rows']) if c['id'] in imported else []}
items=[]
for entry in snapshot['held']:
    c=candidates[entry['source_key']]
    rows=[by_source[(c['source'],r)] for r in c['source_rows']]
    names=sorted({clean(r['values'].get('Contraente')) for r in rows})
    titles=sorted({clean(r['values'].get('Titolo')) for r in rows if clean(r['values'].get('Titolo'))})
    differences={}
    for field in CLIENT_FIELDS:
        values=collections.defaultdict(list)
        for r in rows:
            value=clean(r['values'].get(field))
            if value: values[value].append(r['row'])
        if len({key(v) for v in values})>1: differences[field]=[{'value':v,'rows':rs} for v,rs in values.items()]
    f=c['fiscal_identifier']
    exact={r['id']:r for name in names for r in live_by_name[name_key(name)]}
    fiscal_close={r['id']:r for r in live if near_id(f,r['_fiscal'])}
    similar_names={r['id']:r for r in live if any(difflib.SequenceMatcher(None,name_key(n),name_key(r['name'])).ratio()>=.92 for n in names)}
    items.append({'entry_id':entry['id'],'key':c['key'],'source':c['source'],'names':names,'titles':titles,'fiscal_identifier':f,'fiscal_valid':fiscal_valid(f),'source_rows':c['source_rows'],'differences':differences,'exact_name_matches':[summarize(r) for r in exact.values()],'near_fiscal_matches':[summarize(r) for r in fiscal_close.values()],'similar_name_matches':[summarize(r) for r in similar_names.values()]})
summary={'held':len(items),'registry_conflicts':sum(i['source']=='clients' for i in items),'policy_only':sum(i['source']=='policies' for i in items),'policy_only_fiscal_valid':sum(i['source']=='policies' and i['fiscal_valid'] for i in items),'policy_only_multiple_names':sum(i['source']=='policies' and len(i['names'])>1 for i in items),'policy_only_exact_name':sum(i['source']=='policies' and bool(i['exact_name_matches']) for i in items),'policy_only_near_fiscal':sum(i['source']=='policies' and bool(i['near_fiscal_matches']) for i in items)}
(private/'held-review/analysis.json').write_text(json.dumps({'summary':summary,'items':items},ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps(summary))
for i in items:
    view={k:i[k] for k in ['entry_id','source','names','titles','fiscal_valid','source_rows']}
    view['fiscal_shape']=str(len(i['fiscal_identifier']))+' chars, '+('numeric' if i['fiscal_identifier'].isdigit() else 'alphanumeric')
    view['differences']={k:([v['value'] for v in vals] if k in ['Contraente','Titolo','Provincia','Ufficio','Consulente'] else str(len(vals))+' variants') for k,vals in i['differences'].items()}
    for field in ['exact_name_matches','near_fiscal_matches','similar_name_matches']:
        view[field]=[{k:r[k] for k in ['id','name','fiscal_valid','source_rows']} for r in i[field]]
    print(json.dumps(view,ensure_ascii=True))
