import test from 'node:test';
import assert from 'node:assert/strict';
import {selectionProblem} from '../../resources/js/quote-comparison.js';

test('la selezione richiede due offerte dello stesso rischio e rispetta il limite',()=>{
    assert.match(selectionProblem([]),/almeno due/);
    assert.equal(selectionProblem([{caseId:1},{caseId:'1'}]),'');
    assert.match(selectionProblem([{caseId:1},{caseId:2}]),/pratiche diverse/);
    assert.match(selectionProblem(Array.from({length:7},()=>({caseId:1}))),/sei offerte/);
});
