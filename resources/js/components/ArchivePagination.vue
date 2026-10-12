<script setup lang="ts">
import type { PaginationLink } from '@/types/archive';
import { Link } from '@inertiajs/vue3';
defineProps<{ links: PaginationLink[]; total?: number; from?: number | null; to?: number | null }>();
</script>
<template>
    <div
        v-if="total !== undefined || links.length > 3"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-background/30 px-4 py-3"
    >
        <p v-if="total !== undefined" class="text-[11px] text-muted-foreground">
            {{ total ? `Showing ${from || 0}–${to || 0} of ${total}` : 'No records' }}
        </p>
        <nav v-if="links.length > 3" aria-label="Pagination" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in links" :key="index"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'rounded-md border px-3 py-1.5 text-xs',
                        link.active ? 'border-primary bg-primary/10 text-primary' : 'border-border text-secondary-foreground hover:bg-muted',
                    ]"
                    ><span v-html="link.label" /></Link
                ><span
                    v-else
                    aria-disabled="true"
                    class="rounded-md border border-border px-3 py-1.5 text-xs text-muted-foreground"
                    v-html="link.label"
            /></template>
        </nav>
    </div>
</template>
