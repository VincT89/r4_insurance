import test from 'node:test';
import assert from 'node:assert/strict';
globalThis.document = { addEventListener() {} };
const { matchesFilter, columnType } = await import('../resources/js/table-tools.js');
const check = (cell, rule) => matchesFilter([cell], { column: 0, type: 'text', operator: 'eq', ...rule });
test('text identifiers preserve leading zeros and support case-insensitive matching', () => {
    assert.equal(check('00123', { value: '123' }), false);
    assert.equal(check('Agenzia AXA', { operator: 'contains', value: 'axa' }), true);
    assert.equal(check('', { operator: 'empty' }), true);
    assert.equal(check('0001', { operator: 'filled' }), true);
});
test('Italian amounts and intervals are compared numerically', () => {
    assert.equal(check('1.234,56 €', { type:'number', operator:'gt', value:'1000' }), true);
    assert.equal(check('1.234,56 €', { type:'number', operator:'between', value:'1000', to:'1300' }), true);
    assert.equal(check('N/D', { type:'number', operator:'lt', value:'1000' }), false);
});
test('Italian and ISO dates compare correctly without accepting invalid dates', () => {
    assert.equal(check('14/10/2026', { type:'date', operator:'gte', value:'2026-10-01' }), true);
    assert.equal(check('31/02/2026', { type:'date', operator:'lt', value:'2026-11-01' }), false);
    assert.equal(check('14/10/2026 10:15', { type:'date', operator:'eq', value:'2026-10-14' }), true);
});
test('filter input types follow the column without interpreting identifiers as numbers', () => {
    assert.equal(columnType('Numero polizza'),'text');
    assert.equal(columnType('Telefono'),'text');
    assert.equal(columnType('Premio annuale'),'number');
    assert.equal(columnType('Scadenza'),'date');
    assert.equal(columnType('Data evento'),'date');
});
