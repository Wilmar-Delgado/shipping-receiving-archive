<script setup lang="ts">
import ArchiveBrand from '@/components/ArchiveBrand.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="archive-shell flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="archive-panel w-full max-w-md p-8">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <ArchiveBrand href="/" />
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
