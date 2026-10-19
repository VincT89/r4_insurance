from pathlib import Path
import json, hashlib, zipfile

root=Path(__file__).resolve().parents[4]
saved=Path('C:/Users/vince/Documents/Codex/2026-10-14/riprendiamo-il-gestionale-per-soluzioni-assicurative')
baseline=saved/'work/gestionale-cleanup'
prepared=saved/'work/gestionale-preparato'
detail=json.loads((saved/'outputs/dettaglio-pulizia.json').read_text(encoding='utf-8'))
backup=Path(__file__).resolve().parent
conflicts=[]
updates=[]
removals=[]
def digest(p): return hashlib.sha256(p.read_bytes()).hexdigest()
for name in detail['changed_or_added_files']:
    old=baseline/name; current=root/name; replacement=prepared/name
    if not replacement.is_file(): continue
    if name.startswith('docs/') and old.exists() and not current.exists(): continue
    if current.exists() and old.exists() and digest(current)!=digest(old) and digest(current)!=digest(replacement):
        conflicts.append(name)
    elif current.exists() and not old.exists() and digest(current)!=digest(replacement) and name != 'public/build/manifest.json':
        # The original pagination templates were omitted from the baseline by
        # the dependency-directory copy exclusion; compare to the prepared copy.
        conflicts.append(name)
    if not current.exists() or digest(current)!=digest(replacement): updates.append(name)
for name in detail['removed_files']:
    old=baseline/name; current=root/name
    if current.is_file():
        if not old.exists() or digest(current)!=digest(old): conflicts.append(name)
        else: removals.append(name)
if conflicts:
    print(json.dumps({'conflicts':conflicts},ensure_ascii=False)); raise SystemExit(2)
# Save only the code touched by the change; no credentials or application data.
with zipfile.ZipFile(backup/'codice-prima-della-pulizia.zip','w',zipfile.ZIP_DEFLATED) as z:
    for name in sorted(set(updates+removals)):
        p=root/name
        if p.is_file(): z.write(p,name)
plan={'root':str(root),'source':str(prepared),'updates':updates,'removals':removals,'admin_backup':'not performed: MySQL socket denied; database remains unchanged'}
(backup/'application-plan.json').write_text(json.dumps(plan,ensure_ascii=False,indent=2),encoding='utf-8')
with zipfile.ZipFile(backup/'codice-prima-della-pulizia.zip') as z:
    if z.testzip() is not None: raise RuntimeError('Code backup verification failed')
print(json.dumps({'updates':len(updates),'removals':len(removals),'backup_verified':True,'conflicts':0}))
