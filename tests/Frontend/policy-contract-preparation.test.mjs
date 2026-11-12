import test from 'node:test';
import assert from 'node:assert/strict';
import {contractTemplateApplies,matchingContractClient} from '../../resources/js/policy-contract-preparation.js';

test('module suggestions follow the contracting party, branch and subbranch without hiding other approved choices',()=>{
    const policy={scope:'policy',client_type:'person',branch:'RC auto',subbranch:'Moto'};
    assert.equal(contractTemplateApplies(policy,{client_type:'person'},'RC auto','Moto'),true);
    assert.equal(contractTemplateApplies(policy,{client_type:'company'},'RC auto','Moto'),false);
    assert.equal(contractTemplateApplies(policy,{client_type:'person'},'RC auto','Auto'),false);
    assert.equal(contractTemplateApplies(policy,{},'RC auto','Moto'),false);
    assert.equal(contractTemplateApplies({scope:'client',client_type:'all'},{client_type:'company'},'Incendio',''),true);
});

test('an existing client is linked automatically only for a single exact fiscal and name match',()=>{
    const read={name:'Contraente di collaudo',tax_code:'RSSMRA80A01H501U',vat_number:'01234567897'};
    const candidate={id:5,name:'CONTRAENTE DI COLLAUDO',fields:['tax_code','vat_number']};
    const result={status:'duplicate',errors:{},matches:[candidate]};
    assert.equal(matchingContractClient(result,read,read),candidate);
    assert.equal(matchingContractClient({...result,status:'conflict'},read,read),null);
    assert.equal(matchingContractClient({...result,errors:{tax_code:['Non valido']}},read,read),null);
    assert.equal(matchingContractClient({...result,matches:[candidate,candidate]},read,read),null);
    assert.equal(matchingContractClient({...result,matches:[{...candidate,fields:['vat_number']}]},read,read),null);
    assert.equal(matchingContractClient(result,read,{...read,tax_code:'MODIFICATO'}),null);
    assert.equal(matchingContractClient(result,read,{...read,name:'Altro contraente'}),null);
    assert.equal(matchingContractClient(result,{name:read.name},{name:read.name}),null);
});
