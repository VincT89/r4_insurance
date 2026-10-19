import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { addEventListener() {} };
const { tableExportUrl } = await import('../../resources/js/table-tools.js');

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
