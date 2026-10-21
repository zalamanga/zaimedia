import {
  test, expect, catalog, session, customerOrders, installState, installSession,
  loginThroughForm, freshCustomer, noHorizontalOverflow, apiEndpoint, captureQA,
} from './support.js';

const emptyPage = { data: [], current_page: 1, last_page: 1, total: 0 };

test('catalog reports a failed request and recovers with the retry control', async ({ page, api }) => {
  const { laravel } = await catalog(api);
  let fail = true;
  await page.route(apiEndpoint('/books'), async (route) => {
    if (new URL(route.request().url()).searchParams.get('per_page') === '12' && fail) {
      fail = false;
      return route.fulfill({ status: 503, json: { message: 'Katalog gagal dimuat.' } });
    }
    return route.fallback();
  });
  await page.goto('/');
  const alert = page.getByRole('alert').filter({ hasText: 'Katalog gagal dimuat.' });
  await expect(alert).toBeVisible();
  await alert.getByRole('button', { name: 'Coba lagi' }).click();
  await expect(page.locator('#catalog')).toContainText(laravel.title);
  await expect(alert).toHaveCount(0);
  await captureQA(page, 'catalog-desktop');
});

test('empty catalog renders a usable empty state', async ({ page }) => {
  await page.route(apiEndpoint('/books'), (route) => route.fulfill({ json: emptyPage }));
  await page.goto('/');
  await expect(page.locator('#catalog')).toContainText(/tidak|belum|kosong/i);
  await expect(page.locator('#catalog article')).toHaveCount(0);
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});

test('missing book shows a recoverable detail error instead of blank content', async ({ page }) => {
  await page.goto('/books/nonexistent-integration-book');
  await expect(page.getByRole('heading', { name: 'Buku tidak bisa dibuka' })).toBeVisible();
  await page.getByRole('button', { name: 'Coba lagi' }).click();
  await expect(page.getByRole('heading', { name: 'Buku tidak bisa dibuka' })).toBeVisible();
  await page.getByRole('link', { name: 'Kembali ke katalog' }).click();
  await expect(page).toHaveURL(/\/$/);
});

test('article listing recovers from failure into its empty state', async ({ page }) => {
  let fail = true;
  await page.route(apiEndpoint('/articles'), (route) => {
    if (fail) {
      fail = false;
      return route.fulfill({ status: 503, json: { message: 'Artikel gagal dimuat.' } });
    }
    return route.fulfill({ json: emptyPage });
  });
  await page.goto('/articles');
  const alert = page.getByRole('alert').filter({ hasText: 'Artikel gagal dimuat.' });
  await expect(alert).toBeVisible();
  await alert.getByRole('button', { name: 'Coba lagi' }).click();
  await expect(page.getByText('Belum ada artikel published.', { exact: true })).toBeVisible();
});

test('missing article exposes a retry and route back to articles', async ({ page }) => {
  await page.goto('/articles/nonexistent-integration-article');
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Coba lagi' })).toBeVisible();
  await expect(page.locator('main a[href="/articles"]')).toBeVisible();
});

test('invalid login displays server feedback and keeps the login form available', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email atau username', { exact: true }).fill('nobody-integration@example.test');
  await page.getByLabel('Password', { exact: true }).fill('invalid-demo-password');
  await page.locator('form button:not([type="button"])').click();
  await expect(page.getByRole('alert').first()).toBeVisible();
  await expect(page.getByLabel('Email atau username', { exact: true })).toBeVisible();
  await expect(page).toHaveURL(/\/login/);
});

test('customer can log in through the form and reach the purchased library', async ({ page, baseURL, api }) => {
  const { laravel } = await catalog(api);
  await loginThroughForm(page, baseURL);
  await expect(page.getByRole('heading', { name: 'Library saya', exact: true })).toBeVisible();
  await expect(page.locator('main').first()).toContainText(laravel.title);
  await expect(page.getByRole('button', { name: 'Logout', exact: true }).filter({ visible: true })).toBeVisible();
  await captureQA(page, 'customer-library');
});

test('corrupt stored session clears safely and protected routes request login', async ({ page }) => {
  await installState(page, { zaimedia_user: '{invalid-json', zaimedia_token: 'invalid-integration-session' });
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login\?redirect=/);
  await expect(page.getByLabel('Email atau username', { exact: true })).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('zaimedia_token') === null)).toBe(true);
});

test('expired API session clears stored identity and returns to login', async ({ page, api }) => {
  const current = await session(api);
  await installSession(page, { user: current.user, token: 'expired-integration-session' });
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login/);
  expect(await page.evaluate(() => localStorage.getItem('zaimedia_token') === null)).toBe(true);
  expect(await page.evaluate(() => localStorage.getItem('zaimedia_user') === null)).toBe(true);
});

