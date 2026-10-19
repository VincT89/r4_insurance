"""Prepare a private, reproducible import preview. This program never connects to a database."""
from __future__ import annotations

import argparse
import collections
import datetime as dt
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
import hashlib
import io
import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
if (ROOT / 'work/qa-python').is_dir():
    sys.path.insert(0, str(ROOT / 'work/qa-python'))
import xlrd

CLIENT_FIELDS = {
    'Titolo': 'title', 'Contraente': 'name', 'Data Nascita': 'birth_date', 'Luogo Nascita': 'birth_place',
    'Telefono': 'phone', 'Cellulare': 'mobile', 'FAX': 'fax', 'E-Mail': 'email', 'Target': 'target',
    'Provincia': 'province', 'Città': 'city', 'Indirizzo': 'address', 'CAP': 'postal_code',
    'Referente': 'reference_person', 'E-Mail Referente': 'reference_email', 'Ruolo Referente': 'reference_role',
    'Telefono Referente': 'reference_phone', 'Ufficio': 'office', 'Consulente': 'consultant',
    'Collaboratore': 'collaborator', 'Operatore': 'operator', 'Notizie Salienti': 'salient_notes',
}
FISCAL = 'Cod. Fiscale / P. IVA'
FINANCIAL = {'SPESE EMISSIONE', 'FATTURA', 'QUOTA ASSOCIATIVA'}
KNOWN_BRANCHES = {'AUTO', 'RCA', 'RCAUTO', 'INFORTUNI', 'RCP', 'RCT', 'RC GENERALE', 'TUTELA LEGALE',
                  'VITA', 'TCM', 'INCENDIO', 'FURTO', 'INCENDIO/FURTO', 'CVT', 'CASA', 'ABITAZIONE',
                  'CONDOMINIO', 'GLOBALE FABBRICATI', 'GLOBALI FABBRICATI', 'COMMERCIO', 'UFFICIO',
                  'VIAGGI', 'VIAGGIO', 'CAT-NAT', 'FIDEIUSSIONE', 'FIDEJUSSIONI', 'CANE&GATTO'}


def clean(value):
    if value is None:
        return ''
    if isinstance(value, float) and value.is_integer():
        return str(int(value))
    return re.sub(r'\s+', ' ', str(value)).strip()


def key(value):
    return clean(value).upper()


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def fiscal(value):
    return re.sub(r'\s+', '', clean(value)).upper()


