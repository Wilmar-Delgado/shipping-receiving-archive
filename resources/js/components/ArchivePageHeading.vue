<script setup lang="ts">
defineProps<{ title: string; description: string; eyebrow?: string }>();
</script>
<template>
 <div class="mb-8"><p v-if="eyebrow" class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">{{ eyebrow }}</p><h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">{{ description }}</p></div>
</template>
