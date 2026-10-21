<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Bell, BookOpen, HelpCircle, Home, LayoutDashboard, LogOut, Newspaper, ShoppingBag, UserRound } from '@lucide/vue';
import { api } from './api';
import { auth } from './auth';
import { cart } from './cart';
import ToastHost from './components/ToastHost.vue';
import { toast } from './toast';

const router = useRouter();
const route = useRoute();
const roleLabel = computed(() => auth.user?.role || 'guest');
const notifications = ref([]);
const showNotifications = ref(false);
const unreadCount = computed(() => notifications.value.filter((item) => !item.read_at).length);

async function loadNotifications() {
  if (!auth.isLoggedIn) return;
  const token = auth.token;
  try {
    const { data } = await api.get('/notifications');
    if (auth.token === token) notifications.value = data;
  } catch {
    if (auth.token === token) notifications.value = [];
  }
}

async function markAllRead() {
  try {
    await api.post('/notifications/read-all');
    await loadNotifications();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Notifikasi gagal diperbarui.');
  }
}

async function openNotification(item) {
  showNotifications.value = false;
  if (item.read_at) return;

  try {
    await api.patch(`/notifications/${item.id}/read`);
    item.read_at = new Date().toISOString();
  } catch {
    await loadNotifications();
  }
}

function handleUnauthorized() {
  if (route.path !== '/login') {
    toast.error('Session habis. Silakan login lagi.');
    router.push({ path: '/login', query: { redirect: route.fullPath } });
  }
}

async function logout() {
  await auth.logout();
  toast.info('Kamu sudah logout.');
  router.push('/');
}

