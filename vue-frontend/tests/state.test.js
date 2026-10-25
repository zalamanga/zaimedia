import test from 'node:test';
import assert from 'node:assert/strict';
import { countByStatus, createRequestGate, normalizeCart, orderMessage, redirectTarget, validUser } from '../src/state-utils.js';
import { readJson, readText, removeStored, writeJson, writeText } from '../src/storage.js';

test('corrupted or incorrectly shaped stored data falls back safely', () => {
  const values = new Map([['broken', '{'], ['object', '{}'], ['null', 'null']]);
  globalThis.localStorage = { getItem: (key) => values.get(key) ?? null };
  assert.deepEqual(readJson('broken', []), []);
  assert.deepEqual(readJson('object', [], Array.isArray), []);
  assert.equal(readJson('null', null, validUser), null);
  assert.equal(readText('missing'), null);
});

test('blocked browser storage does not interrupt reads, writes or logout cleanup', () => {
  Object.defineProperty(globalThis, 'localStorage', { configurable: true, get() { throw new Error('SecurityError'); } });
  assert.equal(readText('token'), null);
  assert.deepEqual(readJson('cart', []), []);
  assert.equal(writeText('token', 'demo'), false);
  assert.equal(writeJson('user', { id: 1 }), false);
  assert.doesNotThrow(() => removeStored('token'));
  delete globalThis.localStorage;
});

test('valid storage round trips structured values and removes obsolete entries', () => {
  const values = new Map();
  globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  };
  assert.equal(writeJson('user', { id: 1, role: 'customer' }), true);
  assert.deepEqual(readJson('user', null, validUser), { id: 1, role: 'customer' });
  removeStored('user');
  assert.equal(readJson('user', null), null);
});

test('cart rejects malformed rows and nonfinite prices or quantities', () => {
  assert.deepEqual(normalizeCart({}), []);
  assert.deepEqual(normalizeCart([null, 'x', { id: -1 }, { id: 1, price: Infinity }, { id: 2, price: 5, quantity: 'NaN' }]), []);
});

test('cart merges equivalent IDs and keeps integer quantities within 1..10', () => {
  const rows = normalizeCart([
    { id: '1', title: 'Demo', price: 100, quantity: 2.8 },
    { id: 1, title: 'Demo', price: 100, quantity: 20 },
    { id: 2, price: 0, quantity: -4 },
  ]);
  assert.deepEqual(rows.map(({ id, quantity }) => ({ id, quantity })), [{ id: 1, quantity: 10 }, { id: 2, quantity: 1 }]);
  assert.equal(rows.reduce((sum, row) => sum + row.price * row.quantity, 0), 1000);
});

test('dashboard totals count every row exactly once', () => {
  assert.deepEqual(countByStatus([{ status: 'pending' }, { status: 'paid' }, { status: 'paid' }], 'status'), { all: 3, pending: 1, paid: 2 });
  assert.deepEqual(countByStatus([], 'status'), { all: 0 });
  assert.deepEqual(countByStatus([{ status: 'all' }, {}], 'status'), { all: 2, unknown: 1 });
});

test('newer requests supersede old results and unmount invalidates pending results', () => {
  const gate = createRequestGate();
  const old = gate.begin();
  const latest = gate.begin();
  assert.equal(gate.isCurrent(old), false);
  assert.equal(gate.isCurrent(latest), true);
  gate.cancel();
  assert.equal(gate.isCurrent(latest), false);
});

test('redirects accept local destinations and reject malformed or recursive targets', () => {
  assert.equal(redirectTarget('/cart?book=1'), '/cart?book=1');
  for (const value of ['//example.test', 'https://example.test', '/\\example.test', '/login', ['//example.test'], undefined]) {
    assert.equal(redirectTarget(value), '/dashboard');
  }
});

test('pending orders never claim paid access', () => {
  assert.match(orderMessage({ payment_status: 'paid' }), /sudah paid/);
  for (const status of ['pending', 'failed', 'cancelled', undefined]) {
    assert.doesNotMatch(orderMessage({ payment_status: status }), /sudah paid|tersedia di Library/);
    assert.match(orderMessage({ payment_status: status }), /setelah pembayaran dikonfirmasi/);
  }
});

