import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { addEventListener() {} };
const { tableExportUrl } = await import('../../resources/js/table-tools.js');
const { collectionTotals, collectionMoney } = await import('../../resources/js/collection-totals.js');

test('exports keep the effective date defaults across midnight and pagination', () => {
    const url = tableExportUrl('https://example.test/collections?company=3&page=2', 'page', {from:'2026-10-31',to:'2026-10-31',company:3});
    assert.equal(url.searchParams.get('from'),'2026-10-31');
    assert.equal(url.searchParams.get('to'),'2026-10-31');
    assert.equal(url.searchParams.get('page'),'1');
    assert.equal(url.searchParams.get('company'),'3');
});

test('filtered accounting totals exclude reversals and keep exact cents across all rows', () => {
    const rows = Array.from({length:75}, () => ({collectionCents:'101',collectionRecipient:'our_agency',collectionVoided:'0'}));
    rows.push({collectionCents:'205',collectionRecipient:'company',collectionVoided:'0'});
    rows.push({collectionCents:'99999',collectionRecipient:'our_agency',collectionVoided:'1'});
    assert.deepEqual(collectionTotals(rows),{total:7780n,ours:7575n,direct:205n,receipts:7780n,refunds:0n});
    assert.deepEqual(collectionTotals([]),{total:0n,ours:0n,direct:0n,receipts:0n,refunds:0n});
    assert.equal(collectionMoney(123456n),'1.234,56 €');
    assert.equal(collectionMoney(0n),'0,00 €');
});

test('refunds stay separate and reduce the balance without counting voided refunds', () => {
    const rows=[{collectionCents:'10000',collectionRecipient:'company',collectionVoided:'0'},
        {collectionCents:'12050',collectionDirection:'refund',collectionRecipient:'our_agency',collectionVoided:'0'},
        {collectionCents:'9999',collectionDirection:'refund',collectionRecipient:'company',collectionVoided:'1'}];
    assert.deepEqual(collectionTotals(rows),{total:-2050n,ours:-12050n,direct:10000n,receipts:10000n,refunds:12050n});
    assert.equal(collectionMoney(-2050n),'-20,50 €');
});

test('normal pagination exports from the first page while preserving server filters', () => {
    const url = tableExportUrl('https://example.test/clients?page=2&status=active&q=Riva#results');
    assert.equal(url.pathname, '/clients');
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('_table_all'), '1');
    assert.equal(url.searchParams.get('status'), 'active');
    assert.equal(url.searchParams.get('q'), 'Riva');
    assert.equal(url.hash, '');
});

test('each named paginator resets its own page without changing other tables or filters', () => {
    for (const name of ['history_page', 'activities_page', 'files_page', 'emails_page']) {
        const url = tableExportUrl('https://example.test/claims/12?history_page=2&activities_page=3&files_page=4&emails_page=5&status=open', name);
        assert.equal(url.searchParams.get(name), '1');
        assert.equal(url.searchParams.get('_table_all'), '1');
        assert.equal(url.searchParams.get('status'), 'open');
        assert.equal(url.searchParams.has('page'), false);
        if (name !== 'history_page') assert.equal(url.searchParams.get('history_page'), '2');
        if (name !== 'activities_page') assert.equal(url.searchParams.get('activities_page'), '3');
        if (name !== 'files_page') assert.equal(url.searchParams.get('files_page'), '4');
    }
});
