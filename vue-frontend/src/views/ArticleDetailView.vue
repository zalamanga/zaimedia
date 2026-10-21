<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeft, CalendarDays, Newspaper } from '@lucide/vue';
import { api } from '../api';
import { createRequestGate } from '../state-utils.js';

const route = useRoute();
const article = ref(null);
const loading = ref(true);
const error = ref('');
const requests = createRequestGate();

function dateLabel(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date);
}

async function load() {
  const request = requests.begin();
  loading.value = true;
  error.value = '';
  article.value = null;
  try {
    const { data } = await api.get(`/articles/${encodeURIComponent(route.params.slug)}`);
    if (requests.isCurrent(request)) article.value = data;
  } catch (err) {
    if (requests.isCurrent(request)) error.value = err.response?.data?.message || 'Artikel tidak ditemukan.';
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

watch(() => route.params.slug, load, { immediate: true });
onBeforeUnmount(() => requests.cancel());
</script>

<template>
  <section class="shell py-10">
    <div v-if="loading" role="status" class="panel p-8 text-center text-ink/55">Loading artikel...</div>
    <div v-else-if="error" role="alert" class="panel p-8 text-center">
      <h1 class="text-2xl font-bold">Artikel tidak bisa dibuka</h1>
      <p class="mt-2 text-ink/55">{{ error }}</p>
      <button class="btn-soft mt-5 mr-3" type="button" @click="load">Coba lagi</button>
      <RouterLink to="/articles" class="btn-primary mt-5">Kembali ke artikel</RouterLink>
    </div>

    <article v-else-if="article" class="mx-auto max-w-4xl">
      <RouterLink to="/articles" class="btn-soft mb-6 w-fit">
        <ArrowLeft :size="16" />
        Semua artikel
      </RouterLink>

      <div class="overflow-hidden rounded-3xl bg-white shadow-soft">
        <div class="aspect-[16/8] bg-leaf/5">
          <img v-if="article.cover_url" :src="article.cover_url" class="h-full w-full object-cover" :alt="article.title" />
        </div>
        <div class="p-6 sm:p-8">
          <p class="inline-flex items-center gap-2 rounded-full bg-leaf/10 px-3 py-2 text-sm font-semibold text-leaf">
            <Newspaper :size="16" />
            Artikel
          </p>
          <h1 class="mt-5 text-4xl font-bold leading-tight">{{ article.title }}</h1>
          <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-ink/55">
            <span>{{ article.author?.full_name || 'Zaimedia' }}</span>
            <span class="inline-flex items-center gap-1"><CalendarDays :size="15" />{{ dateLabel(article.published_at || article.created_at) }}</span>
          </div>
          <p v-if="article.excerpt" class="mt-5 rounded-2xl bg-paper p-5 leading-7 text-ink/70">{{ article.excerpt }}</p>
          <div class="mt-8 whitespace-pre-line text-base leading-8 text-ink/75">{{ article.content }}</div>
        </div>
      </div>
    </article>
  </section>
</template>
