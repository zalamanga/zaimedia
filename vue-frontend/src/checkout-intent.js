const EDGE_SPACE = /^[\u0000-\u0020\u0085\u00a0\u1680\u2000-\u200b\u2028\u2029\u202f\u205f\u3000\ufeff]+|[\u0000-\u0020\u0085\u00a0\u1680\u2000-\u200b\u2028\u2029\u202f\u205f\u3000\ufeff]+$/gu;
const MAX_ID = Number.MAX_SAFE_INTEGER;
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function validOperationKey(value) {
  return typeof value === 'string' && UUID.test(value);
}

function text(value, max, required = false) {
  if (value == null && !required) return '';
  if (typeof value !== 'string') throw new Error('Data pengiriman harus berupa teks Unicode yang valid.');
  for (const char of value) {
    const point = char.codePointAt(0);
    if (point >= 0xd800 && point <= 0xdfff) throw new Error('Data pengiriman harus berupa teks Unicode yang valid.');
  }
  const result = value.replace(EDGE_SPACE, '');
  if ((required && !result) || [...result].length > max) throw new Error('Data pengiriman kosong atau melebihi batas panjang.');
  return result;
}

export function normalizeItems(rows) {
  if (!Array.isArray(rows) || rows.length < 1 || rows.length > 100) throw new Error('Cart harus berisi 1 sampai 100 baris buku.');
  const items = new Map();
  for (const row of rows) {
    const id = row?.book_id;
    const quantity = row?.quantity ?? 1;
    if (!Number.isSafeInteger(id) || id < 1 || id > MAX_ID || !Number.isInteger(quantity) || quantity < 1 || quantity > 10) {
      throw new Error('ID buku dan jumlah harus berupa bilangan bulat yang valid.');
    }
    items.set(id, Math.min(10, (items.get(id) || 0) + quantity));
  }
  return [...items.entries()].sort(([a], [b]) => a - b).map(([book_id, quantity]) => ({ book_id, quantity }));
}

export function normalizeIntent(input) {
  const result = { items: normalizeItems(input.items) };
  for (const [field, max] of Object.entries({ shipping_name: 100, shipping_phone: 20, shipping_address: 2000, shipping_city: 50, shipping_postal_code: 10 })) {
    result[field] = text(input[field], max, true);
  }
  result.shipping_method = text(input.shipping_method, 20) || 'regular';
  result.payment_method = text(input.payment_method, 20) || 'bank_transfer';
  if (!['regular', 'express', 'same_day'].includes(result.shipping_method) || !['bank_transfer', 'credit_card', 'e_wallet', 'cod'].includes(result.payment_method)) {
    throw new Error('Metode pengiriman atau pembayaran tidak valid.');
  }
  result.notes = text(input.notes, 2000) || null;
  return result;
}

export async function fingerprint(value) {
  if (!globalThis.crypto?.subtle) throw new Error('Browser tidak mendukung identitas checkout aman. Gunakan browser terbaru melalui HTTPS atau localhost.');
  const digest = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(value)));
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

export function frozenIntent(input) {
  const intent = normalizeIntent(input);
  for (const item of intent.items) Object.freeze(item);
  Object.freeze(intent.items);
  return Object.freeze(intent);
}
