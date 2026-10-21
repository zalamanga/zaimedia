<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BookOpen, CheckCircle2, Eye, FileText, ShoppingBag, Star, UserRound } from '@lucide/vue';
import { api, money } from '../api';
import { auth } from '../auth';
import { cart } from '../cart';
import { toast } from '../toast';
import { createRequestGate } from '../state-utils.js';

const route = useRoute();
const router = useRouter();
const book = ref(null);
const reviews = ref([]);
const reviewForm = ref({ rating: 5, title: '', content: '' });
const error = ref('');
const success = ref('');
const loading = ref(true);
const loadError = ref('');
const requests = createRequestGate();

const distributionTotal = computed(() => Math.max(1, Number(book.value?.rating_count || 0)));
const detailRows = computed(() => [
  ['Author', book.value?.author],
  ['Publisher', book.value?.publisher],
  ['Year', book.value?.year],
  ['Pages', book.value?.pages],
  ['ISBN', book.value?.isbn],
  ['Views', book.value?.views],
].filter((row) => row[1] !== null && row[1] !== undefined && row[1] !== ''));

function bookPath(item) {
  return `/books/${item.slug || item.id}`;
}

async function load() {
  const request = requests.begin();
  const id = encodeURIComponent(route.params.id);
  loading.value = true;
  loadError.value = '';
  book.value = null;
  reviews.value = [];
  try {
    const [{ data: detail }, { data: reviewRows }] = await Promise.all([
      api.get(`/books/${id}`),
      api.get(`/reviews/book/${id}`),
    ]);
    if (!requests.isCurrent(request)) return;
    book.value = detail;
    reviews.value = reviewRows;
  } catch (err) {
    if (requests.isCurrent(request)) loadError.value = err.response?.data?.message || 'Buku tidak ditemukan atau gagal dimuat.';
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

function buyNow() {
  cart.add(book.value);
  router.push('/cart');
}

async function submitReview() {
  error.value = '';
  success.value = '';
  if (reviewForm.value.content.trim().length < 10) {
    error.value = 'Review minimal 10 karakter.';
    toast.error(error.value);
    return;
  }

  try {
    await api.post('/reviews', { ...reviewForm.value, book_id: book.value.id });
    reviewForm.value = { rating: 5, title: '', content: '' };
    success.value = 'Review berhasil dikirim.';
    toast.success(success.value);
    await load();
  } catch (err) {
    error.value = err.response?.data?.message || 'Review gagal dikirim.';
    toast.error(error.value);
  }
}

async function toggleWishlist() {
  if (!auth.isLoggedIn) {
    router.push({ path: '/login', query: { redirect: route.fullPath } });
    return;
  }

  try {
    const { data } = await api.post(`/wishlist/${book.value.id}`);
    toast.success(data.wishlisted ? 'Buku masuk wishlist.' : 'Buku dihapus dari wishlist.');
  } catch (err) {
    toast.error(err.response?.data?.message || 'Wishlist gagal diperbarui.');
  }
}

watch(() => route.params.id, () => {
  error.value = '';
  success.value = '';
  reviewForm.value = { rating: 5, title: '', content: '' };
  load();
}, { immediate: true });
onBeforeUnmount(() => requests.cancel());
</script>

<template>
  <section v-if="loading" role="status" class="shell py-8">Loading book...</section>
  <section v-else-if="loadError" role="alert" class="shell py-8">
    <h1 class="text-2xl font-bold">Buku tidak bisa dibuka</h1>
    <p class="mt-2 text-ink/65">{{ loadError }}</p>
    <button class="btn-soft mt-4" type="button" @click="load">Coba lagi</button>
    <RouterLink to="/" class="btn-primary ml-3 mt-4">Kembali ke katalog</RouterLink>
  </section>
  <section v-else-if="book" class="shell py-8">
    <div class="grid gap-7 lg:grid-cols-[360px_1fr_320px]">
      <aside class="space-y-4">
        <div class="panel overflow-hidden">
          <img v-if="book.cover_url" :src="book.cover_url" class="aspect-[4/5] w-full object-cover" :alt="book.title" />
          <div v-else class="grid aspect-[4/5] place-items-center bg-ink/5 p-6 text-center">{{ book.title }}</div>
        </div>
        <div class="panel p-5">
          <p class="text-sm font-semibold text-leaf">Writer</p>
          <div class="mt-3 flex items-start gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-ink text-white">
              <UserRound :size="20" />
            </span>
            <div>
              <h2 class="font-semibold">{{ book.writer?.full_name || book.writer?.username || 'Writer' }}</h2>
              <p class="mt-1 text-sm leading-6 text-ink/55">{{ book.writer?.bio || 'Writer ZaiMedia' }}</p>
            </div>
          </div>
        </div>
      </aside>

      <main>
        <p class="text-sm font-semibold text-leaf">{{ book.category?.name || 'Book' }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">{{ book.title }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink/55">
          <span class="inline-flex items-center gap-1 text-honey">
            <Star :size="16" fill="currentColor" />
            {{ book.rating_average || 0 }} ({{ book.rating_count || 0 }} review)
          </span>
          <span class="inline-flex items-center gap-1"><Eye :size="16" /> {{ book.views || 0 }} views</span>
          <span class="inline-flex items-center gap-1"><FileText :size="16" /> Digital PDF</span>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <div v-for="row in detailRows" :key="row[0]" class="rounded-md border border-ink/10 bg-white p-4">
            <p class="text-xs text-ink/45">{{ row[0] }}</p>
            <p class="mt-1 font-semibold">{{ row[1] }}</p>
          </div>
        </div>

        <section class="mt-8">
          <h2 class="text-xl font-semibold">Description</h2>
          <p class="mt-3 whitespace-pre-line leading-8 text-ink/70">{{ book.long_description || book.short_description || 'Belum ada deskripsi.' }}</p>
        </section>

        <section class="mt-8">
          <h2 class="text-xl font-semibold">Rating summary</h2>
          <div class="panel mt-3 space-y-3 p-5">
            <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="grid grid-cols-[52px_1fr_32px] items-center gap-3 text-sm">
              <span class="inline-flex items-center gap-1 text-honey">{{ star }} <Star :size="14" fill="currentColor" /></span>
              <div class="h-2 overflow-hidden rounded-full bg-ink/10">
                <div class="h-full rounded-full bg-honey" :style="{ width: `${((book.rating_distribution?.[star] || 0) / distributionTotal) * 100}%` }"></div>
              </div>
              <span class="text-right text-ink/55">{{ book.rating_distribution?.[star] || 0 }}</span>
            </div>
          </div>
        </section>

        <section class="mt-8">
          <h2 class="mb-4 text-xl font-semibold">Reviews</h2>
          <form v-if="auth.isLoggedIn && ['customer', 'writer'].includes(auth.user?.role)" class="panel mb-5 space-y-3 p-5" @submit.prevent="submitReview">
            <div class="grid gap-3 sm:grid-cols-[120px_1fr]">
              <select v-model.number="reviewForm.rating" aria-label="Rating buku" class="field">
                <option v-for="n in 5" :key="n" :value="n">{{ n }} star</option>
              </select>
              <input v-model="reviewForm.title" aria-label="Judul review" class="field" placeholder="Judul review" />
            </div>
            <textarea v-model="reviewForm.content" aria-label="Isi review" class="field min-h-28" placeholder="Tulis pengalaman kamu membaca buku ini" minlength="10" maxlength="2000" required />
            <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <p v-if="success" role="status" class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ success }}</p>
            <button class="btn-primary">Kirim review</button>
          </form>
          <RouterLink v-else-if="!auth.isLoggedIn" to="/login" class="btn-soft mb-5">Login untuk menulis review</RouterLink>
          <p v-else class="panel mb-5 p-4 text-sm text-ink/55">Review hanya tersedia untuk customer/writer yang sudah membeli buku.</p>

          <div v-if="!reviews.length" class="panel p-5 text-ink/55">Belum ada review.</div>
          <div v-for="review in reviews" :key="review.id" class="mb-3 rounded-md border border-ink/10 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span class="flex items-center gap-1 text-honey">
                <Star v-for="n in review.rating" :key="n" :size="15" fill="currentColor" />
              </span>
              <span v-if="review.is_verified" class="inline-flex items-center gap-1 rounded bg-green-50 px-2 py-1 text-xs font-semibold text-green-700">
                <CheckCircle2 :size="13" /> Verified purchase
              </span>
            </div>
            <strong>{{ review.title || review.customer?.full_name }}</strong>
            <p class="mt-1 text-sm text-ink/65">{{ review.content }}</p>
          </div>
        </section>
      </main>

      <aside class="lg:sticky lg:top-24 lg:self-start">
        <div class="panel p-5">
          <p class="text-sm text-ink/55">Harga ebook</p>
          <p class="mt-1 text-3xl font-bold">{{ money(book.price) }}</p>
          <div class="mt-5 grid gap-3">
            <button class="btn-primary w-full" @click="buyNow">
              <ShoppingBag :size="18" />
              Buy now
            </button>
            <button class="btn-soft w-full" @click="cart.add(book)">
              <BookOpen :size="18" />
              Add to cart
            </button>
            <button class="btn-soft w-full" @click="toggleWishlist">
              <Star :size="18" />
              Wishlist
            </button>
          </div>
          <div class="mt-5 space-y-2 rounded-md bg-paper p-4 text-sm text-ink/60">
            <p>Akses digital aktif setelah order paid.</p>
            <p>PDF bisa dibaca dari Library customer.</p>
            <p>Review aktif setelah order paid.</p>
          </div>
        </div>
      </aside>
    </div>

    <section v-if="book.related_books?.length" class="mt-10">
      <div class="mb-5 flex items-center justify-between">
        <div>
          <p class="text-sm font-semibold text-leaf">Related</p>
          <h2 class="text-2xl font-bold">Buku serupa</h2>
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="item in book.related_books" :key="item.id" class="panel overflow-hidden">
          <RouterLink :to="bookPath(item)" class="block aspect-[4/5] bg-ink/5">
            <img v-if="item.cover_url" :src="item.cover_url" class="h-full w-full object-cover" :alt="item.title" />
            <div v-else class="grid h-full place-items-center p-4 text-center text-sm text-ink/50">{{ item.title }}</div>
          </RouterLink>
          <div class="p-4">
            <p class="text-xs text-leaf">{{ item.category?.name || 'Book' }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-12 font-semibold">{{ item.title }}</h3>
            <p class="mt-2 font-semibold">{{ money(item.price) }}</p>
          </div>
        </article>
      </div>
    </section>
  </section>
</template>
