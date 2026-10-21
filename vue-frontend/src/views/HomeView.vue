<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, BookOpen, Eye, LibraryBig, PenLine, Search, ShoppingBag, Sparkles, Star, Users } from '@lucide/vue';
import { api, money } from '../api';
import { auth } from '../auth';
import { cart } from '../cart';
import { toast } from '../toast';
import { createRequestGate } from '../state-utils.js';

const route = useRoute();
const router = useRouter();
const books = ref([]);
const popular = ref([]);
const newest = ref([]);
const categories = ref([]);
const loading = ref(true);
const error = ref('');
const highlightsError = ref('');
const bookRequests = createRequestGate();
const highlightRequests = createRequestGate();
const filters = ref({ q: '', category_id: '', sort: 'newest', page: 1 });
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });

const totalBooks = computed(() => Math.max(pagination.value.total, newest.value.length));
const totalWriters = computed(() => new Set([...books.value, ...newest.value].map((book) => book.writer?.id).filter(Boolean)).size);
const featuredBook = computed(() => popular.value[0] || newest.value[0] || null);
const heroStack = computed(() => [...popular.value, ...newest.value].filter((book, index, rows) => rows.findIndex((item) => item.id === book.id) === index).slice(0, 4));
const categoryHighlights = computed(() => categories.value.slice(0, 6));

function bookPath(book) {
  return `/books/${book.slug || book.id}`;
}

async function loadBooks() {
  const request = bookRequests.begin();
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/books', { params: { ...filters.value, per_page: 12 } });
    if (!bookRequests.isCurrent(request)) return;
    books.value = Array.isArray(data) ? data : data.data || [];
    pagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      from: data.from || (books.value.length ? 1 : 0),
      to: data.to || books.value.length,
      total: data.total ?? books.value.length,
    };
  } catch (err) {
    if (bookRequests.isCurrent(request)) error.value = err.response?.data?.message || 'Katalog gagal dimuat.';
  } finally {
    if (bookRequests.isCurrent(request)) loading.value = false;
  }
}

async function loadHighlights() {
  const request = highlightRequests.begin();
  highlightsError.value = '';
  const results = await Promise.allSettled([
    api.get('/categories'),
    api.get('/books', { params: { sort: 'popular', per_page: 4 } }),
    api.get('/books', { params: { sort: 'newest', per_page: 8 } }),
  ]);
  if (!highlightRequests.isCurrent(request)) return;
  const targets = [categories, popular, newest];
  results.forEach((result, index) => {
    if (result.status === 'fulfilled') {
      const data = result.value.data;
      targets[index].value = Array.isArray(data) ? data : data.data || [];
    }
  });
  if (results.some((result) => result.status === 'rejected')) highlightsError.value = 'Sebagian data katalog gagal dimuat.';
}

function applyCategory(id) {
  filters.value.category_id = id || '';
  filters.value.page = 1;
  loadBooks();
}

function applyFilters() {
  filters.value.page = 1;
  loadBooks();
}

function goPage(page) {
  const next = Math.min(Math.max(1, page), pagination.value.last_page || 1);
  if (next === pagination.value.current_page) return;
  filters.value.page = next;
  loadBooks();
}

async function toggleWishlist(book) {
  if (!auth.isLoggedIn) {
    router.push({ path: '/login', query: { redirect: route.fullPath } });
    return;
  }

  try {
    const { data } = await api.post(`/wishlist/${book.id}`);
    toast.success(data.wishlisted ? 'Buku masuk wishlist.' : 'Buku dihapus dari wishlist.');
  } catch (err) {
    toast.error(err.response?.data?.message || 'Wishlist gagal diperbarui.');
  }
}

onMounted(() => {
  loadHighlights();
  loadBooks();
});
onBeforeUnmount(() => {
  bookRequests.cancel();
  highlightRequests.cancel();
});
</script>

