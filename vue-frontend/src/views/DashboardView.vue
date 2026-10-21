<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  BadgeCheck,
  Banknote,
  BarChart3,
  BookMarked,
  CalendarDays,
  CheckCircle2,
  CircleDollarSign,
  Clock3,
  CreditCard,
  Download,
  ExternalLink,
  FolderTree,
  Gauge,
  LibraryBig,
  MapPin,
  Newspaper,
  PackageCheck,
  ReceiptText,
  Search,
  ShieldCheck,
  Star,
  Trash2,
  Trophy,
  Upload,
  Users,
  Wallet,
  XCircle,
} from '@lucide/vue';
import { api, money } from '../api';
import { auth } from '../auth';
import { cart } from '../cart';
import { toast } from '../toast';
import { countByStatus, createRequestGate } from '../state-utils.js';
import { writeJson } from '../storage.js';
import { downloadBook } from '../private-pdf.js';
import TableHeader from '../components/TableHeader.vue';
import BookRows from '../components/BookRows.vue';
import WithdrawalRows from '../components/WithdrawalRows.vue';

const role = computed(() => auth.user?.role || 'customer');
const isAdmin = computed(() => ['admin', 'super_admin'].includes(role.value));
const route = useRoute();
const router = useRouter();
const defaultTab = computed(() => (isAdmin.value ? 'overview' : role.value === 'writer' ? 'books' : 'library'));
const activeTab = ref(String(route.query.tab || defaultTab.value));
const summary = ref({});
const categories = ref([]);
const books = ref([]);
const orders = ref([]);
const withdrawals = ref([]);
const users = ref([]);
const sales = ref([]);
const auditLogs = ref([]);
const writerRequests = ref([]);
const reviews = ref([]);
const articles = ref([]);
const settings = ref({});
const wishlist = ref([]);
const writerRequestStatus = ref(null);
const writerSales = ref([]);
const library = ref([]);
const message = ref('');
const error = ref('');
const search = ref('');
const loading = ref(false);
const downloading = ref(null);
const requests = createRequestGate();
const savingProfile = ref(false);
const changingPassword = ref(false);
const uploadingAvatar = ref(false);
const editingBookId = ref(null);
const expandedOrderId = ref(null);
const expandedBookId = ref(null);
const bookStatusFilter = ref('all');
const orderPaymentFilter = ref('all');
const orderStatusFilter = ref('all');
const withdrawalStatusFilter = ref('all');
const userRoleFilter = ref('all');
const userStatusFilter = ref('all');
const libraryFilter = ref('all');
const auditActionFilter = ref('all');
const auditDateFrom = ref('');
const auditDateTo = ref('');
const selectedAuditLog = ref(null);
const selectedInvoice = ref(null);
const writerRequestStatusFilter = ref('all');
const reviewStatusFilter = ref('all');
const articleStatusFilter = ref('all');
const writerRequestRejectionReasons = ref({});
const reviewModerationReasons = ref({});
const adminPages = ref({ books: 1, orders: 1, withdrawals: 1, users: 1, sales: 1, audit: 1, writerRequests: 1, reviews: 1, articles: 1 });
const adminPagination = ref({});
const rejectionReasons = ref({});
const withdrawalRejectionReasons = ref({});
const confirmDialog = ref({
  open: false,
  title: '',
  description: '',
  confirmText: 'Hapus',
  action: null,
});
let filterReloadTimer = null;

const emptyBook = {
  title: '',
  author: '',
  publisher: '',
  year: new Date().getFullYear(),
  pages: 0,
  price: 0,
  isbn: '',
  category_id: '',
  cover_image: '',
  pdf_file: '',
  short_description: '',
  long_description: '',
};
const bookForm = ref({ ...emptyBook });
const withdrawal = ref({ amount: 100000, bank_name: '', account_number: '', account_name: '', notes: '' });
const emptyCategory = { name: '', slug: '', description: '' };
const categoryForm = ref({ ...emptyCategory });
const editingCategoryId = ref(null);
const profile = ref({
  full_name: auth.user?.full_name || '',
  phone: auth.user?.phone || '',
  bio: auth.user?.bio || '',
  address: auth.user?.address || '',
  city: auth.user?.city || '',
  postal_code: auth.user?.postal_code || '',
  website: auth.user?.website || '',
  instagram: auth.user?.instagram || '',
  linkedin: auth.user?.linkedin || '',
});
const passwordForm = ref({ current_password: '', new_password: '', confirm_password: '' });
const emptyArticle = { title: '', slug: '', excerpt: '', content: '', cover_image: '', status: 'draft' };
const articleForm = ref({ ...emptyArticle });
const editingArticleId = ref(null);
const writerRequestForm = ref({
  pen_name: auth.user?.full_name || '',
  bio: auth.user?.bio || '',
  reason: '',
  portfolio_url: '',
});

const adminTabs = [
  { id: 'overview', label: 'Overview', icon: Gauge },
  { id: 'profile', label: 'Profile', icon: BadgeCheck },
  { id: 'books', label: 'Books', icon: LibraryBig },
  { id: 'categories', label: 'Categories', icon: FolderTree },
  { id: 'orders', label: 'Orders', icon: PackageCheck },
  { id: 'withdrawals', label: 'Withdrawals', icon: Wallet },
  { id: 'users', label: 'Users', icon: Users },
  { id: 'writerRequests', label: 'Writer Requests', icon: BadgeCheck },
  { id: 'reviews', label: 'Reviews', icon: Star },
  { id: 'articles', label: 'Articles', icon: Newspaper },
  { id: 'sales', label: 'Sales', icon: CircleDollarSign },
  { id: 'settings', label: 'Settings', icon: ShieldCheck },
  { id: 'audit', label: 'Audit', icon: ShieldCheck },
];
const writerTabs = [
  { id: 'profile', label: 'Profile', icon: BadgeCheck },
  { id: 'books', label: 'My Books', icon: BookMarked },
  { id: 'royalty', label: 'Royalty', icon: Banknote },
  { id: 'withdrawals', label: 'Withdrawals', icon: Wallet },
  { id: 'library', label: 'Library', icon: LibraryBig },
  { id: 'wishlist', label: 'Wishlist', icon: Star },
  { id: 'orders', label: 'Orders', icon: PackageCheck },
];
const customerTabs = [
  { id: 'profile', label: 'Profile', icon: BadgeCheck },
  { id: 'library', label: 'Library', icon: LibraryBig },
  { id: 'wishlist', label: 'Wishlist', icon: Star },
  { id: 'orders', label: 'Orders', icon: PackageCheck },
  { id: 'writerRequest', label: 'Become Writer', icon: BookMarked },
];
const tabs = computed(() => (isAdmin.value ? adminTabs : role.value === 'writer' ? writerTabs : customerTabs));

const filteredBooks = computed(() => {
  const rows = bookStatusFilter.value === 'archived'
    ? books.value.filter((book) => book.deleted_at)
    : bookStatusFilter.value !== 'all'
    ? books.value.filter((book) => book.status === bookStatusFilter.value)
    : books.value;
  return filterRows(rows, ['title', 'author', 'status']);
});
const filteredCategories = computed(() => filterRows(categories.value, ['name', 'slug', 'description']));
const filteredOrders = computed(() => {
  const rows = orders.value.filter((order) => {
    const paymentMatch = orderPaymentFilter.value === 'all' || order.payment_status === orderPaymentFilter.value;
    const statusMatch = orderStatusFilter.value === 'all' || order.order_status === orderStatusFilter.value;
    return paymentMatch && statusMatch;
  });
  return filterRows(rows, ['order_number', 'payment_status', 'order_status']);
});
const filteredWithdrawals = computed(() => {
  const rows = withdrawalStatusFilter.value === 'all'
    ? withdrawals.value
    : withdrawals.value.filter((row) => row.status === withdrawalStatusFilter.value);
  return filterRows(rows, ['withdrawal_number', 'bank_name', 'status']);
});
const filteredUsers = computed(() => {
  const rows = users.value.filter((user) => {
    const roleMatch = userRoleFilter.value === 'all' || user.role === userRoleFilter.value;
    const statusMatch = userStatusFilter.value === 'all' || user.status === userStatusFilter.value;
    return roleMatch && statusMatch;
  });
  return filterRows(rows, ['username', 'email', 'full_name', 'role', 'status']);
});
const filteredWriterRequests = computed(() => {
  const rows = writerRequestStatusFilter.value === 'all'
    ? writerRequests.value
    : writerRequests.value.filter((request) => request.status === writerRequestStatusFilter.value);
  return filterRows(rows, ['pen_name', 'reason', 'status']);
});
const filteredReviews = computed(() => {
  const rows = reviewStatusFilter.value === 'all'
    ? reviews.value
    : reviews.value.filter((review) => review.status === reviewStatusFilter.value);
  return filterRows(rows, ['title', 'content', 'status']);
});
const filteredArticles = computed(() => {
  const rows = articleStatusFilter.value === 'all'
    ? articles.value
    : articles.value.filter((article) => article.status === articleStatusFilter.value);
  return filterRows(rows, ['title', 'slug', 'excerpt', 'status']);
});
const libraryStats = computed(() => ({
  total: library.value.length,
  reviewed: library.value.filter((book) => book.has_reviewed).length,
  unreviewed: library.value.filter((book) => !book.has_reviewed).length,
  downloads: library.value.filter((book) => book.has_pdf).length,
}));
const filteredLibrary = computed(() => {
  const rows = library.value.filter((book) => {
    if (libraryFilter.value === 'reviewed') return book.has_reviewed;
    if (libraryFilter.value === 'unreviewed') return !book.has_reviewed;
    if (libraryFilter.value === 'downloadable') return Boolean(book.has_pdf);
    return true;
  });
  return filterRows(rows, ['title', 'author', 'order_number']);
});
const bookStatusCounts = computed(() => countByStatus(books.value, 'status'));
const archivedBookCount = computed(() => books.value.filter((book) => book.deleted_at).length);
const orderPaymentCounts = computed(() => countByStatus(orders.value, 'payment_status'));
const orderStatusCounts = computed(() => countByStatus(orders.value, 'order_status'));
const withdrawalStatusCounts = computed(() => countByStatus(withdrawals.value, 'status'));
const userRoleCounts = computed(() => countByStatus(users.value, 'role'));
const userStatusCounts = computed(() => countByStatus(users.value, 'status'));
const writerRequestCounts = computed(() => countByStatus(writerRequests.value, 'status'));
const reviewCounts = computed(() => countByStatus(reviews.value, 'status'));
const articleCounts = computed(() => countByStatus(articles.value, 'status'));
const auditActionCounts = computed(() => countByStatus(auditLogs.value, 'action'));
const writerBalance = computed(() => summary.value.balance || { available_balance: 0, pending_balance: 0, total_withdrawn: 0 });
const writerRoyaltyTotal = computed(() => writerSales.value.reduce((sum, sale) => sum + Number(sale.royalty_amount || 0), 0));
const writerBooksSold = computed(() => writerSales.value.reduce((sum, sale) => sum + Number(sale.quantity || 0), 0));
const dashboardMetrics = computed(() => {
  if (isAdmin.value) {
    return [
      { key: 'books', label: 'Total Books', value: summary.value.books || 0, helper: `${summary.value.pending_books || 0} pending`, icon: LibraryBig, tone: 'bg-blue-50 text-blue-700' },
      { key: 'users', label: 'Total Users', value: summary.value.users || 0, helper: 'Admin, writer, customer', icon: Users, tone: 'bg-green-50 text-green-700' },
      { key: 'orders', label: 'Total Orders', value: summary.value.orders || 0, helper: `${summary.value.paid_orders || 0} paid`, icon: PackageCheck, tone: 'bg-sky-50 text-sky-700' },
      { key: 'revenue', label: 'Revenue', value: money(summary.value.revenue || 0), helper: 'Paid orders', icon: CircleDollarSign, tone: 'bg-amber-50 text-amber-700' },
    ];
  }

  if (role.value === 'writer') {
    return [
      { key: 'books', label: 'Books', value: summary.value.books || 0, helper: `${summary.value.pending_books || 0} pending`, icon: BookMarked, tone: 'bg-blue-50 text-blue-700' },
      { key: 'approved_books', label: 'Approved', value: summary.value.approved_books || 0, helper: `${summary.value.rejected_books || 0} rejected`, icon: CheckCircle2, tone: 'bg-green-50 text-green-700' },
      { key: 'books_sold', label: 'Books Sold', value: summary.value.books_sold || 0, helper: `${summary.value.total_views || 0} views`, icon: PackageCheck, tone: 'bg-sky-50 text-sky-700' },
      { key: 'sales', label: 'Royalty', value: money(summary.value.sales || 0), helper: 'Total earned', icon: CircleDollarSign, tone: 'bg-amber-50 text-amber-700' },
    ];
  }

  return [
    { key: 'orders', label: 'Orders', value: summary.value.orders || 0, helper: `${summary.value.paid_orders || 0} paid`, icon: PackageCheck, tone: 'bg-blue-50 text-blue-700' },
    { key: 'spend', label: 'Total Spend', value: money(summary.value.spend || 0), helper: 'Paid orders', icon: CreditCard, tone: 'bg-green-50 text-green-700' },
    { key: 'library', label: 'Library', value: libraryStats.value.total, helper: `${libraryStats.value.downloads} with PDF`, icon: LibraryBig, tone: 'bg-sky-50 text-sky-700' },
    { key: 'reviews', label: 'Reviews', value: libraryStats.value.reviewed, helper: `${libraryStats.value.unreviewed} waiting`, icon: BadgeCheck, tone: 'bg-amber-50 text-amber-700' },
  ];
});
const adminRevenueBars = computed(() => {
  const rows = Array.isArray(summary.value.monthly_revenue) ? summary.value.monthly_revenue : [];
  const max = Math.max(...rows.map((row) => Number(row.revenue || 0)), 1);
  return rows.map((row) => ({
    ...row,
    label: new Intl.DateTimeFormat('id-ID', { month: 'short' }).format(new Date(`${row.month}-01`)),
    height: Math.max(8, Math.round((Number(row.revenue || 0) / max) * 100)),
  }));
});
const adminTopBooks = computed(() => Array.isArray(summary.value.top_books) ? summary.value.top_books : []);
const adminRecentOrders = computed(() => Array.isArray(summary.value.recent_orders) ? summary.value.recent_orders : []);
const adminPendingBooks = computed(() => Array.isArray(summary.value.pending_book_queue) ? summary.value.pending_book_queue : []);
const adminAlerts = computed(() => [
  {
    key: 'pending_books',
    label: 'Books waiting for review',
    value: summary.value.pending_books || 0,
    action: 'Review now',
    tab: 'books',
    tone: 'border-amber-200 bg-amber-50 text-amber-800',
  },
  {
    key: 'withdrawals',
    label: 'Withdrawal requests pending',
    value: summary.value.withdrawals || 0,
    action: 'Process now',
    tab: 'withdrawals',
    tone: 'border-sky-200 bg-sky-50 text-sky-800',
  },
].filter((item) => Number(item.value) > 0));
const canRequestWithdrawal = computed(() => {
  const amount = Number(withdrawal.value.amount || 0);
  return amount >= 100000 && amount <= 10000000 && amount <= Number(writerBalance.value.available_balance || 0);
});

