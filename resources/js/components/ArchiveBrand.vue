<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Archive } from 'lucide-vue-next';
withDefaults(defineProps<{ href?: string }>(), { href: '/dashboard' });
</script>
<template>
    <Link :href="href" class="flex items-center gap-3"
        ><span class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-[0_0_0_4px_rgba(32,193,184,.08)]"
            ><Archive :size="21" /></span
        ><span class="text-sm font-bold"
            >ShipArchive<span class="mt-1 block text-[9px] font-normal uppercase tracking-wider text-muted-foreground">Document workspace</span></span
        ></Link
    >
</template>