def fiscal_valid(value):
    # Format and checksum only; no claim of validation against the fiscal registry.
    if re.fullmatch(r'\d{11}', value):
        total = sum(int(c) if i % 2 == 0 else (int(c) * 2 // 10 + int(c) * 2 % 10) for i, c in enumerate(value[:10]))
        return (10 - total % 10) % 10 == int(value[10])
    if not re.fullmatch(r'[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]', value):
        return False
    odd = dict(zip('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', [1,0,5,7,9,13,15,17,19,21,1,0,5,7,9,13,15,17,19,21,2,4,18,20,11,3,6,8,12,14,16,10,22,25,24,23]))
    total = sum(odd[c] if i % 2 == 0 else (int(c) if c.isdigit() else ord(c)-65) for i, c in enumerate(value[:15]))
    return chr(65 + total % 26) == value[-1]


def parse_date(value):
    if not clean(value):
        return None
    for fmt in ('%d/%m/%Y', '%Y-%m-%d'):
        try:
            return dt.datetime.strptime(clean(value), fmt).date().isoformat()
        except ValueError:
            pass
    raise ValueError('Data non valida')


def money(value):
    if clean(value) == '':
        return None
    raw = clean(value)
    if ',' in raw:
        raw = raw.replace('.', '').replace(',', '.')
    amount = Decimal(raw)
    if not amount.is_finite() or amount < 0 or amount > Decimal('9999999999.99'):
        raise ValueError('Premio fuori intervallo')
    return str(amount.quantize(Decimal('.01'), rounding=ROUND_HALF_UP))


def read_source(path, kind):
    binary = path.read_bytes()
    tolerant = False
    try:
        book = xlrd.open_workbook(file_contents=binary, logfile=io.StringIO())
    except xlrd.compdoc.CompDocError:
        tolerant = True
        book = xlrd.open_workbook(file_contents=binary, ignore_workbook_corruption=True, logfile=io.StringIO())
    sheet = book.sheet_by_name('Dati')
    headers = [clean(v) for v in sheet.row_values(6)]
    required = list(CLIENT_FIELDS) + [FISCAL] if kind == 'clients' else [FISCAL, 'Contraente', 'Numero Polizza', 'Compagnia', 'Agenzia', 'Ramo', 'Sottoramo', 'Polizze', 'Efetto', 'Scadenza']
    if len(headers) != len(set(headers)) or not set(required) <= set(headers):
        raise ValueError(f'Intestazioni inattese in {path.name}')
    sha = hashlib.sha256(binary).hexdigest()
    rows = []
    for row in range(7, sheet.nrows):
        values = []
        issues = []
        for col, name in enumerate(headers):
            cell = sheet.cell(row, col)
            if cell.ctype == xlrd.XL_CELL_DATE:
                val = xlrd.xldate_as_datetime(cell.value, book.datemode).date().isoformat()
            else:
                val = cell.value
            if name in (FISCAL, 'Numero Polizza', 'CAP', 'Telefono', 'Cellulare') and cell.ctype == xlrd.XL_CELL_NUMBER:
                issues.append('identificativo_numerico_in_excel')
            if cell.ctype == xlrd.XL_CELL_ERROR:
                issues.append('errore_cella_excel')
            values.append(val)
        if not any(v != '' for v in values):
            continue
        rows.append({'source': kind, 'row': row + 1, 'source_id': digest([sha, 'Dati', row+1]), 'values': dict(zip(headers, values)), 'issues': issues})
    return {'file': path.name, 'sha256': sha, 'sheet': 'Dati', 'header_row': 7, 'headers': headers, 'rows': len(rows), 'tolerant_read': tolerant}, rows


def classification(branch, subbranch):
    b, s = key(branch).replace('CANE& GATTO', 'CANE&GATTO'), key(subbranch)
    reason = ''
    suggestion_b, suggestion_s = b, s
    if b in FINANCIAL or s == 'FATTURA':
        return None, None, 'esclusa', 'Voce amministrativa, non polizza'
    if b in {'RCA', 'RCAUTO'}:
        suggestion_b = 'RC auto'
        suggestion_s = {'AUTO': 'Autovetture', 'MOTO': 'Motocicli', 'RCA': '', 'RCAUTO': ''}.get(s, s)
        reason = 'Accorpamento RCA/RCAUTO proposto; il dettaglio del veicolo si conserva se indicato'
    elif b == 'AUTO':
        suggestion_b = 'Veicoli'
        reason = 'AUTO non identifica da solo la copertura RC: verificare RC auto o altre garanzie'
    elif b in {'GLOBALE FABBRICATI', 'GLOBALI FABBRICATI'}:
        suggestion_b = 'Fabbricati'
        reason = 'Accorpamento delle denominazioni da confermare'
    elif b in {'FIDEIUSSIONE', 'FIDEJUSSIONI'}:
        suggestion_b = 'Fideiussioni'
        reason = 'Uniformazione della denominazione proposta'
    elif b in {'VIAGGI', 'VIAGGIO'}:
        suggestion_b = 'Viaggi'
        reason = 'Uniformazione singolare/plurale proposta'
    elif b not in KNOWN_BRANCHES:
        reason = 'Sigla, prodotto o categoria generica: occorre confermare il ramo e il sottoramo'
    if reason:
        return suggestion_b, suggestion_s or None, 'da_confermare', reason
    return b, s or None, 'conservata', 'Classificazione originale conservata; uniformati solo maiuscole e spazi'


def prepare(registry_rows, policy_rows):
    issues = []
    def issue(kind, rows, code, severity='bloccante'):
        issues.append({'area': kind, 'rows': sorted(set(rows)), 'code': code, 'severity': severity})

    fiscal_groups = collections.defaultdict(list)
    for row in registry_rows:
        f = fiscal(row['values'].get(FISCAL))
        fiscal_groups[f or 'missing:'+row['source_id']].append(row)
    policy_names = collections.defaultdict(set)
    all_policy_groups = collections.defaultdict(list)
    classifications = collections.Counter()
    company_pairs = collections.Counter()
    excluded = []
    records = []
    for row in policy_rows:
        v = row['values']
        if not clean(v.get('Numero Polizza')):
            excluded.append({'row': row['row'], 'reason': 'Riga di riepilogo senza numero polizza', 'source_id': row['source_id']})
            continue
        classifications[(clean(v['Ramo']), clean(v['Sottoramo']))] += 1
        company_pairs[(clean(v['Compagnia']), clean(v['Agenzia']))] += 1
        all_policy_groups[(key(v['Compagnia']), key(v['Agenzia']), clean(v['Numero Polizza']))].append(row)
        if key(v['Ramo']) in FINANCIAL or key(v['Sottoramo']) == 'FATTURA' or key(v['Compagnia']) == 'SPESE':
            excluded.append({'row': row['row'], 'reason': 'Voce amministrativa', 'source_id': row['source_id']})
            continue
        records.append(row)
        policy_names[fiscal(v[FISCAL])].add(key(v['Contraente']))

    clients = []
    client_by_fiscal = {}
    for f, rows in sorted(fiscal_groups.items()):
        values = rows[0]['values']
        problems = set(code for row in rows for code in row['issues'])
        if f.startswith('missing:'):
            f = ''
        if not f or not fiscal_valid(f):
            problems.add('identificativo_fiscale_da_verificare')
        conflicts = [field for field in CLIENT_FIELDS if len({key(r['values'].get(field)) for r in rows if clean(r['values'].get(field))}) > 1]
        if conflicts:
            problems.add('anagrafiche_discordanti_stesso_identificativo')
        data = {}
        for source, target in CLIENT_FIELDS.items():
            choices = [clean(r['values'].get(source)) for r in rows if clean(r['values'].get(source))]
            data[target] = choices[0] if choices else None
        if not data['name']:
            problems.add('contraente_mancante')
        try:
            data['birth_date'] = parse_date(data['birth_date'])
        except ValueError:
            problems.add('data_nascita_non_valida')
        for field in ['email', 'reference_email']:
            if data[field] and not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+', data[field]):
                problems.add(field+'_da_verificare')
        for field, value in data.items():
            limit = 10000 if field == 'salient_notes' else 255
            if value and len(value) > limit:
                problems.add(field+'_troppo_lungo')
        for field in ['phone', 'mobile', 'fax', 'reference_phone']:
            if data[field] and len(data[field]) > 50:
                problems.add(field+'_troppo_lungo')
        if data['postal_code'] and (not re.fullmatch(r'\d{5}', data['postal_code'])):
            problems.add('cap_da_verificare')
        if f in policy_names and any(n != key(data['name']) for n in policy_names[f]):
            problems.add('nome_discordante_tra_export')
        data['client_type'] = 'person' if len(f) == 16 and fiscal_valid(f) else None
        data['tax_code'] = f if len(f) == 16 else None
        data['vat_number'] = None
        if len(f) == 11:
            problems.add('distinguere_codice_fiscale_e_partita_iva')
        item = {'key': digest(['client', f]) if f else rows[0]['source_id'], 'fiscal_identifier': f, 'source_rows': [r['row'] for r in rows], 'source': 'clients',
                'data': data, 'conflicting_fields': conflicts, 'issues': sorted(problems), 'result': 'da_verificare' if problems else 'candidata'}
        clients.append(item)
        if f:
            client_by_fiscal[f] = item
        for code in problems:
            issue('clients', item['source_rows'], code)
    missing = sorted(set(policy_names) - set(client_by_fiscal))
    for f in missing:
        rows = [r for r in records if fiscal(r['values'][FISCAL]) == f]
        v = rows[0]['values']
        item = {'key': digest(['client', f]), 'fiscal_identifier': f, 'source': 'policies', 'source_rows': [r['row'] for r in rows],
                'data': {'name': clean(v['Contraente']), 'title': clean(v['Titolo']), 'client_type': None, 'tax_code': f if len(f) == 16 else None, 'vat_number': None},
                'issues': ['cliente_assente_in_anagrafica'], 'conflicting_fields': [], 'result': 'da_verificare'}
        clients.append(item)
        client_by_fiscal[f] = item
        issue('policies', item['source_rows'], 'cliente_assente_in_anagrafica')

    policy_groups = collections.defaultdict(list)
    for row in records:
        v = row['values']
        # Same key as the application's unique constraint; agencies never create duplicate policies.
        policy_groups[(fiscal(v[FISCAL]), key(v['Compagnia']), clean(v['Numero Polizza']))].append(row)
    ownership = collections.defaultdict(set)
    for row in records:
        v = row['values']
        ownership[(key(v['Compagnia']), clean(v['Numero Polizza']))].add(fiscal(v[FISCAL]))
    policies = []
    duplicate_details = []
    for (f, company, number), rows in sorted(policy_groups.items()):
        v = rows[0]['values']
        problems = set(code for row in rows for code in row['issues'])
        conflicts = [field for field in ['Agenzia', 'Ramo', 'Sottoramo', 'Efetto', 'Scadenza', 'Interesse Assic.', 'Premio annuale']
                     if len({key(r['values'].get(field)) for r in rows}) > 1]
        if len(rows) > 1:
            # No winner selected from historic renewals, rate rows, or changing agencies.
            problems.add('numero_polizza_ripetuto')
            duplicate_details.append({'rows': [r['row'] for r in rows], 'count': len(rows), 'conflicting_fields': conflicts,
                                      'different_installment_dates': len({clean(r['values'].get('Data Rata Attuale')) for r in rows}) > 1})
        if not f or not fiscal_valid(f):
            problems.add('identificativo_fiscale_da_verificare')
        if client_by_fiscal.get(f, {}).get('issues'):
            problems.add('cliente_da_verificare')
        if len(ownership[(company, number)]) > 1:
            problems.add('stessa_polizza_intestatari_diversi')
        b, s, state, reason = classification(v['Ramo'], v['Sottoramo'])
        if state == 'da_confermare':
            problems.add('classificazione_da_confermare')
        if company in {'EUROPE', 'EUROPE ASSISTANCE', 'PRIMA', 'DUAL', 'BHITALIA'}:
            problems.add('denominazione_compagnia_da_confermare')
        if not company or not clean(v['Agenzia']) or not clean(v['Ramo']):
            problems.add('collegamento_catalogo_mancante')
        data = {'category': b, 'subcategory': s, 'company': company, 'agency': key(v['Agenzia']), 'number': number,
                'insured_subject': clean(v['Interesse Assic.']) or None, 'product': None, 'status': None,
                'source_status': clean(v['Polizze']), 'notes': clean(v.get('Note')) or None}
        for field, limit in {'number': 100, 'category': 100, 'subcategory': 100, 'company': 150, 'insured_subject': 255, 'notes': 10000}.items():
            if data[field] and len(data[field]) > limit:
                problems.add(field+'_troppo_lungo')
        for source, target in [('Efetto', 'starts_on'), ('Scadenza', 'expires_on'), ('Data Rata Attuale', 'installment_on'), ('Data Rata Futura', 'next_installment_on')]:
            try:
                data[target] = parse_date(v.get(source))
            except ValueError:
                data[target] = None
                problems.add(target+'_non_valida')
        if not data['starts_on'] or not data['expires_on']:
            problems.add('date_obbligatorie_mancanti')
        elif data['expires_on'] < data['starts_on']:
            problems.add('scadenza_precedente_decorrenza')
        try:
            data['premium'] = money(v.get('Premio annuale'))
            data['installment_premium'] = money(v.get('Premio Rata'))
        except (ValueError, InvalidOperation):
            problems.add('premio_non_valido')
        data['frequency'] = clean(v.get('Frazionamento'))
        data['tacit_renewal'] = clean(v.get('Tacito Rinnovo'))
        data['protocols'] = sorted({clean(r['values'].get('Protocollo')) for r in rows})
        candidates = {'key': digest(['policy', f, company, number]), 'client_key': client_by_fiscal.get(f, {}).get('key'),
                      'source_rows': [r['row'] for r in rows], 'data': data, 'issues': sorted(problems),
                      'conflicting_fields': conflicts, 'status_review': True, 'result': 'da_verificare' if problems else 'candidata_con_stato_da_verificare'}
        policies.append(candidates)
        for code in problems:
            issue('policies', candidates['source_rows'], code)

    taxonomy = []
    for (b, s), count in sorted(classifications.items()):
        nb, ns, result, reason = classification(b, s)
        taxonomy.append({'source_branch': b, 'source_subbranch': s, 'branch': nb, 'subbranch': ns, 'rows': count, 'result': result, 'reason': reason})
    pairs = [{'source_company': c, 'source_agency': a, 'company': key(c), 'agency': key(a), 'rows': n,
              'result': 'esclusa' if key(c) == 'SPESE' else 'da_confermare' if key(c) in {'EUROPE', 'EUROPE ASSISTANCE', 'PRIMA', 'DUAL', 'BHITALIA'} else 'conservata',
              'reason': 'Voce amministrativa' if key(c) == 'SPESE' else 'Confermare la denominazione e il ruolo del soggetto' if key(c) in {'EUROPE', 'EUROPE ASSISTANCE', 'PRIMA', 'DUAL', 'BHITALIA'} else 'Nome originale conservato; agenzia unica con più compagnie'} for (c, a), n in sorted(company_pairs.items())]
    source_statuses = collections.Counter(clean(r['values']['Polizze']) for r in records)
    summary = {
        'version': 1, 'mode': 'preview_only',
        'counts': {'registry_rows': len(registry_rows), 'registry_groups': len(fiscal_groups), 'client_candidates': len(clients),
                   'clients_without_record_issues': sum(not c['issues'] for c in clients), 'clients_to_review': sum(bool(c['issues']) for c in clients),
                   'policy_rows': len(records), 'financial_rows_excluded': sum(e['reason'] == 'Voce amministrativa' for e in excluded),
                   'summary_rows_excluded': sum(e['reason'] != 'Voce amministrativa' for e in excluded), 'policy_groups': len(policies),
                   'policy_repeated_groups': len(duplicate_details), 'policy_candidates_without_record_issues': sum(not p['issues'] for p in policies),
                   'policy_groups_to_review': sum(bool(p['issues']) for p in policies), 'policies_needing_status': len(policies),
                   'clients_only_in_policies': len(missing), 'raw_branches': len({b for b, s in classifications}),
                   'raw_subbranches': len({s for b, s in classifications if s}), 'companies_after_expenses': len({p['company'] for p in pairs if p['result'] != 'esclusa'}),
                   'agencies_after_formatting_and_expenses': len({p['agency'] for p in pairs if p['result'] != 'esclusa'})},
        'taxonomy': taxonomy, 'company_agencies': pairs, 'source_statuses': dict(source_statuses),
        'issue_counts': [{'code': c, 'records': n} for c, n in sorted(collections.Counter(i['code'] for i in issues).items())],
        'decisions': [
            {'topic': 'Agenzie plurimandatarie', 'state': 'definita', 'text': 'Una sola anagrafica agenzia collegata a più compagnie, come confermato.'},
            {'topic': 'Stato delle polizze', 'state': 'aperta', 'text': 'Archivio e OLD conservati; significato da verificare. Nessuno stato in vigore assegnato automaticamente.'},
            {'topic': 'Catalogo rami e sottorami', 'state': 'aperta', 'text': 'Confermare gli accorpamenti proposti e le sigle generiche; i sottorami mancanti restano vuoti.'},
            {'topic': 'Compagnie', 'state': 'aperta', 'text': 'Verificare EUROPE/EUROPE ASSISTANCE e il ruolo di PRIMA, DUAL, BHITALIA. Non unificati automaticamente.'},
            {'topic': 'Righe ripetute', 'state': 'aperta', 'text': 'Stesso numero con date, rate, intestatari o garanzie diverse: conservare tutte le righe e decidere storico/rinnovi prima di importare.'},
            {'topic': 'Identità del cliente', 'state': 'aperta', 'text': 'Verificare identificativi non validi, nomi discordanti, clienti mancanti e codici numerici: CF e partita IVA non sono intercambiabili.'},
        ],
        'mapping': [{'source': s, 'target': 'clients.'+t, 'rule': 'Data ISO; originale conservato' if t == 'birth_date' else 'Testo; zeri iniziali conservati'} for s, t in CLIENT_FIELDS.items()] + [
            {'source': FISCAL, 'target': 'Identità cliente', 'rule': '16 caratteri: CF con controllo formale. 11 cifre: distinguere CF/P.IVA e tipo soggetto prima del caricamento.'},
            {'source': 'Compagnia / Agenzia', 'target': 'Fornitori e collegamenti', 'rule': 'Unica agenzia con più compagnie; nessuna gerarchia di subagenzia dedotta.'},
            {'source': 'Ramo / Sottoramo', 'target': 'Tab polizze: category / subcategory', 'rule': 'Originali conservati; proposte da approvare. Prodotto distinto dalla copertura.'},
            {'source': 'Efetto / Scadenza', 'target': 'Decorrenza / scadenza contratto', 'rule': 'Date italiane convertite in ISO; non usare le date rata come scadenza della polizza.'},
            {'source': 'Premio annuale / Premio Rata', 'target': 'Premio annuale / dettaglio rata', 'rule': 'Valori distinti. Le rate non diventano nuove polizze.'},
            {'source': 'Documenti Polizza / Doc. Compagnia', 'target': 'Riferimenti originali', 'rule': 'Nessun allegato o consenso creato senza documento effettivo.'},
            {'source': 'Interesse Assic.', 'target': 'Soggetto/bene assicurato', 'rule': 'Testo conservato; nessuna targa o relazione creata per supposizione.'},
            {'source': 'Pagamenti / provvigioni / imponibili', 'target': 'Archivio sorgente', 'rule': 'Conservati nella fonte; non si riattivano i moduli amministrativi rimossi.'},
        ],
    }
    return summary, clients, policies, issues, excluded, duplicate_details


def write_json(path, value):
    content = json.dumps(value, ensure_ascii=False, indent=2) + '\n'
    temp = path.with_suffix('.tmp')
    temp.write_bytes(content.encode('utf8'))
    temp.replace(path)
    return hashlib.sha256(content.encode()).hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--registry', required=True, type=Path)
    parser.add_argument('--policies', required=True, type=Path)
    parser.add_argument('--output', type=Path, default=ROOT/'storage/app/private/insurance-import')
    args = parser.parse_args()
    out = args.output.resolve()
    if not out.is_relative_to((ROOT/'storage/app/private').resolve()):
        raise ValueError('Il lotto deve restare nella cartella privata del gestionale.')
    reg_meta, reg = read_source(args.registry, 'clients')
    pol_meta, pol = read_source(args.policies, 'policies')
    result = prepare(reg, pol)
    summary = result[0]
    summary['sources'] = [reg_meta, pol_meta]
    summary['batch_key'] = digest([reg_meta['sha256'], pol_meta['sha256'], 1])
    summary['decisions'].append({'topic': 'Integrità file XLS', 'state': 'aperta' if any(m['tolerant_read'] for m in summary['sources']) else 'definita',
        'text': 'Contenitore OLE letto con tolleranza: verificare un nuovo salvataggio prima del caricamento definitivo.' if any(m['tolerant_read'] for m in summary['sources']) else 'Lettura ordinaria completata.'})
    out.mkdir(parents=True, exist_ok=True)
    names = ['summary.json', 'clients.json', 'policies.json', 'issues.json', 'excluded.json', 'repeated-policies.json']
    hashes = {name: write_json(out/name, value) for name, value in zip(names, result)}
    raw = out/'source-rows.jsonl'
    with raw.open('w', encoding='utf8') as stream:
        for row in reg + pol:
            stream.write(json.dumps(row, ensure_ascii=False)+'\n')
    hashes[raw.name] = hashlib.sha256(raw.read_bytes()).hexdigest()
    write_json(out/'manifest.json', {'version': 1, 'batch_key': summary['batch_key'], 'files': hashes})
    print(json.dumps({'counts': summary['counts'], 'source_statuses': summary['source_statuses'], 'issues': summary['issue_counts'], 'mode': summary['mode']}, ensure_ascii=True))


if __name__ == '__main__':
    main()
