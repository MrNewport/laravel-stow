import test from 'node:test';
import assert from 'node:assert/strict';
import { createBrowserBasket } from '../../resources/js/browser-basket.mjs';

const key = 'shop.basket.v1';
const ids = ['pipe', 'jar', 'pouch'];
const envelope = (items) => JSON.stringify({ version: 1, items });
function memory(initial = {}) {
    const entries = new Map(Object.entries(initial));
    return {
        getItem: (name) => entries.get(name) ?? null,
        setItem: (name, value) => entries.set(name, value),
    };
}
const create = (target = memory(), options = {}) => createBrowserBasket({
    storageKey: key, allowedIds: ids, maxItems: 2, maxQuantity: 9,
    storage: () => target, ...options,
});

test('injectable synchronous adapters store versioned data and reload without DOM globals', () => {
    const target = memory();
    const basket = create(target);
    basket.add('pipe', 2);
    basket.add('jar');
    assert.equal(target.getItem(key), envelope([{ id: 'pipe', quantity: 2 }, { id: 'jar', quantity: 1 }]));
    assert.deepEqual(create(target).items(), basket.items());
    assert.equal(basket.persistent, true);
});

test('combines IDs, bounds quantities/capacity, removes zero, and ignores invalid input', () => {
    const basket = create();
    basket.add('pipe', 2);
    basket.add('pipe', 8);
    basket.set('jar', 3.9);
    basket.add('pouch');
    basket.add('unknown');
    basket.set('pipe', '3');
    basket.set('pipe', Infinity);
    basket.add('jar', NaN);
    assert.deepEqual(basket.items(), [{ id: 'pipe', quantity: 9 }, { id: 'jar', quantity: 3 }]);
    basket.set('pipe', 0);
    basket.add('pouch', 2.9);
    basket.remove('jar');
    assert.deepEqual(basket.items(), [{ id: 'pouch', quantity: 2 }]);
    basket.set('pouch', -1);
    assert.deepEqual(basket.items(), []);
});

test('hydration accepts only positive safe integer quantities and catalog identities', () => {
    const target = memory({ [key]: envelope([
        { id: 'pipe', quantity: 2, price: 0 },
        { id: 'pipe', quantity: 8 },
        { id: 'unknown', quantity: 1 },
        { id: 'jar', quantity: '3' },
        { id: 'jar', quantity: 1.5 },
        { id: 'jar', quantity: 0 },
        { id: 'jar', quantity: Number.MAX_SAFE_INTEGER + 1 },
        { id: 'jar', quantity: 4 },
        { id: 'pouch', quantity: 1 },
        null,
    ]) });
    const basket = create(target);
    assert.deepEqual(basket.items(), [{ id: 'pipe', quantity: 9 }, { id: 'jar', quantity: 4 }]);
    assert.equal(target.getItem(key), envelope(basket.items()));
});

test('malformed and unsupported canonical data recover in memory without legacy fallback', () => {
    for (const raw of ['{invalid', 'null', '[]', '{"version":2,"items":[]}', '{"version":1,"items":null}']) {
        let migrations = 0;
        const target = memory({ [key]: raw });
        const basket = create(target, { migrate: () => { migrations++; return [{ id: 'pipe', quantity: 1 }]; } });
        assert.deepEqual(basket.items(), []);
        assert.equal(basket.persistent, false);
        assert.equal(migrations, 0);
        basket.add('jar');
        assert.equal(basket.persistent, true);
        assert.deepEqual(create(target).items(), [{ id: 'jar', quantity: 1 }]);
    }
});

test('snapshots and subscription payloads cannot mutate basket state or one another', () => {
    const basket = create();
    const notices = [];
    const unsubscribe = basket.subscribe((items, state) => notices.push({ items, state }));
    basket.subscribe((items) => { items[0].quantity = 700; items.push({ id: 'unknown', quantity: 1 }); });
    const result = basket.add('pipe');
    result[0].quantity = 123;
    const copy = basket.items();
    copy.push({ id: 'jar', quantity: 2 });
    assert.deepEqual(basket.items(), [{ id: 'pipe', quantity: 1 }]);
    assert.deepEqual(notices, [{ items: [{ id: 'pipe', quantity: 1 }], state: { persistent: true } }]);
    unsubscribe();
    basket.add('pipe');
    assert.equal(notices.length, 1);
});

test('denied storage access preserves memory and reports failed persistence', () => {
    const basket = create(null, { storage: () => { throw new Error('Denied'); } });
    basket.add('pipe', 2);
    assert.equal(basket.persistent, false);
    assert.deepEqual(basket.load(), [{ id: 'pipe', quantity: 2 }]);
    basket.clear();
    assert.deepEqual(basket.items(), []);
});

test('failed writes retain pending memory and recover without rereading stale storage', () => {
    const backing = memory({ [key]: envelope([{ id: 'pipe', quantity: 1 }]) });
    let blocked = true;
    const target = {
        getItem: backing.getItem,
        setItem(name, value) { if (blocked) throw new Error('Quota'); backing.setItem(name, value); },
    };
    const basket = create(target);
    const notices = [];
    basket.subscribe((items, state) => notices.push({ items, state }));
    basket.clear();
    assert.equal(basket.persistent, false);
    assert.deepEqual(basket.load(), []);
    blocked = false;
    assert.deepEqual(basket.load(), []);
    assert.equal(basket.persistent, true);
    assert.equal(backing.getItem(key), envelope([]));
    assert.deepEqual(notices.at(-1), { items: [], state: { persistent: true } });
});

test('migrates legacy data once and retains an empty canonical tombstone after clear', () => {
    const target = memory({ legacy: JSON.stringify([{ id: 'pipe', quantity: 2 }]) });
    let migrations = 0;
    const options = { migrate(storage) { migrations++; return JSON.parse(storage.getItem('legacy')); } };
    const basket = create(target, options);
    assert.deepEqual(basket.items(), [{ id: 'pipe', quantity: 2 }]);
    basket.clear();
    basket.load();
    assert.deepEqual(create(target, options).items(), []);
    assert.equal(migrations, 1);
    assert.equal(target.getItem(key), envelope([]));
});

test('migration errors and malformed returned data become an empty canonical basket', () => {
    for (const migrate of [() => { throw new Error('Malformed legacy JSON'); }, () => null, () => [{ id: 'pipe', quantity: 1.5 }]]) {
        const target = memory();
        const basket = create(target, { migrate });
        assert.deepEqual(basket.items(), []);
        assert.equal(basket.persistent, true);
        assert.equal(target.getItem(key), envelope([]));
    }
});

test('load refreshes valid external state and listeners only receive changes', () => {
    const target = memory();
    const basket = create(target);
    let notices = 0;
    basket.subscribe(() => notices++);
    basket.load();
    assert.equal(notices, 0);
    target.setItem(key, envelope([{ id: 'jar', quantity: 2 }]));
    assert.deepEqual(basket.load(), [{ id: 'jar', quantity: 2 }]);
    assert.equal(notices, 1);
});

test('configuration is validated and the allowed-ID catalog is copied', () => {
    const allowed = ['pipe'];
    const basket = create(memory(), { allowedIds: allowed });
    allowed.push('jar');
    assert.deepEqual(basket.add('jar'), []);
    for (const options of [{ storageKey: '' }, { allowedIds: 'pipe' }, { allowedIds: [1] }, { maxItems: 0 }, { maxQuantity: Infinity }, { storage: null }]) {
        assert.throws(() => create(memory(), options), TypeError);
    }
});