test('session validation rejects unrelated localStorage values', () => {
  for (const value of [null, [], 'user', { id: 0, role: 'customer' }, { id: 2, role: 'unknown' }]) assert.equal(validUser(value), false);
  assert.equal(validUser({ id: 2, role: 'super_admin' }), true);
});

test('login keeps bearer token in memory with blocked storage and offline logout clears it', async () => {
  Object.defineProperty(globalThis, 'localStorage', { configurable: true, get() { throw new Error('SecurityError'); } });
  globalThis.window = new EventTarget();
  const { api, getAccessToken } = await import('../src/api.js');
  const { auth } = await import('../src/auth.js');
  const adapter = api.defaults.adapter;
  try {
    api.defaults.adapter = async (config) => ({
      config, headers: {}, status: 200, statusText: 'OK',
      data: config.url === '/auth/login' ? { user: { id: 1, role: 'customer' }, token: 'test-memory-token' } : { authorization: config.headers.Authorization },
    });
    await auth.login({ email: 'fixture@example.test', password: 'fixture-only' });
    assert.equal(auth.isLoggedIn, true);
    assert.equal(getAccessToken(), 'test-memory-token');
    assert.equal((await api.get('/fixture')).data.authorization, 'Bearer test-memory-token');
    api.defaults.adapter = async () => { throw new Error('API offline'); };
    await assert.doesNotReject(() => auth.logout());
    assert.equal(auth.isLoggedIn, false);
    assert.equal(getAccessToken(), null);
    assert.equal(auth.user, null);
  } finally {
    api.defaults.adapter = adapter;
    delete globalThis.localStorage;
  }
});

test('a delayed profile refresh cannot restore a logged out user', async () => {
  const { api } = await import('../src/api.js');
  const { auth } = await import('../src/auth.js');
  const adapter = api.defaults.adapter;
  let resolveProfile;
  let markRequested;
  const requested = new Promise((resolve) => { markRequested = resolve; });
  try {
    auth.setSession({ user: { id: 1, role: 'customer' }, token: 'test-refresh-token' });
    api.defaults.adapter = (config) => {
      if (config.url === '/auth/me') {
        markRequested();
        return new Promise((resolve) => { resolveProfile = () => resolve({ config, headers: {}, status: 200, data: { id: 1, role: 'customer' } }); });
      }
      return Promise.resolve({ config, headers: {}, status: 200, data: {} });
    };
    const refresh = auth.refreshMe();
    await requested;
    await auth.logout();
    resolveProfile();
    await refresh;
    assert.equal(auth.user, null);
    assert.equal(auth.isLoggedIn, false);
  } finally {
    api.defaults.adapter = adapter;
  }
});

test('private PDF requests use the authorized API endpoint and reject non-PDF content', async () => {
  const { api } = await import('../src/api.js');
  const { auth } = await import('../src/auth.js');
  const { fetchPrivatePdf } = await import('../src/private-pdf.js');
  const adapter = api.defaults.adapter;
  try {
    auth.setSession({ user: { id: 1, role: 'customer' }, token: 'test-pdf-token' });
    api.defaults.adapter = async (config) => {
      assert.equal(config.url, '/library/5/download');
      assert.equal(config.responseType, 'blob');
      assert.equal(config.headers.Authorization, 'Bearer test-pdf-token');
      return { config, headers: {}, status: 200, data: new Blob(['%PDF-1.4'], { type: 'application/pdf' }) };
    };
    assert.equal((await fetchPrivatePdf(5)).type, 'application/pdf');
    api.defaults.adapter = async (config) => ({ config, headers: {}, status: 200, data: new Blob(['{}'], { type: 'application/json' }) });
    await assert.rejects(() => fetchPrivatePdf(5), /Respons PDF tidak valid/);
  } finally {
    api.defaults.adapter = async (config) => ({ config, headers: {}, status: 200, data: {} });
    await auth.logout();
    api.defaults.adapter = adapter;
  }
});

