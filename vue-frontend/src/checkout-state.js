import { fingerprint, frozenIntent, validOperationKey } from './checkout-intent.js';

const HASH = /^[0-9a-f]{64}$/;
const storageKey = (id) => `zaimedia_checkout_v1_${id}`;
const storageMessage = 'Identitas checkout tidak dapat disimpan di tab ini. Izinkan sessionStorage sebelum mengirim order. Jangan muat ulang saat hasil operasi belum jelas.';

function validMetadata(value, accountId) {
  return value?.version === 1 && value.customer_id === accountId && validOperationKey(value.operation_key)
    && typeof value.intent_hash === 'string' && HASH.test(value.intent_hash)
    && typeof value.cart_hash === 'string' && HASH.test(value.cart_hash)
    && Object.keys(value).sort().join(',') === 'cart_hash,customer_id,intent_hash,operation_key,version';
}

function validOrder(order) {
  return Number.isSafeInteger(order?.id) && order.id > 0 && typeof order.order_number === 'string'
    && order.order_number.length > 0 && ['pending', 'paid', 'failed', 'refunded'].includes(order.payment_status);
}

export function createCheckoutState({ storage = () => globalThis.sessionStorage, uuid = () => globalThis.crypto.randomUUID() } = {}) {
  return {
    accountId: null,
    session: null,
    generation: 0,
    phase: 'idle',
    metadata: null,
    payload: null,
    order: null,
    durable: true,
    message: '',
    get busy() { return this.phase === 'preparing' || this.phase === 'submitting' || this.phase === 'reconciling'; },
    get locked() { return this.busy || (this.phase !== 'idle' && this.phase !== 'reentry'); },
    get unresolved() { return this.phase !== 'idle' && this.phase !== 'confirmed'; },
    setAccount(id, session = null) {
      const nextId = Number.isSafeInteger(id) && id > 0 ? id : null;
      if (this.accountId === nextId && this.session === session) return;
      this.generation += 1;
      this.accountId = nextId;
      this.session = session;
      this.payload = null;
      this.order = null;
      this.metadata = null;
      this.phase = 'idle';
      this.message = '';
      this.durable = true;
      if (!nextId) return;
      try {
        const store = storage();
        if (!store) throw new Error('Unavailable storage');
        const raw = store.getItem(storageKey(nextId));
        if (raw === null) return;
        const metadata = JSON.parse(raw);
        if (!validMetadata(metadata, nextId)) {
          this.phase = 'blocked';
          this.message = 'Catatan checkout tab ini rusak. Periksa daftar order sebelum melakukan checkout lain; identitas lama tidak akan diganti otomatis.';
          return;
        }
        this.metadata = Object.freeze(metadata);
        this.phase = 'reentry';
        this.message = 'Ada checkout yang belum dikonfirmasi. Periksa hasilnya, atau isi ulang data yang sama untuk mencoba operasi yang sama.';
      } catch {
        this.phase = 'blocked';
        this.durable = false;
        this.message = storageMessage;
      }
    },
    reloadMetadata() {
      if (this.phase !== 'blocked') return;
      const id = this.accountId;
      const session = this.session;
      this.accountId = null;
      this.setAccount(id, session);
    },
    context() {
      return { generation: this.generation, accountId: this.accountId, key: this.metadata?.operation_key };
    },
    current(context) {
      return context?.generation === this.generation && context.accountId === this.accountId && context.key === this.metadata?.operation_key;
    },
    async prepare(input, confirmReentry = false) {
      if (!this.accountId || this.busy || !['idle', 'unknown', 'reentry'].includes(this.phase)) throw new Error('Selesaikan konfirmasi checkout sebelumnya dahulu.');
      const generation = this.generation;
      const previousPhase = this.phase;
      const wasNew = !this.metadata;
      this.phase = 'preparing';
      try {
        let payload = this.payload;
        if (!payload) {
          if (!wasNew && !confirmReentry) throw new Error('Konfirmasikan bahwa data yang diisi sama dengan checkout sebelumnya.');
          payload = frozenIntent(input);
        }
        const hash = await fingerprint(payload);
        const cartHash = await fingerprint(payload.items);
        if (this.generation !== generation) return null;
        if (this.metadata && hash !== this.metadata.intent_hash) throw new Error('Data berbeda dari checkout sebelumnya. Periksa order; kunci operasi tidak akan diganti.');
        if (!this.metadata) {
          const key = uuid().toLowerCase();
          if (!validOperationKey(key)) throw new Error('Browser tidak dapat membuat identitas checkout.');
          const metadata = { version: 1, customer_id: this.accountId, operation_key: key, intent_hash: hash, cart_hash: cartHash };
          try {
            const store = storage();
            if (!store) throw new Error('Unavailable storage');
            const encoded = JSON.stringify(metadata);
            store.setItem(storageKey(this.accountId), encoded);
            if (store.getItem(storageKey(this.accountId)) !== encoded) throw new Error('Storage verification failed');
          } catch {
            this.durable = false;
            throw new Error(storageMessage);
          }
          this.metadata = Object.freeze(metadata);
        }
        this.payload = payload;
        this.phase = 'submitting';
        this.message = '';
        return { ...this.context(), wasNew, payload: { ...payload, operation_key: this.metadata.operation_key } };
      } catch (error) {
        if (this.generation === generation) {
          this.phase = previousPhase;
          this.message = error.message;
        }
        throw error;
      }
    },
    fail(context, status) {
      if (!this.current(context)) return;
      if (context.wasNew && status === 422) {
        // Only the first request's validation rejection proves this new operation did not commit.
        if (this.removeMetadata()) {
          this.phase = 'idle';
          this.payload = null;
          this.metadata = null;
          this.message = 'Data ditolak server. Perbaiki isian sebelum membuat order.';
          return;
        }
      }
      this.phase = this.payload ? 'unknown' : 'reentry';
      this.message = 'Hasil checkout belum dapat dikonfirmasi. Periksa hasil atau coba lagi dengan operasi yang sama; jangan membuat order baru.';
      this.verifyDurability();
    },
    startReconcile() {
      if (!this.metadata || this.busy || this.phase === 'confirmed') return null;
      this.phase = 'reconciling';
      return this.context();
    },
    confirm(context, order, hash = this.metadata?.intent_hash) {
      if (!this.current(context)) return false;
      if (hash !== this.metadata.intent_hash || !validOrder(order)) {
        this.fail(context);
        return false;
      }
      this.order = order;
      this.phase = 'confirmed';
      this.message = 'Server mengonfirmasi order ini. Status pembayaran mengikuti order, bukan pengiriman ulang formulir.';
      return true;
    },
    removeMetadata() {
      try {
        const store = storage();
        if (!store) throw new Error('Unavailable storage');
        store.removeItem(storageKey(this.accountId));
        if (store.getItem(storageKey(this.accountId)) !== null) throw new Error('Removal verification failed');
        return true;
      } catch {
        this.durable = false;
        this.message = this.phase === 'confirmed'
          ? 'Order sudah tersimpan. Catatan konfirmasi lokal belum dapat dihapus. Izinkan sessionStorage lalu selesaikan konfirmasi lokal; jangan kirim order lagi.'
          : storageMessage;
        return false;
      }
    },
    verifyDurability() {
      try {
        const raw = storage()?.getItem(storageKey(this.accountId));
        if (!raw || JSON.stringify(this.metadata) !== raw) throw new Error('Metadata unavailable');
      } catch {
        this.durable = false;
      }
    },
    acknowledge() {
      if (this.phase !== 'confirmed' || !this.removeMetadata()) return false;
      this.generation += 1;
      this.metadata = null;
      this.payload = null;
      this.order = null;
      this.phase = 'idle';
      this.message = '';
      return true;
    },
  };
}
