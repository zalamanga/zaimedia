import { createRouter, createWebHistory } from 'vue-router';
import { auth } from './auth';
import HomeView from './views/HomeView.vue';
import AboutView from './views/AboutView.vue';
import ArticlesView from './views/ArticlesView.vue';
import ArticleDetailView from './views/ArticleDetailView.vue';
import AuthView from './views/AuthView.vue';
import DashboardView from './views/DashboardView.vue';
import BookDetailView from './views/BookDetailView.vue';
import CartView from './views/CartView.vue';
import GuideView from './views/GuideView.vue';
import ReaderView from './views/ReaderView.vue';
import OrderSuccessView from './views/OrderSuccessView.vue';
import NotFoundView from './views/NotFoundView.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomeView },
    { path: '/about', component: AboutView },
    { path: '/guides/:type', component: GuideView },
    { path: '/articles', component: ArticlesView },
    { path: '/articles/:slug', component: ArticleDetailView },
    { path: '/books/:id', component: BookDetailView },
    { path: '/cart', component: CartView },
    { path: '/login', component: AuthView, meta: { guest: true } },
    { path: '/reader/:id', component: ReaderView, meta: { auth: true } },
    { path: '/orders/:orderRef/success', component: OrderSuccessView, meta: { auth: true } },
    { path: '/dashboard', component: DashboardView, meta: { auth: true } },
    { path: '/:pathMatch(.*)*', component: NotFoundView },
  ],
});

router.beforeEach((to) => {
  if (to.meta.auth && !auth.isLoggedIn) {
    return { path: '/login', query: { redirect: to.fullPath } };
  }

  if (to.meta.guest && auth.isLoggedIn) {
    return '/dashboard';
  }
});

export default router;
