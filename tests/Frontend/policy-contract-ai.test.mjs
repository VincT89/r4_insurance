import test from 'node:test';
import assert from 'node:assert/strict';
import {interpretAiContract} from '../../resources/js/policy-contract-ai.js';

const catalog={companies:['ALLIANZ','PRIMA','UNIPOL'],categories:['RC auto','RC GENERALE'],subcategories:{'RC auto':['AUTO','MOTO']},agencies:{ALLIANZ:[{id:7,name:'Agenzia collegata',code:'00123'}]}};
const field=(key,value,extra={})=>({key,value,page:1,quote:'Citazione di collaudo dal documento',certainty:'certain',...extra});
const document=(fields,extra={})=>({document_kind:'policy',fields,warnings:[],...extra});

test('AI data use one common schema with policyholder evidence and exact premiums',()=> {
    const parsed=interpretAiContract(document([
        field('number','RCA-001'),field('company','Great Lakes Insurance SE'),field('distributor','Prima Assicurazioni S.p.A.'),
        field('category','RCA'),field('subcategory','AUTO'),field('starts_on','2026-10-25'),field('expires_on','2027-10-25'),
        field('annual_premium','536.46'),field('payment_frequency','Annuale'),field('merit_class_cu','10'),field('tacit_renewal','0'),
        field('client.name','[CONTRAENTE RISERVATO]'),field('client.client_type','person'),field('client.tax_code','RSSMRA80A01H501U'),
        field('vehicle.plate','XX000XX'),field('vehicle.make','Marca di collaudo'),field('vehicle.model','Modello di collaudo'),
    ]),catalog);
    assert.equal(parsed.values.company,'PRIMA');
    assert.ok(parsed.notes.company.includes('Great Lakes Insurance SE'));
    assert.equal(parsed.values.category,'RC auto');
    assert.equal(parsed.values.premium,'536.46');
    assert.equal(parsed.values.merit_class_cu,'10');
    assert.equal(parsed.values.tacit_renewal,'0');
    assert.equal(parsed.client.tax_code,'RSSMRA80A01H501U');
    assert.equal(parsed.client.name,'[CONTRAENTE RISERVATO]');
    assert.equal(parsed.values.payment_method,undefined);
    assert.deepEqual(parsed.vehicles,[{plate:'XX000XX',make:'Marca di collaudo',model:'Modello di collaudo'}]);
});

test('uncertain or conflicting AI facts are never silently selected',()=> {
    const parsed=interpretAiContract(document([field('number','A001'),field('number','B002'),field('premium','100.00',{certainty:'uncertain'}),field('client.tax_code','RSSMRA80A01H501U',{quote:''}),field('client.name','[RISERVATO]')]),catalog);
    assert.equal(parsed.values.number,undefined);
    assert.equal(parsed.values.premium,undefined);
    assert.equal(parsed.client.tax_code,undefined);
    assert.ok(parsed.warnings.some(value=>value.includes('valori diversi')));
});

test('AI extraction preserves the first installment difference and derives only complete periods',()=> {
    const parsed=interpretAiContract(document([field('company','UNIPOL'),field('starts_on','2023-07-31'),field('expires_on','2026-07-31'),field('payment_frequency','Semestrale'),field('first_installment_premium','27475.00'),field('installment_premium','27475.09')]),catalog);
    assert.equal(parsed.values.premium,'164850.45');
    assert.equal(parsed.values.first_installment_premium,'27475.00');
    assert.equal(parsed.values.installment_premium,'27475.09');
    assert.ok(parsed.notes.premium.includes('5 rate successive'));
});

test('catalogue links cannot select an agency belonging to another insurer',()=> {
    const allianz=interpretAiContract(document([field('company','ALLIANZ S.p.A.'),field('agency.code','123')]),catalog);
    assert.equal(allianz.values.supplier_agency_id,'7');
    const unipol=interpretAiContract(document([field('company','UNIPOL'),field('agency.code','123')]),catalog);
    assert.equal(unipol.values.supplier_agency_id,undefined);
});

test('conditions, facsimiles and multiple policies require document review rather than example data',()=> {
    for(const kind of ['conditions','quotation','multiple','unknown']) {
        const parsed=interpretAiContract(document([field('number','EXAMPLE001'),field('premium','100.00')],{document_kind:kind}),catalog);
        assert.deepEqual(parsed.values,{});
        assert.deepEqual(parsed.client,{});
        assert.ok(parsed.warnings.length);
    }
});

test('an uncertain policyholder name preserves independently evidenced registry fields for review',()=>{
    const parsed=interpretAiContract(document([field('client.name','Denominazione ripetuta',{certainty:'uncertain'}),field('client.client_type','company'),field('client.tax_code','02772010878'),field('client.address','Indirizzo del documento pubblico')]),catalog);
    assert.equal(parsed.client.name,undefined);
    assert.equal(parsed.client.client_type,'company');
    assert.equal(parsed.client.tax_code,'02772010878');
    assert.equal(parsed.client.address,'Indirizzo del documento pubblico');
    assert.ok(parsed.warnings.some(warning=>warning.startsWith('Nome del contraente:')));
});

test('conflicting installments cannot become a guessed triennial total or an ordinary premium copied from the first',()=>{
    const parsed=interpretAiContract(document([field('starts_on','2023-07-31'),field('expires_on','2026-07-31'),field('annual_premium','54950.00'),field('first_installment_premium','27475.00'),field('installment_premium','27475.09',{certainty:'uncertain'}),field('payment_frequency',null,{certainty:'uncertain',page:null,quote:''}),field('premium',null,{certainty:'uncertain',page:null,quote:''})]),catalog);
    assert.equal(parsed.values.premium,undefined);
    assert.equal(parsed.values.installment_premium,undefined);
    assert.equal(parsed.values.payment_frequency,undefined);
    assert.equal(parsed.values.first_installment_premium,'27475.00');
});
