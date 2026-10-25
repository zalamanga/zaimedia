<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { CheckCircle2, Minus, Plus, Trash2 } from '@lucide/vue';
import { api, money } from '../api';
import { auth } from '../auth';
import { cart } from '../cart';
import { toast } from '../toast';
import { createRequestGate, orderMessage } from '../state-utils.js';
import { checkoutState } from '../checkout.js';

const router = useRouter();
const error = ref('');
const success = ref('');
const order = ref(null);
const checkingOut = computed(() => checkoutState.busy);
const formLocked = computed(() => checkoutState.locked);
const confirmReentry = ref(false);
let mounted = true;
const config = ref(null);
const configError = ref('');
const configLoading = ref(false);
const requests = createRequestGate();
const tax = computed(() => config.value ? Math.round(cart.subtotal * config.value.tax_rate) : null);
const total = computed(() => tax.value === null ? null : cart.subtotal + tax.value);

async function loadConfig() {
  const request = requests.begin();
  configLoading.value = true;
  configError.value = '';
  try {
    const { data } = await api.get('/storefront-config');
    if (!requests.isCurrent(request)) return;
    const rate = Number(data.tax_rate);
    if (!Number.isFinite(rate) || rate < 0 || rate > 1) throw new Error('Invalid tax rate');
    config.value = { tax_rate: rate, checkout_demo_mode: data.checkout_demo_mode === true };
  } catch {
    if (requests.isCurrent(request)) {
      config.value = null;
      configError.value = 'Konfigurasi checkout gagal dimuat. Coba lagi sebelum membuat order.';
    }
  } finally {
    if (requests.isCurrent(request)) configLoading.value = false;
  }
}
onMounted(() => {
  loadConfig();
  if (checkoutState.phase === 'confirmed') applyConfirmation(checkoutState.context());
});
onBeforeUnmount(() => { mounted = false; requests.cancel(); });
const emptyShipping = () => ({
  shipping_name: auth.user?.full_name || '',
  shipping_phone: auth.user?.phone || '',
  shipping_address: '',
  shipping_city: '',
  shipping_postal_code: '',
  shipping_method: 'regular',
  payment_method: 'bank_transfer',
  notes: '',
});
const shipping = ref(emptyShipping());
if (checkoutState.payload) {
  const { items, ...fields } = checkoutState.payload;
  shipping.value = fields;
}
watch(() => auth.token, () => {
  shipping.value = emptyShipping();
  confirmReentry.value = false;
  error.value = '';
  success.value = '';
  order.value = null;
});

function updateQty(item, delta) {
  cart.setQuantity(item.id, Number(item.quantity || 1) + delta);
}

async function applyConfirmation(context) {
  if (!mounted || !checkoutState.current(context) || checkoutState.phase !== 'confirmed') return;
  const confirmedOrder = checkoutState.order;
  const cleanup = await cart.clearConfirmed(context);
  if (!mounted || !checkoutState.current(context)) return;
  order.value = confirmedOrder;
  success.value = orderMessage(confirmedOrder);
  if (cleanup === 'storage-unavailable') {
    checkoutState.message = 'Order sudah tersimpan. Cart lokal belum dapat dibersihkan dengan pasti. Izinkan penyimpanan, lalu selesaikan konfirmasi; tidak perlu mengirim order lagi.';
    error.value = '';
    return;
  }
  if (cleanup === 'stale') return;
  if (cleanup === 'different' && cart.items.length) success.value += ' Cart yang berbeda tetap disimpan.';
  const released = checkoutState.acknowledge();
  if (released) {
    toast.success(success.value);
    router.push(`/orders/${confirmedOrder.order_number}/success`);
  }
}

async function checkout() {
  if (checkingOut.value) return;
  if (!auth.isLoggedIn) {
    toast.info('Login dulu untuk checkout.');
    router.push({ path: '/login', query: { redirect: '/cart' } });
    return;
  }
  const retrying = checkoutState.phase === 'unknown' && checkoutState.payload;
  if (!retrying && (!config.value || !cart.items.length)) return;
  if (!formLocked.value) cart.persist();
  error.value = '';
  success.value = '';
  const token = auth.token;
  const generation = checkoutState.generation;
  let context;
  try {
    context = await checkoutState.prepare(retrying ? null : {
      ...shipping.value,
      items: cart.items.map((item) => ({ book_id: item.id, quantity: item.quantity })),
    }, confirmReentry.value);
    if (!context || !checkoutState.current(context) || auth.token !== token) return;
    const { data } = await api.post('/orders', context.payload, {
      timeout: 20000,
      headers: { Authorization: `Bearer ${token}` },
    });
    if (checkoutState.confirm(context, data)) await applyConfirmation(context);
  } catch (err) {
    if (context) checkoutState.fail(context, err.response?.status);
    if (mounted && generation === checkoutState.generation && auth.token === token) {
      error.value = checkoutState.message || 'Checkout belum dapat dikirim. Periksa data formulir.';
    }
  }
}

