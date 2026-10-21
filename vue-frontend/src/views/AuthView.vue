<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BookOpen, PenLine, ShieldCheck, ShoppingBag } from '@lucide/vue';
import { auth } from '../auth';
import { toast } from '../toast';
import { redirectTarget } from '../state-utils.js';

const router = useRouter();
const route = useRoute();
const mode = ref(route.query.mode === 'register' ? 'register' : 'login');
const error = ref('');
const submitting = ref(false);
const form = ref({
  username: '',
  full_name: '',
  email: '',
  password: '',
  confirm_password: '',
  phone: '',
  role: 'customer',
});

function switchMode(nextMode) {
  mode.value = nextMode;
  error.value = '';
}

watch(() => route.query.mode, (nextMode) => {
  mode.value = nextMode === 'register' ? 'register' : 'login';
  error.value = '';
});

async function submit() {
  if (submitting.value) return;
  error.value = '';
  submitting.value = true;
  try {
    if (mode.value === 'login') {
      await auth.login({ email: form.value.email, password: form.value.password });
    } else {
      if (form.value.password !== form.value.confirm_password) {
        error.value = 'Password dan konfirmasi password tidak cocok.';
        return;
      }
      if (form.value.password.length < 8) {
        error.value = 'Password minimal 8 karakter.';
        return;
      }
      await auth.register(form.value);
    }
    toast.success(mode.value === 'login' ? 'Login berhasil.' : 'Akun berhasil dibuat.');
    router.push(redirectTarget(route.query.redirect));
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal masuk, cek data lagi.';
    toast.error(error.value);
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <section class="shell grid min-h-[calc(100vh-4rem)] items-center gap-8 py-8 lg:grid-cols-[1fr_460px]">
    <div class="space-y-8">
      <div>
        <div class="mb-4 inline-flex w-fit items-center gap-2 rounded-md bg-leaf/10 px-3 py-2 text-sm font-semibold text-leaf">
          <BookOpen :size="16" />
          ZaiMedia workspace
        </div>
        <h1 class="max-w-3xl text-4xl font-bold leading-tight sm:text-5xl">Masuk, upload, beli, dan baca dalam satu alur.</h1>
        <p class="mt-4 max-w-xl leading-7 text-ink/65">
          Satu sistem untuk admin, writer, dan customer. Alur lama tetap dijaga, tapi tampilannya sekarang lebih rapi dan responsive.
        </p>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div class="panel p-4">
          <ShieldCheck class="text-leaf" :size="22" />
          <h2 class="mt-3 font-semibold">Admin</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Review buku, order, user, category, dan withdrawal.</p>
        </div>
        <div class="panel p-4">
          <PenLine class="text-leaf" :size="22" />
          <h2 class="mt-3 font-semibold">Writer</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Submit buku, cek royalty, dan tarik saldo.</p>
        </div>
        <div class="panel p-4">
          <ShoppingBag class="text-leaf" :size="22" />
          <h2 class="mt-3 font-semibold">Customer</h2>
          <p class="mt-1 text-sm leading-6 text-ink/55">Checkout, library, reader PDF, dan review.</p>
        </div>
      </div>
    </div>

    <form class="panel space-y-4 p-5 sm:p-6" :aria-busy="submitting" @submit.prevent="submit">
      <div class="grid grid-cols-2 rounded-md bg-paper p-1">
        <button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="mode === 'login' ? 'bg-white shadow' : 'text-ink/60'" @click="switchMode('login')">Login</button>
        <button type="button" class="rounded px-3 py-2 text-sm font-semibold" :class="mode === 'register' ? 'bg-white shadow' : 'text-ink/60'" @click="switchMode('register')">Register</button>
      </div>

      <div>
        <h2 class="text-2xl font-bold">{{ mode === 'login' ? 'Login akun' : 'Daftar akun baru' }}</h2>
        <p class="mt-1 text-sm text-ink/55">{{ mode === 'login' ? 'Pakai email atau username.' : 'Akun baru otomatis menjadi customer. Kamu bisa apply menjadi writer dari dashboard.' }}</p>
      </div>

      <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>

      <div v-if="mode === 'register'" class="rounded-md border border-leaf/20 bg-leaf/5 p-4">
        <ShoppingBag class="text-leaf" :size="20" />
        <strong class="mt-3 block">Customer account</strong>
        <span class="mt-1 block text-sm leading-6 text-ink/60">
          Daftar sebagai customer dulu. Setelah login, ajukan akses writer lewat tab Become Writer.
        </span>
      </div>

      <div v-if="mode === 'register'" class="grid gap-3 sm:grid-cols-2">
        <input v-model="form.username" aria-label="Username" autocomplete="username" class="field" placeholder="Username" required />
        <input v-model="form.full_name" aria-label="Nama lengkap" autocomplete="name" class="field" placeholder="Nama lengkap" required />
      </div>
      <input v-model="form.email" :aria-label="mode === 'login' ? 'Email atau username' : 'Email'" :type="mode === 'register' ? 'email' : 'text'" autocomplete="username" class="field" :placeholder="mode === 'login' ? 'Email atau username' : 'Email'" required />
      <input v-if="mode === 'register'" v-model="form.phone" aria-label="Nomor HP" autocomplete="tel" class="field" placeholder="Nomor HP" />
      <input v-model="form.password" aria-label="Password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" :minlength="mode === 'register' ? 8 : undefined" class="field" placeholder="Password" type="password" required />
      <input v-if="mode === 'register'" v-model="form.confirm_password" aria-label="Konfirmasi password" autocomplete="new-password" minlength="8" class="field" placeholder="Konfirmasi password" type="password" required />

      <button class="btn-primary w-full" :disabled="submitting">{{ submitting ? 'Memproses...' : mode === 'login' ? 'Login' : 'Create account' }}</button>

      <p class="text-center text-sm text-ink/55">
        {{ mode === 'login' ? 'Belum punya akun?' : 'Sudah punya akun?' }}
        <button type="button" class="font-semibold text-leaf" @click="switchMode(mode === 'login' ? 'register' : 'login')">
          {{ mode === 'login' ? 'Register' : 'Login' }}
        </button>
      </p>
    </form>
  </section>
</template>
