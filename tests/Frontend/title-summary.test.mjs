import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { addEventListener() {} };
const { titleTotals } = await import('../../resources/js/title-summary.js');

test('filtered totals separate due-date groups and exclude closed or settled titles', () => {
    const totals = titleTotals([
        { titleSummaryGroup: 'overdue', titleBalanceCents: '20000' },
        { titleSummaryGroup: 'due_soon', titleBalanceCents: '30000' },
        { titleSummaryGroup: 'due_soon', titleBalanceCents: '5000' },
        { titleSummaryGroup: 'future', titleBalanceCents: '70000' },
        { titleSummaryGroup: '', titleBalanceCents: '90000' },
        { titleSummaryGroup: 'overdue', titleBalanceCents: '0' },
    ]);
    assert.deepEqual(totals, { overdue: { count: 1, cents: 20000n }, due_soon: { count: 2, cents: 35000n }, future: { count: 1, cents: 70000n } });
    assert.equal(Object.values(totals).reduce((sum, group) => sum + group.cents, 0n), 125000n);
});

test('a filter with no matching titles clears all totals', () => {
    assert.deepEqual(titleTotals([]), { overdue: { count: 0, cents: 0n }, due_soon: { count: 0, cents: 0n }, future: { count: 0, cents: 0n } });
});

test('monetary totals retain integer precision and reject malformed balances', () => {
    assert.equal(titleTotals([{ titleSummaryGroup: 'future', titleBalanceCents: '9007199254740993' }]).future.cents, 9007199254740993n);
    assert.throws(() => titleTotals([{ titleSummaryGroup: 'overdue', titleBalanceCents: '12.30' }]), /Importo del titolo/);
});
