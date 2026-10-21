import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { addEventListener() {} };
const { CommunicationSelection, withoutClaimed } = await import('../../resources/js/communication-selection.js');

test('selection survives page navigation and remains separated by agency', () => {
    const first = new CommunicationSelection('period-and-filters');
    first.add(1, 10); first.add(26, 10); first.add(51, 20);
    const next = new CommunicationSelection('period-and-filters', first.snapshot(100), 200);
    assert.deepEqual(next.forAgency(10), ['1','26']);
    assert.deepEqual(next.forAgency(20), ['51']);
    next.items.delete('1'); next.add(27, 10);
    const restored = new CommunicationSelection('period-and-filters', next.snapshot(200), 300);
    assert.deepEqual(restored.forAgency(10), ['26','27']);
});

test('changed server filters or an expired selection cannot silently submit hidden old rows', () => {
    const original = new CommunicationSelection('scope-one'); original.add(4, 2);
    const saved = original.snapshot(100);
    assert.equal(new CommunicationSelection('scope-two', saved, 200).items.size, 0);
    assert.equal(new CommunicationSelection('scope-one', saved, saved.expires).items.size, 0);
});

test('selection rejects invalid identifiers, duplicates and more than 500 rows per agency', () => {
    const selection = new CommunicationSelection('scope');
    for (let id=1; id<=500; id++) assert.equal(selection.add(id, 1), true);
    assert.equal(selection.add(501, 1), false);
    assert.equal(selection.add(1, 1), true);
    assert.equal(selection.add(501, 2), true);
    assert.equal(selection.add('<script>', 2), false);
    assert.equal(selection.add(502, null), false);
    assert.equal(selection.items.size, 501);
});

test('confirmed rows disappear from cross-page selection while other agencies and pending rows remain', () => {
    const selection = new CommunicationSelection('pending');
    selection.add(1,10); selection.add(28,10); selection.add(29,10); selection.add(60,20);
    const saved = selection.snapshot(100);
    const afterSend = withoutClaimed(saved,[1,28]);
    const restored = new CommunicationSelection('pending',afterSend,200);
    assert.deepEqual(restored.forAgency(10),['29']);
    assert.deepEqual(restored.forAgency(20),['60']);
    assert.equal(afterSend.expires,saved.expires);
    assert.equal(withoutClaimed(null,[1]),null);
});

test('appendices keep independent selections and claims even when their numeric id matches a policy', () => {
    const selection = new CommunicationSelection('daily');
    assert.equal(selection.add('1', 10), true);
    assert.equal(selection.add('change-1', 10), true);
    assert.equal(selection.add('change-2', 10), true);
    for (const invalid of ['change-0', 'change--1', 'change-1x', 'change-01', 'change-<script>']) assert.equal(selection.add(invalid, 10), false);
    const saved = withoutClaimed(selection.snapshot(100), ['change-1']);
    const restored = new CommunicationSelection('daily', saved, 200);
    assert.deepEqual(restored.forAgency(10), ['1', 'change-2']);
});
