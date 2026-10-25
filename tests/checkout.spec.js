import { randomUUID } from 'node:crypto';
import { test, expect, catalog, session, freshCustomer, installState, installSession, loginThroughForm, apiEndpoint, noHorizontalOverflow, captureQA } from './support.js';

const shipping = {
  shipping_name: 'Synthetic checkout reader', shipping_phone: '0000000000',
  shipping_address: 'Synthetic checkout address', shipping_city: 'Test City', shipping_postal_code: '00000',
};

async function captureCheckout(page, name) {
  const close = page.getByRole('button', { name: 'Tutup notifikasi', exact: true });
  while (await close.count()) {
    const count = await close.count();
    await close.first().click();
    await expect(close).toHaveCount(count - 1);
  }
  await captureQA(page, name);
}

async function fillShipping(page, address = shipping.shipping_address) {
  for (const [name, value] of [
    ['Nama penerima', shipping.shipping_name], ['Nomor HP', shipping.shipping_phone],
    ['Alamat', address], ['Kota', shipping.shipping_city], ['Kode pos', shipping.shipping_postal_code],
  ]) await page.getByLabel(name, { exact: true }).fill(value);
}

async function cartPage(page, api) {
  const customer = await freshCustomer(api);
  const { vue } = await catalog(api);
  await installSession(page, customer);
  await page.goto(`/books/${vue.slug}`);
  await page.getByRole('button', { name: 'Buy now', exact: true }).click();
  await fillShipping(page);
  await expect(page.getByRole('button', { name: 'Checkout demo', exact: true })).toBeEnabled();
  return { customer, book: vue };
}

async function orders(api, token) {
  const response = await api.get('/orders/me', token);
  expect(response.status()).toBe(200);
  return response.json();
}

test('native concurrent checkout retries converge, conflict, and reconcile only for their owner', async ({ api }) => {
  const customer = await freshCustomer(api);
  const other = await session(api, 'otherCustomer');
  const { vue } = await catalog(api);
  const payload = { ...shipping, operation_key: randomUUID(), items: [{ book_id: vue.id, quantity: 1 }] };
  const responses = await Promise.all([api.post('/orders', payload, customer.token), api.post('/orders', payload, customer.token), api.post('/orders', payload, customer.token)]);
  for (const response of responses) expect(response.status()).toBe(201);
  const snapshots = await Promise.all(responses.map((response) => response.json()));
  expect(snapshots[1]).toEqual(snapshots[0]); expect(snapshots[2]).toEqual(snapshots[0]);
  expect(responses.map((response) => response.headers()['checkout-replayed']).sort()).toEqual(['false', 'true', 'true']);
  expect((await orders(api, customer.token)).map((row) => row.id)).toEqual([snapshots[0].id]);
  expect((await api.post('/orders', { ...payload, notes: 'Different intent' }, customer.token)).status()).toBe(409);
  const receipt = await api.get(`/checkout-operations/${payload.operation_key}`, customer.token);
  expect(receipt.status()).toBe(200); expect((await receipt.json()).order).toEqual(snapshots[0]);
  for (const token of [customer.token, other.token]) {
    const absent = await api.get(`/checkout-operations/${token === customer.token ? randomUUID() : payload.operation_key}`, token);
    expect(absent.status()).toBe(404); expect((await absent.json()).outcome_unknown).toBe(true);
  }
  const notices = await api.get('/notifications', customer.token);
  expect((await notices.json()).filter((row) => row.type === 'order.created')).toHaveLength(1);
});

