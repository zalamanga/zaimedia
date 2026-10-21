<script setup>
import { CheckCircle2, Info, X, XCircle } from '@lucide/vue';
import { toast } from '../toast';

const icons = {
  success: CheckCircle2,
  error: XCircle,
  info: Info,
};
</script>

<template>
  <div aria-live="polite" aria-atomic="false" class="pointer-events-none fixed right-4 top-20 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3">
    <TransitionGroup name="toast">
      <div
        v-for="item in toast.items"
        :key="item.id"
        class="pointer-events-auto flex items-start gap-3 rounded-md border bg-white p-4 shadow-soft"
        :class="{
          'border-green-200': item.type === 'success',
          'border-red-200': item.type === 'error',
          'border-ink/10': item.type === 'info',
        }"
      >
        <component
          :is="icons[item.type] || Info"
          class="mt-0.5 shrink-0"
          :class="{
            'text-green-700': item.type === 'success',
            'text-red-700': item.type === 'error',
            'text-leaf': item.type === 'info',
          }"
          :size="20"
        />
        <p class="min-w-0 flex-1 text-sm font-semibold leading-6 text-ink/75">{{ item.message }}</p>
        <button aria-label="Tutup notifikasi" class="rounded p-1 text-ink/35 hover:bg-paper hover:text-ink" type="button" @click="toast.remove(item.id)">
          <X :size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
