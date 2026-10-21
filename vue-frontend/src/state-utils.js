export function validUser(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    && Number.isInteger(Number(value.id)) && Number(value.id) > 0
    && ['customer', 'writer', 'admin', 'super_admin'].includes(value.role);
}

export function normalizeCart(value) {
  if (!Array.isArray(value)) return [];
  const items = new Map();
  for (const row of value) {
    if (!row || typeof row !== 'object') continue;
    const id = Number(row.id);
    const price = Number(row.price);
    const quantity = Number(row.quantity ?? 1);
    if (!Number.isInteger(id) || id <= 0 || !Number.isFinite(price) || price < 0 || !Number.isFinite(quantity)) continue;
    const existing = items.get(id);
    items.set(id, {
      id,
      title: String(row.title || 'Buku'),
      author: String(row.author || ''),
      cover_url: typeof row.cover_url === 'string' ? row.cover_url : '',
      price,
      quantity: Math.min(10, Math.max(1, Math.trunc(quantity) + (existing?.quantity || 0))),
    });
  }
  return [...items.values()];
}

export function countByStatus(rows, field) {
  return rows.reduce((counts, row) => {
    const key = row[field] || 'unknown';
    if (key !== 'all') counts[key] = (counts[key] || 0) + 1;
    return counts;
  }, { all: rows.length });
}

export function createRequestGate() {
  let current = 0;
  return {
    begin: () => ++current,
    isCurrent: (request) => request === current,
    cancel: () => { current += 1; },
  };
}

export function redirectTarget(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
    && !value.includes('\\') && !value.startsWith('/login') ? value : '/dashboard';
}

export function orderMessage(order) {
  return order?.payment_status === 'paid'
    ? 'Order sudah paid. Buku tersedia di Library.'
    : 'Order tersimpan. Buku akan tersedia setelah pembayaran dikonfirmasi.';
}