async function reconcile() {
  const context = checkoutState.startReconcile();
  if (!context) return;
  const token = auth.token;
  error.value = '';
  try {
    const { data } = await api.get(`/checkout-operations/${context.key}`, {
      timeout: 20000,
      headers: { Authorization: `Bearer ${token}` },
    });
    if (data.operation_key !== context.key) { checkoutState.fail(context); return; }
    if (checkoutState.confirm(context, data.order, data.intent_hash)) await applyConfirmation(context);
  } catch {
    checkoutState.fail(context);
  }
}
</script>

<template>
  <section class="shell grid gap-6 py-8 lg:grid-cols-[1fr_420px]">
    <div class="panel min-w-0 p-[20px] sm:p-[24px]">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-leaf">Digital cart</p>
          <h1 class="break-words text-3xl font-bold">Checkout</h1>
        </div>
        <span class="rounded-md bg-paper px-3 py-2 text-sm font-semibold">{{ cart.count }} item</span>
      </div>

      <div v-if="!cart.items.length && !success" class="rounded-md bg-paper p-8 text-center text-ink/60">
        Cart masih kosong.
        <RouterLink to="/" class="mt-4 inline-flex text-sm font-semibold text-leaf">Cari buku</RouterLink>
      </div>

      <div v-if="success" class="rounded-md bg-green-50 p-5 text-green-800">
        <div class="flex items-start gap-3">
          <CheckCircle2 class="mt-0.5 shrink-0" :size="22" />
          <div>
            <h2 class="font-semibold">{{ success }}</h2>
            <p class="mt-1 text-sm">Order {{ order?.order_number }} berstatus {{ order?.payment_status }}.</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <RouterLink to="/dashboard" class="btn-primary">Buka Library</RouterLink>
              <RouterLink to="/" class="btn-soft">Belanja lagi</RouterLink>
            </div>
          </div>
        </div>
      </div>

      <div v-for="item in cart.items" :key="item.id" class="flex flex-wrap gap-4 border-b border-ink/10 py-4 last:border-b-0">
        <img v-if="item.cover_url" :src="item.cover_url" class="h-28 w-20 rounded-md object-cover" :alt="item.title" />
        <div v-else role="img" :aria-label="'Sampul belum tersedia: ' + item.title" class="grid h-28 w-20 shrink-0 place-items-center rounded-md bg-ink/5 p-2 text-center text-xs text-ink/50">Sampul belum tersedia</div>
        <div class="min-w-0 flex-1 basis-40 break-words">
          <h2 class="font-semibold">{{ item.title }}</h2>
          <p class="text-sm text-ink/55">{{ item.author }}</p>
          <p class="mt-2 font-semibold">{{ money(item.price) }}</p>
          <button class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-red-600 disabled:opacity-50" :disabled="formLocked" @click="cart.remove(item.id)">
            <Trash2 :size="15" />
            Remove
          </button>
        </div>
        <div class="flex max-w-full flex-wrap items-center gap-2 sm:justify-end">
          <button class="btn-soft h-10 w-10 p-0" type="button" :disabled="formLocked" :aria-label="'Kurangi jumlah ' + item.title" @click="updateQty(item, -1)">
            <Minus :size="16" />
          </button>
          <input :value="item.quantity" :disabled="formLocked" :aria-label="'Jumlah ' + item.title" type="number" min="1" max="10" step="1" class="field h-10 w-20 text-center" @change="cart.setQuantity(item.id, $event.target.value)" />
          <button class="btn-soft h-10 w-10 p-0" type="button" :disabled="formLocked" :aria-label="'Tambah jumlah ' + item.title" @click="updateQty(item, 1)">
            <Plus :size="16" />
          </button>
        </div>
      </div>
    </div>

    <form class="panel min-w-0 h-fit space-y-3 p-[20px] sm:p-[24px]" :aria-busy="checkingOut || configLoading" @submit.prevent="checkout">
      <div>
        <p class="text-sm font-semibold text-leaf">{{ config?.checkout_demo_mode ? 'Payment demo' : 'Order buku digital' }}</p>
        <h2 class="text-xl font-semibold">Data customer</h2>
      </div>
      <p v-if="error && error !== checkoutState.message" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <div v-if="checkoutState.message || checkoutState.phase === 'preparing' || checkoutState.phase === 'submitting'" :role="error || (checkoutState.unresolved && !checkingOut) ? 'alert' : 'status'" class="rounded-md bg-paper p-4 text-sm leading-6">
        <p>{{ checkingOut ? 'Mengonfirmasi checkout. Cart dan data dikunci selama permintaan berjalan.' : checkoutState.message }}</p>
        <p v-if="!checkoutState.durable" class="mt-2 font-semibold">{{ checkoutState.phase === 'confirmed' ? 'Order sudah dikonfirmasi server. Pemulihan catatan lokal belum selesai; tombol di bawah tidak mengirim order baru.' : 'Catatan tab tidak tersedia. Jangan tutup atau muat ulang tab ini selama hasil belum jelas. Tidak ada jaminan pemulihan setelah tab ditutup.' }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button v-if="checkoutState.metadata && !checkingOut && checkoutState.phase !== 'confirmed'" class="btn-soft" type="button" @click="reconcile">Periksa hasil</button>
          <button v-if="checkoutState.phase === 'unknown' && checkoutState.payload" class="btn-primary" type="button" @click="checkout">Coba operasi yang sama</button>
          <button v-if="checkoutState.phase === 'blocked'" class="btn-soft" type="button" @click="checkoutState.reloadMetadata()">Baca ulang catatan tab</button>
          <button v-if="checkoutState.phase === 'confirmed'" class="btn-soft" type="button" @click="applyConfirmation(checkoutState.context())">Selesaikan konfirmasi lokal</button>
          <RouterLink v-if="checkoutState.unresolved" class="font-semibold text-leaf underline" to="/dashboard?tab=orders">Lihat daftar order</RouterLink>
        </div>
      </div>
      <p v-if="configLoading" role="status" class="text-sm text-ink/65">Memuat konfigurasi checkout...</p>
      <div v-if="configError" role="alert" class="text-sm text-red-700">
        <p>{{ configError }}</p><button class="btn-soft mt-2" type="button" @click="loadConfig">Coba lagi</button>
      </div>
      <fieldset :disabled="formLocked" class="space-y-3">
      <input v-model="shipping.shipping_name" aria-label="Nama penerima" autocomplete="name" class="field" placeholder="Nama penerima" maxlength="100" required />
      <input v-model="shipping.shipping_phone" aria-label="Nomor HP" autocomplete="tel" class="field" placeholder="Nomor HP" required />
      <textarea v-model="shipping.shipping_address" aria-label="Alamat" autocomplete="street-address" class="field min-h-24" placeholder="Alamat" required />
      <div class="grid gap-3 sm:grid-cols-2">
        <input v-model="shipping.shipping_city" aria-label="Kota" autocomplete="address-level2" class="field" placeholder="Kota" required />
        <input v-model="shipping.shipping_postal_code" aria-label="Kode pos" autocomplete="postal-code" class="field" placeholder="Kode pos" required />
      </div>
      <select v-model="shipping.payment_method" aria-label="Metode pembayaran" class="field px-[12px]">
        <option value="bank_transfer">Bank transfer</option>
        <option value="credit_card">Credit card</option>
        <option value="e_wallet">E-wallet</option>
        <option value="cod">COD</option>
      </select>
      <textarea v-model="shipping.notes" aria-label="Catatan opsional" class="field" placeholder="Catatan opsional" />
      <label v-if="checkoutState.phase === 'reentry'" class="flex items-start gap-2 rounded-md bg-paper p-3 text-sm leading-6">
        <input v-model="confirmReentry" class="mt-1" type="checkbox" required />
        <span>Saya mengisi ulang buku, jumlah, dan data yang sama. Kirim ulang operasi lama hanya jika identitas datanya cocok.</span>
      </label>
      </fieldset>
      <div class="rounded-md bg-paper p-4">
        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm"><span>Subtotal</span><strong class="ml-auto min-w-0 max-w-full break-words">{{ money(cart.subtotal) }}</strong></div>
        <div class="mt-2 flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm"><span>Pajak{{ config ? ' ' + new Intl.NumberFormat('id-ID', { style: 'percent', maximumFractionDigits: 2 }).format(config.tax_rate) : '' }}</span><strong class="ml-auto min-w-0 max-w-full break-words">{{ tax === null ? 'Belum tersedia' : money(tax) }}</strong></div>
        <div class="mt-3 flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-ink/10 pt-3"><span>Estimasi total</span><strong class="ml-auto min-w-0 max-w-full break-words">{{ total === null ? 'Belum tersedia' : money(total) }}</strong></div>
      </div>
      <button class="btn-primary w-full" :disabled="!cart.items.length || formLocked || !config || configLoading">
        {{ checkingOut ? 'Processing...' : config?.checkout_demo_mode ? 'Checkout demo' : 'Buat order' }}
      </button>
      <p class="text-center text-xs leading-5 text-ink/65">{{ config?.checkout_demo_mode ? 'Mode demo: tidak ada pembayaran nyata. Order demo langsung paid dan ebook aktif di Library.' : 'Belum tersedia payment gateway. Order menunggu konfirmasi pembayaran; akses buku mengikuti status dari server.' }} Total akhir dihitung server memakai harga buku terbaru.</p>
    </form>
  </section>
</template>
