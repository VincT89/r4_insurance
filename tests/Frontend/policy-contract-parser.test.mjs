import test from 'node:test';
import assert from 'node:assert/strict';
import {parsePolicyContract} from '../../resources/js/policy-contract-parser.js';
import {policyContractPageText} from '../../resources/js/policy-contract-text.js';

const catalog = {companies:['Compagnia dimostrativa'], categories:['RC auto'], subcategories:{'RC auto':['Autovetture']}, agencies:{'COMPAGNIA DIMOSTRATIVA':[{id:7, name:'Agenzia dimostrativa'}]}};

test('reads labelled contract data and number without confusing the quotation reference', () => {
    const result = parsePolicyContract(`Preventivo n. 100
Numero polizza: DEMO/2026-123
Compagnia: Compagnia dimostrativa
Agenzia: Agenzia dimostrativa
Ramo: RC auto
Sottoramo: Autovetture
Prodotto: Auto completa
Decorrenza: 02/11/2026
Scadenza polizza: 02/11/2027
Scadenza rata: 02/05/2027
Premio annuo lordo: 1.200,00
Premio rata: 600,00
Frazionamento: semestrale
Modalità di pagamento: Bonifico a Intermediario
Classe universale: 4`, catalog);
    assert.equal(result.values.number, 'DEMO/2026-123');
    assert.equal(result.values.company, catalog.companies[0]);
    assert.equal(result.values.supplier_agency_id, '7');
    assert.equal(result.values.category, 'RC auto');
    assert.equal(result.values.subcategory, 'Autovetture');
    assert.equal(result.values.expires_on, '2027-11-02');
    assert.equal(result.values.premium, '1200.00');
    assert.equal(result.values.installment_premium, '600.00');
    assert.equal(result.values.payment_frequency, 'Semestrale');
    assert.equal(result.values.payment_method, 'Bonifico a Intermediario');
    assert.equal(result.values.merit_class_cu, '4');
});

test('ambiguous policy numbers and dates are not guessed', () => {
    const result = parsePolicyContract('Polizza n. AA001\nPolizza n. BB002\nDecorrenza 31/02/2026\nScadenza rata 10/06/2026');
    assert.equal(result.values.number, undefined);
    assert.equal(result.values.starts_on, undefined);
    assert.equal(result.values.expires_on, undefined);
    assert.equal(result.warnings.length, 1);
});

test('extracts contracting party separately from insurer and vehicle owner', () => {
    const result = parsePolicyContract(`Compagnia: Compagnia dimostrativa
Partita IVA: 12345678901
Contraente: Persona Dimostrativa
Codice fiscale: RSSMRA80A01H501U
Email: persona@example.test
Indirizzo: Via Dimostrativa 10
CAP: 80100
Comune: Napoli
Proprietario
Nome e cognome: Altra Persona
Codice fiscale: VRDLGI80A01H501O
Decorrenza 02/11/2026`, catalog);
    assert.equal(result.client.name, 'Persona Dimostrativa');
    assert.equal(result.client.tax_code, 'RSSMRA80A01H501U');
    assert.equal(result.client.vat_number, undefined);
    assert.equal(result.client.email, 'persona@example.test');
    assert.equal(result.client.city, 'Napoli');
    assert.equal(result.client.postal_code, '80100');
});

test('never derives ownership or a client from an unlabelled fiscal identifier', () => {
    assert.deepEqual(parsePolicyContract('Intestatario veicolo: Altra Persona\nCodice fiscale: RSSMRA80A01H501U\nTarga AA123BB').client, {});
});

test('does not turn annual premium into total for a shorter contract', () => {
    const result = parsePolicyContract('Decorrenza: 01/01/2026\nScadenza polizza: 01/07/2026\nPremio annuo: 1000,00');
    assert.equal(result.values.premium, undefined);
    assert.ok(result.warnings.some(message => message.includes('durata diversa')));
});

test('does not guess an insurer or agency when more than one matches', () => {
    const result = parsePolicyContract('Compagnia A\nCompagnia B\nAgenzia: Agenzia dimostrativa', {...catalog, companies:['Compagnia A','Compagnia B']});
    assert.equal(result.values.company, undefined);
    assert.equal(result.values.supplier_agency_id, undefined);
});

