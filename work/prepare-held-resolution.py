"""Prepare the specific client resolutions approved during the held-record review."""
import json, hashlib, pathlib

root = pathlib.Path(__file__).resolve().parents[1]
private = root / 'storage/app/private/insurance-import'
review = private / 'held-review'
analysis = json.loads((review / 'analysis.json').read_text(encoding='utf8'))
snapshot = json.loads((review / 'database-snapshot.json').read_text(encoding='utf8'))
entries = {e['id']: e for e in snapshot['held']}
approved_links = {4932:62, 4933:91, 4934:95, 4936:110, 4938:124, 4940:130,
                  4941:145, 4943:171, 4944:176, 4946:177, 4948:195}
operations = []
for item in analysis['items']:
    matches = item['exact_name_matches'] + item['near_fiscal_matches'] + item['similar_name_matches']
    action = 'link' if item['entry_id'] in approved_links else (
        'create' if item['source'] == 'policies' and item['fiscal_valid'] and not matches else None)
    if not action:
        continue
    entry = entries[item['entry_id']]
    operations.append({'entry_id': entry['id'], 'action': action,
                       'target_id': approved_links.get(entry['id']),
                       'source_key': entry['source_key'], 'payload_hash': entry['payload_hash'],
                       'expected_entry': entry})
assert sum(o['action'] == 'link' for o in operations) == 11
assert sum(o['action'] == 'create' for o in operations) == 30
assert 4939 not in [o['entry_id'] for o in operations]
plan = {'version': 1, 'source_hashes': {name: hashlib.sha256((private/name).read_bytes()).hexdigest()
        for name in ['clients.json', 'summary.json', 'source-rows.jsonl']},
        'expected_clients': snapshot['clients'], 'operations': operations}
(review / 'resolution-plan.json').write_text(json.dumps(plan, ensure_ascii=False), encoding='utf8')
print(json.dumps({'links': 11, 'minimal_clients': 30, 'remaining_held': len(entries)-len(operations)}))