<template>
  <div v-if="highlightsError" role="alert" class="shell py-3 text-sm text-red-700">
    {{ highlightsError }}
    <button type="button" class="ml-2 underline" @click="loadHighlights">Coba lagi</button>
  </div>
  <section class="border-b border-ink/10 bg-white">
    <div class="shell grid min-w-0 gap-8 py-8 lg:grid-cols-[1.05fr_0.95fr] lg:py-12">
      <div class="min-w-0 flex flex-col justify-center">
        <div class="mb-4 inline-flex w-fit items-center gap-2 rounded-md bg-leaf/10 px-3 py-2 text-sm font-semibold text-leaf">
          <Sparkles :size="16" />
          ZaiMedia digital bookstore
        </div>
        <h1 class="max-w-3xl break-words text-3xl font-bold leading-tight sm:text-5xl">
          Baca ebook lokal dan dukung penulis.
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-ink/65">
          Marketplace buku digital untuk pembaca dan penulis. Customer bisa beli dan baca PDF, sementara writer mengajukan karya lalu dipantau dari dashboard yang rapi.
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
          <a href="#catalog" class="btn-primary">
            <BookOpen :size="18" />
            Jelajahi katalog
          </a>
          <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/login'" class="btn-soft">
            <Users :size="18" />
            {{ auth.isLoggedIn ? 'Dashboard' : 'Masuk akun' }}
          </RouterLink>
        </div>
        <div class="mt-8 grid gap-3 sm:grid-cols-3">
          <div class="rounded-md border border-ink/10 bg-paper p-4">
            <p class="text-2xl font-bold">{{ totalBooks }}+</p>
            <p class="mt-1 text-sm text-ink/55">Buku tersedia</p>
          </div>
          <div class="rounded-md border border-ink/10 bg-paper p-4">
            <p class="text-2xl font-bold">{{ totalWriters }}+</p>
            <p class="mt-1 text-sm text-ink/55">Writer aktif</p>
          </div>
          <div class="rounded-md border border-ink/10 bg-paper p-4">
            <p class="text-2xl font-bold">Royalti</p>
            <p class="mt-1 text-sm text-ink/55">Dipantau dari dashboard writer</p>
          </div>
        </div>
      </div>

      <div class="relative min-h-[420px] min-w-0 overflow-hidden rounded-lg bg-ink p-5 text-white shadow-soft sm:min-h-[520px]">
        <div v-if="featuredBook" class="absolute inset-0 opacity-25">
          <img v-if="featuredBook.cover_url" :src="featuredBook.cover_url" class="h-full w-full object-cover" :alt="featuredBook.title" />
        </div>
        <div class="relative z-10 grid h-full content-between gap-6">
          <div class="flex items-center justify-between gap-4">
            <span class="rounded-md bg-white/15 px-3 py-2 text-sm font-semibold">Featured shelf</span>
            <span class="inline-flex items-center gap-1 text-sm text-white/70"><Eye :size="15" />{{ featuredBook?.views || 0 }} views</span>
          </div>
          <div class="grid min-w-0 gap-5 sm:grid-cols-[1fr_180px] sm:items-end">
            <div>
              <p class="text-sm font-semibold text-white/60">{{ featuredBook?.category?.name || 'Curated book' }}</p>
              <h2 class="mt-2 max-w-xl text-3xl font-bold leading-tight">{{ featuredBook?.title || 'Belum ada buku approved' }}</h2>
              <p class="mt-3 line-clamp-3 max-w-xl text-sm leading-6 text-white/70">{{ featuredBook?.short_description || featuredBook?.long_description || 'Buku approved akan tampil sebagai highlight di sini.' }}</p>
              <div class="mt-5 flex flex-wrap gap-2">
                <RouterLink v-if="featuredBook" :to="bookPath(featuredBook)" class="btn-primary bg-white text-ink hover:bg-white/90">
                  Detail buku
                  <ArrowRight :size="16" />
                </RouterLink>
                <button v-if="featuredBook" class="btn-soft border-white/20 bg-white/10 text-white hover:bg-white hover:text-ink" @click="cart.add(featuredBook)">
                  <ShoppingBag :size="16" />
                  Add cart
                </button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-1">
              <RouterLink
                v-for="book in heroStack.slice(0, 3)"
                :key="book.id"
                :to="bookPath(book)"
                class="grid grid-cols-[48px_1fr] gap-3 rounded-md bg-white/10 p-2 transition hover:bg-white/15"
              >
                <span class="h-16 overflow-hidden rounded bg-white/10">
                  <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
                </span>
                <span class="min-w-0">
                  <strong class="line-clamp-2 text-sm">{{ book.title }}</strong>
                  <span class="mt-1 block text-xs text-white/60">{{ money(book.price) }}</span>
                </span>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="shell py-6">
    <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
      <div class="overflow-hidden rounded-lg border border-ink/10 bg-white shadow-soft">
        <div class="grid gap-6 p-5 md:grid-cols-[1fr_220px] md:items-center">
          <div>
            <p class="text-sm font-semibold text-leaf">Reader flow</p>
            <h2 class="mt-1 text-2xl font-bold">Baca dari Library setelah order lunas.</h2>
            <p class="mt-2 text-sm leading-6 text-ink/60">Akses PDF aktif saat status order paid. Customer dapat membaca online atau mengunduh buku dari Library.</p>
          </div>
          <RouterLink to="/cart" class="btn-primary w-full md:w-auto">
            <ShoppingBag :size="18" />
            Buka cart
          </RouterLink>
        </div>
      </div>
      <RouterLink :to="auth.isLoggedIn ? '/dashboard?tab=writerRequest' : '/login?mode=register'" class="overflow-hidden rounded-lg border border-ink/10 bg-white p-5 shadow-soft transition hover:-translate-y-1 hover:border-leaf/30">
        <PenLine class="text-leaf" :size="24" />
        <h2 class="mt-3 text-xl font-bold">Punya naskah?</h2>
        <p class="mt-2 text-sm leading-6 text-ink/60">Daftar sebagai customer, ajukan Become Writer, lalu tunggu approval admin.</p>
      </RouterLink>
    </div>
  </section>

  <section class="shell py-4">
    <div class="grid gap-4 md:grid-cols-3">
      <RouterLink to="/guides/buy" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
        <BookOpen class="text-leaf" :size="24" />
        <h2 class="mt-3 font-semibold">Cara membeli</h2>
        <p class="mt-2 text-sm leading-6 text-ink/60">Lihat step checkout, library, reader, dan download PDF.</p>
      </RouterLink>
      <RouterLink to="/guides/writer" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
        <PenLine class="text-leaf" :size="24" />
        <h2 class="mt-3 font-semibold">Jadi penulis</h2>
        <p class="mt-2 text-sm leading-6 text-ink/60">Pelajari alur customer apply writer sampai upload buku.</p>
      </RouterLink>
      <RouterLink to="/articles" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/30">
        <Sparkles class="text-leaf" :size="24" />
        <h2 class="mt-3 font-semibold">Artikel & blog</h2>
        <p class="mt-2 text-sm leading-6 text-ink/60">Baca update dan konten yang dikelola dari CMS admin.</p>
      </RouterLink>
    </div>
  </section>

  <section v-if="categoryHighlights.length" class="shell py-4">
    <div class="mb-5 flex items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-leaf">Kategori</p>
        <h2 class="text-2xl font-bold">Pilih rak bacaan</h2>
      </div>
      <a href="#catalog" class="btn-soft">Lihat katalog</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <button
        v-for="category in categoryHighlights"
        :key="category.id"
        class="group rounded-lg border border-ink/10 bg-white p-5 text-left shadow-soft transition hover:-translate-y-1 hover:border-leaf/30"
        @click="applyCategory(category.id)"
      >
        <LibraryBig class="text-leaf" :size="22" />
        <strong class="mt-4 block text-lg">{{ category.name }}</strong>
        <span class="mt-2 line-clamp-2 block text-sm leading-6 text-ink/55">{{ category.description || 'Koleksi buku digital pilihan.' }}</span>
        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-leaf">
          Browse
          <ArrowRight class="transition group-hover:translate-x-1" :size="15" />
        </span>
      </button>
    </div>
  </section>

  <section class="shell py-4">
    <div class="mb-5 flex items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-leaf">Popular</p>
        <h2 class="text-2xl font-bold">Banyak dilihat</h2>
      </div>
      <button class="btn-soft" @click="filters.sort = 'popular'; applyFilters()">Lihat popular</button>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <article v-for="book in popular" :key="book.id" class="panel overflow-hidden">
        <RouterLink :to="bookPath(book)" class="block aspect-[4/5] bg-ink/5">
          <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
          <div v-else class="grid h-full place-items-center p-4 text-center text-sm text-ink/50">{{ book.title }}</div>
        </RouterLink>
        <div class="space-y-3 p-4">
          <h3 class="line-clamp-2 min-h-12 font-semibold">{{ book.title }}</h3>
          <div class="flex items-center justify-between gap-2">
            <span class="font-semibold">{{ money(book.price) }}</span>
            <div class="flex items-center gap-2">
              <button class="btn-soft px-3" title="Wishlist" @click="toggleWishlist(book)">
                <Star :size="16" />
              </button>
              <button class="btn-primary px-3" title="Add to cart" @click="cart.add(book)">
                <ShoppingBag :size="16" />
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </section>

  <section class="shell py-6">
    <div class="mb-5 flex items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-leaf">Terbaru</p>
        <h2 class="text-2xl font-bold">Baru masuk katalog</h2>
      </div>
      <button class="btn-soft" @click="filters.sort = 'newest'; applyFilters()">Lihat terbaru</button>
    </div>
    <div v-if="!newest.length" class="panel p-8 text-center text-ink/55">Belum ada buku terbaru.</div>
    <div v-else class="grid gap-4 md:grid-cols-2">
      <article v-for="book in newest.slice(0, 4)" :key="book.id" class="panel grid gap-4 overflow-hidden p-4 sm:grid-cols-[112px_1fr]">
        <RouterLink :to="bookPath(book)" class="block aspect-[4/5] overflow-hidden rounded-md bg-ink/5 sm:h-36 sm:w-28">
          <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
          <div v-else class="grid h-full place-items-center p-2 text-center text-xs text-ink/50">{{ book.title }}</div>
        </RouterLink>
        <div class="flex min-w-0 flex-col justify-between gap-4">
          <div>
            <p class="text-xs font-semibold text-leaf">{{ book.category?.name || 'Book' }}</p>
            <h3 class="mt-1 line-clamp-2 text-lg font-semibold">{{ book.title }}</h3>
            <p class="mt-1 text-sm text-ink/55">{{ book.author }}</p>
            <p class="mt-3 line-clamp-2 text-sm leading-6 text-ink/60">{{ book.short_description || book.long_description || 'Belum ada deskripsi.' }}</p>
          </div>
          <div class="flex items-center justify-between gap-2">
            <strong>{{ money(book.price) }}</strong>
            <div class="flex gap-2">
              <RouterLink :to="bookPath(book)" class="btn-soft px-3" title="Detail">
                <ArrowRight :size="16" />
              </RouterLink>
              <button class="btn-soft px-3" title="Wishlist" @click="toggleWishlist(book)">
                <Star :size="16" />
              </button>
              <button class="btn-primary px-3" title="Add to cart" @click="cart.add(book)">
                <ShoppingBag :size="16" />
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </section>

  <section id="catalog" class="shell py-8">
    <div class="mb-6 grid gap-3 md:grid-cols-[1fr_220px_180px_auto]">
      <label class="relative">
        <Search class="absolute left-3 top-2.5 text-ink/40" :size="18" />
        <input v-model="filters.q" aria-label="Cari judul, author, atau deskripsi" class="field pl-10" placeholder="Cari judul, author, atau deskripsi" @keyup.enter="applyFilters" />
      </label>
      <select v-model="filters.category_id" aria-label="Kategori buku" class="field" @change="applyFilters">
        <option value="">Semua kategori</option>
        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }} ({{ category.books_count || 0 }})</option>
      </select>
      <select v-model="filters.sort" aria-label="Urutan buku" class="field" @change="applyFilters">
        <option value="newest">Terbaru</option>
        <option value="popular">Popular</option>
        <option value="price_low">Harga rendah</option>
        <option value="price_high">Harga tinggi</option>
      </select>
      <button class="btn-primary" @click="applyFilters">
        <Search :size="17" />
        Search
      </button>
    </div>

    <div class="no-scrollbar mb-6 flex gap-2 overflow-x-auto pb-1">
      <button class="btn-soft whitespace-nowrap" :class="{ 'border-leaf text-leaf': !filters.category_id }" @click="applyCategory('')">Semua</button>
      <button
        v-for="category in categories"
        :key="category.id"
        class="btn-soft whitespace-nowrap"
        :class="{ 'border-leaf text-leaf': filters.category_id === category.id }"
        @click="applyCategory(category.id)"
      >
        {{ category.name }}
        <span class="rounded bg-ink/5 px-2 text-xs text-ink/50">{{ category.books_count || 0 }}</span>
      </button>
    </div>

    <div v-if="loading" role="status" class="panel p-8 text-center text-ink/60">Loading books...</div>
    <div v-else-if="error" role="alert" class="panel p-8 text-center text-red-700">
      <p>{{ error }}</p>
      <button class="btn-soft mt-3" type="button" @click="loadBooks">Coba lagi</button>
    </div>
    <div v-else-if="!books.length" class="panel p-8 text-center text-ink/60">Buku belum ditemukan.</div>
    <template v-else>
      <div class="mb-4 flex flex-col gap-2 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
        <p>Menampilkan {{ pagination.from }}-{{ pagination.to }} dari {{ pagination.total }} buku</p>
        <p>Halaman {{ pagination.current_page }} / {{ pagination.last_page }}</p>
      </div>

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="book in books" :key="book.id" class="panel overflow-hidden">
          <RouterLink :to="bookPath(book)" class="block aspect-[4/5] bg-ink/5">
            <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
            <div v-else class="grid h-full place-items-center px-4 text-center text-sm text-ink/50">{{ book.title }}</div>
          </RouterLink>
          <div class="space-y-3 p-4">
            <div>
              <p class="text-xs text-leaf">{{ book.category?.name || 'Uncategorized' }}</p>
              <h2 class="line-clamp-2 min-h-12 text-lg font-semibold">{{ book.title }}</h2>
              <p class="text-sm text-ink/55">{{ book.author }}</p>
            </div>
            <div class="flex items-center justify-between gap-2">
              <strong>{{ money(book.price) }}</strong>
              <div class="flex items-center gap-2">
                <RouterLink :to="bookPath(book)" class="btn-soft px-3" title="Detail">
                  <ArrowRight :size="16" />
                </RouterLink>
                <button class="btn-soft px-3" title="Wishlist" @click="toggleWishlist(book)">
                  <Star :size="16" />
                </button>
                <button class="btn-primary px-3" title="Add to cart" @click="cart.add(book)">
                  <ShoppingBag :size="16" />
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <div v-if="pagination.last_page > 1" class="mt-8 flex flex-wrap items-center justify-center gap-2">
        <button class="btn-soft" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">Prev</button>
        <button
          v-for="page in pagination.last_page"
          :key="page"
          class="btn-soft h-10 w-10 p-0"
          :class="{ 'border-leaf bg-leaf text-white hover:bg-leaf hover:text-white': page === pagination.current_page }"
          @click="goPage(page)"
        >
          {{ page }}
        </button>
        <button class="btn-soft" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">Next</button>
      </div>
    </template>
  </section>
</template>