test('reads number on the following line and keeps repeated identical values', () => {
    const result = parsePolicyContract('N. polizza\n001/2026\nNumero polizza: 001/2026\nDecorrenza: 2026-11-02\nPremio totale di polizza: EUR 1 200,10');
    assert.equal(result.values.number, '001/2026');
    assert.equal(result.values.starts_on, '2026-11-02');
    assert.equal(result.values.premium, '1200.10');
});

test('reads the combined branch layout and expected payment method used by the existing policy PDFs', () => {
    const result = parsePolicyContract('Ramo: RC auto / Autovetture\nMetodo previsto: Bonifico a Intermediario', catalog);
    assert.equal(result.values.category, 'RC auto');
    assert.equal(result.values.subcategory, 'Autovetture');
    assert.equal(result.values.payment_method, 'Bonifico a Intermediario');
});

test('keeps an explicit total for a short contract even when an annual premium is also printed', () => {
    const result = parsePolicyContract('Decorrenza: 01/01/2026\nScadenza polizza: 01/07/2026\nPremio annuo: 1000,00\nPremio totale del contratto: 540,00');
    assert.equal(result.values.premium, '540.00');
    assert.deepEqual(result.warnings, []);
});

test('the branch precedes the slash even when the subbranch is also present as a separate catalog branch', () => {
    const result = parsePolicyContract('Ramo: RC auto / AUTO', {...catalog,categories:['AUTO','RC auto'],subcategories:{'RC auto':['AUTO']}});
    assert.equal(result.values.category, 'RC auto');
    assert.equal(result.values.subcategory, 'AUTO');
    assert.deepEqual(result.warnings, []);
});

test('reads policy dates with the printed hour without using the installment date', () => {
    const result = parsePolicyContract('Effetto polizza: dalle ore 24:00 del 05/11/2026\nScadenza: alle ore 24 del 05/11/2027\nScadenza rata: 05/05/2027');
    assert.equal(result.values.starts_on, '2026-11-05');
    assert.equal(result.values.expires_on, '2027-11-05');
});

test('keeps conflicting totals visible and does not replace them with the annual premium', () => {
    const result = parsePolicyContract('Premio totale: 600,00\nPremio totale: 700,00\nPremio annuo: 1200,00');
    assert.equal(result.values.premium, undefined);
    assert.deepEqual(result.candidates.premium, ['600.00','700.00']);
    assert.ok(result.warnings.some(message=>message.includes('valori diversi')));
});

test('warns when an existing document is marked as a draft or a test and does not infer its simulated premium', () => {
    const result = parsePolicyContract('DOCUMENTO DI PROVA - NON VALIDO\nNumero polizza: SA-2026-12\nPremio simulato: EUR 720,00');
    assert.equal(result.values.number, 'SA-2026-12');
    assert.equal(result.values.premium, undefined);
    assert.ok(result.warnings.some(message=>message.includes('contratto definitivo')));
});

const token = (str,x,y)=>({str,transform:[10,0,0,10,x,y]});

test('reads a PDF table with aligned headers without relying on the internal text order', () => {
    const text = policyContractPageText([
        token('01/11/2027',200,680), token('Decorrenza',20,700), token('Premio totale',380,700),
        token('1.200,00',380,680), token('Scadenza polizza',200,700), token('01/11/2026',20,680),
    ]);
    const result = parsePolicyContract(text);
    assert.equal(result.values.starts_on, '2026-11-01');
    assert.equal(result.values.expires_on, '2027-11-01');
    assert.equal(result.values.premium, '1200.00');
});

test('does not pair PDF table columns when their positions or value counts do not agree', () => {
    const text = policyContractPageText([token('Decorrenza',20,700), token('Scadenza polizza',200,700), token('01/11/2026',85,680), token('01/11/2027',280,680)]);
    const result = parsePolicyContract(text);
    assert.equal(result.values.starts_on, undefined);
    assert.equal(result.values.expires_on, undefined);
});
