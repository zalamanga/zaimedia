import { reactive } from 'vue';
import { toast } from './toast.js';
import { auth } from './auth.js';
import { readJson, writeJson } from './storage.js';
import { normalizeCart } from './state-utils.js';

const key = 'zaimedia_cart_vue';

export const cart = reactive({
  items: normalizeCart(readJson(key, [])),
  add(book) {
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
    const found = this.items.find((item) => item.id === id);
    this.items = this.items.filter((item) => item.id !== id);
    this.persist();
    if (found) {
      toast.info(`${found.title} dihapus dari cart.`);
    }
  },
  clear() {
    this.items = [];
    this.persist();
  },
  persist() {
    this.items = normalizeCart(this.items);
    writeJson(key, this.items);
  },
  get count() {
    return this.items.reduce((sum, item) => sum + item.quantity, 0);
  },
  get subtotal() {
    return this.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
  },
});
