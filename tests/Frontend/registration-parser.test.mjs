import test from 'node:test';
import assert from 'node:assert/strict';
import {registrationCandidates,mergeRegistrationCandidates} from '../../resources/js/registration-parser.js';

test('reads labelled vehicle fields and ignores an unrelated date',()=>{
    const result=registrationCandidates('DOCUMENTO DI COLLAUDO\n(A) GA123BC\n(B) 15.04.2021\n(D.1) FIAT\n(D.3) PANDA\n(E) ZFA31200001234567\nScadenza 28.10.2031');
    assert.deepEqual(result,{plate:['GA123BC'],make:['FIAT'],model:['PANDA'],vin:['ZFA31200001234567'],registered_on:['2021-04-15']});
});
test('front and back share one identity but conflicting vehicles remain ambiguous',()=>{
    const first=registrationCandidates('(A) GA123BC\n(D.1) FIAT');
    const back=registrationCandidates('(A) GA123BC\n(E) ZFA31200001234567');
    assert.deepEqual(mergeRegistrationCandidates([first,back]).plate,['GA123BC']);
    assert.deepEqual(mergeRegistrationCandidates([first,registrationCandidates('(A) GB456DE')]).plate,['GA123BC','GB456DE']);
});
test('does not invent missing data or repair uncertain VIN letters',()=>{
    const result=registrationCandidates('Targa: NON LEGGIBILE\n(B) 31.02.2022\n(E) ZFAQ1200001234567\nNome: Mario\n14 CU');
    assert.deepEqual(result,{plate:[],make:[],model:[],vin:[],registered_on:[]});
});
test('reads spaced plate and field labels flattened onto one PDF line',()=>{
    const result=registrationCandidates('(A) GA 123 BC (B) 1/2/2020 (D.1) FIAT (D.3) PANDA (E) ZFA31200001234567');
    assert.deepEqual(result.plate,['GA123BC']);assert.deepEqual(result.registered_on,['2020-02-01']);assert.deepEqual(result.make,['FIAT']);
});