function filterRows(rows, fields) {
  const q = search.value.toLowerCase().trim();
  if (!q) return rows;
  return rows.filter((row) => fields.some((field) => String(row[field] || '').toLowerCase().includes(q)));
}

function rowsFrom(data) {
  return Array.isArray(data) ? data : (data?.data || []);
}

function rememberPagination(key, data) {
  if (!Array.isArray(data) && data?.current_page) {
    adminPagination.value[key] = {
      current_page: data.current_page,
      last_page: data.last_page,
      from: data.from || 0,
      to: data.to || 0,
      total: data.total || 0,
    };
  }
}

function pageInfo(key) {
  return adminPagination.value[key] || { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 };
}

function adminSearchParam() {
  return search.value.trim() ? search.value.trim() : undefined;
}

async function setAdminPage(key, page) {
  const info = pageInfo(key);
  const next = Math.min(Math.max(1, page), info.last_page || 1);
  if (next === info.current_page) return;
  adminPages.value[key] = next;
  await load();
}

function valueLabel(value) {
  if (value && typeof value === 'object') {
    return money(value.available_balance ?? value.pending_balance ?? value.total_withdrawn ?? 0);
  }
  if (String(value).includes('.') || Number(value) > 99999) return money(value);
  return value;
}

function statusClass(status) {
  const map = {
    approved: 'bg-green-50 text-green-700 ring-green-200',
    paid: 'bg-green-50 text-green-700 ring-green-200',
    delivered: 'bg-green-50 text-green-700 ring-green-200',
    completed: 'bg-green-50 text-green-700 ring-green-200',
    active: 'bg-green-50 text-green-700 ring-green-200',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200',
    shipped: 'bg-blue-50 text-blue-700 ring-blue-200',
    writer: 'bg-leaf/10 text-leaf ring-leaf/20',
    admin: 'bg-ink text-white ring-ink',
    customer: 'bg-slate-50 text-slate-700 ring-slate-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
    failed: 'bg-red-50 text-red-700 ring-red-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
    suspended: 'bg-red-50 text-red-700 ring-red-200',
    archived: 'bg-slate-100 text-slate-600 ring-slate-200',
  };
  return map[status] || 'bg-slate-50 text-slate-700 ring-slate-200';
}

function dateLabel(value) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function methodLabel(value) {
  return String(value || '-').replaceAll('_', ' ');
}

function bookPath(book) {
  return `/books/${book?.slug || book?.id}`;
}

function closeInvoice() {
  selectedInvoice.value = null;
}

function printInvoice() {
  window.print();
}

function classBasename(value) {
  return String(value || '').split('\\').pop();
}

function compactAuditValue(value) {
  if (value === null || value === undefined || value === '') return '-';
  if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

function auditSummary(log) {
  const metadata = log?.metadata || {};
  if (metadata.title) return metadata.title;
  if (metadata.before || metadata.after) {
    const keys = [...new Set([...Object.keys(metadata.before || {}), ...Object.keys(metadata.after || {})])];
    return keys.slice(0, 3).join(', ') || 'Perubahan data';
  }
  return classBasename(log?.target_type) ? `${classBasename(log.target_type)} #${log.target_id}` : 'Detail aktivitas';
}

function auditDiffRows(log) {
  const metadata = log?.metadata || {};
  const before = metadata.before || {};
  const after = metadata.after || {};
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])];

  return keys.map((key) => ({
    key,
    before: compactAuditValue(before[key]),
    after: compactAuditValue(after[key]),
    changed: JSON.stringify(before[key] ?? null) !== JSON.stringify(after[key] ?? null),
  }));
}

function auditMetadataRows(log) {
  const metadata = log?.metadata || {};
  return Object.entries(metadata)
    .filter(([key]) => !['before', 'after'].includes(key))
    .map(([key, value]) => ({ key, value: compactAuditValue(value) }));
}

function closeAuditDetail() {
  selectedAuditLog.value = null;
}

function showError(err, fallback) {
  error.value = err.response?.data?.message || fallback;
  toast.error(error.value);
}

function openConfirm({ title, description, confirmText = 'Hapus', action }) {
  confirmDialog.value = {
    open: true,
    title,
    description,
    confirmText,
    action,
  };
}

function closeConfirm() {
  confirmDialog.value = {
    open: false,
    title: '',
    description: '',
    confirmText: 'Hapus',
    action: null,
  };
}

async function runConfirmAction() {
  const action = confirmDialog.value.action;
  closeConfirm();
  try {
    if (action) await action();
  } catch (err) {
    error.value = err.response?.data?.message || 'Aksi gagal dijalankan.';
    toast.error(error.value);
  }
}

async function load() {
  const request = requests.begin();
  loading.value = true;
  error.value = '';
  try {
    const [summaryRes, categoryRes] = await Promise.all([api.get('/dashboard/summary'), api.get('/categories')]);
    if (!requests.isCurrent(request)) return;
    summary.value = summaryRes.data;
    categories.value = categoryRes.data;

    if (isAdmin.value) {
      const params = (key, extra = {}) => ({ page: adminPages.value[key], per_page: 12, q: adminSearchParam(), ...extra });
      const [bookRes, orderRes, withdrawalRes, userRes, writerRequestRes, reviewRes, articleRes, settingRes, saleRes, auditRes] = await Promise.all([
        api.get('/admin/books', { params: params('books', { status: bookStatusFilter.value }) }),
        api.get('/admin/orders', { params: params('orders', { payment_status: orderPaymentFilter.value, order_status: orderStatusFilter.value }) }),
        api.get('/admin/withdrawals', { params: params('withdrawals', { status: withdrawalStatusFilter.value }) }),
        api.get('/admin/users', { params: params('users', { role: userRoleFilter.value, status: userStatusFilter.value }) }),
        api.get('/admin/writer-requests', { params: params('writerRequests', { status: writerRequestStatusFilter.value }) }),
        api.get('/admin/reviews', { params: params('reviews', { status: reviewStatusFilter.value }) }),
        api.get('/admin/articles', { params: params('articles', { status: articleStatusFilter.value }) }),
        api.get('/admin/settings'),
        api.get('/admin/sales', { params: params('sales') }),
        api.get('/admin/audit-logs', { params: params('audit', { action: auditActionFilter.value, date_from: auditDateFrom.value || undefined, date_to: auditDateTo.value || undefined }) }),
      ]);
      if (!requests.isCurrent(request)) return;
      books.value = rowsFrom(bookRes.data);
      orders.value = rowsFrom(orderRes.data);
      withdrawals.value = rowsFrom(withdrawalRes.data);
      users.value = rowsFrom(userRes.data);
      writerRequests.value = rowsFrom(writerRequestRes.data);
      reviews.value = rowsFrom(reviewRes.data);
      articles.value = rowsFrom(articleRes.data);
      settings.value = settingRes.data;
      sales.value = rowsFrom(saleRes.data);
      auditLogs.value = rowsFrom(auditRes.data);
      rememberPagination('books', bookRes.data);
      rememberPagination('orders', orderRes.data);
      rememberPagination('withdrawals', withdrawalRes.data);
      rememberPagination('users', userRes.data);
      rememberPagination('writerRequests', writerRequestRes.data);
      rememberPagination('reviews', reviewRes.data);
      rememberPagination('articles', articleRes.data);
      rememberPagination('sales', saleRes.data);
      rememberPagination('audit', auditRes.data);
    } else if (role.value === 'writer') {
      const [bookRes, balanceRes, salesRes, withdrawalRes, orderRes, libraryRes, wishlistRes, writerRequestRes] = await Promise.all([
        api.get('/writer/books'),
        api.get('/royalty/balance'),
        api.get('/royalty/sales'),
        api.get('/royalty/withdrawals'),
        api.get('/orders/me'),
        api.get('/library'),
        api.get('/wishlist'),
        api.get('/writer-request/me'),
      ]);
      if (!requests.isCurrent(request)) return;
      books.value = bookRes.data;
      summary.value.balance = balanceRes.data;
      writerSales.value = salesRes.data;
      withdrawals.value = withdrawalRes.data;
      orders.value = orderRes.data;
      library.value = libraryRes.data;
      wishlist.value = wishlistRes.data;
      writerRequestStatus.value = writerRequestRes.data;
    } else {
      const [orderRes, libraryRes, wishlistRes, writerRequestRes] = await Promise.all([
        api.get('/orders/me'),
        api.get('/library'),
        api.get('/wishlist'),
        api.get('/writer-request/me'),
      ]);
      if (!requests.isCurrent(request)) return;
      orders.value = orderRes.data;
      library.value = libraryRes.data;
      wishlist.value = wishlistRes.data;
      writerRequestStatus.value = writerRequestRes.data;
    }
  } catch (err) {
    if (!requests.isCurrent(request)) return;
    error.value = err.response?.data?.message || 'Gagal memuat dashboard.';
    toast.error(error.value);
  } finally {
    if (requests.isCurrent(request)) loading.value = false;
  }
}

async function download(book) {
  if (downloading.value !== null) return;
  downloading.value = book.id;
  try {
    await downloadBook(book);
  } catch {
    toast.error('PDF gagal diunduh. Pastikan akun memiliki akses buku.');
  } finally {
    downloading.value = null;
  }
}

async function uploadFile(event, type) {
  const file = event.target.files?.[0];
  if (!file) return;
  try {
    const body = new FormData();
    body.append(type, file);
    const { data } = await api.post(`/uploads/${type}`, body, { headers: { 'Content-Type': 'multipart/form-data' } });
    if (type === 'cover') bookForm.value.cover_image = data.file;
    if (type === 'pdf') bookForm.value.pdf_file = data.file;
    toast.success(type === 'cover' ? 'Cover berhasil diupload.' : 'PDF berhasil diupload.');
  } catch (err) {
    error.value = err.response?.data?.message || 'Upload gagal.';
    toast.error(error.value);
  } finally {
    event.target.value = '';
  }
}

async function uploadArticleCover(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  try {
    const body = new FormData();
    body.append('cover', file);
    const { data } = await api.post('/admin/articles/upload-cover', body, { headers: { 'Content-Type': 'multipart/form-data' } });
    articleForm.value.cover_image = data.file;
    toast.success('Cover artikel berhasil diupload.');
  } catch (err) {
    showError(err, 'Cover artikel gagal diupload.');
  } finally {
    event.target.value = '';
  }
}