test('logout still removes local identity when the API is offline', async ({ page, api, context }) => {
  await installSession(page, await session(api));
  await page.goto('/dashboard');
  await expect(page.getByRole('heading', { name: 'Library saya', exact: true })).toBeVisible();
  await context.setOffline(true);
  try {
    await page.getByRole('button', { name: 'Logout', exact: true }).filter({ visible: true }).click();
    await expect(page).toHaveURL(/\/$/);
    expect(await page.evaluate(() => localStorage.getItem('zaimedia_token') === null)).toBe(true);
    expect(await page.evaluate(() => localStorage.getItem('zaimedia_user') === null)).toBe(true);
  } finally {
    await context.setOffline(false);
  }
});

test('invalid stored cart becomes an empty cart without blocking checkout page', async ({ page }) => {
  await installState(page, { zaimedia_cart_vue: '{invalid-json' });
  await page.goto('/cart');
  await expect(page.getByRole('heading', { name: 'Checkout', exact: true })).toBeVisible();
  await expect(page.getByText(/cart.*kosong|keranjang.*kosong/i)).toBeVisible();
  await expect(page.getByRole('link', { name: 'Cari buku', exact: true })).toBeVisible();
});

test('guest buying a book is redirected to login with the cart destination preserved', async ({ page, api }) => {
  const { laravel } = await catalog(api);
  await page.goto(`/books/${laravel.slug}`);
  await expect(page.getByRole('heading', { name: laravel.title, exact: true })).toBeVisible();
  await captureQA(page, 'book-desktop');
  await page.getByRole('button', { name: 'Buy now', exact: true }).click();
  await expect(page).toHaveURL(/\/cart$/);
  await fillShipping(page);
  await expect(page.getByRole('button', { name: 'Checkout demo', exact: true })).toBeEnabled();
  await captureQA(page, 'cart-desktop');
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page).toHaveURL(/\/login\?redirect=\/cart/);
});

test('pending order never claims the book is ready or offers a reader link', async ({ page, api }) => {
  const { pending } = await customerOrders(api);
  await installSession(page, await session(api));
  await page.goto(`/orders/${pending.order_number || pending.id}/success`);
  await expect(page.getByRole('heading', { name: 'Order tersimpan, akses buku belum aktif.' })).toBeVisible();
  await expect(page.locator('main a[href^="/reader/"]')).toHaveCount(0);
  await expect(page.locator('main button').filter({ hasText: /Download/i })).toHaveCount(0);
});

test('paid order presents access to the purchased book', async ({ page, api }) => {
  const { paid } = await customerOrders(api);
  await installSession(page, await session(api));
  await page.goto(`/orders/${paid.order_number || paid.id}/success`);
  await expect(page.getByRole('heading', { name: 'Buku sudah tersedia di Library.' })).toBeVisible();
  await expect(page.locator('main a[href^="/reader/"]').first()).toBeVisible();
});

test('demo checkout creates a paid order and real library access for a new synthetic buyer', async ({ page, api }) => {
  const customer = await freshCustomer(api);
  const { vue } = await catalog(api);
  await installSession(page, customer);
  await page.goto(`/books/${vue.slug}`);
  await page.getByRole('button', { name: 'Buy now', exact: true }).click();
  await fillShipping(page);
  await page.getByRole('button', { name: 'Checkout demo', exact: true }).click();
  await expect(page).toHaveURL(/\/orders\/[^/]+\/success$/);
  await expect(page.getByRole('heading', { name: 'Buku sudah tersedia di Library.' })).toBeVisible();
  expect((await api.get(`/library/${vue.id}/download`, customer.token)).status()).toBe(200);
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('zaimedia_cart_vue')))).toEqual([]);
});

test('paid buyer reads a private PDF through a blob instead of a public file URL', async ({ page, api }) => {
  const { laravel } = await catalog(api);
  await installSession(page, await session(api));
  await page.goto(`/reader/${laravel.id}`);
  const frame = page.locator('iframe');
  await expect(frame).toHaveAttribute('title', laravel.title);
  await expect(frame).toHaveAttribute('src', /^blob:/);
  await expect(page.getByRole('alert')).toHaveCount(0);
});

