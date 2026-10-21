<script setup>
import { ArrowLeft, Home, Search } from '@lucide/vue';
</script>

<template>
  <section class="shell grid min-h-[70vh] place-items-center py-10">
    <div class="panel max-w-2xl p-8 text-center sm:p-10">
      <div class="mx-auto grid h-14 w-14 place-items-center rounded-md bg-leaf/10 text-leaf">
        <Search :size="26" />
      </div>
      <p class="mt-6 text-sm font-semibold uppercase tracking-wide text-leaf">404</p>
      <h1 class="mt-2 text-3xl font-bold sm:text-4xl">Halaman tidak ditemukan</h1>
      <p class="mt-3 leading-7 text-ink/60">
        Link yang kamu buka tidak tersedia atau sudah dipindahkan. Kembali ke katalog untuk lanjut cari buku.
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <button class="btn-soft" type="button" @click="$router.back()">
          <ArrowLeft :size="17" />
          Kembali
        </button>
        <RouterLink to="/" class="btn-primary">
          <Home :size="17" />
          Buka katalog
        </RouterLink>
      </div>
    </div>
  </section>
</template>
