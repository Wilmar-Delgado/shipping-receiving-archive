<script setup lang="ts">
import type { Component } from 'vue';
defineProps<{ label: string; value: number; description: string; icon: Component; pending?: boolean }>();
</script>
<template>
    <section class="archive-panel flex items-start gap-3 px-4 py-5">
        <span
            :class="[
                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                pending ? 'archive-warning bg-amber-500/10' : 'bg-primary/10 text-primary',
            ]"
            ><component :is="icon" :size="18"
        /></span>
        <div>
            <h2 class="text-xs text-secondary-foreground">{{ label }}</h2>
            <p class="mt-2 text-2xl font-bold tabular-nums">{{ value.toLocaleString() }}</p>
            <p class="mt-1 text-[10px] text-muted-foreground">{{ description }}</p>
        </div>
    </section>
</template>
