import { reactive } from 'vue';
import { toast } from './toast.js';
import { auth } from './auth.js';
import { readJson, readText, writeJson } from './storage.js';
import { normalizeCart } from './state-utils.js';
import { checkoutState } from './checkout.js';
import { fingerprint, normalizeItems } from './checkout-intent.js';

const key = 'zaimedia_cart_vue';

export const cart = reactive({
  items: normalizeCart(readJson(key, [])),
  add(book) {
    if (!this.canEdit()) return;
    const writerId = Number(book.writer_id || book.writer?.id || 0);
    if (auth.user?.role === 'writer' && writerId && writerId === Number(auth.user.id)) {
      toast.error('Writer tidak bisa membeli buku sendiri.');
      return;
    }

    const found = this.items.find((item) => item.id === Number(book.id));
    if (found) {
      found.quantity = Math.min(10, Number(found.quantity || 1) + 1);
      toast.info(`${book.title} ditambahkan lagi ke cart.`);
    } else {
      this.items.push({
        id: book.id,
        title: book.title,
        author: book.author,
        price: Number(book.price),
        cover_url: book.cover_url,
        quantity: 1,
      });
      toast.success(`${book.title} masuk cart.`);
    }
    this.persist();
  },
  remove(id) {
    if (!this.canEdit()) return;
    const found = this.items.find((item) => item.id === id);
    this.items = this.items.filter((item) => item.id !== id);
    this.persist();
    if (found) {
      toast.info(`${found.title} dihapus dari cart.`);
    }
  },
  clear() {
    if (!this.canEdit()) return;
    this.items = [];
    this.persist();
  },
  persist() {
    this.items = normalizeCart(this.items);
    writeJson(key, this.items);
  },
  canEdit() {
    if (!checkoutState.locked) return true;
    toast.info('Konfirmasikan hasil checkout sebelumnya sebelum mengubah cart.');
    return false;
  },
  setQuantity(id, quantity) {
    if (!this.canEdit()) return;
    const item = this.items.find((row) => row.id === id);
    if (!item) return;
    const value = Number(quantity);
    item.quantity = Number.isFinite(value) ? Math.min(10, Math.max(1, Math.trunc(value))) : 1;
    this.persist();
  },
  async clearConfirmed(context) {
    if (!checkoutState.current(context) || checkoutState.phase !== 'confirmed') return 'stale';
    let hash;
    let snapshot;
    try {
      const items = this.items.length ? normalizeItems(this.items.map((item) => ({ book_id: item.id, quantity: item.quantity }))) : [];
      snapshot = JSON.stringify(items);
      hash = items.length ? await fingerprint(items) : null;
    } catch { return 'different'; }
    if (!checkoutState.current(context)) return 'stale';
    if (this.items.length && hash !== checkoutState.metadata.cart_hash) return 'different';
    try {
      const current = this.items.length ? normalizeItems(this.items.map((item) => ({ book_id: item.id, quantity: item.quantity }))) : [];
      if (JSON.stringify(current) !== snapshot) return 'different';
      const raw = readText(key);
      if (raw === null) return 'storage-unavailable';
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return 'storage-unavailable';
      const stored = normalizeCart(parsed);
      if (stored.length !== parsed.length) return 'storage-unavailable';
      const storedItems = stored.length ? normalizeItems(stored.map((item) => ({ book_id: item.id, quantity: item.quantity }))) : [];
      if (JSON.stringify(storedItems) !== snapshot) {
        this.items = stored;
        return 'different';
      }
    } catch { return 'storage-unavailable'; }
    // No await between the final persisted identity read, write and readback.
    if (!writeJson(key, []) || readText(key) !== '[]') return 'storage-unavailable';
    this.items = [];
    return 'cleared';
  },
  get count() {
    return this.items.reduce((sum, item) => sum + item.quantity, 0);
  },
  get subtotal() {
    return this.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
  },
});
