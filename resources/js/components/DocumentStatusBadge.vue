<script setup lang="ts">
defineProps<{ label: string; tone?: 'neutral' | 'pending' | 'teal' }>();
</script>
<template>
    <span
        :class="[
            'inline-flex max-w-full items-center rounded-full border px-2 py-0.5 text-[10px] font-medium',
            tone === 'pending'
                ? 'archive-warning border-amber-500/30 bg-amber-500/10'
                : tone === 'teal'
                  ? 'border-primary/20 bg-primary/10 text-primary'
                  : 'border-border bg-muted text-secondary-foreground',
        ]"
        >{{ label }}</span
    >
</template>
