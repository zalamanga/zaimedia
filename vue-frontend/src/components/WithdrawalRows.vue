<script setup>
import { money } from '../api';
defineProps({ rows: { type: Array, default: () => [] }, admin: Boolean, reasons: { type: Object, default: () => ({}) } });
defineEmits(['process']);
</script>

<template>
<div class="divide-y divide-ink/10">
  <div v-if="!rows.length" class="p-8 text-center text-ink/55">Tidak ada withdrawal.</div>
  <div v-for="row in rows" :key="row.id" class="grid gap-4 p-5 xl:grid-cols-[1fr_180px_320px] xl:items-start">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <strong>{{ row.withdrawal_number || row.withdrawal_code }}</strong>
        <span class="rounded bg-paper px-2 py-1 text-xs font-semibold capitalize">{{ row.status }}</span>
      </div>
      <p class="mt-1 text-sm text-ink/55">{{ row.writer?.full_name || row.writer_name || row.account_name }} - {{ row.writer?.email || row.bank_name }}</p>
      <div class="mt-3 grid gap-2 text-sm text-ink/60 sm:grid-cols-2">
        <p><span class="text-ink/40">Bank:</span> <strong class="text-ink">{{ row.bank_name }}</strong></p>
        <p><span class="text-ink/40">Account:</span> <strong class="text-ink">{{ row.account_number }}</strong></p>
        <p class="sm:col-span-2"><span class="text-ink/40">Name:</span> <strong class="text-ink">{{ row.account_name }}</strong></p>
      </div>
      <p v-if="row.notes" class="mt-3 rounded-md bg-paper p-3 text-sm text-ink/60">{{ row.notes }}</p>
      <p v-if="row.rejection_reason" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ row.rejection_reason }}</p>
    </div>
    <strong>{{ money(row.amount) }}</strong>
    <div v-if="admin" class="space-y-3">
      <div class="flex flex-wrap gap-2">
        <button v-if="row.status === 'pending'" class="btn-primary px-3" @click="$emit('process', row, 'approved')">Approve</button>
        <button v-if="row.status === 'approved' || row.status === 'processing'" class="btn-primary px-3" @click="$emit('process', row, 'completed')">Complete</button>
        <span v-if="row.status !== 'pending' && row.status !== 'approved' && row.status !== 'processing'" class="rounded bg-paper px-3 py-2 text-sm font-semibold capitalize">{{ row.status }}</span>
      </div>
      <form v-if="row.status === 'pending'" class="space-y-2" @submit.prevent="$emit('process', row, 'rejected')">
        <textarea v-model="reasons[row.id]" aria-label="Alasan penolakan withdrawal" class="field min-h-20" placeholder="Alasan reject withdrawal" />
        <button class="btn-soft w-full text-red-600">Reject withdrawal</button>
      </form>
    </div>
    <span v-else class="w-fit rounded bg-paper px-3 py-2 text-sm font-semibold capitalize">{{ row.status }}</span>
  </div>
</div>
</template>
