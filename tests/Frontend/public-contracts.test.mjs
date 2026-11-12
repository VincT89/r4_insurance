import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {parsePolicyContract} from '../../resources/js/policy-contract-parser.js';
import {policyContractPageText} from '../../resources/js/policy-contract-text.js';

const companies=['ALLIANZ','ALLIANZ VIVA','AXA','PRIMA','TUA','EUROPE','GROUPAMA','UNIPOL','ZURICH','NOBIS COMPAGNIA DI ASSICURAZIONI SPA'];

for(const name of ['allianz-auto','allianz-infortuni','unipol-rct','zurich-incendio','nobis-collettiva','nobis-facsimile']) {
    test('reads original PDF coordinates: '+name,async()=> {
        const fixture=JSON.parse(await readFile(new URL('../Fixtures/Contracts/'+name+'.json',import.meta.url),'utf8'));
        const text=fixture.pages.map(page=>policyContractPageText(page.items)).join('\n\f\n');
        const parsed=parsePolicyContract(text,{companies,categories:['RC auto','RC GENERALE','INFORTUNI','INCENDIO'],subcategories:{'RC auto':['AUTO','MOTO'],'INFORTUNI':['INFORTUNI'],'INCENDIO':['COMMERCIO','INCENDIO']}});
        for(const [field,value] of Object.entries(fixture.expected)) assert.equal(parsed.values[field],value,field+' — '+fixture.source);
        for(const field of fixture.empty) assert.equal(parsed.values[field],undefined,field+' must remain for manual review');
        assert.notEqual(parsed.client.vat_number,'01333250320');
        assert.notEqual(parsed.client.vat_number,'05380900968');
        assert.equal(parsed.client.email,undefined);
        if(name==='allianz-infortuni') { assert.ok(parsed.notes.premium.includes('2 annualità')); assert.equal(parsed.values.category,'INFORTUNI'); }
        if(name==='allianz-auto') { assert.equal(parsed.values.category,'RC auto'); assert.equal(parsed.values.subcategory,'AUTO'); assert.equal(parsed.values.tacit_renewal,'0'); }
        if(name==='unipol-rct') { assert.equal(parsed.values.category,'RC GENERALE'); assert.equal(parsed.client.name,"UNIVERSITA' STUDI CATANIA"); assert.equal(parsed.client.tax_code,'02772010878'); }
        if(name==='zurich-incendio') assert.equal(parsed.values.category,'INCENDIO');
        if(name==='nobis-facsimile') {
            assert.deepEqual(parsed.client,{});
            assert.deepEqual(parsed.vehicles,[]);
            assert.equal(parsed.values.number,undefined);
            assert.ok(parsed.warnings.some(message=>message.includes('facsimile')));
        }
    });
}

test('ordinary words never select insurers with those names',()=> {
    const parsed=parsePolicyContract('La tua polizza deve essere pagata prima della scadenza.\nContraente: Persona di collaudo\nNumero polizza: AAA123',{companies});
    assert.equal(parsed.values.company,undefined);
});

test('the original private RCA layout keeps the discounted total and the policyholder column',async()=> {
    const fixture=JSON.parse(await readFile(new URL('../Fixtures/Contracts/private-rca-redacted.json',import.meta.url),'utf8'));
    const parsed=parsePolicyContract(policyContractPageText(fixture.pages[0].items),{companies,categories:['RC auto'],subcategories:{'RC auto':['AUTO']}});
    for(const [field,value] of Object.entries({number:'RCA-RISERVATA-001',starts_on:'2026-10-25',expires_on:'2027-10-25',premium:'536.46',first_installment_premium:'536.46',installment_premium:'536.46',payment_frequency:'Annuale',merit_class_cu:'10',merit_class_internal:'10',category:'RC auto',subcategory:'AUTO',company:'PRIMA'})) assert.equal(parsed.values[field],value,field);
    assert.equal(parsed.client.name,'[CONTRAENTE RISERVATO]');
    assert.equal(parsed.client.tax_code,'RSSMRA80A01H501U');
    assert.equal(parsed.client.client_type,'person');
    assert.equal(parsed.client.postal_code,'00000');
    assert.equal(parsed.client.province,'XX');
    assert.equal(parsed.client.email,undefined);
    assert.equal(parsed.client.phone,undefined);
    assert.equal(parsed.values.payment_method,undefined);
    assert.equal(parsed.values.tacit_renewal,undefined);
    assert.deepEqual(parsed.vehicles,[{plate:'XX000XX'}]);
    assert.ok(parsed.values.insured_subject.includes('Volkswagen Golf'));
    assert.ok(parsed.notes.company.includes('Great Lakes Insurance SE'));
});

test('an empty policyholder column cannot borrow the owner or driver identity',async()=> {
    const fixture=JSON.parse(await readFile(new URL('../Fixtures/Contracts/private-rca-redacted.json',import.meta.url),'utf8'));
    const {contractor,owner,top,bottom}=fixture.columns;
    const items=fixture.pages[0].items.filter(item=>!(item.transform[5]<top && item.transform[5]>bottom && item.transform[4]>=contractor-3 && item.transform[4]<owner-3));
    const parsed=parsePolicyContract(policyContractPageText(items),{companies});
    assert.equal(parsed.client.name,undefined);
    assert.equal(parsed.client.tax_code,undefined);
    assert.equal(parsed.values.premium,'536.46');
});

test('actual Groupama scan keeps redacted data empty and requires a choice between its two policies',async()=> {
    const fixture=JSON.parse(await readFile(new URL('../Fixtures/Contracts/groupama-ocr.json',import.meta.url),'utf8'));
    const parsed=parsePolicyContract(fixture.text,{companies});
    assert.equal(parsed.values.company,'GROUPAMA');
    assert.equal(parsed.values.payment_frequency,'Annuale');
    assert.deepEqual(parsed.candidates.number,['108310490','108310489']);
    for(const field of ['number','starts_on','expires_on','premium']) assert.equal(parsed.values[field],undefined);
    assert.ok(parsed.warnings.some(message=>message.includes('Numero polizza')));
});

test('blank columns cannot move an installment date into the policy expiry',()=> {
    const item=(str,x,y)=>({str,transform:[10,0,0,10,x,y]});
    const text=policyContractPageText([item('EFFETTO POLIZZA',20,700),item('SCADENZA POLIZZA',160,700),item('SCADENZA PRIMA RATA',310,700),item('01/01/2026',20,680),item('01/07/2026',310,680)]);
    const parsed=parsePolicyContract(text,{companies});
    assert.equal(parsed.values.starts_on,'2026-01-01');
    assert.equal(parsed.values.expires_on,undefined);
});