test('delayed logout keeps a newly established session and sends only its captured bearer', async () => {
  const { api, getAccessToken } = await import('../src/api.js');
  const { auth } = await import('../src/auth.js');
  const adapter = api.defaults.adapter;
  let finishLogout;
  let requested;
  const sent = new Promise((resolve) => { requested = resolve; });
  try {
    auth.setSession({ user: { id: 1, role: 'customer' }, token: 'old-logout-session' });
    api.defaults.adapter = (config) => {
      assert.equal(config.headers.Authorization, 'Bearer old-logout-session');
      requested();
      return new Promise((resolve) => { finishLogout = () => resolve({ config, headers: {}, status: 200, data: {} }); });
    };
    const pending = auth.logout();
    auth.setSession({ user: { id: 2, role: 'customer' }, token: 'new-account-session' });
    await sent;
    finishLogout();
    assert.equal(await pending, false);
    assert.equal(auth.user.id, 2);
    assert.equal(getAccessToken(), 'new-account-session');
  } finally { api.defaults.adapter = adapter; }
});

test('an old request unauthorized response cannot clear a newer account', async () => {
  const { AxiosError } = await import('axios');
  const { api, getAccessToken } = await import('../src/api.js');
  const { auth } = await import('../src/auth.js');
  const adapter = api.defaults.adapter;
  let failOld;
  let requested;
  const sent = new Promise((resolve) => { requested = resolve; });
  try {
    auth.setSession({ user: { id: 1, role: 'customer' }, token: 'old-expired-session' });
    api.defaults.adapter = (config) => new Promise((resolve, reject) => {
      requested();
      failOld = () => reject(new AxiosError('Synthetic unauthorized', 'ERR_BAD_REQUEST', config, {}, { status: 401, data: {}, config }));
    });
    const pending = api.get('/synthetic-delayed');
    await sent;
    auth.setSession({ user: { id: 2, role: 'customer' }, token: 'new-valid-session' });
    const rejected = assert.rejects(pending);
    failOld();
    await rejected;
    assert.equal(auth.user.id, 2);
    assert.equal(getAccessToken(), 'new-valid-session');
  } finally { api.defaults.adapter = adapter; }
});

test('global cart methods lock the submitted snapshot and confirmation preserves changed carts', async () => {
  const { auth } = await import('../src/auth.js');
  const { cart } = await import('../src/cart.js');
  const { checkoutState } = await import('../src/checkout.js');
  const values = new Map();
  const localValues = new Map();
  globalThis.localStorage = {
    getItem: (key) => localValues.get(key) ?? null,
    setItem: (key, value) => localValues.set(key, value),
    removeItem: (key) => localValues.delete(key),
  };
  globalThis.sessionStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  };
  window.setTimeout = () => 0;
  try {
    auth.setSession({ user: { id: 7, role: 'customer' }, token: 'cart-operation-session' });
    cart.items = [{ id: 2, title: 'Synthetic', price: 10, quantity: 1 }];
    const context = await checkoutState.prepare({
      items: [{ book_id: 2, quantity: 1 }], shipping_name: 'Synthetic', shipping_phone: '000',
      shipping_address: 'Synthetic', shipping_city: 'Test', shipping_postal_code: '00000',
    });
    cart.add({ id: 3, title: 'Another', price: 20 });
    cart.remove(2); cart.setQuantity(2, 5); cart.clear();
    assert.deepEqual(cart.items.map(({ id, quantity }) => ({ id, quantity })), [{ id: 2, quantity: 1 }]);
    checkoutState.confirm(context, { id: 1, order_number: 'ORD-SYNTHETIC', payment_status: 'pending' });
    cart.items = [{ id: 9, title: 'Changed separately', price: 30, quantity: 1 }];
    assert.equal(await cart.clearConfirmed(context), 'different');
    assert.equal(cart.items[0].id, 9);
    cart.items = [{ id: 2, title: 'Synthetic', price: 10, quantity: 1 }];
    cart.persist();
    assert.equal(await cart.clearConfirmed(context), 'cleared');
    assert.deepEqual(cart.items, []);
    auth.setSession({ user: { id: 8, role: 'customer' }, token: 'new-cart-account' });
    cart.items = [{ id: 9, title: 'New account', price: 30, quantity: 1 }];
    assert.equal(await cart.clearConfirmed(context), 'stale');
    assert.equal(cart.items[0].id, 9);
    assert.equal(checkoutState.payload, null);
  } finally {
    auth.clearSession();
    delete globalThis.sessionStorage;
    delete globalThis.localStorage;
  }
});