test('writer dashboard shows owned books and no user management controls', async ({ page, api }) => {
  const { laravel } = await catalog(api);
  await installSession(page, await session(api, 'writer'));
  await page.goto('/dashboard');
  await expect(page.getByRole('heading', { name: 'Buku saya', exact: true })).toBeVisible();
  await expect(page.locator('main strong').filter({ hasText: laravel.title }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Edit', exact: true }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Users', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Royalty', exact: true }).filter({ visible: true })).toBeVisible();
  await captureQA(page, 'writer-books');
  for (const width of [320, 390]) {
    await page.setViewportSize({ width, height: 844 });
    await noHorizontalOverflow(page);
    await expect(page.getByLabel('Search current table', { exact: true })).toBeVisible();
    await expect(page.locator('.book-metrics').first()).toBeVisible();
    const overlappingMetrics = await page.locator('.book-metrics').evaluateAll((groups) => groups.some((group) => {
      const boxes = [...group.children].map((element) => element.getBoundingClientRect());
      return boxes.some((a, index) => boxes.slice(index + 1).some((b) =>
        Math.min(a.right, b.right) - Math.max(a.left, b.left) > 0.5
        && Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 0.5));
    }));
    expect(overlappingMetrics, `Writer metrics overlap at ${width}px`).toBe(false);
    const clippedFields = await page.locator('main input:visible, main select:visible').evaluateAll((fields) => fields.some((field) => {
      const box = field.getBoundingClientRect();
      return box.left < -1 || box.right > window.innerWidth + 1;
    }));
    expect(clippedFields, `Writer form or filter fields are clipped at ${width}px`).toBe(false);
    if (width === 390) await captureQA(page, 'writer-books-mobile-390');
  }
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.getByRole('button', { name: 'Withdrawals', exact: true }).filter({ visible: true }).click();
  await expect(page.getByText('Tidak ada withdrawal.', { exact: true })).toBeVisible();
});

test('administrator can open user management but cannot change system settings', async ({ page, api }) => {
  await installSession(page, await session(api, 'admin'));
  await page.goto('/dashboard?tab=users');
  await expect(page.getByRole('heading', { name: 'User management', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Settings', exact: true }).filter({ visible: true }).click();
  await expect(page.getByRole('heading', { name: 'System settings', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Simpan settings', exact: true })).toBeDisabled();
  await page.getByRole('button', { name: 'Orders', exact: true }).filter({ visible: true }).click();
  await expect(page.getByRole('heading', { name: 'Order management', exact: true })).toBeVisible();
  await expect(page.getByText('DEMO-PAID', { exact: true })).toBeVisible();
  await captureQA(page, 'admin-orders');
  const audit = page.getByRole('button', { name: 'Audit', exact: true }).filter({ visible: true });
  await audit.scrollIntoViewIfNeeded();
  await audit.click();
  await expect(page.getByRole('heading', { name: 'Audit log admin', exact: true })).toBeVisible();
  const footerOverlap = await audit.evaluate((button) => {
    const footer = document.querySelector('footer');
    if (!footer) return false;
    const a = button.getBoundingClientRect();
    const b = footer.getBoundingClientRect();
    return Math.min(a.right, b.right) - Math.max(a.left, b.left) > 0.5
      && Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 0.5;
  });
  expect(footerOverlap, 'Admin sidebar overlaps the page footer.').toBe(false);
  await captureQA(page, 'admin-sidebar-audit');
});

test('super administrator dashboard retains management controls after refresh', async ({ page, api }) => {
  await installSession(page, await session(api, 'superAdmin'));
  await page.goto('/dashboard?tab=settings');
  await expect(page.getByRole('heading', { name: 'System settings', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Simpan settings', exact: true })).toBeEnabled();
  await page.reload();
  await expect(page.getByRole('heading', { name: 'System settings', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Simpan settings', exact: true })).toBeEnabled();
});

for (const width of [320, 390, 768]) {
  test(`public pages fit ${width}px and their navigation works with the keyboard`, async ({ page, api }) => {
    const { laravel } = await catalog(api);
    await page.setViewportSize({ width, height: 844 });
    for (const path of ['/', `/books/${laravel.slug}`, '/cart', '/login']) {
      await page.goto(path);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
      await noHorizontalOverflow(page);
      if (width === 390 && path === '/') {
        await expect(page.locator('#catalog')).toContainText(laravel.title);
        await captureQA(page, 'catalog-mobile-390');
      }
      if (path.startsWith('/books/')) {
        await page.getByRole('button', { name: 'Add to cart', exact: true }).click();
      }
      if (path === '/cart') {
        await expect(page.getByLabel(`Jumlah ${laravel.title}`, { exact: true })).toHaveValue('1');
      }
    }
    const nav = page.getByRole('navigation', { name: width < 640 ? 'Navigasi ponsel' : 'Navigasi utama' });
    const cartLink = nav.getByRole('link', { name: /^Cart\s*\d*$/ });
    await cartLink.focus();
    await expect(cartLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/cart$/);
    await noHorizontalOverflow(page);
  });
}

test('deep routes load and refresh through the production web server', async ({ page, api }) => {
  const { laravel } = await catalog(api);
  for (const path of ['/about', '/guides/buy', `/books/${laravel.slug}`]) {
    await page.goto(path);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
  }
});

async function fillShipping(page) {
  await page.getByLabel('Nama penerima', { exact: true }).fill('Integration Test Reader');
  await page.getByLabel('Nomor HP', { exact: true }).fill('0000000000');
  await page.getByLabel('Alamat', { exact: true }).fill('Alamat sintetis untuk pengujian lokal');
  await page.getByLabel('Kota', { exact: true }).fill('Test City');
  await page.getByLabel('Kode pos', { exact: true }).fill('00000');
}
