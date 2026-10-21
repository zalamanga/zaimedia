<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowRight, BookOpen, CheckCircle2, Download, LibraryBig, ReceiptText, ShieldCheck, ShoppingBag, Sparkles } from '@lucide/vue';
import { api, money } from '../api';
import { createRequestGate } from '../state-utils.js';
import { downloadBook } from '../private-pdf.js';
import { toast } from '../toast';

const route = useRoute();
const order = ref(null);
const loading = ref(true);
const error = ref('');
const downloading = ref(null);
const requests = createRequestGate();
const isPaid = computed(() => order.value?.payment_status === 'paid');

const paidItems = computed(() => order.value?.payment_status === 'paid' ? order.value.items || [] : []);
const orderRef = computed(() => String(route.params.orderRef || route.params.id || ''));

function bookPath(item) {
  const book = item?.book || {};
  return `/books/${book.slug || book.id || item.book_id}`;
}

async function load() {
  const request = requests.begin();
  loading.value = true;
  error.value = '';
  order.value = null;
  try {
    const reference = encodeURIComponent(orderRef.value);
    const endpoint = /^\d+$/.test(orderRef.value) ? `/orders/${reference}` : `/orders/by-number/${reference}`;
    const { data } = await api.get(endpoint);
    if (requests.isCurrent(request)) order.value = data;
  } catch (err) {
    if (requests.isCurrent(request)) error.value = err.response?.data?.message || 'Order tidak ditemukan.';
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

async function download(book) {
  if (downloading.value !== null) return;
  downloading.value = book.id;
  try {
    await downloadBook(book);
  } catch {
    toast.error('PDF gagal diunduh. Pastikan akun memiliki akses buku.');
  } finally {
    downloading.value = null;
  }
}

watch(orderRef, load, { immediate: true });
onBeforeUnmount(() => requests.cancel());
</script>

<template>
  <section class="shell py-8">
    <div v-if="loading" role="status" class="panel p-8 text-center text-ink/55">Loading order...</div>
    <div v-else-if="error" role="alert" class="panel p-8 text-center">
      <h1 class="text-2xl font-bold">Order tidak bisa dibuka</h1>
      <p class="mt-2 text-ink/55">{{ error }}</p>
      <button class="btn-soft mt-5 mr-3" type="button" @click="load">Coba lagi</button>
      <RouterLink to="/" class="btn-primary mt-5">Kembali ke katalog</RouterLink>
    </div>

    <template v-else-if="order">
      <div class="relative overflow-hidden rounded-3xl bg-leaf text-white shadow-glow">
        <div class="absolute inset-0 opacity-20">
          <div class="h-full w-full bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.8),transparent_28%),radial-gradient(circle_at_80%_10%,rgba(56,189,248,0.8),transparent_24%),radial-gradient(circle_at_65%_90%,rgba(245,158,11,0.75),transparent_28%)]"></div>
        </div>
        <div class="relative grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_320px] lg:items-center">
          <div>
            <div class="mb-4 inline-flex h-16 w-16 animate-popIn items-center justify-center rounded-full bg-white text-leaf shadow-glow">
              <CheckCircle2 :size="36" />
            </div>
            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold text-white/75">
              <Sparkles :size="16" />
              {{ isPaid ? 'Pembayaran dikonfirmasi' : 'Status pembayaran: ' + order.payment_status }}
            </p>
            <h1 class="mt-4 max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">{{ isPaid ? 'Buku sudah tersedia di Library.' : 'Order tersimpan, akses buku belum aktif.' }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/65">
              Nomor order <strong class="text-white">{{ order.order_number }}</strong> sudah {{ order.payment_status }}. Link ini tetap dilindungi login, jadi detail order hanya bisa dibuka oleh pemilik akun.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <RouterLink to="/dashboard?tab=library" class="btn-primary bg-white text-ink hover:bg-white/90">
                <LibraryBig :size="17" />
                Buka Library
              </RouterLink>
              <RouterLink to="/" class="btn-soft border-white/20 bg-white/10 text-white hover:bg-white hover:text-ink">
                Belanja lagi
                <ArrowRight :size="16" />
              </RouterLink>
            </div>
          </div>
          <div class="rounded-2xl border border-white/15 bg-white/15 p-5 backdrop-blur">
            <ShieldCheck class="text-white/70" :size="26" />
            <p class="text-sm text-white/55">Total pembayaran</p>
            <strong class="mt-1 block text-3xl">{{ money(order.total) }}</strong>
            <div class="mt-4 rounded-xl bg-white/10 p-3 text-sm text-white/70">
              <p class="flex justify-between"><span>Order ref</span><strong>{{ order.order_number }}</strong></p>
              <p class="mt-2 flex justify-between"><span>Items</span><strong>{{ order.items?.length || 0 }}</strong></p>
            </div>
          </div>
        </div>
        <div class="relative grid gap-px bg-white/10 sm:grid-cols-3">
          <div class="bg-white/10 p-5">
            <p class="text-sm text-white/50">Payment</p>
            <strong class="mt-1 block capitalize">{{ order.payment_status }}</strong>
          </div>
          <div class="bg-white/10 p-5">
            <p class="text-sm text-white/50">Order</p>
            <strong class="mt-1 block capitalize">{{ order.order_status }}</strong>
          </div>
          <div class="bg-white/10 p-5">
            <p class="text-sm text-white/50">Items</p>
            <strong class="mt-1 block">{{ order.items?.length || 0 }} buku</strong>
          </div>
        </div>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <RouterLink to="/dashboard?tab=library" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
          <LibraryBig class="text-leaf" :size="24" />
          <h2 class="mt-3 font-semibold">Buka Library</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Lihat semua buku yang sudah kamu beli.</p>
        </RouterLink>
        <RouterLink to="/dashboard?tab=orders" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
          <ReceiptText class="text-leaf" :size="24" />
          <h2 class="mt-3 font-semibold">Riwayat Order</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Cek status dan detail pembayaran.</p>
        </RouterLink>
        <RouterLink to="/" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
          <ShoppingBag class="text-leaf" :size="24" />
          <h2 class="mt-3 font-semibold">Belanja Lagi</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Cari buku baru di katalog ZaiMedia.</p>
        </RouterLink>
      </div>

      <div class="panel mt-6 overflow-hidden animate-popIn">
        <div class="border-b border-leaf/10 bg-white/80 p-5">
          <h2 class="text-xl font-semibold">Buku di order ini</h2>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-for="item in order.items || []" :key="item.id" class="grid gap-4 p-5 sm:grid-cols-[64px_1fr_auto] sm:items-center">
            <img v-if="item.book?.cover_url" :src="item.book.cover_url" class="h-24 w-16 rounded-xl object-cover shadow-soft" :alt="item.book?.title" />
            <div v-else class="grid h-24 w-16 place-items-center rounded-xl bg-leaf/5 p-2 text-center text-xs text-ink/50">{{ item.book?.title }}</div>
            <div>
              <strong>{{ item.book?.title }}</strong>
              <p class="mt-1 text-sm text-ink/55">{{ item.book?.author }} - Qty {{ item.quantity }} x {{ money(item.price) }}</p>
              <p class="mt-1 text-xs text-ink/45">Subtotal {{ money(item.subtotal) }}</p>
            </div>
            <div class="flex flex-wrap gap-2 sm:justify-end">
              <RouterLink v-if="paidItems.length && item.book?.has_pdf" class="btn-primary px-3" :to="`/reader/${item.book.id}`">
                <BookOpen :size="16" />
                Read
              </RouterLink>
              <button v-if="paidItems.length && item.book?.has_pdf" class="btn-soft px-3" type="button" :disabled="downloading !== null" @click="download(item.book)">
                <Download :size="16" />
                PDF
              </button>
              <RouterLink class="btn-soft px-3" :to="bookPath(item)">Detail</RouterLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </section>
</template>
