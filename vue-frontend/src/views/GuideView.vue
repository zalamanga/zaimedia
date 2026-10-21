<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { BadgeCheck, BookOpen, CheckCircle2, CreditCard, LibraryBig, PenLine, Send, ShieldCheck } from '@lucide/vue';

const route = useRoute();
const type = computed(() => route.params.type || 'buy');
const isWriterGuide = computed(() => type.value === 'writer');

const buySteps = [
  ['Pilih buku', 'Cari buku dari katalog, kategori, atau section popular.'],
  ['Masukkan cart', 'Klik tombol cart lalu cek quantity dan total.'],
  ['Checkout', 'Isi data customer dan buat order. Pembayaran belum terhubung ke gateway.'],
  ['Baca dari Library', 'Setelah order paid, buku otomatis tersedia di Library dan Reader PDF.'],
];

const writerSteps = [
  ['Register customer', 'Semua user baru mulai sebagai customer.'],
  ['Apply Become Writer', 'Isi nama pena, bio, alasan, dan portfolio opsional.'],
  ['Tunggu admin review', 'Admin akan approve atau reject request dari dashboard.'],
  ['Upload buku', 'Setelah approved, dashboard writer aktif untuk upload buku dan mengelola royalty.'],
];

const steps = computed(() => isWriterGuide.value ? writerSteps : buySteps);
const title = computed(() => isWriterGuide.value ? 'Tata cara menjadi penulis' : 'Tata cara membeli buku');
const subtitle = computed(() => isWriterGuide.value
  ? 'Customer bisa naik menjadi writer setelah request disetujui admin.'
  : 'Akses ebook aktif di Library setelah status order paid. Mode demo hanya mensimulasikan pembayaran.');
</script>

<template>
  <section class="shell py-10">
    <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
      <div class="rounded-3xl bg-leaf p-8 text-white shadow-glow">
        <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold">
          <BookOpen v-if="!isWriterGuide" :size="16" />
          <PenLine v-else :size="16" />
          Panduan Zaimedia
        </p>
        <h1 class="mt-5 text-4xl font-bold leading-tight sm:text-5xl">{{ title }}</h1>
        <p class="mt-4 max-w-2xl leading-7 text-white/75">{{ subtitle }}</p>
      </div>

      <div class="panel p-6">
        <ShieldCheck class="text-leaf" :size="28" />
        <h2 class="mt-4 text-xl font-semibold">Aturan penting</h2>
        <p class="mt-2 text-sm leading-7 text-ink/60">
          Writer boleh membeli buku penulis lain, tetapi tidak bisa membeli atau review buku sendiri. Semua buku writer harus melalui review admin.
        </p>
      </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-4">
      <article v-for="(step, index) in steps" :key="step[0]" class="panel p-5">
        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-leaf/10 font-bold text-leaf">{{ index + 1 }}</span>
        <h2 class="mt-4 font-semibold">{{ step[0] }}</h2>
        <p class="mt-2 text-sm leading-6 text-ink/60">{{ step[1] }}</p>
      </article>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl bg-white p-5 shadow-soft">
        <CreditCard class="text-leaf" :size="24" />
        <h3 class="mt-3 font-semibold">Payment demo</h3>
        <p class="mt-2 text-sm leading-6 text-ink/60">Checkout saat ini auto-paid sesuai kebutuhan demo.</p>
      </div>
      <div class="rounded-2xl bg-white p-5 shadow-soft">
        <LibraryBig class="text-leaf" :size="24" />
        <h3 class="mt-3 font-semibold">Library aman</h3>
        <p class="mt-2 text-sm leading-6 text-ink/60">Buku yang dibeli tetap bisa dibaca walau katalog diarsipkan.</p>
      </div>
      <div class="rounded-2xl bg-white p-5 shadow-soft">
        <BadgeCheck class="text-leaf" :size="24" />
        <h3 class="mt-3 font-semibold">Admin review</h3>
        <p class="mt-2 text-sm leading-6 text-ink/60">Writer request dan buku masuk proses approval admin.</p>
      </div>
    </div>

    <RouterLink :to="isWriterGuide ? '/dashboard?tab=writerRequest' : '/'" class="btn-primary mt-8 w-fit">
      <Send :size="17" />
      {{ isWriterGuide ? 'Ajukan writer request' : 'Mulai cari buku' }}
      <CheckCircle2 :size="17" />
    </RouterLink>
  </section>
</template>
