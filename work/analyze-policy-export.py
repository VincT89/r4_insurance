import collections
import io
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'work/qa-python'))
import xlrd

source = next(ROOT.parent.glob('Gestionale*.xls'))
book = xlrd.open_workbook(str(source), ignore_workbook_corruption=True, logfile=io.StringIO())
sheet = book.sheet_by_name('Dati')
header = [str(v).strip() for v in sheet.row_values(6)]
clean = lambda value: str(value).strip() if value != '' else ''
records = [dict(zip(header, map(clean, sheet.row_values(row)))) for row in range(7, sheet.nrows) if any(v != '' for v in sheet.row_values(row))]
policies = [r for r in records if r['Numero Polizza']]
classifications = collections.Counter((r['Ramo'], r['Sottoramo']) for r in policies)
providers = collections.Counter((r['Compagnia'], r['Agenzia']) for r in policies)
policy_keys = collections.Counter((r['Compagnia'], r['Agenzia'], r['Numero Polizza']) for r in policies)
people = collections.defaultdict(set)
for row in policies:
    key = row['Cod. Fiscale / P. IVA'].replace(' ', '').upper()
    if key:
        people[key].add(row['Contraente'])
subbranches = collections.defaultdict(set)
agencies = collections.defaultdict(set)
for branch, subbranch in classifications:
    subbranches[subbranch].add(branch)
for company, agency in providers:
    agencies[agency].add(company)
report = {
    'source': str(source), 'sheet': sheet.name, 'header_row': 7,
    'read_note': 'XLS letto con tolleranza alle incongruenze del contenitore OLE. File originale invariato; nessuna importazione.',
    'headers': {xlrd.colname(i): name for i, name in enumerate(header)},
    'nonempty_data_rows': len(records), 'rows_with_policy_number': len(policies),
    'distinct_company_agency_policy_keys': len(policy_keys),
    'repeated_policy_keys': sum(n > 1 for n in policy_keys.values()),
    'distinct_fiscal_identifiers': len(people),
    'fiscal_identifiers_with_multiple_name_spellings': sum(len(names) > 1 for names in people.values()),
    'rows_without_fiscal_identifier': sum(not r['Cod. Fiscale / P. IVA'] for r in policies),
    'branches': sorted({k[0] for k in classifications if k[0]}),
    'distinct_subbranch_labels': len({k[1] for k in classifications if k[1]}),
    'classifications': [{'branch': k[0], 'subbranch': k[1], 'rows': n} for k, n in sorted(classifications.items())],
    'companies': sorted({k[0] for k in providers if k[0]}),
    'distinct_agency_labels': len({k[1] for k in providers if k[1]}),
    'company_agencies': [{'company': k[0], 'agency': k[1], 'rows': n} for k, n in sorted(providers.items())],
    'subbranches_used_under_multiple_branches': {k: sorted(v) for k, v in subbranches.items() if k and len(v) > 1},
    'agencies_used_with_multiple_companies': {k: sorted(v) for k, v in agencies.items() if k and len(v) > 1},
    'distinct_title_labels': len({r['Titolo'] for r in policies}),
    'missing_values': {key: sum(not r[key] for r in policies) for key in ['Compagnia', 'Agenzia', 'Ramo', 'Sottoramo', 'Contraente', 'Efetto', 'Scadenza']},
    'nonempty_field_counts': {key: sum(bool(r[key]) for r in policies) for key in header},
}
registry_source = next(ROOT.parent.glob('Anagrafica*.xls'))
registry = xlrd.open_workbook(str(registry_source), ignore_workbook_corruption=True, logfile=io.StringIO()).sheet_by_name('Dati')
registry_keys = {clean(registry.cell_value(r, 2)).replace(' ', '').upper() for r in range(7, registry.nrows) if registry.cell_value(r, 2) != ''}
report['registry_match'] = {
    'source': str(registry_source),
    'registry_distinct_fiscal_identifiers': len(registry_keys),
    'policy_identifiers_found_in_registry': len(set(people) & registry_keys),
    'policy_identifiers_absent_from_registry': len(set(people) - registry_keys),
}
(ROOT / 'work/policy-export-analysis.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
summary = {k: v for k, v in report.items() if k not in ['headers', 'company_agencies', 'nonempty_field_counts']}
print(json.dumps(summary, ensure_ascii=True, indent=2))
