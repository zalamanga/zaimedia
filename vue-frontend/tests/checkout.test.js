import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fingerprint, frozenIntent, normalizeIntent } from '../src/checkout-intent.js';
import { createCheckoutState } from '../src/checkout-state.js';

const vectors = JSON.parse(await readFile(new URL('../../tests/checkout-intent-vectors.json', import.meta.url), 'utf8'));
const input = () => structuredClone(vectors[0].input);
const key = 'a9c7829b-16fe-4b62-9fa6-cc414d8dbaad';
const order = { id: 1, order_number: 'ORD-SYNTHETIC', payment_status: 'pending' };

function fixture() {
  const values = new Map();
  let blocked = false;
  const storage = {
    getItem(name) { if (blocked) throw new Error('SecurityError'); return values.get(name) ?? null; },
    setItem(name, value) { if (blocked) throw new Error('QuotaExceededError'); values.set(name, value); },
    removeItem(name) { if (blocked) throw new Error('SecurityError'); values.delete(name); },
  };
  const state = createCheckoutState({ storage: () => storage, uuid: () => key });
  state.setAccount(1, 'synthetic-session-A');
  return { state, values, storage, block: () => { blocked = true; } };
}

test('PHP/JS shared vectors have exact normalized fields and SHA256', async () => {
  for (const vector of vectors) {
    const actual = normalizeIntent(vector.input);
    assert.deepEqual(actual, vector.expected, vector.name);
    assert.equal(await fingerprint(actual), vector.sha256, vector.name);
  }
});

test('canonicalization rejects unsafe IDs, fractional quantities and malformed Unicode', () => {
  for (const patch of [
    { items: [] }, { items: [{ book_id: '2' }] }, { items: [{ book_id: 0 }] },
    { items: [{ book_id: 9007199254740992 }] }, { items: [{ book_id: 2, quantity: 1.5 }] },
    { items: [{ book_id: 2.5 }] }, { items: [{ book_id: Infinity }] }, { items: [{ book_id: 2, quantity: NaN }] },
    { items: [{ book_id: 2, quantity: 11 }] }, { shipping_name: '\ud800' },
    { shipping_name: '😀'.repeat(101) }, { shipping_method: '0' }, { payment_method: '0' },
  ]) assert.throws(() => normalizeIntent({ ...input(), ...patch }));
  assert.equal(normalizeIntent({ ...input(), notes: '' }).notes, null);
  assert.equal(normalizeIntent({ ...input(), notes: '0' }).notes, '0');
  assert.notEqual(normalizeIntent({ ...input(), notes: 'é' }).notes, normalizeIntent({ ...input(), notes: 'é' }).notes);
});

test('submission locks immediately and persists only non-PII identity before POST', async () => {
  const { state, values } = fixture();
  const pending = state.prepare(input());
  assert.equal(state.busy, true);
  await assert.rejects(() => state.prepare(input()), /sebelumnya/);
  const context = await pending;
  assert.equal(state.phase, 'submitting');
  assert.equal(state.locked, true);
  assert.equal(context.key, key);
  const metadata = JSON.parse([...values.values()][0]);
  assert.deepEqual(Object.keys(metadata).sort(), ['cart_hash', 'customer_id', 'intent_hash', 'operation_key', 'version']);
  assert.doesNotMatch(JSON.stringify(metadata), /Synthetic|Éka|Jalan|shipping|session-A|notes/);
  assert.throws(() => { state.payload.items[0].quantity = 4; }, TypeError);
});

test('unknown response preserves same key and original immutable intent on retry', async () => {
  const { state } = fixture();
  const first = await state.prepare(input());
  state.fail(first, 503);
  assert.equal(state.phase, 'unknown');
  assert.equal(state.locked, true);
  const changed = input(); changed.shipping_address = 'Changed after send';
  const retry = await state.prepare(changed);
  assert.equal(retry.key, first.key);
  assert.deepEqual(retry.payload, first.payload);
  assert.equal(retry.wasNew, false);
});

test('first validation rejection permits correction but retry rejection stays protected', async () => {
  const { state, values } = fixture();
  const first = await state.prepare(input());
  state.fail(first, 422);
  assert.equal(state.phase, 'idle');
  assert.equal(state.metadata, null);
  assert.equal(values.size, 0);
  const second = await state.prepare(input());
  state.fail(second);
  const retry = await state.prepare(null);
  state.fail(retry, 422);
  assert.equal(state.phase, 'unknown');
  assert.equal(state.metadata.operation_key, key);
});