test('busy checkout locks cart, form and navigation until its real committed response arrives', async ({ page, api }) => {
  const { customer, book } = await cartPage(page, api);
  let saved;
  const committed = new Promise((resolve) => { saved = resolve; });
  let release;
  const wait = new Promise((resolve) => { release = resolve; });
  let calls = 0;
  await page.route(apiEndpoint('/orders'), async (route) => {
    calls++;
    const response = await route.fetch(); saved(); await wait; await route.fulfill({ response });
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await committed;
  try {
    await expect(page.getByLabel(`Jumlah ${book.title}`, { exact: true })).toBeDisabled();
    await expect(page.getByLabel('Alamat', { exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Remove', exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Processing...', exact: true })).toBeDisabled();
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Home', exact: true }).click();
    await expect(page).toHaveURL(/\/cart$/);
    expect(calls).toBe(1);
  } finally { release(); }
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(await orders(api, customer.token)).toHaveLength(1);
});

test('lost response after real commit retries the same operation without a second order', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  const keys = [];
  await page.route(apiEndpoint('/orders'), async (route) => {
    keys.push(route.request().postDataJSON().operation_key);
    if (keys.length > 1) return route.fallback();
    const response = await route.fetch(); expect(response.status()).toBe(201);
    return route.abort('connectionreset');
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByText(/Hasil checkout belum dapat dikonfirmasi/).first()).toBeVisible();
  await expect(page.getByRole('alert').filter({ hasText: 'Hasil checkout belum dapat dikonfirmasi' })).toHaveCount(1);
  await expect(page.getByLabel('Alamat', { exact: true })).toBeDisabled();
  const metadata = await page.evaluate((id) => sessionStorage.getItem(`zaimedia_checkout_v1_${id}`), customer.user.id);
  expect(metadata).not.toMatch(/shipping|Synthetic|address|token|notes/);
  await captureCheckout(page, 'checkout-unknown');
  await page.getByRole('button', { name: 'Coba operasi yang sama', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(keys).toHaveLength(2); expect(keys[0]).toBe(keys[1]);
  expect(await orders(api, customer.token)).toHaveLength(1);
});

test('reload reconciles a committed operation without persisting shipping data or sending another POST', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  let posts = 0;
  await page.route(apiEndpoint('/orders'), async (route) => {
    posts++;
    const response = await route.fetch(); expect(response.status()).toBe(201);
    return route.abort('connectionreset');
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Coba operasi yang sama' })).toBeVisible();
  page.once('dialog', (dialog) => dialog.accept());
  await page.reload();
  await expect(page.getByLabel('Alamat', { exact: true })).toHaveValue('');
  await page.getByRole('button', { name: 'Periksa hasil', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(posts).toBe(1); expect(await orders(api, customer.token)).toHaveLength(1);
});

test('missing receipt after reload keeps its identity and only accepts explicit matching reentry', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  const keys = [];
  await page.route(apiEndpoint('/orders'), (route) => {
    keys.push(route.request().postDataJSON().operation_key);
    return keys.length === 1 ? route.abort('connectionreset') : route.fallback();
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Coba operasi yang sama' })).toBeVisible();
  page.once('dialog', (dialog) => dialog.accept());
  await page.reload();
  await page.getByRole('button', { name: 'Periksa hasil', exact: true }).click();
  await expect(page.getByText(/Hasil checkout belum dapat dikonfirmasi/).first()).toBeVisible();
  await fillShipping(page, 'Changed intent must not send');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByText(/Data berbeda dari checkout sebelumnya/).first()).toBeVisible();
  expect(keys).toHaveLength(1); expect(await orders(api, customer.token)).toHaveLength(0);
  await fillShipping(page);
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(keys).toHaveLength(2); expect(keys[1]).toBe(keys[0]);
  expect(await orders(api, customer.token)).toHaveLength(1);
});

test('blocked sessionStorage refuses a new checkout before any order POST', async ({ page, api }) => {
  const customer = await freshCustomer(api);
  const { vue } = await catalog(api);
  await installState(page, {
    zaimedia_user: JSON.stringify(customer.user), zaimedia_token: customer.token,
    zaimedia_cart_vue: JSON.stringify([{ ...vue, quantity: 1 }]),
  });
  await page.addInitScript(() => Object.defineProperty(window, 'sessionStorage', { configurable: true, get() { throw new DOMException('Synthetic blocked storage', 'SecurityError'); } }));
  let posts = 0;
  page.on('request', (request) => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/orders') posts++; });
  await page.goto('/cart');
  await expect(page.getByText(/Identitas checkout tidak dapat disimpan/)).toBeVisible();
  await expect(page.getByLabel('Alamat', { exact: true })).toBeDisabled();
  expect(posts).toBe(0); expect(await orders(api, customer.token)).toHaveLength(0);
});

test('confirmed order survives cart persistence failure and reload without another POST', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  let posts = 0;
  await page.route(apiEndpoint('/orders'), async (route) => {
    posts++;
    const response = await route.fetch(); expect(response.status()).toBe(201);
    await page.evaluate(() => {
      window.__qaOriginalSetItem = Storage.prototype.setItem;
      Storage.prototype.setItem = function (key, value) {
        if (this === localStorage && key === 'zaimedia_cart_vue') throw new DOMException('Synthetic cart write failure', 'QuotaExceededError');
        return window.__qaOriginalSetItem.call(this, key, value);
      };
    });
    await route.fulfill({ response });
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByText(/Order sudah tersimpan. Cart lokal belum/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Selesaikan konfirmasi lokal', exact: true })).toBeVisible();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('zaimedia_cart_vue')).length)).toBe(1);
  expect(await page.evaluate((id) => Boolean(sessionStorage.getItem(`zaimedia_checkout_v1_${id}`)), customer.user.id)).toBe(true);
  await captureCheckout(page, 'checkout-confirmed-local-cleanup');
  await page.reload();
  await page.getByRole('button', { name: 'Periksa hasil', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(posts).toBe(1); expect(await orders(api, customer.token)).toHaveLength(1);
  expect(await page.evaluate(() => localStorage.getItem('zaimedia_cart_vue'))).toBe('[]');
});

test('checkout recovery reflows at320 and390 and keyboard reaches same-operation retry', async ({ page, api }) => {
  await cartPage(page, api);
  await page.route(apiEndpoint('/orders'), (route) => route.abort('connectionreset'));
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  const retry = page.getByRole('button', { name: 'Coba operasi yang sama', exact: true });
  await expect(retry).toBeVisible();
  for (const width of [320, 390, 640]) {
    await page.setViewportSize({ width, height: 844 });
    await noHorizontalOverflow(page);
    await retry.focus(); await expect(retry).toBeFocused();
    const box = await retry.boundingBox(); expect(box.width).toBeGreaterThan(44); expect(box.height).toBeGreaterThanOrEqual(40);
  }
  await page.keyboard.press('Enter');
  await expect(retry).toBeVisible();
  await captureCheckout(page, 'checkout-recovery-mobile');
});

test('confirmed checkout preserves a newer persisted cart from another tab', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  const { laravel } = await catalog(api);
  const newerCart = [{ ...laravel, quantity: 2 }];
  await page.route(apiEndpoint('/orders'), async (route) => {
    const response = await route.fetch(); expect(response.status()).toBe(201);
    await page.evaluate((items) => localStorage.setItem('zaimedia_cart_vue', JSON.stringify(items)), newerCart);
    await route.fulfill({ response });
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(await orders(api, customer.token)).toHaveLength(1);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('zaimedia_cart_vue')).map(({ id, quantity }) => ({ id, quantity })))).toEqual([{ id: laravel.id, quantity: 2 }]);
  await page.goto('/cart');
  await expect(page.getByLabel(`Jumlah ${laravel.title}`, { exact: true })).toHaveValue('2');
});

test('logout and a late old-account401 cannot clear or navigate a newer logged-in account', async ({ page, api, baseURL }) => {
  await cartPage(page, api);
  const newer = await freshCustomer(api);
  let committed;
  const saved = new Promise((resolve) => { committed = resolve; });
  let release;
  const held = new Promise((resolve) => { release = resolve; });
  await page.route(apiEndpoint('/orders'), async (route) => {
    const response = await route.fetch(); expect(response.status()).toBe(201);
    committed(); await held;
    // Real order committed above; only this delayed browser response is replaced as an auth fault.
    await route.fulfill({ status: 401, json: { message: 'Synthetic old session expired' } });
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await saved;
  try {
    await page.getByRole('button', { name: 'Logout', exact: true }).filter({ visible: true }).click();
    await expect(page).toHaveURL(/\/$/);
    await loginThroughForm(page, baseURL, newer.user.username);
  } finally { release(); }
  await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
  await expect.poll(() => page.evaluate(() => JSON.parse(localStorage.getItem('zaimedia_user')).id)).toBe(newer.user.id);
  await page.goto('/cart');
  await expect(page.getByLabel('Alamat', { exact: true })).toHaveValue('');
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('zaimedia_cart_vue')).length)).toBe(1);
  expect(await page.evaluate((id) => sessionStorage.getItem(`zaimedia_checkout_v1_${id}`), newer.user.id)).toBeNull();
});

test('initial server422 permits a corrected new operation without mislabeling an unknown retry', async ({ page, api }) => {
  const { customer } = await cartPage(page, api);
  const keys = [];
  await page.route(apiEndpoint('/orders'), async (route) => {
    const body = route.request().postDataJSON(); keys.push(body.operation_key);
    if (keys.length > 1) return route.fallback();
    const response = await route.fetch({ postData: { ...body, items: [{ ...body.items[0], quantity: 11 }] } });
    expect(response.status()).toBe(422);
    return route.fulfill({ response });
  });
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByText(/Data ditolak server/).first()).toBeVisible();
  await expect(page.getByLabel('Alamat', { exact: true })).toBeEnabled();
  expect(await orders(api, customer.token)).toHaveLength(0);
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  expect(keys).toHaveLength(2); expect(keys[0]).not.toBe(keys[1]);
  expect(await orders(api, customer.token)).toHaveLength(1);
});

test('corrupt operation metadata remains untouched and its recovery message supports200percent text', async ({ page, api }) => {
  const customer = await freshCustomer(api);
  const { vue } = await catalog(api);
  await installState(page, { zaimedia_user: JSON.stringify(customer.user), zaimedia_token: customer.token, zaimedia_cart_vue: JSON.stringify([{ ...vue, quantity: 1 }]) });
  const corrupt = JSON.stringify({ version: 1, customer_id: customer.user.id, operation_key: randomUUID(), intent_hash: ['a'.repeat(64)], cart_hash: 'a'.repeat(64) });
  await page.addInitScript(({ id, raw }) => sessionStorage.setItem(`zaimedia_checkout_v1_${id}`, raw), { id: customer.user.id, raw: corrupt });
  let posts = 0;
  page.on('request', (request) => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/orders') posts++; });
  await page.goto('/cart');
  await expect(page.getByText(/Catatan checkout tab ini rusak/)).toBeVisible();
  await page.addStyleTag({ content: 'html { font-size: 200%; }' });
  await noHorizontalOverflow(page);
  const title = await page.getByRole('heading', { name: vue.title, exact: true }).boundingBox();
  const cover = await page.locator('main .panel').first().getByRole('img').first().boundingBox();
  const overlaps = (a, b) => Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x) > 0.5
    && Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y) > 0.5;
  expect(overlaps(title, cover), 'Book title overlaps its cover at200% text size').toBe(false);
  expect(await page.locator('main form .bg-paper > div').evaluateAll((rows) => rows.some((row) => {
    const label = row.querySelector(':scope > span'); const amount = row.querySelector(':scope > strong');
    if (!label || !amount) return false;
    const a = label.getBoundingClientRect(); const b = amount.getBoundingClientRect();
    return Math.min(a.right, b.right) - Math.max(a.left, b.left) > 0.5 && Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 0.5;
  })), 'Order estimate labels overlap amounts at200% text size').toBe(false);
  expect(await page.locator('main form input, main form select, main form textarea, main form button').evaluateAll((fields) => fields.some((field) => {
    const box = field.getBoundingClientRect();
    return box.width > 0 && (box.left < -1 || box.right > window.innerWidth + 1);
  }))).toBe(false);
  await expect(page.getByLabel('Alamat', { exact: true })).toBeDisabled();
  expect(await page.evaluate((id) => sessionStorage.getItem(`zaimedia_checkout_v1_${id}`), customer.user.id)).toBe(corrupt);
  expect(posts).toBe(0);
  await captureCheckout(page, 'checkout-corrupt-200percent-text');
});

test('long synthetic cart title and quantity10 remain readable across text scale and viewports', async ({ page, api }) => {
  const customer = await freshCustomer(api);
  const { vue } = await catalog(api);
  const title = 'Panduan Vue Lanjutan untuk Pembaca Digital dan Penulis: '+ 'KonfigurasiDataTanpaSpasi'.repeat(3);
  await installState(page, {
    zaimedia_user: JSON.stringify(customer.user), zaimedia_token: customer.token,
    zaimedia_cart_vue: JSON.stringify([{ ...vue, title, quantity: 10 }]),
  });
  await page.goto('/cart'); await fillShipping(page);
  await page.route(apiEndpoint('/orders'), (route) => route.abort('connectionreset'));
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Coba operasi yang sama' })).toBeVisible();
  const intersects = (a, b) => Math.min(a.x + a.width, b.x + b.width) - Math.max(a.x, b.x) > 0.5
    && Math.min(a.y + a.height, b.y + b.height) - Math.max(a.y, b.y) > 0.5;
  for (const percent of [100, 200]) {
    await page.evaluate((value) => { document.documentElement.style.fontSize = `${value}%`; }, percent);
    for (const width of [320, 390, 640, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      await noHorizontalOverflow(page);
      const heading = page.getByRole('heading', { name: title, exact: true });
      const cover = page.locator('main .panel').first().getByRole('img').first();
      const quantity = page.getByLabel(`Jumlah ${title}`, { exact: true });
      const rectangles = await Promise.all([heading.boundingBox(), cover.boundingBox(), quantity.locator('..').boundingBox()]);
      for (let a = 0; a < rectangles.length; a++) for (let b = a + 1; b < rectangles.length; b++) {
        expect(intersects(rectangles[a], rectangles[b]), `Cart content overlaps at${width}/${percent}%`).toBe(false);
      }
      await expect(quantity).toHaveValue('10');
      expect(await quantity.evaluate((input) => {
        const style = getComputedStyle(input); const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d'); context.font = style.font;
        return input.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) >= context.measureText(input.value).width + 16;
      }), `Quantity value clipped at${width}/${percent}%`).toBe(true);
      expect(await page.getByLabel('Metode pembayaran', { exact: true }).evaluate((select) => {
        const style = getComputedStyle(select); const context = document.createElement('canvas').getContext('2d'); context.font = style.font;
        return select.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) - 20 >= context.measureText(select.selectedOptions[0].text).width;
      }), `Payment value clipped at${width}/${percent}%`).toBe(true);
      expect(await page.locator('main form .bg-paper > div').evaluateAll((rows) => rows.some((row) => {
        const label = row.querySelector(':scope > span'); const amount = row.querySelector(':scope > strong');
        if (!label || !amount) return false;
        const a = label.getBoundingClientRect(); const b = amount.getBoundingClientRect();
        return amount.scrollWidth > amount.clientWidth + 1 || (Math.min(a.right,b.right)-Math.max(a.left,b.left)>0.5 && Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>0.5);
      })), `Estimate clipped or overlapping at${width}/${percent}%`).toBe(false);
    }
  }
  await captureCheckout(page, 'checkout-long-title-200percent-text-fixture');
});