test('confirmed checkout retains its receipt when local cart cleanup cannot be verified', async () => {
  globalThis.window ||= new EventTarget();
  const { auth } = await import('../src/auth.js');
  const { cart } = await import('../src/cart.js');
  const { checkoutState } = await import('../src/checkout.js');
  const sessionValues = new Map();
  const localValues = new Map();
  let blocked = false;
  globalThis.sessionStorage = {
    getItem: (key) => sessionValues.get(key) ?? null,
    setItem: (key, value) => sessionValues.set(key, value),
    removeItem: (key) => sessionValues.delete(key),
  };
  const localStore = {
    getItem: (key) => { if (blocked) throw new Error('SecurityError'); return localValues.get(key) ?? null; },
    setItem: (key, value) => { if (blocked) throw new Error('QuotaExceededError'); localValues.set(key, value); },
    removeItem: (key) => localValues.delete(key),
  };
  globalThis.localStorage = localStore;
  try {
    auth.setSession({ user: { id: 17, role: 'customer' }, token: 'confirmed-cart-session' });
    cart.items = [{ id: 2, title: 'Synthetic', price: 10, quantity: 1 }];
    cart.persist();
    const context = await checkoutState.prepare({
      items: [{ book_id: 2, quantity: 1 }], shipping_name: 'Synthetic', shipping_phone: '000',
      shipping_address: 'Synthetic', shipping_city: 'Test', shipping_postal_code: '00000',
    });
    checkoutState.confirm(context, { id: 1, order_number: 'ORD-SYNTHETIC', payment_status: 'pending' });
    blocked = true;
    assert.equal(await cart.clearConfirmed(context), 'storage-unavailable');
    assert.equal(checkoutState.phase, 'confirmed');
    assert.equal(cart.items.length, 1);
    assert.equal(JSON.parse(localValues.get('zaimedia_cart_vue')).length, 1);
    assert.equal(sessionValues.size, 1);
    blocked = false;
    const originalCart = localValues.get('zaimedia_cart_vue');
    localValues.set('zaimedia_cart_vue', JSON.stringify([{ id: 9, title: 'Newer cart from another tab', price: 20, quantity: 2 }]));
    assert.equal(await cart.clearConfirmed(context), 'different');
    assert.equal(JSON.parse(localValues.get('zaimedia_cart_vue'))[0].id, 9);
    assert.equal(cart.items[0].id, 9);
    cart.items = JSON.parse(originalCart);
    localValues.set('zaimedia_cart_vue', '{corrupt');
    assert.equal(await cart.clearConfirmed(context), 'storage-unavailable');
    assert.equal(localValues.get('zaimedia_cart_vue'), '{corrupt');
    localValues.delete('zaimedia_cart_vue');
    assert.equal(await cart.clearConfirmed(context), 'storage-unavailable');
    localValues.set('zaimedia_cart_vue', originalCart);
    delete globalThis.localStorage;
    assert.equal(await cart.clearConfirmed(context), 'storage-unavailable');
    assert.equal(cart.items.length, 1);
    globalThis.localStorage = { ...localStore, setItem() {} };
    assert.equal(await cart.clearConfirmed(context), 'storage-unavailable');
    assert.equal(cart.items.length, 1);
    globalThis.localStorage = localStore;
    assert.equal(await cart.clearConfirmed(context), 'cleared');
    assert.equal(localValues.get('zaimedia_cart_vue'), '[]');
    assert.deepEqual(cart.items, []);
    assert.equal(checkoutState.acknowledge(), true);
  } finally {
    auth.clearSession();
    delete globalThis.sessionStorage;
    delete globalThis.localStorage;
  }
});
