import { test, expect, rows, catalog, session, customerOrders, assertNoPublicPdfPaths } from './support.js';

test('public configuration and catalog expose useful metadata without PDF paths', async ({ api }) => {
  const configResponse = await api.get('/storefront-config');
  expect(configResponse.status()).toBe(200);
  const config = await configResponse.json();
  expect(typeof config.checkout_demo_mode).toBe('boolean');
  expect(Number(config.tax_rate)).toBeGreaterThanOrEqual(0);
  expect(Number(config.tax_rate)).toBeLessThanOrEqual(1);
  const { books, laravel } = await catalog(api);
  assertNoPublicPdfPaths(books);
  const response = await api.get(`/books/${laravel.slug}`);
  expect(response.status()).toBe(200);
  const detail = await response.json();
  expect(detail.id).toBe(laravel.id);
  expect(detail.has_pdf).toBe(true);
  assertNoPublicPdfPaths(detail);
});

test('customer and writer roles cannot access administrator endpoints', async ({ api }) => {
  const customer = await session(api);
  const writer = await session(api, 'writer');
  expect((await api.get('/admin/users', customer.token)).status()).toBe(403);
  expect((await api.get('/writer/books', customer.token)).status()).toBe(403);
  expect((await api.get('/admin/users', writer.token)).status()).toBe(403);
  expect((await api.get('/writer/books', writer.token)).status()).toBe(200);
});

test('guest, nonbuyer and pending buyer cannot download a private PDF', async ({ api }) => {
  const { laravel, vue } = await catalog(api);
  const buyer = await session(api);
  const other = await session(api, 'otherCustomer');
  expect((await api.get(`/library/${laravel.id}/download`)).status()).toBe(401);
  expect((await api.get(`/library/${laravel.id}/download`, other.token)).status()).toBe(403);
  expect((await api.get(`/library/${vue.id}/download`, buyer.token)).status()).toBe(403);
});

test('paid buyer receives a real PDF through the authenticated download endpoint', async ({ api }) => {
  const { laravel } = await catalog(api);
  const buyer = await session(api);
  const response = await api.get(`/library/${laravel.id}/download`, buyer.token);
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('application/pdf');
  const content = await response.body();
  expect(content.subarray(0, 5).toString('ascii')).toBe('%PDF-');
  const library = await api.get('/library', buyer.token);
  expect(rows(await library.json()).some((book) => book.id === laravel.id)).toBe(true);
});

test('order details remain private to the buyer', async ({ api }) => {
  const { paid } = await customerOrders(api);
  const buyer = await session(api);
  const other = await session(api, 'otherCustomer');
  expect((await api.get(`/orders/${paid.id}`, buyer.token)).status()).toBe(200);
  expect([403, 404]).toContain((await api.get(`/orders/${paid.id}`, other.token)).status());
  expect((await api.get(`/orders/${paid.id}`)).status()).toBe(401);
});

test('super administrator can read administrator data without losing its role', async ({ api }) => {
  const { token } = await session(api, 'superAdmin');
  const me = await api.get('/auth/me', token);
  expect((await me.json()).role).toBe('super_admin');
  for (const path of ['/admin/users', '/admin/books', '/admin/settings']) {
    expect((await api.get(path, token)).status(), path).toBe(200);
  }
});

test('writer multipart uploads keep PDFs private and serve PNG and GIF covers through nginx', async ({ api }) => {
  const { token } = await session(api, 'writer');
  const { laravel } = await catalog(api);
  const original = await api.get(`/library/${laravel.id}/download`, token);
  expect(original.status()).toBe(200);
  const pdf = await original.body();
  const uploadedPdfResponse = await api.multipart('/uploads/pdf', {
    pdf: { name: 'integration-demo.pdf', mimeType: 'application/pdf', buffer: pdf },
  }, token);
  expect(uploadedPdfResponse.status()).toBe(200);
  const uploadedPdf = await uploadedPdfResponse.json();
  expect(typeof uploadedPdf.file).toBe('string');
  expect(uploadedPdf.url).toBeUndefined();

  const images = [
    { name: 'integration-cover.png', mimeType: 'image/png', base64: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNQaEj4DwADxAIAttaXgAAAAABJRU5ErkJggg==' },
    { name: 'integration-cover.gif', mimeType: 'image/gif', base64: 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' },
  ];
  let cover;
  for (const image of images) {
    const buffer = Buffer.from(image.base64, 'base64');
    const response = await api.multipart('/uploads/cover', {
      cover: { name: image.name, mimeType: image.mimeType, buffer },
    }, token);
    expect(response.status(), image.mimeType).toBe(200);
    cover = await response.json();
    const served = await api.resource(cover.url);
    expect(served.status(), `nginx ${image.mimeType}`).toBe(200);
    expect(served.headers()['content-type']).toContain(image.mimeType);
    expect((await served.body()).equals(buffer)).toBe(true);
  }

  const createdResponse = await api.post('/writer/books', {
    title: `Integration upload ${Date.now().toString(36)}`,
    author: 'Synthetic Integration Writer',
    price: 1000,
    category_id: laravel.category_id,
    cover_image: cover.file,
    pdf_file: uploadedPdf.file,
    status: 'approved',
  }, token);
  expect(createdResponse.status()).toBe(201);
  const created = await createdResponse.json();
  expect(created.status).toBe('pending');
  expect(created.has_pdf).toBe(true);
  assertNoPublicPdfPaths(created);
  expect((await api.get(`/books/${created.id}`)).status()).toBe(404);
  expect((await catalog(api)).books.some((book) => book.id === created.id)).toBe(false);
  for (const path of [`/uploads/books/pdf/${uploadedPdf.file}`, `/storage/app/private/books/pdf/${uploadedPdf.file}`]) {
    expect((await api.resource(path)).status()).toBe(404);
  }
});