async function exportReport(type) {
  try {
    const { data } = await api.get(`/admin/exports/${type}`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([data], { type: 'text/csv' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `${type}-report.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast.success(`Export ${type} berhasil dibuat.`);
  } catch (err) {
    showError(err, `Export ${type} gagal.`);
  }
}

async function uploadAvatar(event) {
  if (uploadingAvatar.value) return;
  const file = event.target.files?.[0];
  if (!file) return;
  uploadingAvatar.value = true;
  try {
    const body = new FormData();
    body.append('avatar', file);
    const { data } = await api.post('/users/me/avatar', body, { headers: { 'Content-Type': 'multipart/form-data' } });
    auth.user = data;
    writeJson('zaimedia_user', data);
    toast.success('Foto profile berhasil diupload.');
  } catch (err) {
    showError(err, 'Foto profile gagal diupload.');
  } finally {
    uploadingAvatar.value = false;
    event.target.value = '';
  }
}


function editBook(book) {
  editingBookId.value = book.id;
  bookForm.value = {
    title: book.title || '',
    author: book.author || '',
    publisher: book.publisher || '',
    year: book.year || new Date().getFullYear(),
    pages: book.pages || 0,
    price: Number(book.price || 0),
    isbn: book.isbn || '',
    category_id: book.category_id || '',
    cover_image: book.cover_image || '',
    pdf_file: book.pdf_file || '',
    short_description: book.short_description || '',
    long_description: book.long_description || '',
  };
}

function resetBookForm() {
  editingBookId.value = null;
  bookForm.value = { ...emptyBook };
}

async function saveBook() {
  try {
    const payload = { ...bookForm.value, category_id: bookForm.value.category_id || null };
    if (!payload.pdf_file) delete payload.pdf_file;
    if (editingBookId.value) {
      await api.put(`/writer/books/${editingBookId.value}`, payload);
      message.value = 'Perubahan buku disimpan dan masuk review ulang.';
    } else {
      await api.post('/writer/books', payload);
      message.value = 'Buku masuk review admin.';
    }
    toast.success(message.value);
    resetBookForm();
    await load();
  } catch (err) {
    showError(err, 'Buku gagal disimpan.');
  }
}

async function reviewBook(book, status) {
  const rejection_reason = status === 'rejected' ? String(rejectionReasons.value[book.id] || '').trim() : null;
  if (status === 'rejected' && !rejection_reason) {
    error.value = 'Alasan penolakan wajib diisi.';
    toast.error(error.value);
    expandedBookId.value = book.id;
    return;
  }
  try {
    await api.patch(`/admin/books/${book.id}/review`, { status, rejection_reason });
    rejectionReasons.value[book.id] = '';
    message.value = status === 'approved' ? 'Buku berhasil di-approve.' : status === 'rejected' ? 'Buku berhasil ditolak.' : 'Status buku diperbarui.';
    toast.success(message.value);
    await load();
  } catch (err) {
    showError(err, 'Status buku gagal diperbarui.');
  }
}

async function deleteBook(book) {
  openConfirm({
    title: 'Archive buku?',
    description: `${book.title} akan disembunyikan dari katalog publik. Order dan library customer tetap aman.`,
    confirmText: 'Archive',
    action: async () => {
      await api.delete(`/writer/books/${book.id}`);
      message.value = 'Buku berhasil diarsipkan.';
      toast.success(message.value);
      await load();
    },
  });
}

async function restoreBook(book) {
  try {
    await api.patch(`/writer/books/${book.id}/restore`);
    toast.success('Buku berhasil direstore.');
    await load();
  } catch (err) {
    showError(err, 'Buku gagal direstore.');
  }
}

async function changeOrder(order, field, value) {
  try {
    await api.patch(`/admin/orders/${order.id}/status`, { [field]: value });
    toast.success('Status order berhasil diperbarui.');
    await load();
  } catch (err) {
    showError(err, 'Status order gagal diperbarui.');
  }
}

async function processWithdrawal(row, status) {
  const rejection_reason = status === 'rejected' ? String(withdrawalRejectionReasons.value[row.id] || '').trim() : null;
  if (status === 'rejected' && !rejection_reason) {
    error.value = 'Alasan penolakan withdrawal wajib diisi.';
    toast.error(error.value);
    return;
  }
  try {
    await api.patch(`/admin/withdrawals/${row.id}`, { status, rejection_reason });
    withdrawalRejectionReasons.value[row.id] = '';
    toast.success('Status withdrawal berhasil diperbarui.');
    await load();
  } catch (err) {
    showError(err, 'Status withdrawal gagal diperbarui.');
  }
}

async function updateUser(user, field, value) {
  try {
    await api.patch(`/admin/users/${user.id}/${field}`, { [field]: value });
    toast.success('Data user berhasil diperbarui.');
    await load();
  } catch (err) {
    showError(err, 'Data user gagal diperbarui.');
    await load();
  }
}

async function submitWriterRequest() {
  try {
    const { data } = await api.post('/writer-request', writerRequestForm.value);
    writerRequestStatus.value = data;
    message.value = 'Request menjadi writer berhasil dikirim. Admin akan mereview pengajuan kamu.';
    toast.success(message.value);
    writerRequestForm.value.reason = '';
    await load();
  } catch (err) {
    showError(err, 'Writer request gagal dikirim.');
  }
}

async function processWriterRequest(row, status) {
  const rejection_reason = status === 'rejected' ? String(writerRequestRejectionReasons.value[row.id] || '').trim() : null;
  if (status === 'rejected' && !rejection_reason) {
    error.value = 'Alasan penolakan writer request wajib diisi.';
    toast.error(error.value);
    return;
  }

  try {
    await api.patch(`/admin/writer-requests/${row.id}`, { status, rejection_reason });
    writerRequestRejectionReasons.value[row.id] = '';
    toast.success(status === 'approved' ? 'Writer request berhasil diapprove.' : 'Writer request berhasil ditolak.');
    await load();
  } catch (err) {
    showError(err, 'Writer request gagal diproses.');
  }
}

async function saveSettings() {
  try {
    const { data } = await api.patch('/admin/settings', settings.value);
    settings.value = data;
    toast.success('Settings berhasil disimpan.');
    await load();
  } catch (err) {
    showError(err, 'Settings gagal disimpan.');
  }
}

async function moderateReview(review, status) {
  const moderation_reason = status === 'hidden' ? String(reviewModerationReasons.value[review.id] || '').trim() : null;
  if (status === 'hidden' && !moderation_reason) {
    toast.error('Alasan hide review wajib diisi.');
    return;
  }

  try {
    await api.patch(`/admin/reviews/${review.id}`, { status, moderation_reason });
    reviewModerationReasons.value[review.id] = '';
    toast.success(status === 'hidden' ? 'Review disembunyikan.' : 'Review ditampilkan lagi.');
    await load();
  } catch (err) {
    showError(err, 'Review gagal dimoderasi.');
  }
}

async function openInvoice(order) {
  try {
    const { data } = await api.get(`/orders/${order.id}/invoice`);
    selectedInvoice.value = data;
  } catch (err) {
    showError(err, 'Invoice gagal dibuka.');
  }
}

async function removeWishlist(book) {
  try {
    await api.post(`/wishlist/${book.id}`);
    toast.success('Buku dihapus dari wishlist.');
    await load();
  } catch (err) {
    showError(err, 'Wishlist gagal diperbarui.');
  }
}

async function requestWithdrawal() {
  try {
    await api.post('/royalty/withdrawals', withdrawal.value);
    message.value = 'Withdrawal berhasil diajukan.';
    toast.success(message.value);
    withdrawal.value = { amount: 100000, bank_name: '', account_number: '', account_name: '', notes: '' };
    await load();
  } catch (err) {
    showError(err, 'Withdrawal gagal diajukan.');
  }
}

function editCategory(category) {
  editingCategoryId.value = category.id;
  categoryForm.value = {
    name: category.name || '',
    slug: category.slug || '',
    description: category.description || '',
  };
}

function resetCategoryForm() {
  editingCategoryId.value = null;
  categoryForm.value = { ...emptyCategory };
}

async function saveCategory() {
  try {
    const payload = { ...categoryForm.value };
    if (editingCategoryId.value) {
      await api.put(`/admin/categories/${editingCategoryId.value}`, payload);
      message.value = 'Kategori berhasil diperbarui.';
    } else {
      await api.post('/admin/categories', payload);
      message.value = 'Kategori berhasil ditambahkan.';
    }
    toast.success(message.value);
    resetCategoryForm();
    await load();
  } catch (err) {
    showError(err, 'Kategori gagal disimpan.');
  }
}

function editArticle(article) {
  editingArticleId.value = article.id;
  articleForm.value = {
    title: article.title || '',
    slug: article.slug || '',
    excerpt: article.excerpt || '',
    content: article.content || '',
    cover_image: article.cover_image || '',
    status: article.status || 'draft',
  };
}

function resetArticleForm() {
  editingArticleId.value = null;
  articleForm.value = { ...emptyArticle };
}

async function saveArticle() {
  try {
    const payload = { ...articleForm.value };
    if (editingArticleId.value) {
      await api.put(`/admin/articles/${editingArticleId.value}`, payload);
      message.value = 'Artikel berhasil diperbarui.';
    } else {
      await api.post('/admin/articles', payload);
      message.value = 'Artikel berhasil dibuat.';
    }
    toast.success(message.value);
    resetArticleForm();
    await load();
  } catch (err) {
    showError(err, 'Artikel gagal disimpan.');
  }
}

async function deleteArticle(article) {
  openConfirm({
    title: 'Hapus artikel?',
    description: `${article.title} akan dihapus dari CMS artikel.`,
    action: async () => {
      await api.delete(`/admin/articles/${article.id}`);
      toast.success('Artikel berhasil dihapus.');
      await load();
    },
  });
}

async function deleteCategory(category) {
  openConfirm({
    title: 'Hapus kategori?',
    description: `${category.name} akan dihapus jika belum dipakai buku mana pun.`,
    action: async () => {
      await api.delete(`/admin/categories/${category.id}`);
      message.value = 'Kategori berhasil dihapus.';
      toast.success(message.value);
      await load();
    },
  });
}

async function saveProfile() {
  if (savingProfile.value) return;
  savingProfile.value = true;
  try {
    const { data } = await api.patch('/users/me', profile.value);
    auth.user = data;
    writeJson('zaimedia_user', data);
    message.value = 'Profile berhasil disimpan.';
    toast.success(message.value);
  } catch (err) {
    showError(err, 'Profile gagal disimpan.');
  } finally {
    savingProfile.value = false;
  }
}

async function changePassword() {
  if (changingPassword.value) return;
  if (passwordForm.value.new_password !== passwordForm.value.confirm_password) {
    error.value = 'Konfirmasi password baru tidak cocok.';
    toast.error(error.value);
    return;
  }
  changingPassword.value = true;
  try {
    await api.post('/users/me/change-password', passwordForm.value);
    passwordForm.value = { current_password: '', new_password: '', confirm_password: '' };
    message.value = 'Password berhasil diganti.';
    toast.success(message.value);
  } catch (err) {
    showError(err, 'Password gagal diganti.');
  } finally {
    changingPassword.value = false;
  }
}

watch(tabs, (availableTabs) => {
  if (!availableTabs.some((tab) => tab.id === activeTab.value)) {
    activeTab.value = defaultTab.value;
  }
}, { immediate: true });

watch(activeTab, (tab) => {
  router.replace({ query: { ...route.query, tab } });
  search.value = '';
});

watch(() => route.query.tab, (tab) => {
  if (tabs.value.some((item) => item.id === tab)) activeTab.value = tab;
});

watch([
  search,
  bookStatusFilter,
  orderPaymentFilter,
  orderStatusFilter,
  withdrawalStatusFilter,
  userRoleFilter,
  userStatusFilter,
  writerRequestStatusFilter,
  reviewStatusFilter,
  articleStatusFilter,
  auditActionFilter,
  auditDateFrom,
  auditDateTo,
], () => {
  if (!isAdmin.value) return;
  adminPages.value = { books: 1, orders: 1, withdrawals: 1, users: 1, sales: 1, audit: 1, writerRequests: 1, reviews: 1, articles: 1 };
  window.clearTimeout(filterReloadTimer);
  filterReloadTimer = window.setTimeout(() => load(), 250);
});

onMounted(async () => {
  try {
    await auth.refreshMe();
  } catch {
    // The global API interceptor handles expired sessions.
  }
  await load();
});
onBeforeUnmount(() => {
  requests.cancel();
  window.clearTimeout(filterReloadTimer);
});
</script>

<template>
  <section class="shell py-6 lg:max-w-none">
    <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
      <aside class="hidden overflow-hidden rounded-lg border border-ink/10 bg-white shadow-soft lg:sticky lg:top-24 lg:flex lg:h-[calc(100vh-7rem)] lg:flex-col">
        <div class="shrink-0 bg-ink p-5 text-white">
          <p class="text-sm text-white/55">{{ role }} workspace</p>
          <h2 class="mt-1 text-xl font-semibold">Zaimedia</h2>
          <p class="mt-3 line-clamp-1 text-sm text-white/60">{{ auth.user?.full_name }}</p>
        </div>
        <nav aria-label="Menu dashboard" class="min-h-0 flex-1 space-y-1 overflow-y-auto p-3">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-sm font-semibold text-ink/65 transition hover:bg-paper hover:text-leaf"
            :class="activeTab === tab.id ? 'border-l-4 border-leaf bg-paper text-leaf' : 'border-l-4 border-transparent'"
            @click="activeTab = tab.id"
          >
            <component :is="tab.icon" :size="18" />
            {{ tab.label }}
          </button>
        </nav>
        <div class="shrink-0 border-t border-ink/10 p-4 text-sm text-ink/55">
          <p class="line-clamp-1 font-semibold text-ink">{{ auth.user?.email }}</p>
          <p class="mt-1 capitalize">{{ role }}</p>
        </div>
      </aside>

      <main class="min-w-0">
        <div class="mb-5 overflow-hidden rounded-lg bg-ink text-white shadow-soft">
          <div class="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
              <p class="text-sm font-semibold text-white/55">{{ role }} dashboard</p>
              <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Halo, {{ auth.user?.full_name }}</h1>
              <p class="mt-2 max-w-2xl text-sm text-white/65">
                Overview, approval, order, royalty, dan aktivitas penting Zaimedia dalam satu workspace.
              </p>
            </div>
            <div class="rounded-md bg-white/10 px-4 py-3 text-sm">
              <p class="text-white/55">Signed in as</p>
              <strong>{{ auth.user?.email }}</strong>
            </div>
          </div>
          <div class="no-scrollbar flex gap-1 overflow-x-auto border-t border-white/10 p-2 lg:hidden">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === tab.id ? 'bg-white text-ink hover:bg-white hover:text-ink' : 'text-white/65 hover:bg-white/10 hover:text-white'"
              @click="activeTab = tab.id"
            >
              <component :is="tab.icon" :size="16" />
              {{ tab.label }}
            </button>
          </div>
        </div>

    <p v-if="message" role="status" class="mb-5 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ message }}</p>
    <p v-if="error" role="alert" class="mb-5 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="item in dashboardMetrics" :key="item.key" class="panel p-5 transition hover:-translate-y-1 hover:border-leaf/25">
        <div class="flex items-center gap-4">
          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md" :class="item.tone">
            <component :is="item.icon" :size="22" />
          </span>
          <div class="min-w-0">
            <p class="text-sm text-ink/55">{{ item.label }}</p>
            <strong class="mt-1 block text-2xl">{{ item.value }}</strong>
            <p class="mt-1 text-xs text-ink/45">{{ item.helper }}</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="activeTab !== 'overview' && activeTab !== 'profile'" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
      <label class="relative w-full sm:max-w-md sm:flex-1">
        <Search class="absolute left-3 top-2.5 text-ink/35" :size="18" />
        <input v-model="search" aria-label="Search current table" class="field pl-10" placeholder="Search current table" />
      </label>
      <button class="btn-soft w-full sm:w-auto" :disabled="loading" @click="load">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
    </div>

    <div v-if="loading" role="status" class="panel p-8 text-center text-ink/55">Loading dashboard...</div>

    <template v-else>
      <div v-if="isAdmin && activeTab === 'overview'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
          <div v-if="adminAlerts.length" class="grid gap-3 md:grid-cols-2">
            <button
              v-for="alert in adminAlerts"
              :key="alert.key"
              class="rounded-md border p-4 text-left transition hover:-translate-y-1"
              :class="alert.tone"
              @click="activeTab = alert.tab"
            >
              <div class="flex items-center justify-between gap-4">
                <div>
                  <strong class="text-lg">{{ alert.value }}</strong>
                  <p class="mt-1 text-sm">{{ alert.label }}</p>
                </div>
                <span class="text-sm font-semibold">{{ alert.action }}</span>
              </div>
            </button>
          </div>

          <section class="panel overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-ink/10 bg-paper/70 p-5 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-xl font-semibold">Revenue overview</h2>
                <p class="mt-1 text-sm text-ink/55">Paid order revenue dari 6 bulan terakhir.</p>
              </div>
              <button class="btn-soft w-fit" @click="activeTab = 'sales'">
                <BarChart3 :size="16" />
                Lihat sales
              </button>
            </div>
            <div class="p-5">
              <div v-if="!adminRevenueBars.length" class="grid min-h-72 place-items-center rounded-md bg-paper text-center text-ink/55">
                Belum ada revenue untuk ditampilkan.
              </div>
              <div v-else class="grid min-h-72 grid-cols-[56px_1fr] gap-4">
                <div class="flex flex-col justify-between py-3 text-right text-xs text-ink/35">
                  <span>{{ money(Math.max(...adminRevenueBars.map((row) => Number(row.revenue || 0)))) }}</span>
                  <span>{{ money(Math.max(...adminRevenueBars.map((row) => Number(row.revenue || 0))) / 2) }}</span>
                  <span>0</span>
                </div>
                <div class="flex items-end gap-3 rounded-md border border-ink/10 bg-gradient-to-b from-paper to-white px-4 py-3">
                  <div v-for="row in adminRevenueBars" :key="row.month" class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                    <div class="flex h-52 w-full items-end rounded-md bg-white/70 px-1">
                      <div
                        class="w-full rounded-md bg-leaf transition"
                        :style="{ height: `${row.height}%` }"
                        :title="`${row.label}: ${money(row.revenue || 0)}`"
                      ></div>
                    </div>
                    <span class="text-xs font-semibold text-ink/55">{{ row.label }}</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
              <div>
                <h2 class="text-xl font-semibold">Recent orders</h2>
                <p class="mt-1 text-sm text-ink/55">Order terbaru seperti dashboard lama.</p>
              </div>
              <button class="btn-soft px-3" @click="activeTab = 'orders'">View all</button>
            </div>
            <div class="divide-y divide-ink/10">
              <div v-if="!adminRecentOrders.length" class="p-8 text-center text-ink/55">Belum ada order.</div>
              <div v-for="order in adminRecentOrders" :key="order.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <strong>{{ order.order_number }}</strong>
                  <p class="mt-1 text-sm text-ink/55">{{ order.customer?.full_name || '-' }} - {{ order.customer?.email || '-' }}</p>
                </div>
                <div class="text-left sm:text-right">
                  <strong class="text-leaf">{{ money(order.total) }}</strong>
                  <p class="mt-1 text-xs text-ink/45">{{ dateLabel(order.created_at) }}</p>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-6">
          <section class="panel overflow-hidden">
            <div class="border-b border-ink/10 bg-paper/70 p-5">
              <h2 class="flex items-center gap-2 text-xl font-semibold">
                <Trophy :size="20" />
                Top selling books
              </h2>
            </div>
            <div class="divide-y divide-ink/10">
              <div v-if="!adminTopBooks.length" class="p-8 text-center text-ink/55">Belum ada sales.</div>
              <div v-for="(book, index) in adminTopBooks" :key="book.book_id" class="flex items-center gap-3 p-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-ink text-sm font-bold text-white">#{{ index + 1 }}</span>
                <div class="min-w-0 flex-1">
                  <strong class="line-clamp-1">{{ book.title }}</strong>
                  <p class="mt-1 text-sm text-ink/55">{{ book.total_sold }} sold</p>
                </div>
                <span class="text-sm font-semibold text-leaf">{{ money(book.total_revenue) }}</span>
              </div>
            </div>
          </section>

          <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
              <h2 class="flex items-center gap-2 text-xl font-semibold">
                <Clock3 :size="20" />
                Pending books
              </h2>
              <button class="btn-soft px-3" @click="activeTab = 'books'; bookStatusFilter = 'pending'">Review</button>
            </div>
            <div class="divide-y divide-ink/10">
              <div v-if="!adminPendingBooks.length" class="p-8 text-center text-ink/55">Tidak ada buku pending.</div>
              <div v-for="book in adminPendingBooks" :key="book.id" class="p-4">
                <strong class="line-clamp-1">{{ book.title }}</strong>
                <p class="mt-1 text-sm text-ink/55">{{ book.writer?.full_name || book.writer?.username || '-' }}</p>
                <p class="mt-1 text-xs text-ink/45">{{ dateLabel(book.created_at) }}</p>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div v-if="activeTab === 'profile'" class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <form class="panel space-y-4 p-5" @submit.prevent="saveProfile">
          <TableHeader title="Profile" />
          <div class="flex flex-col gap-4 rounded-md bg-paper p-4 sm:flex-row sm:items-center">
            <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-md bg-ink text-xl font-bold text-white">
              <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="h-full w-full object-cover" :alt="auth.user?.full_name || 'Avatar'" />
              <span v-else>{{ String(auth.user?.full_name || auth.user?.username || 'U').slice(0, 1).toUpperCase() }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-semibold">{{ auth.user?.full_name || auth.user?.username }}</p>
              <p class="mt-1 text-sm text-ink/55">JPG/PNG/WebP maksimal 3MB. Foto ini tampil di header dan profile.</p>
            </div>
            <label class="btn-soft w-full cursor-pointer sm:w-auto" :class="{ 'pointer-events-none opacity-60': uploadingAvatar }">
              <Upload :size="16" />
              {{ uploadingAvatar ? 'Uploading...' : 'Upload foto' }}
              <input class="sr-only" type="file" accept="image/*" :disabled="uploadingAvatar" @change="uploadAvatar" />
            </label>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <input v-model="profile.full_name" aria-label="Nama lengkap" autocomplete="name" class="field" placeholder="Nama lengkap" />
            <input v-model="profile.phone" aria-label="Nomor HP" autocomplete="tel" class="field" placeholder="Nomor HP" />
            <input v-model="profile.city" aria-label="Kota" autocomplete="address-level2" class="field" placeholder="Kota" />
            <input v-model="profile.postal_code" aria-label="Kode pos" autocomplete="postal-code" class="field" placeholder="Kode pos" />
            <input v-model="profile.website" aria-label="Website" class="field" placeholder="Website" />
            <input v-model="profile.instagram" aria-label="Instagram" class="field" placeholder="Instagram" />
          </div>
          <textarea v-model="profile.address" aria-label="Alamat" autocomplete="street-address" class="field" placeholder="Alamat" />
          <textarea v-model="profile.bio" aria-label="Bio singkat" class="field" placeholder="Bio singkat" />
          <button class="btn-primary" :disabled="savingProfile">{{ savingProfile ? 'Menyimpan...' : 'Simpan profile' }}</button>
        </form>

        <form class="panel space-y-4 p-5" @submit.prevent="changePassword">
          <TableHeader title="Security" />
          <input v-model="passwordForm.current_password" aria-label="Password lama" autocomplete="current-password" class="field" type="password" placeholder="Password lama" required />
          <input v-model="passwordForm.new_password" aria-label="Password baru" autocomplete="new-password" minlength="8" class="field" type="password" placeholder="Password baru" required />
          <input v-model="passwordForm.confirm_password" aria-label="Konfirmasi password baru" autocomplete="new-password" minlength="8" class="field" type="password" placeholder="Konfirmasi password baru" required />
          <button class="btn-primary w-full" :disabled="changingPassword">{{ changingPassword ? 'Mengganti...' : 'Ganti password' }}</button>
        </form>
      </div>

      <div v-if="role === 'writer' && activeTab === 'books'" class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <form class="panel space-y-3 p-5" @submit.prevent="saveBook">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">{{ editingBookId ? 'Edit buku' : 'Tambah buku' }}</h2>
            <button v-if="editingBookId" type="button" class="text-sm font-semibold text-leaf" @click="resetBookForm">Batal edit</button>
          </div>
          <input v-model="bookForm.title" aria-label="Judul" class="field" placeholder="Judul" required />
          <input v-model="bookForm.author" aria-label="Author" class="field" placeholder="Author" required />
          <input v-model="bookForm.publisher" aria-label="Publisher" class="field" placeholder="Publisher" />
          <div class="grid gap-3 sm:grid-cols-3">
            <input v-model.number="bookForm.year" aria-label="Year" class="field" type="number" placeholder="Year" />
            <input v-model.number="bookForm.pages" aria-label="Pages" min="0" class="field" type="number" placeholder="Pages" />
            <input v-model.number="bookForm.price" aria-label="Price" min="0" class="field" type="number" placeholder="Price" required />
          </div>
          <select v-model="bookForm.category_id" aria-label="Kategori buku" class="field">
            <option value="">Kategori</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <textarea v-model="bookForm.short_description" aria-label="Deskripsi pendek" class="field" placeholder="Deskripsi pendek" />
          <textarea v-model="bookForm.long_description" aria-label="Deskripsi lengkap" class="field" placeholder="Deskripsi lengkap" />
          <label class="btn-soft w-full cursor-pointer">
            <Upload :size="16" />
            Upload cover
            <input class="sr-only" type="file" accept="image/*" @change="uploadFile($event, 'cover')" />
          </label>
          <label class="btn-soft w-full cursor-pointer">
            <Upload :size="16" />
            Upload PDF
            <input class="sr-only" type="file" accept="application/pdf" @change="uploadFile($event, 'pdf')" />
          </label>
          <p v-if="bookForm.cover_image || bookForm.pdf_file" class="text-xs text-ink/55">
            Cover: {{ bookForm.cover_image || '-' }}<br />PDF: {{ bookForm.pdf_file || '-' }}
          </p>
          <button class="btn-primary w-full">{{ editingBookId ? 'Simpan perubahan' : 'Submit review' }}</button>
        </form>

        <div class="panel min-w-0 overflow-hidden">
          <div class="border-b border-ink/10 bg-paper/70 p-5">
            <div class="flex min-w-0 flex-col gap-3">
              <h2 class="text-xl font-semibold">Buku saya</h2>
              <div class="flex min-w-0 flex-wrap gap-2">
                <button
                  v-for="status in ['all', 'pending', 'approved', 'rejected', 'archived']"
                  :key="status"
                  class="btn-soft whitespace-nowrap px-3 py-2 capitalize"
                  :class="{ 'border-leaf text-leaf': bookStatusFilter === status }"
                  @click="bookStatusFilter = status"
                >
                  {{ status }}
                  <span class="rounded bg-ink/5 px-2 text-xs text-ink/50">{{ status === 'archived' ? archivedBookCount : (bookStatusCounts[status] || 0) }}</span>
                </button>
              </div>
            </div>
          </div>
          <BookRows :rows="filteredBooks" editable @edit="editBook" @delete="deleteBook" @restore="restoreBook" />
        </div>
      </div>

      <div v-if="role === 'writer' && activeTab === 'royalty'" class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-6">
          <div class="overflow-hidden rounded-lg bg-ink text-white shadow-soft">
            <div class="p-6">
              <p class="text-sm font-semibold uppercase tracking-[0.22em] text-white/50">Royalty balance</p>
              <h2 class="mt-2 text-3xl font-bold">{{ money(writerBalance.available_balance) }}</h2>
              <p class="mt-1 text-sm text-white/55">Saldo tersedia untuk withdrawal.</p>
            </div>
            <div class="grid gap-px bg-white/10 sm:grid-cols-3">
              <div class="bg-white/5 p-5">
                <p class="text-sm text-white/50">Pending</p>
                <strong class="mt-1 block text-xl">{{ money(writerBalance.pending_balance) }}</strong>
              </div>
              <div class="bg-white/5 p-5">
                <p class="text-sm text-white/50">Withdrawn</p>
                <strong class="mt-1 block text-xl">{{ money(writerBalance.total_withdrawn) }}</strong>
              </div>
              <div class="bg-white/5 p-5">
                <p class="text-sm text-white/50">Royalty total</p>
                <strong class="mt-1 block text-xl">{{ money(writerRoyaltyTotal) }}</strong>
              </div>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="panel p-5">
              <p class="text-sm text-ink/55">Books sold</p>
              <strong class="mt-2 block text-2xl">{{ writerBooksSold }}</strong>
            </div>
            <div class="panel p-5">
              <p class="text-sm text-ink/55">Sales records</p>
              <strong class="mt-2 block text-2xl">{{ writerSales.length }}</strong>
            </div>
          </div>

          <div class="panel overflow-hidden">
            <TableHeader title="Riwayat sales" />
            <div v-if="!writerSales.length" class="p-8 text-center text-ink/55">Belum ada penjualan.</div>
            <div v-else class="divide-y divide-ink/10">
              <div v-for="sale in writerSales" :key="sale.id" class="grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="flex gap-3">
                  <img v-if="sale.book?.cover_url" :src="sale.book.cover_url" class="h-16 w-12 rounded object-cover" :alt="sale.book?.title" />
                  <div>
                    <strong>{{ sale.book?.title || sale.title }}</strong>
                    <p class="text-sm text-ink/55">{{ sale.order?.order_number || sale.order_number }} - Qty {{ sale.quantity }}</p>
                    <p class="text-xs text-ink/45">Royalty{{ sale.royalty_percentage != null ? ' ' + sale.royalty_percentage + '%' : '' }} dari {{ money(sale.book_price) }}</p>
                  </div>
                </div>
                <strong class="text-green-700">+{{ money(sale.royalty_amount) }}</strong>
              </div>
            </div>
          </div>
        </div>

        <form class="panel space-y-3 p-5" @submit.prevent="requestWithdrawal">
          <div>
            <p class="text-sm font-semibold text-leaf">Withdrawal</p>
            <h2 class="text-xl font-semibold">Tarik saldo</h2>
            <p class="mt-1 text-sm text-ink/55">Minimal Rp100.000, maksimal Rp10.000.000.</p>
          </div>
          <div class="rounded-md bg-paper p-4">
            <div class="flex justify-between text-sm"><span>Available</span><strong>{{ money(writerBalance.available_balance) }}</strong></div>
            <div class="mt-2 flex justify-between text-sm"><span>Request amount</span><strong>{{ money(withdrawal.amount) }}</strong></div>
          </div>
          <input v-model.number="withdrawal.amount" aria-label="Amount" class="field" type="number" placeholder="Amount" />
          <input v-model="withdrawal.bank_name" aria-label="Bank" class="field" placeholder="Bank" />
          <input v-model="withdrawal.account_number" aria-label="Nomor rekening" class="field" placeholder="Nomor rekening" />
          <input v-model="withdrawal.account_name" aria-label="Nama rekening" class="field" placeholder="Nama rekening" />
          <textarea v-model="withdrawal.notes" aria-label="Catatan opsional" class="field" placeholder="Catatan opsional" />
          <p v-if="Number(withdrawal.amount || 0) > Number(writerBalance.available_balance || 0)" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            Nominal melebihi saldo tersedia.
          </p>
          <button class="btn-primary w-full" :disabled="!canRequestWithdrawal">Ajukan withdrawal</button>
        </form>
      </div>

      <div v-if="role === 'writer' && activeTab === 'withdrawals'" class="panel overflow-hidden">
        <TableHeader title="Riwayat withdrawal" />
        <WithdrawalRows :rows="filteredWithdrawals" :admin="false" />
      </div>

      <div v-if="isAdmin && activeTab === 'books'" class="panel overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-ink/10 bg-paper/70 p-5 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="text-xl font-semibold">Review buku</h2>
          <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1 sm:pb-0">
            <button
              v-for="status in ['all', 'pending', 'approved', 'rejected', 'archived']"
              :key="status"
              class="btn-soft whitespace-nowrap px-3 py-2 capitalize"
              :class="{ 'border-leaf text-leaf': bookStatusFilter === status }"
              @click="bookStatusFilter = status"
            >
              {{ status }}
              <span class="rounded bg-ink/5 px-2 text-xs text-ink/50">{{ status === 'archived' ? archivedBookCount : (bookStatusCounts[status] || 0) }}</span>
            </button>
          </div>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!filteredBooks.length" class="p-8 text-center text-ink/55">Tidak ada buku untuk filter ini.</div>
          <div v-for="book in filteredBooks" :key="book.id" class="p-5">
            <div class="grid gap-4 md:grid-cols-[72px_1fr_auto] md:items-center">
              <button class="h-24 w-16 overflow-hidden rounded-md bg-ink/5 text-left" @click="expandedBookId = expandedBookId === book.id ? null : book.id">
                <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
                <span v-else class="grid h-full place-items-center px-1 text-center text-[10px] text-ink/45">{{ book.title }}</span>
              </button>
              <button class="min-w-0 text-left" @click="expandedBookId = expandedBookId === book.id ? null : book.id">
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="line-clamp-1">{{ book.title }}</strong>
                  <span class="inline-flex rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(book.deleted_at ? 'archived' : book.status)">{{ book.deleted_at ? 'archived' : book.status }}</span>
                </div>
                <p class="mt-1 text-sm text-ink/55">{{ book.writer?.full_name }} - {{ book.author }} - {{ money(book.price) }}</p>
                <p class="mt-1 text-xs text-ink/45">{{ book.category?.name || 'Uncategorized' }} - {{ book.publisher || 'No publisher' }} - {{ book.year || '-' }}</p>
              </button>
              <div class="flex flex-wrap gap-2 md:justify-end">
                <button class="btn-soft" @click="expandedBookId = expandedBookId === book.id ? null : book.id">
                  Detail
                </button>
                <button v-if="book.deleted_at" class="btn-primary" @click="restoreBook(book)">
                  <CheckCircle2 :size="16" />
                  Restore
                </button>
                <button v-else class="btn-primary" @click="reviewBook(book, 'approved')">
                  <CheckCircle2 :size="16" />
                  Approve
                </button>
              </div>
            </div>

            <div v-if="expandedBookId === book.id" class="mt-5 rounded-md bg-paper p-4">
              <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
                <div class="space-y-4">
                  <div class="rounded-md bg-white p-4">
                    <p class="text-sm font-semibold text-leaf">Metadata</p>
                    <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                      <div><span class="text-ink/45">Pages</span><strong class="block">{{ book.pages || 0 }}</strong></div>
                      <div><span class="text-ink/45">ISBN</span><strong class="block">{{ book.isbn || '-' }}</strong></div>
                      <div><span class="text-ink/45">Views</span><strong class="block">{{ book.views || 0 }}</strong></div>
                    </div>
                  </div>
                  <div class="rounded-md bg-white p-4">
                    <p class="text-sm font-semibold text-leaf">Description</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink/65">{{ book.long_description || book.short_description || 'Belum ada deskripsi.' }}</p>
                  </div>
                  <div v-if="book.rejection_reason" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <strong>Alasan reject sebelumnya:</strong>
                    <p class="mt-1">{{ book.rejection_reason }}</p>
                  </div>
                </div>

                <div class="space-y-4">
                  <div class="rounded-md bg-white p-4">
                    <p class="text-sm font-semibold text-leaf">Files</p>
                    <div class="mt-3 grid gap-2">
                      <a v-if="book.cover_url" class="btn-soft" :href="book.cover_url" target="_blank" rel="noreferrer">
                        <ExternalLink :size="16" />
                        View cover
                      </a>
                      <RouterLink v-if="book.has_pdf" class="btn-soft" :to="`/reader/${book.id}`">
                        <Download :size="16" />
                        Open PDF
                      </RouterLink>
                      <p v-if="!book.has_pdf" class="rounded-md bg-paper p-3 text-sm text-ink/55">PDF belum tersedia.</p>
                    </div>
                  </div>

                  <form v-if="!book.deleted_at" class="rounded-md bg-white p-4" @submit.prevent="reviewBook(book, 'rejected')">
                    <p class="text-sm font-semibold text-leaf">Reject reason</p>
                    <textarea v-model="rejectionReasons[book.id]" aria-label="Alasan penolakan buku" class="field mt-3 min-h-28" placeholder="Tulis alasan agar writer tahu revisinya" />
                    <button class="btn-soft mt-3 w-full text-red-600">
                      <XCircle :size="16" />
                      Reject book
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="pageInfo('books').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('books').from }}-{{ pageInfo('books').to }} dari {{ pageInfo('books').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('books').current_page <= 1" @click="setAdminPage('books', pageInfo('books').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('books').current_page >= pageInfo('books').last_page" @click="setAdminPage('books', pageInfo('books').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'categories'" class="grid gap-6 lg:grid-cols-[380px_1fr]">
        <form class="panel space-y-3 p-5" @submit.prevent="saveCategory">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-leaf">Catalog setup</p>
              <h2 class="text-xl font-semibold">{{ editingCategoryId ? 'Edit kategori' : 'Tambah kategori' }}</h2>
            </div>
            <button v-if="editingCategoryId" type="button" class="text-sm font-semibold text-leaf" @click="resetCategoryForm">Batal</button>
          </div>
          <input v-model="categoryForm.name" aria-label="Nama kategori" class="field" placeholder="Nama kategori" required />
          <input v-model="categoryForm.slug" aria-label="Slug opsional" class="field" placeholder="Slug opsional" />
          <textarea v-model="categoryForm.description" aria-label="Deskripsi kategori" class="field min-h-28" placeholder="Deskripsi kategori" />
          <button class="btn-primary w-full">{{ editingCategoryId ? 'Simpan kategori' : 'Tambah kategori' }}</button>
          <p class="text-xs leading-5 text-ink/45">Slug akan dibuat otomatis dari nama kalau dikosongkan.</p>
        </form>

        <div class="panel overflow-hidden">
          <TableHeader title="Daftar kategori" />
          <div v-if="!filteredCategories.length" class="p-8 text-center text-ink/55">Belum ada kategori.</div>
          <div v-else class="divide-y divide-ink/10">
            <div v-for="category in filteredCategories" :key="category.id" class="grid gap-4 p-5 md:grid-cols-[1fr_auto] md:items-center">
              <div>
                <strong>{{ category.name }}</strong>
                <p class="text-sm text-ink/55">{{ category.slug }}</p>
                <p v-if="category.description" class="mt-1 line-clamp-2 text-sm text-ink/55">{{ category.description }}</p>
              </div>
              <div class="flex gap-2">
                <button class="btn-soft px-3" @click="editCategory(category)">Edit</button>
                <button class="btn-soft px-3 text-red-600" @click="deleteCategory(category)">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'orders'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px_220px_auto] lg:items-center">
          <h2 class="text-xl font-semibold">Order management</h2>
          <select v-model="orderPaymentFilter" aria-label="Filter pembayaran" class="field bg-white">
            <option value="all">Semua payment ({{ orderPaymentCounts.all || 0 }})</option>
            <option value="pending">Payment pending ({{ orderPaymentCounts.pending || 0 }})</option>
            <option value="paid">Payment paid ({{ orderPaymentCounts.paid || 0 }})</option>
            <option value="failed">Payment failed ({{ orderPaymentCounts.failed || 0 }})</option>
            <option value="refunded">Payment refunded ({{ orderPaymentCounts.refunded || 0 }})</option>
          </select>
          <select v-model="orderStatusFilter" aria-label="Filter status order" class="field bg-white">
            <option value="all">Semua order ({{ orderStatusCounts.all || 0 }})</option>
            <option value="pending">Order pending ({{ orderStatusCounts.pending || 0 }})</option>
            <option value="processing">Processing ({{ orderStatusCounts.processing || 0 }})</option>
            <option value="shipped">Shipped ({{ orderStatusCounts.shipped || 0 }})</option>
            <option value="delivered">Delivered ({{ orderStatusCounts.delivered || 0 }})</option>
            <option value="cancelled">Cancelled ({{ orderStatusCounts.cancelled || 0 }})</option>
          </select>
          <button class="btn-primary whitespace-nowrap" @click="exportReport('orders')">Export CSV</button>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!filteredOrders.length" class="p-8 text-center text-ink/55">Tidak ada order untuk filter ini.</div>
          <div v-for="order in filteredOrders" :key="order.id" class="p-5">
            <div class="grid gap-4 lg:grid-cols-[1fr_160px_280px] lg:items-center">
              <button class="text-left" @click="expandedOrderId = expandedOrderId === order.id ? null : order.id">
                <strong>{{ order.order_number }}</strong>
                <p class="text-sm text-ink/55">{{ order.customer?.full_name }} - {{ order.customer?.email }}</p>
                <p class="text-xs text-ink/45">{{ order.shipping_city }} - {{ order.shipping_method }}</p>
              </button>
              <strong>{{ money(order.total) }}</strong>
              <div class="grid gap-2 sm:grid-cols-2">
                <select class="field" :aria-label="'Pembayaran ' + order.order_number" :value="order.payment_status" @change="changeOrder(order, 'payment_status', $event.target.value)">
                  <option v-for="status in ['pending','paid','failed','refunded']" :key="status" :value="status">{{ status }}</option>
                </select>
                <select class="field" :aria-label="'Status ' + order.order_number" :value="order.order_status" @change="changeOrder(order, 'order_status', $event.target.value)">
                  <option v-for="status in ['pending','processing','shipped','delivered','cancelled']" :key="status" :value="status">{{ status }}</option>
                </select>
                <button class="btn-soft sm:col-span-2" @click="openInvoice(order)">Invoice</button>
              </div>
            </div>
            <div v-if="expandedOrderId === order.id" class="mt-4 rounded-md bg-paper p-4">
              <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><ReceiptText :size="16" /> Order</p>
                  <p class="text-sm text-ink/55">{{ dateLabel(order.created_at) }}</p>
                  <p class="mt-2 text-sm capitalize text-ink/55">{{ methodLabel(order.shipping_method) }} delivery</p>
                </div>
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><CreditCard :size="16" /> Payment</p>
                  <p class="text-sm capitalize text-ink/55">{{ methodLabel(order.payment_method) }}</p>
                  <span class="mt-2 inline-flex rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(order.payment_status)">{{ order.payment_status }}</span>
                </div>
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><MapPin :size="16" /> Customer</p>
                  <p class="font-semibold">{{ order.shipping_name || order.customer?.full_name }}</p>
                  <p class="text-sm text-ink/55">{{ order.shipping_phone || order.customer?.email }}</p>
                </div>
              </div>

              <div class="mt-4 rounded-md bg-white p-4">
                <p class="text-sm font-semibold text-leaf">Shipping address</p>
                <p class="mt-2 text-sm leading-6 text-ink/65">
                  {{ order.shipping_address }}<br />
                  {{ order.shipping_city }}, {{ order.shipping_postal_code }}
                </p>
              </div>

              <div class="mt-4 rounded-md bg-white p-4">
                <p class="mb-3 text-sm font-semibold text-leaf">Order items</p>
                <div v-for="item in order.items || []" :key="item.id" class="grid gap-3 border-t border-ink/10 py-3 text-sm first:border-t-0 sm:grid-cols-[1fr_auto]">
                  <div class="flex gap-3">
                    <img v-if="item.book?.cover_url" :src="item.book.cover_url" class="h-16 w-12 rounded object-cover" :alt="item.book?.title" />
                    <div>
                      <p class="font-semibold">{{ item.book?.title || item.title }}</p>
                      <p class="text-ink/55">{{ item.book?.author }} - Qty {{ item.quantity }} x {{ money(item.price) }}</p>
                    </div>
                  </div>
                  <strong class="sm:text-right">{{ money(item.subtotal) }}</strong>
                </div>
              </div>

              <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_280px]">
                <div class="rounded-md bg-white p-4">
                  <p class="text-sm font-semibold text-leaf">Notes</p>
                  <p class="mt-2 text-sm leading-6 text-ink/60">{{ order.notes || 'Tidak ada catatan.' }}</p>
                </div>
                <div class="rounded-md bg-white p-4">
                  <div class="flex justify-between text-sm"><span>Subtotal</span><strong>{{ money(order.subtotal) }}</strong></div>
                  <div class="mt-2 flex justify-between text-sm"><span>Tax</span><strong>{{ money(order.tax) }}</strong></div>
                  <div class="mt-2 flex justify-between text-sm"><span>Shipping</span><strong>{{ money(order.shipping_cost) }}</strong></div>
                  <div class="mt-3 flex justify-between border-t border-ink/10 pt-3 text-base"><span>Total</span><strong>{{ money(order.total) }}</strong></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="pageInfo('orders').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('orders').from }}-{{ pageInfo('orders').to }} dari {{ pageInfo('orders').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('orders').current_page <= 1" @click="setAdminPage('orders', pageInfo('orders').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('orders').current_page >= pageInfo('orders').last_page" @click="setAdminPage('orders', pageInfo('orders').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'withdrawals'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px_auto] lg:items-center">
          <h2 class="text-xl font-semibold">Withdrawal management</h2>
          <select v-model="withdrawalStatusFilter" aria-label="Filter withdrawal" class="field bg-white">
            <option value="all">Semua status ({{ withdrawalStatusCounts.all || 0 }})</option>
            <option value="pending">Pending ({{ withdrawalStatusCounts.pending || 0 }})</option>
            <option value="approved">Approved ({{ withdrawalStatusCounts.approved || 0 }})</option>
            <option value="processing">Processing ({{ withdrawalStatusCounts.processing || 0 }})</option>
            <option value="completed">Completed ({{ withdrawalStatusCounts.completed || 0 }})</option>
            <option value="rejected">Rejected ({{ withdrawalStatusCounts.rejected || 0 }})</option>
          </select>
          <button class="btn-primary whitespace-nowrap" @click="exportReport('withdrawals')">Export CSV</button>
        </div>
        <WithdrawalRows :rows="filteredWithdrawals" :admin="true" :reasons="withdrawalRejectionReasons" @process="processWithdrawal" />
        <div v-if="pageInfo('withdrawals').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('withdrawals').from }}-{{ pageInfo('withdrawals').to }} dari {{ pageInfo('withdrawals').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('withdrawals').current_page <= 1" @click="setAdminPage('withdrawals', pageInfo('withdrawals').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('withdrawals').current_page >= pageInfo('withdrawals').last_page" @click="setAdminPage('withdrawals', pageInfo('withdrawals').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'users'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px_220px] lg:items-center">
          <h2 class="text-xl font-semibold">User management</h2>
          <select v-model="userRoleFilter" aria-label="Filter role pengguna" class="field bg-white">
            <option value="all">Semua role ({{ userRoleCounts.all || 0 }})</option>
            <option value="super_admin">Super Admin ({{ userRoleCounts.super_admin || 0 }})</option>
            <option value="admin">Admin ({{ userRoleCounts.admin || 0 }})</option>
            <option value="writer">Writer ({{ userRoleCounts.writer || 0 }})</option>
            <option value="customer">Customer ({{ userRoleCounts.customer || 0 }})</option>
          </select>
          <select v-model="userStatusFilter" aria-label="Filter status pengguna" class="field bg-white">
            <option value="all">Semua status ({{ userStatusCounts.all || 0 }})</option>
            <option value="active">Active ({{ userStatusCounts.active || 0 }})</option>
            <option value="inactive">Inactive ({{ userStatusCounts.inactive || 0 }})</option>
            <option value="suspended">Suspended ({{ userStatusCounts.suspended || 0 }})</option>
          </select>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!filteredUsers.length" class="p-8 text-center text-ink/55">Tidak ada user untuk filter ini.</div>
          <div v-for="user in filteredUsers" :key="user.id" class="grid gap-4 p-5 lg:grid-cols-[1fr_180px_180px] lg:items-center">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ user.full_name }}</strong>
                <span v-if="user.id === auth.user?.id" class="rounded bg-leaf/10 px-2 py-1 text-xs font-semibold text-leaf">Akun kamu</span>
              </div>
              <p class="text-sm text-ink/55">{{ user.username }} - {{ user.email }}</p>
            </div>
            <select class="field" :aria-label="'Role ' + user.username" :value="user.role" :disabled="user.id === auth.user?.id || auth.user?.role !== 'super_admin'" @change="updateUser(user, 'role', $event.target.value)">
              <option value="super_admin">super_admin</option>
              <option value="admin">admin</option>
              <option value="writer">writer</option>
              <option value="customer">customer</option>
            </select>
            <select class="field" :aria-label="'Status ' + user.username" :value="user.status" :disabled="user.id === auth.user?.id" @change="updateUser(user, 'status', $event.target.value)">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
              <option value="suspended">suspended</option>
            </select>
          </div>
        </div>
        <div v-if="pageInfo('users').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('users').from }}-{{ pageInfo('users').to }} dari {{ pageInfo('users').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('users').current_page <= 1" @click="setAdminPage('users', pageInfo('users').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('users').current_page >= pageInfo('users').last_page" @click="setAdminPage('users', pageInfo('users').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'writerRequests'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px] lg:items-center">
          <div>
            <h2 class="text-xl font-semibold">Writer requests</h2>
            <p class="mt-1 text-sm text-ink/55">Customer yang ingin upgrade menjadi writer.</p>
          </div>
          <select v-model="writerRequestStatusFilter" aria-label="Filter pengajuan writer" class="field bg-white">
            <option value="all">All ({{ writerRequestCounts.all || 0 }})</option>
            <option value="pending">Pending ({{ writerRequestCounts.pending || 0 }})</option>
            <option value="approved">Approved ({{ writerRequestCounts.approved || 0 }})</option>
            <option value="rejected">Rejected ({{ writerRequestCounts.rejected || 0 }})</option>
          </select>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!filteredWriterRequests.length" class="p-8 text-center text-ink/55">Belum ada writer request.</div>
          <div v-for="request in filteredWriterRequests" :key="request.id" class="grid gap-4 p-5 xl:grid-cols-[1fr_220px_320px] xl:items-start">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ request.pen_name }}</strong>
                <span class="rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(request.status)">{{ request.status }}</span>
              </div>
              <p class="mt-1 text-sm text-ink/55">{{ request.user?.full_name }} - {{ request.user?.email }}</p>
              <div class="mt-3 grid gap-3 text-sm text-ink/65 md:grid-cols-2">
                <div class="rounded-md bg-paper p-3">
                  <p class="font-semibold text-ink">Bio</p>
                  <p class="mt-1 line-clamp-4">{{ request.bio }}</p>
                </div>
                <div class="rounded-md bg-paper p-3">
                  <p class="font-semibold text-ink">Reason</p>
                  <p class="mt-1 line-clamp-4">{{ request.reason }}</p>
                </div>
              </div>
              <a v-if="request.portfolio_url" class="mt-3 inline-flex text-sm font-semibold text-leaf" :href="request.portfolio_url" target="_blank" rel="noreferrer">Portfolio</a>
              <p v-if="request.rejection_reason" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ request.rejection_reason }}</p>
            </div>
            <div class="text-sm text-ink/55">
              <p>Diajukan</p>
              <strong class="text-ink">{{ dateLabel(request.created_at) }}</strong>
              <p v-if="request.reviewed_at" class="mt-3">Direview</p>
              <strong v-if="request.reviewed_at" class="text-ink">{{ dateLabel(request.reviewed_at) }}</strong>
            </div>
            <div v-if="request.status === 'pending'" class="space-y-3">
              <div class="flex flex-wrap gap-2">
                <button class="btn-primary px-3" @click="processWriterRequest(request, 'approved')">
                  <CheckCircle2 :size="16" />
                  Approve
                </button>
              </div>
              <form class="space-y-2" @submit.prevent="processWriterRequest(request, 'rejected')">
                <textarea v-model="writerRequestRejectionReasons[request.id]" aria-label="Alasan penolakan writer" class="field min-h-20" placeholder="Alasan reject request" />
                <button class="btn-soft w-full text-red-600">
                  <XCircle :size="16" />
                  Reject request
                </button>
              </form>
            </div>
            <span v-else class="w-fit rounded bg-paper px-3 py-2 text-sm font-semibold capitalize">{{ request.status }}</span>
          </div>
        </div>
        <div v-if="pageInfo('writerRequests').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('writerRequests').from }}-{{ pageInfo('writerRequests').to }} dari {{ pageInfo('writerRequests').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('writerRequests').current_page <= 1" @click="setAdminPage('writerRequests', pageInfo('writerRequests').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('writerRequests').current_page >= pageInfo('writerRequests').last_page" @click="setAdminPage('writerRequests', pageInfo('writerRequests').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'reviews'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px] lg:items-center">
          <div>
            <h2 class="text-xl font-semibold">Review moderation</h2>
            <p class="mt-1 text-sm text-ink/55">Sembunyikan review bermasalah tanpa menghapus histori.</p>
          </div>
          <select v-model="reviewStatusFilter" aria-label="Filter review" class="field bg-white">
            <option value="all">All ({{ reviewCounts.all || 0 }})</option>
            <option value="visible">Visible ({{ reviewCounts.visible || 0 }})</option>
            <option value="hidden">Hidden ({{ reviewCounts.hidden || 0 }})</option>
          </select>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!filteredReviews.length" class="p-8 text-center text-ink/55">Belum ada review.</div>
          <div v-for="review in filteredReviews" :key="review.id" class="grid gap-4 p-5 lg:grid-cols-[1fr_320px] lg:items-start">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ review.title || 'Review tanpa judul' }}</strong>
                <span class="rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(review.status)">{{ review.status }}</span>
                <span class="rounded bg-honey/20 px-2 py-1 text-xs font-semibold text-ink">{{ review.rating }} star</span>
              </div>
              <p class="mt-1 text-sm text-ink/55">{{ review.customer?.full_name }} - {{ review.book?.title }}</p>
              <p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink/70">{{ review.content }}</p>
              <p v-if="review.moderation_reason" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ review.moderation_reason }}</p>
            </div>
            <div class="space-y-3">
              <button v-if="review.status === 'hidden'" class="btn-primary px-3" @click="moderateReview(review, 'visible')">Show review</button>
              <form v-else class="space-y-2" @submit.prevent="moderateReview(review, 'hidden')">
                <textarea v-model="reviewModerationReasons[review.id]" aria-label="Alasan menyembunyikan review" class="field min-h-20" placeholder="Alasan hide review" />
                <button class="btn-soft w-full text-red-600">
                  <XCircle :size="16" />
                  Hide review
                </button>
              </form>
            </div>
          </div>
        </div>
        <div v-if="pageInfo('reviews').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('reviews').from }}-{{ pageInfo('reviews').to }} dari {{ pageInfo('reviews').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('reviews').current_page <= 1" @click="setAdminPage('reviews', pageInfo('reviews').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('reviews').current_page >= pageInfo('reviews').last_page" @click="setAdminPage('reviews', pageInfo('reviews').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'articles'" class="grid gap-6 xl:grid-cols-[420px_1fr]">
        <form class="panel h-fit space-y-3 p-5" @submit.prevent="saveArticle">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-leaf">Article CMS</p>
              <h2 class="text-xl font-semibold">{{ editingArticleId ? 'Edit artikel' : 'Buat artikel' }}</h2>
            </div>
            <button v-if="editingArticleId" class="text-sm font-semibold text-leaf" type="button" @click="resetArticleForm">Batal</button>
          </div>
          <input v-model="articleForm.title" aria-label="Judul artikel" class="field" placeholder="Judul artikel" required />
          <input v-model="articleForm.slug" aria-label="Slug artikel opsional" class="field" placeholder="Slug opsional" />
          <textarea v-model="articleForm.excerpt" aria-label="Ringkasan pendek" class="field min-h-20" placeholder="Ringkasan pendek" />
          <textarea v-model="articleForm.content" aria-label="Isi artikel / panduan" class="field min-h-56" placeholder="Isi artikel / panduan" required />
          <select v-model="articleForm.status" aria-label="Status artikel" class="field">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
          <label class="btn-soft w-full cursor-pointer">
            <Upload :size="16" />
            Upload cover artikel
            <input class="sr-only" type="file" accept="image/*" @change="uploadArticleCover" />
          </label>
          <p v-if="articleForm.cover_image" class="rounded-md bg-paper p-3 text-xs text-ink/55">Cover: {{ articleForm.cover_image }}</p>
          <button class="btn-primary w-full">{{ editingArticleId ? 'Simpan artikel' : 'Publish / simpan draft' }}</button>
        </form>

        <div class="panel overflow-hidden">
          <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px] lg:items-center">
            <div>
              <h2 class="text-xl font-semibold">Artikel dan panduan</h2>
              <p class="mt-1 text-sm text-ink/55">Kelola blog, pengumuman, dan konten edukasi website.</p>
            </div>
            <select v-model="articleStatusFilter" aria-label="Filter status artikel" class="field bg-white">
              <option value="all">All ({{ articleCounts.all || 0 }})</option>
              <option value="draft">Draft ({{ articleCounts.draft || 0 }})</option>
              <option value="published">Published ({{ articleCounts.published || 0 }})</option>
            </select>
          </div>
          <div class="divide-y divide-ink/10">
            <div v-if="!filteredArticles.length" class="p-8 text-center text-ink/55">Belum ada artikel.</div>
            <div v-for="article in filteredArticles" :key="article.id" class="grid gap-4 p-5 lg:grid-cols-[96px_1fr_auto] lg:items-center">
              <div class="h-24 w-24 overflow-hidden rounded-xl bg-leaf/5">
                <img v-if="article.cover_url" :src="article.cover_url" class="h-full w-full object-cover" :alt="article.title" />
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="line-clamp-1">{{ article.title }}</strong>
                  <span class="rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(article.status)">{{ article.status }}</span>
                </div>
                <p class="mt-1 text-sm text-ink/55">/{{ article.slug }} - {{ article.author?.full_name || 'Admin' }}</p>
                <p class="mt-2 line-clamp-2 text-sm text-ink/60">{{ article.excerpt || article.content }}</p>
              </div>
              <div class="flex flex-wrap gap-2 lg:justify-end">
                <RouterLink v-if="article.status === 'published'" class="btn-soft px-3" :to="`/articles/${article.slug}`">View</RouterLink>
                <button class="btn-soft px-3" @click="editArticle(article)">Edit</button>
                <button class="btn-soft px-3 text-red-600" @click="deleteArticle(article)">Delete</button>
              </div>
            </div>
          </div>
          <div v-if="pageInfo('articles').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
            <span>Menampilkan {{ pageInfo('articles').from }}-{{ pageInfo('articles').to }} dari {{ pageInfo('articles').total }}</span>
            <div class="flex gap-2">
              <button class="btn-soft" :disabled="pageInfo('articles').current_page <= 1" @click="setAdminPage('articles', pageInfo('articles').current_page - 1)">Prev</button>
              <button class="btn-soft" :disabled="pageInfo('articles').current_page >= pageInfo('articles').last_page" @click="setAdminPage('articles', pageInfo('articles').current_page + 1)">Next</button>
            </div>
          </div>
        </div>
      </div>

      <form v-if="isAdmin && activeTab === 'settings'" class="panel space-y-4 p-5" @submit.prevent="saveSettings">
        <div class="border-b border-ink/10 pb-4">
          <h2 class="text-xl font-semibold">System settings</h2>
          <p class="mt-1 text-sm text-ink/55">Hanya super admin yang bisa menyimpan perubahan settings.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label class="space-y-1">
            <span class="text-sm font-semibold text-ink/60">Platform name</span>
            <input v-model="settings.platform_name" class="field" />
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-ink/60">Royalty %</span>
            <input v-model.number="settings.royalty_percentage" class="field" type="number" min="1" max="95" />
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-ink/60">Tax rate</span>
            <input v-model.number="settings.tax_rate" class="field" type="number" min="0" max="1" step="0.01" />
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-ink/60">Minimum withdrawal</span>
            <input v-model.number="settings.minimum_withdrawal" class="field" type="number" min="0" />
          </label>
          <label class="space-y-1">
            <span class="text-sm font-semibold text-ink/60">Maximum withdrawal</span>
            <input v-model.number="settings.maximum_withdrawal" class="field" type="number" min="0" />
          </label>
          <label class="flex min-h-10 items-center gap-3 rounded-md border border-ink/10 bg-white px-3 py-2 text-sm font-semibold">
            <input v-model="settings.checkout_auto_paid" type="checkbox" />
            Checkout auto-paid
          </label>
        </div>
        <button class="btn-primary" :disabled="auth.user?.role !== 'super_admin'">Simpan settings</button>
      </form>

      <div v-if="isAdmin && activeTab === 'sales'" class="panel overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-ink/10 bg-paper/70 p-5 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="text-xl font-semibold">Sales and royalty</h2>
          <button class="btn-primary" @click="exportReport('sales')">Export CSV</button>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!sales.length" class="p-8 text-center text-ink/55">Belum ada sales.</div>
          <div v-for="sale in sales" :key="sale.id" class="grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-center">
            <div>
              <strong>{{ sale.book?.title }}</strong>
              <p class="text-sm text-ink/55">{{ sale.book?.writer?.full_name }} - {{ sale.order?.order_number }}</p>
            </div>
            <span>Qty {{ sale.quantity }}</span>
            <strong class="text-green-700">{{ money(sale.royalty_amount) }}</strong>
          </div>
        </div>
        <div v-if="pageInfo('sales').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('sales').from }}-{{ pageInfo('sales').to }} dari {{ pageInfo('sales').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('sales').current_page <= 1" @click="setAdminPage('sales', pageInfo('sales').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('sales').current_page >= pageInfo('sales').last_page" @click="setAdminPage('sales', pageInfo('sales').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="isAdmin && activeTab === 'audit'" class="panel overflow-hidden">
        <div class="grid gap-3 border-b border-ink/10 bg-paper/70 p-5 lg:grid-cols-[1fr_220px_160px_160px_auto] lg:items-center">
          <h2 class="text-xl font-semibold">Audit log admin</h2>
          <select v-model="auditActionFilter" aria-label="Filter aksi audit" class="field bg-white">
            <option value="all">Semua action ({{ auditActionCounts.all || 0 }})</option>
            <option value="book.review">Book review ({{ auditActionCounts['book.review'] || 0 }})</option>
            <option value="book.archive">Book archive ({{ auditActionCounts['book.archive'] || 0 }})</option>
            <option value="book.restore">Book restore ({{ auditActionCounts['book.restore'] || 0 }})</option>
            <option value="category.create">Category create ({{ auditActionCounts['category.create'] || 0 }})</option>
            <option value="category.update">Category update ({{ auditActionCounts['category.update'] || 0 }})</option>
            <option value="category.delete">Category delete ({{ auditActionCounts['category.delete'] || 0 }})</option>
            <option value="order.status_update">Order status ({{ auditActionCounts['order.status_update'] || 0 }})</option>
            <option value="user.role_update">User role ({{ auditActionCounts['user.role_update'] || 0 }})</option>
            <option value="user.status_update">User status ({{ auditActionCounts['user.status_update'] || 0 }})</option>
            <option value="withdrawal.process">Withdrawal ({{ auditActionCounts['withdrawal.process'] || 0 }})</option>
          </select>
          <input v-model="auditDateFrom" aria-label="Audit dari tanggal" class="field bg-white" type="date" />
          <input v-model="auditDateTo" aria-label="Audit sampai tanggal" class="field bg-white" type="date" />
          <button class="btn-primary whitespace-nowrap" @click="exportReport('audit-logs')">Export CSV</button>
        </div>
        <div class="divide-y divide-ink/10">
          <div v-if="!auditLogs.length" class="p-8 text-center text-ink/55">Belum ada audit log.</div>
          <div v-for="log in auditLogs" :key="log.id" class="grid gap-3 p-5 lg:grid-cols-[1fr_auto] lg:items-start">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ log.action }}</strong>
                <span class="rounded bg-paper px-2 py-1 text-xs font-semibold text-ink/55">{{ classBasename(log.target_type) }} #{{ log.target_id }}</span>
              </div>
              <p class="mt-1 text-sm text-ink/55">{{ log.actor?.full_name || 'System' }} - {{ log.actor?.email || '-' }}</p>
              <p class="mt-2 text-sm text-ink/65">{{ auditSummary(log) }}</p>
            </div>
            <div class="flex items-center gap-3 lg:flex-col lg:items-end">
              <span class="text-sm text-ink/45">{{ dateLabel(log.created_at) }}</span>
              <button class="btn-soft px-3" type="button" @click="selectedAuditLog = log">Detail</button>
            </div>
          </div>
        </div>
        <div v-if="pageInfo('audit').last_page > 1" class="flex flex-col gap-3 border-t border-ink/10 p-4 text-sm text-ink/55 sm:flex-row sm:items-center sm:justify-between">
          <span>Menampilkan {{ pageInfo('audit').from }}-{{ pageInfo('audit').to }} dari {{ pageInfo('audit').total }}</span>
          <div class="flex gap-2">
            <button class="btn-soft" :disabled="pageInfo('audit').current_page <= 1" @click="setAdminPage('audit', pageInfo('audit').current_page - 1)">Prev</button>
            <button class="btn-soft" :disabled="pageInfo('audit').current_page >= pageInfo('audit').last_page" @click="setAdminPage('audit', pageInfo('audit').current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <div v-if="selectedAuditLog" class="fixed inset-0 z-40 grid place-items-center bg-ink/45 p-4">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-md bg-white shadow-soft">
          <div class="flex items-start justify-between gap-4 border-b border-ink/10 bg-paper/70 p-5">
            <div>
              <p class="text-sm font-semibold text-leaf">Audit detail</p>
              <h2 class="mt-1 text-xl font-semibold">{{ selectedAuditLog.action }}</h2>
              <p class="mt-1 text-sm text-ink/55">{{ classBasename(selectedAuditLog.target_type) }} #{{ selectedAuditLog.target_id }} - {{ dateLabel(selectedAuditLog.created_at) }}</p>
            </div>
            <button class="btn-soft px-3" type="button" @click="closeAuditDetail">Tutup</button>
          </div>
          <div class="max-h-[70vh] space-y-5 overflow-y-auto p-5">
            <div class="grid gap-3 text-sm sm:grid-cols-2">
              <div class="rounded-md bg-paper p-4">
                <p class="text-ink/45">Actor</p>
                <strong class="mt-1 block">{{ selectedAuditLog.actor?.full_name || 'System' }}</strong>
                <span class="text-ink/55">{{ selectedAuditLog.actor?.email || '-' }}</span>
              </div>
              <div class="rounded-md bg-paper p-4">
                <p class="text-ink/45">Request</p>
                <strong class="mt-1 block">{{ selectedAuditLog.ip_address || '-' }}</strong>
                <span class="line-clamp-1 text-ink/55">{{ selectedAuditLog.user_agent || '-' }}</span>
              </div>
            </div>

            <div v-if="auditDiffRows(selectedAuditLog).length" class="overflow-hidden rounded-md border border-ink/10">
              <div class="grid grid-cols-[120px_1fr_1fr] bg-paper px-4 py-3 text-xs font-semibold text-ink/45">
                <span>Field</span>
                <span>Before</span>
                <span>After</span>
              </div>
              <div v-for="row in auditDiffRows(selectedAuditLog)" :key="row.key" class="grid grid-cols-[120px_1fr_1fr] gap-3 border-t border-ink/10 px-4 py-3 text-sm">
                <strong class="break-words">{{ row.key }}</strong>
                <span class="break-words text-ink/55">{{ row.before }}</span>
                <span class="break-words" :class="row.changed ? 'font-semibold text-leaf' : 'text-ink/55'">{{ row.after }}</span>
              </div>
            </div>

            <div v-if="auditMetadataRows(selectedAuditLog).length" class="rounded-md border border-ink/10 p-4">
              <h3 class="font-semibold">Metadata</h3>
              <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div v-for="row in auditMetadataRows(selectedAuditLog)" :key="row.key" class="rounded-md bg-paper p-3">
                  <p class="text-ink/45">{{ row.key }}</p>
                  <strong class="mt-1 block break-words text-ink/75">{{ row.value }}</strong>
                </div>
              </div>
            </div>

            <pre v-if="selectedAuditLog.metadata" class="max-h-52 overflow-auto rounded-md bg-ink p-4 text-xs text-white/80">{{ JSON.stringify(selectedAuditLog.metadata, null, 2) }}</pre>
          </div>
        </div>
      </div>

      <div v-if="(role === 'customer' || role === 'writer') && activeTab === 'library'" class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
          <h2 class="text-xl font-semibold">Library saya</h2>
          <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-ink/65">{{ library.length }} buku</span>
        </div>
        <div v-if="!library.length" class="p-8 text-center text-ink/55">
          Belum ada buku di library.
          <RouterLink to="/" class="mt-3 inline-flex font-semibold text-leaf">Cari buku dulu</RouterLink>
        </div>
        <div v-else class="space-y-5 p-5">
          <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-md bg-paper p-4">
              <p class="text-sm text-ink/55">Owned</p>
              <strong class="mt-1 block text-2xl">{{ libraryStats.total }}</strong>
            </div>
            <div class="rounded-md bg-paper p-4">
              <p class="text-sm text-ink/55">Reviewed</p>
              <strong class="mt-1 block text-2xl">{{ libraryStats.reviewed }}</strong>
            </div>
            <div class="rounded-md bg-paper p-4">
              <p class="text-sm text-ink/55">Need review</p>
              <strong class="mt-1 block text-2xl">{{ libraryStats.unreviewed }}</strong>
            </div>
            <div class="rounded-md bg-paper p-4">
              <p class="text-sm text-ink/55">Downloads</p>
              <strong class="mt-1 block text-2xl">{{ libraryStats.downloads }}</strong>
            </div>
          </div>

          <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
            <button
              v-for="item in [
                ['all', 'All'],
                ['unreviewed', 'Need review'],
                ['reviewed', 'Reviewed'],
                ['downloadable', 'Has PDF'],
              ]"
              :key="item[0]"
              class="btn-soft whitespace-nowrap"
              :class="{ 'border-leaf text-leaf': libraryFilter === item[0] }"
              @click="libraryFilter = item[0]"
            >
              {{ item[1] }}
            </button>
          </div>

          <div v-if="!filteredLibrary.length" class="rounded-md bg-paper p-8 text-center text-ink/55">Tidak ada buku untuk filter ini.</div>
          <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="book in filteredLibrary" :key="book.id" class="overflow-hidden rounded-md border border-ink/10 bg-white">
              <RouterLink :to="bookPath(book)" class="relative block aspect-[4/5] bg-ink/5">
                <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
                <div v-else class="grid h-full place-items-center p-4 text-center text-sm text-ink/50">{{ book.title }}</div>
                <span class="absolute right-3 top-3 rounded bg-green-600 px-2 py-1 text-xs font-semibold text-white">Owned</span>
                <span v-if="book.has_reviewed" class="absolute right-3 top-11 rounded bg-honey px-2 py-1 text-xs font-semibold text-ink">Reviewed</span>
              </RouterLink>
              <div class="space-y-3 p-4">
                <div>
                  <p class="text-xs font-semibold text-leaf">{{ book.category?.name || 'Book' }}</p>
                  <h3 class="mt-1 line-clamp-2 min-h-12 font-semibold">{{ book.title }}</h3>
                  <p class="text-sm text-ink/55">{{ book.author }}</p>
                </div>
                <div class="rounded-md bg-paper p-3 text-xs text-ink/55">
                  <p>{{ book.order_number }}</p>
                  <p>{{ book.has_reviewed ? 'Review sudah ditulis' : 'Belum review' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <RouterLink v-if="book.has_pdf" class="btn-primary" :to="`/reader/${book.id}`">
                    <ExternalLink :size="16" />
                    Read
                  </RouterLink>
                  <button v-if="book.has_pdf" class="btn-soft" type="button" :disabled="downloading !== null" @click="download(book)">
                    <Download :size="16" />
                    PDF
                  </button>
                  <RouterLink class="btn-soft col-span-2" :to="bookPath(book)">{{ book.has_reviewed ? 'Detail review' : 'Tulis review' }}</RouterLink>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>

      <div v-if="(role === 'customer' || role === 'writer') && activeTab === 'wishlist'" class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
          <h2 class="text-xl font-semibold">Wishlist</h2>
          <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-ink/65">{{ wishlist.length }} buku</span>
        </div>
        <div v-if="!wishlist.length" class="p-8 text-center text-ink/55">
          Wishlist masih kosong.
          <RouterLink to="/" class="mt-3 inline-flex font-semibold text-leaf">Cari buku</RouterLink>
        </div>
        <div v-else class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
          <article v-for="book in wishlist" :key="book.id" class="overflow-hidden rounded-md border border-ink/10 bg-white">
            <RouterLink :to="bookPath(book)" class="relative block aspect-[4/5] bg-ink/5">
              <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
              <div v-else class="grid h-full place-items-center p-4 text-center text-sm text-ink/50">{{ book.title }}</div>
            </RouterLink>
            <div class="space-y-3 p-4">
              <div>
                <p class="text-xs font-semibold text-leaf">{{ book.category?.name || 'Book' }}</p>
                <h3 class="mt-1 line-clamp-2 min-h-12 font-semibold">{{ book.title }}</h3>
                <p class="text-sm text-ink/55">{{ book.author }}</p>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <button class="btn-primary" @click="cart.add(book)">Cart</button>
                <button class="btn-soft text-red-600" @click="removeWishlist(book)">Remove</button>
              </div>
            </div>
          </article>
        </div>
      </div>

      <div v-if="(role === 'customer' || role === 'writer') && activeTab === 'orders'" class="panel overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
          <h2 class="text-xl font-semibold">Order saya</h2>
          <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-ink/65">{{ orders.length }} order</span>
        </div>
        <div v-if="!orders.length" class="p-8 text-center text-ink/55">Belum ada order.</div>
        <div v-else class="divide-y divide-ink/10">
          <div v-for="order in filteredOrders" :key="order.id" class="p-5">
            <div class="grid w-full gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
              <button class="text-left" @click="expandedOrderId = expandedOrderId === order.id ? null : order.id">
                <strong>{{ order.order_number }}</strong>
                <p class="text-sm text-ink/55">{{ dateLabel(order.created_at) }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                  <span class="inline-flex rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(order.payment_status)">{{ order.payment_status }}</span>
                  <span class="inline-flex rounded px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(order.order_status)">{{ order.order_status }}</span>
                </div>
              </button>
              <div class="text-left sm:text-right">
                <strong>{{ money(order.total) }}</strong>
                <button class="btn-soft mt-2 px-3 py-2" type="button" @click="openInvoice(order)">Invoice</button>
              </div>
            </div>
            <div v-if="expandedOrderId === order.id" class="mt-4 rounded-md bg-paper p-4">
              <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><CalendarDays :size="16" /> Order date</p>
                  <p class="text-sm text-ink/60">{{ dateLabel(order.created_at) }}</p>
                </div>
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><CreditCard :size="16" /> Payment</p>
                  <p class="text-sm capitalize text-ink/60">{{ methodLabel(order.payment_method) }}</p>
                </div>
                <div class="rounded-md bg-white p-4">
                  <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-leaf"><MapPin :size="16" /> Delivery</p>
                  <p class="text-sm capitalize text-ink/60">{{ methodLabel(order.shipping_method) }}</p>
                </div>
              </div>

              <div class="mt-4 rounded-md bg-white p-4">
                <p class="text-sm font-semibold text-leaf">Alamat</p>
                <p class="mt-2 text-sm leading-6 text-ink/65">
                  {{ order.shipping_name }} - {{ order.shipping_phone }}<br />
                  {{ order.shipping_address }}<br />
                  {{ order.shipping_city }}, {{ order.shipping_postal_code }}
                </p>
              </div>

              <div class="mt-4 rounded-md bg-white p-4">
                <p class="mb-3 text-sm font-semibold text-leaf">Buku</p>
                <div v-for="item in order.items || []" :key="item.id" class="grid gap-3 border-t border-ink/10 py-3 text-sm first:border-t-0 sm:grid-cols-[1fr_auto] sm:items-center">
                  <div class="flex gap-3">
                    <img v-if="item.book?.cover_url" :src="item.book.cover_url" class="h-16 w-12 rounded object-cover" :alt="item.book?.title" />
                    <div>
                      <p class="font-semibold">{{ item.book?.title || item.title }}</p>
                      <p class="text-ink/55">Qty {{ item.quantity }} x {{ money(item.price) }}</p>
                    </div>
                  </div>
                  <div class="flex flex-wrap gap-2 sm:justify-end">
                    <RouterLink v-if="order.payment_status === 'paid' && item.book?.has_pdf" class="btn-primary px-3 py-2" :to="`/reader/${item.book.id}`">
                      <ExternalLink :size="15" />
                      Read
                    </RouterLink>
                    <button v-if="order.payment_status === 'paid' && item.book?.has_pdf" class="btn-soft px-3 py-2" type="button" :disabled="downloading !== null" @click="download(item.book)">
                      <Download :size="15" />
                      PDF
                    </button>
                    <strong class="flex items-center">{{ money(item.subtotal) }}</strong>
                  </div>
                </div>
              </div>

              <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_280px]">
                <div class="rounded-md bg-white p-4">
                  <p class="text-sm font-semibold text-leaf">Catatan</p>
                  <p class="mt-2 text-sm leading-6 text-ink/60">{{ order.notes || 'Tidak ada catatan.' }}</p>
                </div>
                <div class="rounded-md bg-white p-4">
                  <div class="flex justify-between text-sm"><span>Subtotal</span><strong>{{ money(order.subtotal) }}</strong></div>
                  <div class="mt-2 flex justify-between text-sm"><span>Tax</span><strong>{{ money(order.tax) }}</strong></div>
                  <div class="mt-2 flex justify-between text-sm"><span>Shipping</span><strong>{{ money(order.shipping_cost) }}</strong></div>
                  <div class="mt-3 flex justify-between border-t border-ink/10 pt-3 text-base"><span>Total</span><strong>{{ money(order.total) }}</strong></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="role === 'customer' && activeTab === 'writerRequest'" class="grid gap-6 lg:grid-cols-[1fr_420px]">
        <section class="panel overflow-hidden">
          <div class="border-b border-ink/10 bg-paper/70 p-5">
            <h2 class="text-xl font-semibold">Menjadi Writer</h2>
            <p class="mt-1 text-sm text-ink/55">Ajukan akses writer agar kamu bisa upload dan menjual buku di Zaimedia.</p>
          </div>
          <div class="space-y-4 p-5">
            <div v-if="writerRequestStatus" class="rounded-md border p-4" :class="statusClass(writerRequestStatus.status)">
              <div class="flex flex-wrap items-center gap-2">
                <strong>Status request: {{ writerRequestStatus.status }}</strong>
                <span class="rounded bg-white/70 px-2 py-1 text-xs">{{ dateLabel(writerRequestStatus.created_at) }}</span>
              </div>
              <p class="mt-2 text-sm">Pen name: {{ writerRequestStatus.pen_name }}</p>
              <p v-if="writerRequestStatus.rejection_reason" class="mt-2 rounded-md bg-white/70 p-3 text-sm">
                {{ writerRequestStatus.rejection_reason }}
              </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div class="rounded-md bg-paper p-4">
                <p class="text-sm text-ink/55">Step 1</p>
                <strong class="mt-1 block">Apply</strong>
              </div>
              <div class="rounded-md bg-paper p-4">
                <p class="text-sm text-ink/55">Step 2</p>
                <strong class="mt-1 block">Admin review</strong>
              </div>
              <div class="rounded-md bg-paper p-4">
                <p class="text-sm text-ink/55">Step 3</p>
                <strong class="mt-1 block">Upload buku</strong>
              </div>
            </div>

            <p class="text-sm leading-6 text-ink/65">
              Setelah request diapprove, akun kamu berubah menjadi writer. Kamu tetap bisa membeli buku penulis lain, punya Library dan Orders, tetapi tidak bisa membeli atau review buku sendiri.
            </p>
          </div>
        </section>

        <form class="panel h-fit space-y-3 p-5" @submit.prevent="submitWriterRequest">
          <div>
            <p class="text-sm font-semibold text-leaf">Writer application</p>
            <h2 class="text-xl font-semibold">Form pengajuan</h2>
          </div>
          <input v-model="writerRequestForm.pen_name" aria-label="Nama pena / display name" class="field" placeholder="Nama pena / display name" required />
          <textarea v-model="writerRequestForm.bio" aria-label="Bio penulis" minlength="20" class="field min-h-28" placeholder="Bio penulis minimal 20 karakter" required />
          <textarea v-model="writerRequestForm.reason" aria-label="Alasan ingin menjadi writer" minlength="20" class="field min-h-28" placeholder="Alasan ingin menjadi writer minimal 20 karakter" required />
          <input v-model="writerRequestForm.portfolio_url" aria-label="Portfolio URL opsional" class="field" placeholder="Portfolio URL opsional" />
          <button class="btn-primary w-full" :disabled="writerRequestStatus?.status === 'pending'">
            {{ writerRequestStatus?.status === 'pending' ? 'Menunggu review admin' : 'Kirim request' }}
          </button>
        </form>
      </div>
    </template>

      </main>
    </div>

    <div v-if="selectedInvoice" class="fixed inset-0 z-40 grid place-items-center bg-ink/45 p-4">
      <div class="panel max-h-[92vh] w-full max-w-3xl overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-ink/10 bg-paper/80 p-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-sm font-semibold text-leaf">Invoice</p>
            <h2 class="text-2xl font-bold">{{ selectedInvoice.invoice_number }}</h2>
            <p class="mt-1 text-sm text-ink/55">Issued {{ dateLabel(selectedInvoice.issued_at) }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="btn-soft px-3" type="button" @click="printInvoice">
              <ReceiptText :size="16" />
              Print
            </button>
            <button class="btn-primary px-3" type="button" @click="closeInvoice">Tutup</button>
          </div>
        </div>

        <div class="max-h-[72vh] overflow-y-auto p-5">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-md bg-paper p-4">
              <p class="text-xs font-semibold uppercase text-ink/45">Seller</p>
              <strong class="mt-1 block">{{ selectedInvoice.seller?.name || 'Zaimedia' }}</strong>
              <p class="text-sm text-ink/55">{{ selectedInvoice.seller?.email || 'support@zaimedia.local' }}</p>
            </div>
            <div class="rounded-md bg-paper p-4">
              <p class="text-xs font-semibold uppercase text-ink/45">Customer</p>
              <strong class="mt-1 block">{{ selectedInvoice.order.customer?.full_name || selectedInvoice.order.shipping_name }}</strong>
              <p class="text-sm text-ink/55">{{ selectedInvoice.order.customer?.email || selectedInvoice.order.shipping_phone }}</p>
            </div>
          </div>

          <div class="mt-5 overflow-hidden rounded-md border border-ink/10">
            <div class="grid grid-cols-[1fr_90px_120px] gap-3 bg-ink px-4 py-3 text-sm font-semibold text-white">
              <span>Item</span>
              <span class="text-right">Qty</span>
              <span class="text-right">Subtotal</span>
            </div>
            <div v-for="item in selectedInvoice.order.items || []" :key="item.id" class="grid grid-cols-[1fr_90px_120px] gap-3 border-t border-ink/10 px-4 py-3 text-sm">
              <div class="min-w-0">
                <strong class="line-clamp-1">{{ item.book?.title || 'Book' }}</strong>
                <p class="text-ink/50">{{ item.book?.author || '-' }}</p>
              </div>
              <span class="text-right">{{ item.quantity }}</span>
              <span class="text-right font-semibold">{{ money(item.subtotal) }}</span>
            </div>
          </div>

          <div class="ml-auto mt-5 w-full max-w-sm space-y-2 rounded-md bg-paper p-4 text-sm">
            <div class="flex justify-between gap-4">
              <span class="text-ink/55">Subtotal</span>
              <strong>{{ money(selectedInvoice.order.subtotal) }}</strong>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-ink/55">Tax</span>
              <strong>{{ money(selectedInvoice.order.tax_amount) }}</strong>
            </div>
            <div class="flex justify-between gap-4 border-t border-ink/10 pt-3 text-base">
              <span>Total</span>
              <strong>{{ money(selectedInvoice.order.total) }}</strong>
            </div>
          </div>

          <div class="mt-5 rounded-md bg-leaf/10 p-4 text-sm leading-6 text-leaf">
            Status pembayaran {{ selectedInvoice.order.payment_status }} dan status order {{ selectedInvoice.order.order_status }}.
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmDialog.open" class="fixed inset-0 z-40 grid place-items-center bg-ink/45 p-4">
      <div class="panel w-full max-w-md p-5">
        <div class="flex items-start gap-3">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-red-50 text-red-700">
            <Trash2 :size="20" />
          </span>
          <div class="min-w-0">
            <h2 class="text-xl font-semibold">{{ confirmDialog.title }}</h2>
            <p class="mt-2 text-sm leading-6 text-ink/60">{{ confirmDialog.description }}</p>
          </div>
        </div>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
          <button class="btn-soft" type="button" @click="closeConfirm">Batal</button>
          <button class="btn-primary bg-red-600 hover:bg-red-700" type="button" @click="runConfirmAction">
            <Trash2 :size="16" />
            {{ confirmDialog.confirmText }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
