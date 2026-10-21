<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowRight, Newspaper, Search } from '@lucide/vue';
import { api } from '../api';
import { createRequestGate } from '../state-utils.js';

const articles = ref([]);
const loading = ref(true);
const error = ref('');
const requests = createRequestGate();
const q = ref('');
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

async function load(page = 1) {
  const request = requests.begin();
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/articles', { params: { q: q.value || undefined, page, per_page: 9 } });
    if (!requests.isCurrent(request)) return;
    articles.value = Array.isArray(data) ? data : data.data || [];
    pagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      total: data.total ?? articles.value.length,
    };
  } catch (err) {
    if (requests.isCurrent(request)) error.value = err.response?.data?.message || 'Artikel gagal dimuat.';
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

onMounted(() => load());
onBeforeUnmount(() => requests.cancel());
</script>

<template>
  <section class="shell py-10">
    <div class="rounded-3xl bg-leaf p-8 text-white shadow-glow">
      <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold">
        <Newspaper :size="16" />
        Artikel & Blog
      </p>
      <h1 class="mt-5 text-4xl font-bold leading-tight sm:text-5xl">Update, panduan, dan cerita dari Zaimedia.</h1>
      <p class="mt-4 max-w-2xl leading-7 text-white/75">Konten ini dikelola dari CMS admin.</p>
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
      <label class="relative flex-1">
        <Search class="absolute left-3 top-2.5 text-ink/35" :size="18" />
        <input v-model="q" aria-label="Cari artikel" class="field pl-10" placeholder="Cari artikel" @keyup.enter="load(1)" />
      </label>
      <button class="btn-primary" @click="load(1)">Search</button>
    </div>

    <div v-if="loading" role="status" class="panel mt-6 p-8 text-center text-ink/55">Loading articles...</div>
    <div v-else-if="error" role="alert" class="panel mt-6 p-8 text-center text-red-700">
      <p>{{ error }}</p>
      <button class="btn-soft mt-3" type="button" @click="load(pagination.current_page)">Coba lagi</button>
    </div>
    <div v-else-if="!articles.length" class="panel mt-6 p-8 text-center text-ink/55">Belum ada artikel published.</div>
    <div v-else class="mt-6 grid gap-5 md:grid-cols-3">
      <article v-for="article in articles" :key="article.id" class="panel overflow-hidden transition hover:-translate-y-1 hover:border-leaf/30">
        <RouterLink :to="`/articles/${article.slug}`" class="block aspect-[16/10] bg-leaf/5">
          <img v-if="article.cover_url" :src="article.cover_url" class="h-full w-full object-cover" :alt="article.title" />
        </RouterLink>
        <div class="p-5">
          <p class="text-sm font-semibold text-leaf">{{ article.author?.full_name || 'Zaimedia' }}</p>
          <h2 class="mt-2 line-clamp-2 text-xl font-bold">{{ article.title }}</h2>
          <p class="mt-3 line-clamp-3 text-sm leading-6 text-ink/60">{{ article.excerpt || article.content }}</p>
          <RouterLink :to="`/articles/${article.slug}`" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-leaf">
            Baca artikel
            <ArrowRight :size="15" />
          </RouterLink>
        </div>
      </article>
    </div>

    <div v-if="pagination.last_page > 1" class="mt-8 flex justify-center gap-2">
      <button class="btn-soft" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Prev</button>
      <button class="btn-soft" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Next</button>
    </div>
  </section>
</template>
