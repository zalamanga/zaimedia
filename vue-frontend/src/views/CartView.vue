<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { CheckCircle2, Minus, Plus, Trash2 } from '@lucide/vue';
import { api, money } from '../api';
import { auth } from '../auth';
import { cart } from '../cart';
import { toast } from '../toast';
import { createRequestGate, orderMessage } from '../state-utils.js';

const router = useRouter();
const error = ref('');
const success = ref('');
const order = ref(null);
const checkingOut = ref(false);
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
onMounted(loadConfig);
onBeforeUnmount(() => requests.cancel());
const shipping = ref({
  shipping_name: auth.user?.full_name || '',
  shipping_phone: auth.user?.phone || '',
  shipping_address: '',
  shipping_city: '',
  shipping_postal_code: '',
  shipping_method: 'regular',
  payment_method: 'bank_transfer',
  notes: '',
});

function updateQty(item, delta) {
  item.quantity = Math.min(10, Math.max(1, Number(item.quantity || 1) + delta));
  cart.persist();
}

async function checkout() {
  if (checkingOut.value) return;
  if (!auth.isLoggedIn) {
    toast.info('Login dulu untuk checkout.');
    router.push({ path: '/login', query: { redirect: '/cart' } });
    return;
  }
  cart.persist();
  if (!config.value || !cart.items.length) return;
  error.value = '';
  success.value = '';
  checkingOut.value = true;
  try {
    const { data } = await api.post('/orders', {
      ...shipping.value,
      items: cart.items.map((item) => ({ book_id: item.id, quantity: item.quantity })),
    });
    order.value = data;
    cart.clear();
    success.value = orderMessage(data);
    toast.success(success.value);
    router.push(`/orders/${data.order_number || data.id}/success`);
  } catch (err) {
    error.value = err.response?.data?.message || 'Checkout gagal.';
    toast.error(error.value);
  } finally {
    checkingOut.value = false;
  }
}
</script>

<template>
  <section class="shell grid gap-6 py-8 lg:grid-cols-[1fr_420px]">
    <div class="panel p-5 sm:p-6">
      <div class="mb-5 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-semibold text-leaf">Digital cart</p>
          <h1 class="text-3xl font-bold">Checkout</h1>
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

      <div v-for="item in cart.items" :key="item.id" class="grid gap-4 border-b border-ink/10 py-4 last:border-b-0 sm:grid-cols-[80px_1fr_auto]">
        <img v-if="item.cover_url" :src="item.cover_url" class="h-28 w-20 rounded-md object-cover" :alt="item.title" />
        <div v-else class="grid h-28 w-20 place-items-center rounded-md bg-ink/5 p-2 text-center text-xs text-ink/50">{{ item.title }}</div>
        <div>
          <h2 class="font-semibold">{{ item.title }}</h2>
          <p class="text-sm text-ink/55">{{ item.author }}</p>
          <p class="mt-2 font-semibold">{{ money(item.price) }}</p>
          <button class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-red-600" @click="cart.remove(item.id)">
            <Trash2 :size="15" />
            Remove
          </button>
        </div>
        <div class="flex items-center gap-2 sm:justify-end">
          <button class="btn-soft h-10 w-10 p-0" type="button" :aria-label="'Kurangi jumlah ' + item.title" @click="updateQty(item, -1)">
            <Minus :size="16" />
          </button>
          <input v-model.number="item.quantity" :aria-label="'Jumlah ' + item.title" type="number" min="1" max="10" step="1" class="field h-10 w-20 text-center" @change="cart.persist()" />
          <button class="btn-soft h-10 w-10 p-0" type="button" :aria-label="'Tambah jumlah ' + item.title" @click="updateQty(item, 1)">
            <Plus :size="16" />
          </button>
        </div>
      </div>
    </div>

    <form class="panel h-fit space-y-3 p-5 sm:p-6" :aria-busy="checkingOut || configLoading" @submit.prevent="checkout">
      <div>
        <p class="text-sm font-semibold text-leaf">{{ config?.checkout_demo_mode ? 'Payment demo' : 'Order buku digital' }}</p>
        <h2 class="text-xl font-semibold">Data customer</h2>
      </div>
      <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <p v-if="configLoading" role="status" class="text-sm text-ink/65">Memuat konfigurasi checkout...</p>
      <div v-if="configError" role="alert" class="text-sm text-red-700">
        <p>{{ configError }}</p><button class="btn-soft mt-2" type="button" @click="loadConfig">Coba lagi</button>
      </div>
      <input v-model="shipping.shipping_name" aria-label="Nama penerima" autocomplete="name" class="field" placeholder="Nama penerima" required />
      <input v-model="shipping.shipping_phone" aria-label="Nomor HP" autocomplete="tel" class="field" placeholder="Nomor HP" required />
      <textarea v-model="shipping.shipping_address" aria-label="Alamat" autocomplete="street-address" class="field min-h-24" placeholder="Alamat" required />
      <div class="grid gap-3 sm:grid-cols-2">
        <input v-model="shipping.shipping_city" aria-label="Kota" autocomplete="address-level2" class="field" placeholder="Kota" required />
        <input v-model="shipping.shipping_postal_code" aria-label="Kode pos" autocomplete="postal-code" class="field" placeholder="Kode pos" required />
      </div>
      <select v-model="shipping.payment_method" aria-label="Metode pembayaran" class="field">
        <option value="bank_transfer">Bank transfer</option>
        <option value="credit_card">Credit card</option>
        <option value="e_wallet">E-wallet</option>
        <option value="cod">COD</option>
      </select>
      <textarea v-model="shipping.notes" aria-label="Catatan opsional" class="field" placeholder="Catatan opsional" />
      <div class="rounded-md bg-paper p-4">
        <div class="flex justify-between text-sm"><span>Subtotal</span><strong>{{ money(cart.subtotal) }}</strong></div>
        <div class="mt-2 flex justify-between text-sm"><span>Pajak{{ config ? ' ' + new Intl.NumberFormat('id-ID', { style: 'percent', maximumFractionDigits: 2 }).format(config.tax_rate) : '' }}</span><strong>{{ tax === null ? 'Belum tersedia' : money(tax) }}</strong></div>
        <div class="mt-3 flex justify-between border-t border-ink/10 pt-3"><span>Estimasi total</span><strong>{{ total === null ? 'Belum tersedia' : money(total) }}</strong></div>
      </div>
      <button class="btn-primary w-full" :disabled="!cart.items.length || checkingOut || !config || configLoading">
        {{ checkingOut ? 'Processing...' : config?.checkout_demo_mode ? 'Checkout demo' : 'Buat order' }}
      </button>
      <p class="text-center text-xs leading-5 text-ink/65">{{ config?.checkout_demo_mode ? 'Mode demo: tidak ada pembayaran nyata. Order demo langsung paid dan ebook aktif di Library.' : 'Belum tersedia payment gateway. Order menunggu konfirmasi pembayaran; akses buku mengikuti status dari server.' }} Total akhir dihitung server memakai harga buku terbaru.</p>
    </form>
  </section>
</template>