onMounted(() => {
  window.addEventListener('zaimedia:unauthorized', handleUnauthorized);
  window.addEventListener('keydown', closeNotifications);
});
function closeNotifications(event) {
  if (event.key === 'Escape') showNotifications.value = false;
}
watch(() => auth.token, () => {
  notifications.value = [];
  showNotifications.value = false;
  loadNotifications();
}, { immediate: true });
onBeforeUnmount(() => {
  window.removeEventListener('zaimedia:unauthorized', handleUnauthorized);
  window.removeEventListener('keydown', closeNotifications);
});
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <header class="sticky top-0 z-30 border-b border-leaf/10 bg-white/80 shadow-sm backdrop-blur-xl">
      <div class="shell flex min-h-16 items-center justify-between gap-3 py-2">
        <RouterLink to="/" class="flex items-center gap-3">
          <span class="grid h-10 w-10 place-items-center rounded-2xl bg-leaf text-white shadow-glow">
            <BookOpen :size="19" />
          </span>
          <span>
            <strong class="block leading-tight">Zaimedia</strong>
            <small class="hidden text-xs text-ink/55 sm:block">Bright digital bookstore</small>
          </span>
        </RouterLink>

        <nav aria-label="Navigasi utama" class="hidden min-w-0 flex-wrap items-center justify-end gap-2 rounded-full border border-leaf/10 bg-white/70 p-1 shadow-sm sm:flex">
          <RouterLink to="/" aria-label="Home" class="btn-soft px-3" active-class="border-leaf text-leaf">
            <Home :size="17" />
            <span class="hidden md:inline">Home</span>
          </RouterLink>
          <RouterLink to="/about" aria-label="About" class="btn-soft px-3" active-class="border-leaf text-leaf">
            <HelpCircle :size="17" />
            <span class="hidden lg:inline">About</span>
          </RouterLink>
          <RouterLink to="/articles" aria-label="Artikel" class="btn-soft px-3" active-class="border-leaf text-leaf">
            <Newspaper :size="17" />
            <span class="hidden lg:inline">Artikel</span>
          </RouterLink>
          <RouterLink to="/cart" class="btn-soft px-3" active-class="border-leaf text-leaf">
            <ShoppingBag :size="17" />
            <span class="hidden sm:inline">Cart</span>
            <span v-if="cart.count" class="rounded bg-honey/20 px-2 text-xs">{{ cart.count }}</span>
          </RouterLink>
          <RouterLink v-if="auth.isLoggedIn" to="/dashboard" class="btn-soft px-3" active-class="border-leaf text-leaf">
            <LayoutDashboard :size="17" />
            <span class="hidden sm:inline">Dashboard</span>
          </RouterLink>
          <div v-if="auth.isLoggedIn" class="relative">
            <button class="btn-soft px-3" type="button" aria-label="Notifikasi" aria-controls="notification-panel" :aria-expanded="showNotifications" @click="showNotifications = !showNotifications; loadNotifications()">
              <Bell :size="17" />
              <span v-if="unreadCount" class="rounded bg-honey px-2 text-xs text-ink">{{ unreadCount }}</span>
            </button>
            <div v-if="showNotifications" id="notification-panel" aria-label="Notifikasi" class="absolute right-0 top-12 z-50 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-md border border-ink/10 bg-white shadow-soft">
              <div class="flex items-center justify-between border-b border-ink/10 bg-paper p-3">
                <strong>Notifications</strong>
                <button class="text-xs font-semibold text-leaf" @click="markAllRead">Read all</button>
              </div>
              <div class="max-h-96 overflow-y-auto">
                <div v-if="!notifications.length" class="p-4 text-sm text-ink/55">Belum ada notifikasi.</div>
                <RouterLink
                  v-for="item in notifications"
                  :key="item.id"
                  :to="item.action_url || '/dashboard'"
                  class="block border-b border-ink/10 p-3 text-sm hover:bg-paper"
                  :class="{ 'bg-leaf/5': !item.read_at }"
                  @click="openNotification(item)"
                >
                  <strong class="block">{{ item.title }}</strong>
                  <span class="mt-1 block text-ink/55">{{ item.message }}</span>
                </RouterLink>
              </div>
            </div>
          </div>
          <span v-if="auth.isLoggedIn" class="hidden items-center gap-2 rounded-full border border-leaf/10 bg-white px-3 py-2 text-sm font-semibold text-ink/65 lg:inline-flex">
            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-6 w-6 rounded object-cover" :alt="auth.user?.full_name || 'Avatar'" />
            <UserRound v-else :size="16" />
            {{ auth.user?.full_name || auth.user?.username }}
            <small class="rounded bg-leaf/10 px-2 py-0.5 text-xs uppercase text-leaf">{{ roleLabel }}</small>
          </span>
          <RouterLink v-if="!auth.isLoggedIn" to="/login" class="btn-primary">
            <UserRound :size="17" />
            Login
          </RouterLink>
          <button v-else class="btn-primary px-3" @click="logout">
            <LogOut :size="17" />
            <span class="hidden sm:inline">Logout</span>
          </button>
        </nav>
      </div>
    </header>

    <main class="flex-1 pb-20 sm:pb-0">
      <RouterView />
    </main>
    <ToastHost />

    <nav aria-label="Navigasi ponsel" class="fixed inset-x-3 bottom-3 z-40 rounded-3xl border border-leaf/10 bg-white/95 px-3 py-2 shadow-glow backdrop-blur sm:hidden">
      <div class="mx-auto grid max-w-md grid-cols-[repeat(4,minmax(0,1fr))] gap-2">
        <RouterLink to="/" class="flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-2xl text-xs font-semibold text-ink/55" active-class="bg-leaf/10 text-leaf">
          <Home :size="18" />
          <span class="max-w-full truncate">Home</span>
        </RouterLink>
        <RouterLink to="/cart" class="relative flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-2xl text-xs font-semibold text-ink/55" active-class="bg-leaf/10 text-leaf">
          <ShoppingBag :size="18" />
          <span class="max-w-full truncate">Cart</span>
          <span v-if="cart.count" class="absolute right-4 top-1 rounded bg-honey px-1.5 text-[10px] text-ink">{{ cart.count }}</span>
        </RouterLink>
        <RouterLink :to="auth.isLoggedIn ? '/dashboard' : '/login'" class="flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-2xl text-xs font-semibold text-ink/55" active-class="bg-leaf/10 text-leaf">
          <img v-if="auth.isLoggedIn && auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-5 w-5 rounded object-cover" :alt="auth.user?.full_name || 'Avatar'" />
          <LayoutDashboard v-else-if="auth.isLoggedIn" :size="18" />
          <UserRound v-else :size="18" />
          <span class="max-w-full truncate">{{ auth.isLoggedIn ? 'Dashboard' : 'Login' }}</span>
        </RouterLink>
        <button v-if="auth.isLoggedIn" class="flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-2xl text-xs font-semibold text-ink/55" type="button" @click="logout">
          <LogOut :size="18" />
          <span class="max-w-full truncate">Logout</span>
        </button>
        <RouterLink v-else :to="{ path: '/login', query: { mode: 'register' } }" class="flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 rounded-2xl text-xs font-semibold text-ink/55" active-class="bg-leaf/10 text-leaf">
          <UserRound :size="18" />
          <span class="max-w-full truncate">Daftar</span>
        </RouterLink>
      </div>
    </nav>

    <footer class="mb-20 border-t border-ink/10 bg-white sm:mb-0">
      <div class="shell flex flex-col gap-3 py-6 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
        <p>Copyright 2026 ZaiMedia. Laravel API + Vue frontend.</p>
        <div class="flex flex-wrap gap-3">
          <RouterLink to="/" class="font-semibold hover:text-leaf">Catalog</RouterLink>
          <RouterLink to="/about" class="font-semibold hover:text-leaf">About</RouterLink>
          <RouterLink to="/articles" class="font-semibold hover:text-leaf">Artikel</RouterLink>
          <RouterLink to="/guides/buy" class="font-semibold hover:text-leaf">Cara beli</RouterLink>
          <RouterLink to="/guides/writer" class="font-semibold hover:text-leaf">Jadi writer</RouterLink>
          <RouterLink to="/cart" class="font-semibold hover:text-leaf">Cart</RouterLink>
          <RouterLink v-if="auth.isLoggedIn" to="/dashboard" class="font-semibold hover:text-leaf">Dashboard</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
