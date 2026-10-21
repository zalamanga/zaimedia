<script setup>
import { BookOpen, HeartHandshake, ShieldCheck, Sparkles, Users } from '@lucide/vue';
</script>

<template>
  <section class="shell py-10">
    <div class="relative overflow-hidden rounded-3xl bg-leaf p-8 text-white shadow-glow sm:p-10">
      <div class="relative z-10 max-w-3xl">
        <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-2 text-sm font-semibold">
          <Sparkles :size="16" />
          Tentang Zaimedia
        </p>
        <h1 class="mt-5 text-4xl font-bold leading-tight sm:text-5xl">Marketplace buku digital untuk pembaca dan penulis lokal.</h1>
        <p class="mt-4 max-w-2xl leading-7 text-white/75">
          Zaimedia membantu pembaca menemukan ebook, menyimpan buku di library, dan membantu penulis menerbitkan karya lewat alur review admin yang rapi.
        </p>
      </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-3">
      <article class="panel p-6">
        <BookOpen class="text-leaf" :size="26" />
        <h2 class="mt-4 text-xl font-semibold">Baca digital</h2>
        <p class="mt-2 leading-7 text-ink/60">Buku tersedia di Library dan Reader PDF setelah status order paid.</p>
      </article>
      <article class="panel p-6">
        <Users class="text-leaf" :size="26" />
        <h2 class="mt-4 text-xl font-semibold">Penulis terkurasi</h2>
        <p class="mt-2 leading-7 text-ink/60">Customer bisa apply menjadi writer, lalu admin approve sebelum bisa upload buku.</p>
      </article>
      <article class="panel p-6">
        <ShieldCheck class="text-leaf" :size="26" />
        <h2 class="mt-4 text-xl font-semibold">Dashboard lengkap</h2>
        <p class="mt-2 leading-7 text-ink/60">Admin memantau buku, order, withdrawal, user, audit log, dan artikel dari satu tempat.</p>
      </article>
    </div>

    <section class="mt-8 grid gap-6 lg:grid-cols-[1fr_420px]">
      <div class="panel p-6">
        <h2 class="text-2xl font-bold">Misi kami</h2>
        <p class="mt-3 leading-8 text-ink/65">
          Zaimedia dibuat untuk menjaga alur marketplace buku digital yang sederhana: pembaca bisa membeli dan membaca dengan cepat, sedangkan writer punya ruang untuk mengelola karya, royalty, dan withdrawal secara transparan.
        </p>
      </div>
      <div class="panel p-6">
        <HeartHandshake class="text-leaf" :size="28" />
        <h2 class="mt-4 text-2xl font-bold">Untuk siapa?</h2>
        <p class="mt-3 leading-8 text-ink/65">
          Pembaca ebook, penulis indie, admin katalog, dan tim kecil yang ingin punya sistem toko buku digital rapi.
        </p>
      </div>
    </section>
  </section>
</template>
