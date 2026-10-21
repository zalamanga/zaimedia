<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Download, FileText, Star } from '@lucide/vue';
import { api } from '../api';
import { createRequestGate } from '../state-utils.js';
import { downloadBook, fetchPrivatePdf } from '../private-pdf.js';
import { toast } from '../toast';

const route = useRoute();
const router = useRouter();
const book = ref(null);
const loading = ref(true);
const error = ref('');
const pdfUrl = ref('');
const downloading = ref(false);
const requests = createRequestGate();
let controller;

function releasePdf() {
  controller?.abort();
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
  pdfUrl.value = '';
}

function bookPath(item) {
  return `/books/${item?.slug || item?.id}`;
}

async function load() {
  const request = requests.begin();
  releasePdf();
  controller = new AbortController();
  const signal = controller.signal;
  loading.value = true;
  error.value = '';
  book.value = null;
  try {
    const { data } = await api.get(`/library/${encodeURIComponent(route.params.id)}`, { signal });
    if (!requests.isCurrent(request)) return;
    book.value = data;
    if (!data.has_pdf) throw new Error('PDF belum tersedia untuk buku ini.');
    const blob = await fetchPrivatePdf(data.id, signal);
    if (requests.isCurrent(request)) pdfUrl.value = URL.createObjectURL(blob);
  } catch (err) {
    if (requests.isCurrent(request)) error.value = err.response?.data?.message || (err.message === 'PDF belum tersedia untuk buku ini.' ? err.message : 'Buku tidak bisa dibuka.');
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

async function download() {
  if (downloading.value) return;
  downloading.value = true;
  try {
    await downloadBook(book.value);
  } catch {
    toast.error('PDF gagal diunduh.');
  } finally {
    downloading.value = false;
  }
}

watch(() => route.params.id, load, { immediate: true });
onBeforeUnmount(() => {
  requests.cancel();
  releasePdf();
});
</script>

<template>
  <section class="min-h-[calc(100vh-4rem)] bg-ink text-white">
    <div class="border-b border-white/10">
      <div class="shell flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
          <button class="mb-2 inline-flex items-center gap-2 text-sm font-semibold text-white/65 hover:text-white" @click="router.push('/dashboard')">
            <ArrowLeft :size="16" />
            Library
          </button>
          <h1 class="truncate text-xl font-semibold">{{ book?.title || 'Reader' }}</h1>
          <p class="truncate text-sm text-white/55">{{ book?.author || 'Loading book' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="pdfUrl" class="btn-soft border-white/10 bg-white/10 text-white hover:bg-white hover:text-ink" type="button" :disabled="downloading" @click="download">
            <Download :size="16" />
            Download
          </button>
          <RouterLink v-if="book" class="btn-soft border-white/10 bg-white/10 text-white hover:bg-white hover:text-ink" :to="bookPath(book)">
            <Star :size="16" />
            Review
          </RouterLink>
        </div>
      </div>
    </div>

    <div v-if="loading" role="status" class="shell grid min-h-[70vh] place-items-center text-center">
      <div>
        <FileText class="mx-auto mb-4 text-white/55" :size="42" />
        <p class="text-white/65">Loading PDF...</p>
      </div>
    </div>

    <div v-else-if="error" role="alert" class="shell grid min-h-[70vh] place-items-center text-center">
      <div class="max-w-md rounded-lg border border-white/10 bg-white/10 p-6">
        <h2 class="text-xl font-semibold">Tidak bisa membuka buku</h2>
        <p class="mt-2 text-sm text-white/65">{{ error }}</p>
        <button class="btn-soft mt-5 mr-3" type="button" @click="load">Coba lagi</button>
        <RouterLink to="/dashboard" class="btn-primary mt-5 bg-white text-ink hover:bg-white/90">Kembali ke library</RouterLink>
      </div>
    </div>

    <iframe
      v-else-if="pdfUrl"
      :src="`${pdfUrl}#toolbar=1&navpanes=1&scrollbar=1`"
      class="h-[calc(100vh-9.5rem)] w-full border-0 bg-white sm:h-[calc(100vh-8rem)]"
      :title="book.title"
    />
  </section>
</template>