test('reload has no PII payload, and reentry requires confirmation and the same hash', async () => {
  const { state, storage } = fixture();
  const first = await state.prepare(input()); state.fail(first);
  const reloaded = createCheckoutState({ storage: () => storage, uuid: () => { throw new Error('Must never replace key'); } });
  reloaded.setAccount(1, 'new-session');
  assert.equal(reloaded.payload, null);
  assert.equal(reloaded.phase, 'reentry');
  await assert.rejects(() => reloaded.prepare(input()), /Konfirmasikan/);
  await assert.rejects(() => reloaded.prepare({ ...input(), notes: 'Changed' }, true), /Data berbeda/);
  assert.equal(reloaded.phase, 'reentry');
  const same = await reloaded.prepare(input(), true);
  assert.equal(same.key, first.key);
  assert.deepEqual(same.payload, first.payload);
});

test('missing reconciliation never clears an unresolved operation or unlocks a new key', async () => {
  const { state } = fixture();
  const first = await state.prepare(input()); state.fail(first);
  const check = state.startReconcile();
  assert.equal(state.phase, 'reconciling');
  state.fail(check, 404);
  assert.equal(state.phase, 'unknown');
  assert.equal(state.metadata.operation_key, key);
  assert.equal(state.locked, true);
});

test('storage unavailable before submission refuses checkout without allocating an attempt', async () => {
  const state = createCheckoutState({ storage: () => undefined, uuid: () => key });
  state.setAccount(1, 'A');
  assert.equal(state.phase, 'blocked');
  await assert.rejects(() => state.prepare(input()));
  const fixtureState = fixture(); fixtureState.block();
  await assert.rejects(() => fixtureState.state.prepare(input()), /sessionStorage/);
  assert.equal(fixtureState.state.metadata, null);
  assert.equal(fixtureState.state.payload, null);
});

test('storage loss after send preserves memory identity and visibly drops durability claim', async () => {
  const { state, block } = fixture();
  const context = await state.prepare(input()); block(); state.fail(context);
  assert.equal(state.phase, 'unknown');
  assert.equal(state.metadata.operation_key, key);
  assert.equal(state.durable, false);
  assert.equal((await state.prepare(null)).key, key);
});

test('corrupted or extra-field metadata blocks replacement and never loads PII', () => {
  const { state, values } = fixture();
  state.setAccount(null);
  values.set('zaimedia_checkout_v1_1', '{broken');
  state.setAccount(1, 'A');
  assert.equal(state.phase, 'blocked');
  assert.equal(state.metadata, null);
  values.set('zaimedia_checkout_v1_1', JSON.stringify({ version: 1, customer_id: 1, operation_key: key, intent_hash: 'a'.repeat(64), cart_hash: 'a'.repeat(64), shipping_name: 'Unexpected PII' }));
  state.reloadMetadata();
  assert.equal(state.phase, 'blocked');
  for (const field of ['intent_hash', 'cart_hash']) {
    const corrupt = JSON.stringify({ version: 1, customer_id: 1, operation_key: key, intent_hash: 'a'.repeat(64), cart_hash: 'a'.repeat(64), [field]: ['a'.repeat(64)] });
    values.set('zaimedia_checkout_v1_1', corrupt);
    state.reloadMetadata();
    assert.equal(state.phase, 'blocked');
    assert.equal(values.get('zaimedia_checkout_v1_1'), corrupt);
  }
});

test('account switch clears payload and stale completion cannot confirm or reset another account', async () => {
  const { state } = fixture();
  const first = await state.prepare(input());
  state.setAccount(2, 'B');
  assert.equal(state.payload, null);
  assert.equal(state.metadata, null);
  assert.equal(state.confirm(first, order), false);
  state.fail(first, 422);
  assert.equal(state.phase, 'idle');
  state.setAccount(1, 'A-new');
  assert.equal(state.phase, 'reentry');
  assert.equal(state.payload, null);
});

test('account change while hashing cannot create an operation for the new account', async () => {
  const { state, values } = fixture();
  const pending = state.prepare(input());
  state.setAccount(2, 'B');
  assert.equal(await pending, null);
  assert.equal(values.size, 0);
  assert.equal(state.phase, 'idle');
});

test('confirmation validates receipt identity and order before allowing acknowledgment', async () => {
  const { state, values } = fixture();
  const first = await state.prepare(input());
  assert.equal(state.confirm(first, {}), false);
  assert.equal(state.phase, 'unknown');
  assert.equal(state.confirm(first, order, 'b'.repeat(64)), false);
  assert.equal(state.confirm(first, order), true);
  assert.equal(state.phase, 'confirmed');
  assert.equal(state.acknowledge(), true);
  assert.equal(state.phase, 'idle');
  assert.equal(values.size, 0);
  assert.equal(state.payload, null);
});

test('failed receipt removal never unlocks a replacement operation', async () => {
  const { state, block } = fixture();
  const context = await state.prepare(input());
  state.confirm(context, order); block();
  assert.equal(state.acknowledge(), false);
  assert.equal(state.phase, 'confirmed');
  assert.equal(state.durable, false);
  assert.match(state.message, /Order sudah tersimpan/);
  assert.match(state.message, /jangan kirim order lagi/);
  assert.doesNotMatch(state.message, /sebelum mengirim order/);
  await assert.rejects(() => state.prepare(input()));
});
