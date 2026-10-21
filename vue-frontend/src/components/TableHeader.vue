<script setup>
defineProps({ title: String });
</script>

<template>
<div class="flex items-center justify-between border-b border-ink/10 bg-paper/70 p-5">
  <h2 class="text-xl font-semibold">{{ title }}</h2>
</div>
</template>
