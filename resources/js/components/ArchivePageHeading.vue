<script setup lang="ts">
defineProps<{ title: string; description: string; eyebrow?: string }>();
</script>
<template>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[25px] font-semibold leading-tight tracking-tight">{{ title }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-secondary-foreground">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
