import { test as base, expect } from '@playwright/test';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const usernames = {
  customer: 'demo_customer',
  otherCustomer: 'demo_other_customer',
  writer: 'demo_writer',
  admin: 'demo_admin',
  superAdmin: 'demo_super_admin',
};
const sessions = new Map();
let fixture;

function isolation(baseURL) {
  const target = new URL(baseURL);
  if (target.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(target.hostname) || target.port !== '18090') {
    throw new Error('These integration tests only support the isolated loopback service on port 18090.');
  }
  if (!fixture) {
    const path = process.env.ZAIMEDIA_TEST_FIXTURE_FILE;
    if (!path) throw new Error('Set ZAIMEDIA_TEST_FIXTURE_FILE to the private isolated-test JSON file.');
    fixture = JSON.parse(readFileSync(path, 'utf8').replace(/^\uFEFF/, ''));
  }
  if (fixture.project !== 'zaimedia-codex-check-20261022'
    || fixture.allowMutations !== true
    || new URL(fixture.baseURL).origin !== target.origin
    || typeof fixture.password !== 'string'
    || fixture.password.length < 8) {
    throw new Error('The private fixture must authorize the isolated test project and matching loopback origin.');
  }
  return fixture;
}

export const test = base.extend({
  api: async ({ request, baseURL }, use) => {
    isolation(baseURL);
    const call = async (method, path, data, token, multipart) => {
      if (!path.startsWith('/') || path.startsWith('//') || path.includes('://')) throw new Error('Expected a relative API path.');
      isolation(baseURL);
      return request.fetch(new URL(`/api${path}`, baseURL).href, {
        method,
        ...(multipart ? { multipart } : { data }),
        maxRedirects: 0,
        headers: {
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      });
    };
    await use({
      get: (path, token) => call('GET', path, undefined, token),
      post: (path, data, token) => call('POST', path, data, token),
      multipart: (path, files, token) => call('POST', path, undefined, token, files),
      resource: (pathOrURL) => {
        const url = new URL(pathOrURL, baseURL);
        if (url.origin !== new URL(baseURL).origin) throw new Error('Resource request must stay on the isolated loopback origin.');
        isolation(baseURL);
        return request.get(url.href, { maxRedirects: 0 });
      },
    });
  },
  page: async ({ page, baseURL }, use, testInfo) => {
    isolation(baseURL);
    const origin = new URL(baseURL).origin;
    const outbound = [];
    const pageErrors = [];
    const failedAssets = [];
    await page.context().route('**/*', async (route) => {
      const request = route.request();
      const url = new URL(request.url());
      if (!['http:', 'https:'].includes(url.protocol)) return route.continue();
      if (url.origin !== origin) {
        outbound.push(`${request.method()} ${url.origin}${url.pathname}`);
        return route.abort('blockedbyclient');
      }
      if (!['GET', 'HEAD'].includes(request.method()) && !url.pathname.startsWith('/api/')) {
        outbound.push(`${request.method()} unexpected local endpoint ${url.pathname}`);
        return route.abort('blockedbyclient');
      }
      return route.continue();
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('response', (response) => {
      const url = new URL(response.url());
      if (url.origin === origin && response.status() >= 400 && !url.pathname.startsWith('/api/')) {
        failedAssets.push(`${response.status()} ${url.pathname}`);
      }
    });
    await use(page);
    testInfo.annotations.push({ type: 'network', description: `outbound=${outbound.length}; pageerrors=${pageErrors.length}; failed-assets=${failedAssets.length}` });
    expect(outbound, 'The browser attempted an outbound or unexpected write request.').toEqual([]);
    expect(pageErrors, 'Uncaught browser errors.').toEqual([]);
    expect(failedAssets, 'Local pages or assets failed.').toEqual([]);
  },
});

export { expect };

export function rows(data) {
  return Array.isArray(data) ? data : data.data || [];
}

export async function session(api, role = 'customer') {
  if (!usernames[role]) throw new Error('Unknown synthetic test role.');
  if (!sessions.has(role)) {
    sessions.set(role, (async () => {
      const response = await api.post('/auth/login', { email: usernames[role], password: fixture.password });
      expect(response.status(), `Synthetic ${role} login status`).toBe(200);
      const data = await response.json();
      expect(typeof data.token).toBe('string');
      expect(Boolean(data.user?.id)).toBe(true);
      return { token: data.token, user: data.user };
    })());
  }
  return sessions.get(role);
}

export async function catalog(api) {
  const response = await api.get('/books?per_page=100');
  expect(response.status()).toBe(200);
  const books = rows(await response.json());
  const laravel = books.find((book) => book.slug === 'belajar-laravel-demo');
  const vue = books.find((book) => book.slug === 'panduan-vue-demo');
  expect(Boolean(laravel && vue), 'Run DemoSeeder against the isolated test database first.').toBe(true);
  return { books, laravel, vue };
}

export async function customerOrders(api) {
  const { token } = await session(api);
  const response = await api.get('/orders/me', token);
  expect(response.status()).toBe(200);
  const orders = rows(await response.json());
  const paid = orders.find((order) => order.payment_status === 'paid');
  const pending = orders.find((order) => order.payment_status === 'pending');
  expect(Boolean(paid && pending), 'Expected distinct paid and pending synthetic orders.').toBe(true);
  return { paid, pending };
}

export async function installState(page, state) {
  await page.addInitScript((entries) => {
    try {
      if (sessionStorage.getItem('zaimedia_e2e_initialized')) return;
      for (const [key, value] of Object.entries(entries)) localStorage.setItem(key, value);
      sessionStorage.setItem('zaimedia_e2e_initialized', '1');
    } catch { /* The script also runs in browser-owned PDF frames without local storage. */ }
  }, state);
}

export async function installSession(page, value) {
  await installState(page, { zaimedia_user: JSON.stringify(value.user), zaimedia_token: value.token });
}

export async function loginThroughForm(page, baseURL, username = usernames.customer) {
  await page.goto('/login');
  await page.getByLabel('Email atau username', { exact: true }).fill(username);
  try {
    await page.getByLabel('Password', { exact: true }).fill(isolation(baseURL).password);
    await page.locator('form button:not([type="button"])').click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
    const current = await page.evaluate(() => ({
      token: localStorage.getItem('zaimedia_token'),
      user: JSON.parse(localStorage.getItem('zaimedia_user')),
    }));
    // Login deliberately revokes older tokens for this synthetic account.
    sessions.set('customer', Promise.resolve(current));
  } finally {
    // Playwright's failure context can include password input values.
    const passwordField = page.getByLabel('Password', { exact: true });
    if (await passwordField.count()) await passwordField.fill('');
  }
}

export async function freshCustomer(api) {
  const suffix = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  const response = await api.post('/auth/register', {
    username: `e2e_${suffix}`,
    full_name: 'Integration Test Reader',
    email: `e2e_${suffix}@example.test`,
    password: fixture.password,
    confirm_password: fixture.password,
    phone: '0000000000',
  });
  expect(response.status()).toBe(201);
  const data = await response.json();
  return { token: data.token, user: data.user };
}

export function assertNoPublicPdfPaths(value, path = 'response') {
  const leaks = [];
  function inspect(node, current) {
    if (!node || typeof node !== 'object') return;
    for (const [key, child] of Object.entries(node)) {
      if (/^(pdf_file|pdf_path|pdf_url|download_url)$/.test(key)) leaks.push(`${current}.${key}`);
      inspect(child, `${current}.${key}`);
    }
  }
  inspect(value, path);
  expect(leaks, 'Public catalog must not disclose private PDF paths.').toEqual([]);
}

export async function noHorizontalOverflow(page) {
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
}

export function apiEndpoint(path) {
  return (url) => url.protocol === 'http:'
    && ['127.0.0.1', 'localhost'].includes(url.hostname)
    && url.port === '18090'
    && url.pathname === `/api${path}`;
}

export async function captureQA(page, name) {
  const directory = process.env.ZAIMEDIA_QA_DIR;
  if (!directory) return;
  mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: resolve(directory, `${name}.png`), fullPage: true, animations: 'disabled' });
}
