<script setup>
import { money } from '../api';
defineProps({ rows: { type: Array, default: () => [] }, editable: Boolean });
defineEmits(['edit', 'delete', 'restore']);
function bookPath(book) {
  return `/books/${book?.slug || book?.id}`;
}
</script>

<template>
<div class="divide-y divide-ink/10">
  <div v-if="!rows.length" class="p-8 text-center text-ink/55">Tidak ada buku.</div>
  <div v-for="book in rows" :key="book.id" class="grid min-w-0 grid-cols-[64px_minmax(0,1fr)] gap-4 p-5 sm:grid-cols-[72px_minmax(0,1fr)]">
    <component :is="book.status === 'approved' && !book.deleted_at ? 'RouterLink' : 'div'" :to="book.status === 'approved' && !book.deleted_at ? bookPath(book) : undefined" class="block h-24 w-16 overflow-hidden rounded-md bg-ink/5">
      <img v-if="book.cover_url" :src="book.cover_url" class="h-full w-full object-cover" :alt="book.title" />
      <span v-else class="grid h-full place-items-center px-1 text-center text-[10px] text-ink/45">{{ book.title }}</span>
    </component>
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <strong class="line-clamp-1">{{ book.title }}</strong>
        <span class="rounded px-2 py-1 text-xs font-semibold ring-1"
          :class="{
            'bg-green-50 text-green-700 ring-green-200': !book.deleted_at && book.status === 'approved',
            'bg-amber-50 text-amber-700 ring-amber-200': !book.deleted_at && book.status === 'pending',
            'bg-red-50 text-red-700 ring-red-200': !book.deleted_at && book.status === 'rejected',
            'bg-slate-100 text-slate-600 ring-slate-200': book.deleted_at
          }"
        >{{ book.deleted_at ? 'archived' : book.status }}</span>
      </div>
      <p class="mt-1 text-sm text-ink/55">{{ book.author }} - {{ book.category?.name || '-' }}</p>
      <div class="book-metrics mt-3 text-xs text-ink/55">
        <span>Views: <strong class="block break-words text-ink">{{ book.views || 0 }}</strong></span>
        <span>Sales: <strong class="block break-words text-ink">{{ book.sales_count || 0 }}</strong></span>
        <span>Royalty: <strong class="block break-words text-ink">{{ money(book.sales_sum_royalty_amount || 0) }}</strong></span>
        <span>Price: <strong class="block break-words text-ink">{{ money(book.price) }}</strong></span>
      </div>
      <p v-if="book.status === 'rejected' && book.rejection_reason" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
        {{ book.rejection_reason }}
      </p>
    </div>
    <div class="col-span-2 flex flex-wrap gap-2 sm:col-span-1 sm:col-start-2">
      <RouterLink v-if="book.status === 'approved' && !book.deleted_at" class="btn-soft px-3" :to="bookPath(book)">Detail</RouterLink>
      <button v-if="editable && !book.deleted_at" class="btn-soft px-3" @click="$emit('edit', book)">Edit</button>
      <button v-if="editable && !book.deleted_at" class="btn-soft px-3 text-red-600" @click="$emit('delete', book)">Archive</button>
      <button v-if="editable && book.deleted_at" class="btn-primary px-3" @click="$emit('restore', book)">Restore</button>
    </div>
  </div>
</div>
</template>
