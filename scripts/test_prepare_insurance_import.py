import unittest
import hashlib
from pathlib import Path
import uuid
from prepare_insurance_import import clean, fiscal, fiscal_valid, parse_date, money, prepare, write_json, CLIENT_FIELDS, FISCAL, digest, ROOT


class ImportPreparationTest(unittest.TestCase):
    def test_integrity_hash_matches_written_bytes_on_windows(self):
        path = ROOT/'work'/('import-hash-test-'+uuid.uuid4().hex+'.json')
        try:
            expected = write_json(path, {'test': 'Dati dimostrativi con accenti: città'})
            self.assertEqual(hashlib.sha256(path.read_bytes()).hexdigest(), expected)
        finally:
            if path.is_file(): path.unlink()

    def registry(self, row=8, **changes):
        values = {name: '' for name in CLIENT_FIELDS}
        values.update({'Contraente': 'Cliente dimostrativo', FISCAL: 'RSSMRA85T10A562S', 'CAP': '00100'})
        values.update(changes)
        return {'source': 'clients', 'row': row, 'source_id': digest(['demo',row]), 'values': values, 'issues': []}

    def policy(self, row=8, **changes):
        values = {'Contraente': 'Cliente dimostrativo', 'Titolo': 'Sig.', FISCAL: 'RSSMRA85T10A562S', 'Numero Polizza': '00012',
                  'Compagnia': 'COMPAGNIA DEMO', 'Agenzia': 'AGENZIA DEMO', 'Ramo': 'INFORTUNI', 'Sottoramo': '', 'Polizze': 'OLD',
                  'Efetto': '01/01/2025', 'Scadenza': '01/01/2026', 'Interesse Assic.': '', 'Premio annuale': 100.10, 'Premio Rata': 50.05}
        values.update(changes)
        return {'source': 'policies', 'row': row, 'source_id': digest(['demo',row]), 'values': values, 'issues': []}

    def test_values_preserve_leading_zeros_and_validate_dates_and_money(self):
        self.assertEqual(fiscal(' 00123456789 '), '00123456789')
        self.assertEqual(clean('00012'), '00012')
        self.assertTrue(fiscal_valid('RSSMRA85T10A562S'))
        self.assertFalse(fiscal_valid('RSSMRA85T10A562A'))
        self.assertTrue(fiscal_valid('12345678903'))
        self.assertEqual(parse_date('29/02/2024'), '2024-02-29')
        with self.assertRaises(ValueError): parse_date('29/02/2025')
        self.assertEqual(money('1.234,56'), '1234.56')
        with self.assertRaises(ValueError): money('-3')

    def test_repeated_policies_are_not_resolved_to_an_arbitrary_winner(self):
        result = prepare([self.registry()], [self.policy(), self.policy(9, **{'Scadenza': '01/01/2027'})])
        candidate = result[2][0]
        self.assertEqual(candidate['source_rows'], [8, 9])
        self.assertIn('numero_polizza_ripetuto', candidate['issues'])
        self.assertIn('Scadenza', candidate['conflicting_fields'])
        self.assertIsNone(candidate['data']['status'])
        self.assertTrue(candidate['status_review'])
        self.assertEqual(len(result[2]), 1)

    def test_financial_rows_are_preserved_separately_and_not_imported_as_policies(self):
        result = prepare([self.registry()], [self.policy(), self.policy(9, **{'Ramo': 'SPESE EMISSIONE'}), self.policy(10, **{'Ramo': 'ASSIMEDICI', 'Sottoramo': 'FATTURA'})])
        self.assertEqual(len(result[2]), 1)
        self.assertEqual(result[0]['counts']['financial_rows_excluded'], 2)
        self.assertEqual([r['row'] for r in result[4]], [9,10])

    def test_client_conflicts_and_missing_clients_block_policy_readiness(self):
        result = prepare([self.registry(), self.registry(9, Contraente='Altro nominativo dimostrativo')], [self.policy(), self.policy(10, **{FISCAL: '12345678903'})])
        self.assertEqual(result[0]['counts']['clients_only_in_policies'], 1)
        self.assertTrue(all('cliente_da_verificare' in p['issues'] for p in result[2]))
        self.assertEqual(len(result[1]), 2)

    def test_repeated_runs_have_stable_keys_and_do_not_merge_policy_owners(self):
        rows = [self.policy(), self.policy(9, **{FISCAL: '12345678903'})]
        first = prepare([self.registry()], rows)
        second = prepare([self.registry()], list(reversed(rows)))
        self.assertEqual([p['key'] for p in first[2]], [p['key'] for p in second[2]])
        self.assertEqual(len(first[2]), 2)
        self.assertTrue(all('stessa_polizza_intestatari_diversi' in p['issues'] for p in first[2]))

    def test_blank_subbranch_and_original_status_remain_unresolved(self):
        candidate = prepare([self.registry()], [self.policy()])[2][0]
        self.assertIsNone(candidate['data']['subcategory'])
        self.assertIsNone(candidate['data']['status'])
        self.assertEqual(candidate['data']['source_status'], 'OLD')
        self.assertEqual(candidate['data']['number'], '00012')


if __name__ == '__main__':
    unittest.main()
