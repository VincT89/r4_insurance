"""Compare every staged source cell with an independent XLS reader; output counts only."""
import sys, json, hashlib, pathlib, datetime, re
root = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(root / 'work/qa-python'))
from python_calamine import CalamineWorkbook
private = root / 'storage/app/private/insurance-import'
summary = json.loads((private/'summary.json').read_text(encoding='utf8'))
staged = [json.loads(line) for line in (private/'source-rows.jsonl').read_text(encoding='utf8').splitlines()]
def clean(value):
    if value is None: return ''
    if isinstance(value, (datetime.date,datetime.datetime)): return value.isoformat()[:10]
    if isinstance(value, float) and value.is_integer(): return str(int(value))
    return re.sub(r'\s+', ' ', str(value)).strip()
result = {'sources': [], 'ok': True}
for source, metadata in zip(['clients','policies'], summary['sources']):
    original = root.parent / metadata['file']
    assert hashlib.sha256(original.read_bytes()).hexdigest() == metadata['sha256']
    book = CalamineWorkbook.from_path(str(original))
    sheet = book.get_sheet_by_name('Dati')
    rows = sheet.to_python(skip_empty_area=False)
    headers = [clean(v) for v in rows[6]]
    assert headers == metadata['headers'], (source,'headers')
    cells = 0
    mismatches = []
    records = [row for row in staged if row['source']==source]
    for record in records:
        values = rows[record['row']-1]
        for i, field in enumerate(headers):
            actual = clean(values[i]) if i < len(values) else ''
            expected = clean(record['values'][field])
            if actual != expected: mismatches.append({'row':record['row'],'column':i+1})
            cells += 1
    assert not mismatches, (source,'mismatches',mismatches[:10],len(mismatches))
    result['sources'].append({'source':source,'rows':len(records),'cellsCompared':cells,'source_sha256':metadata['sha256'],'mismatches':len(mismatches)})
(root/'work/source-reader-verification.json').write_text(json.dumps(result,indent=2),encoding='utf8')
print(json.dumps(result))
