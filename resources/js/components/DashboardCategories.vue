<script setup lang="ts">
import type { SharedData } from '@/types';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowDownToLine, ClipboardList, PackageCheck } from 'lucide-vue-next';
defineProps<{ stats: { receiving: number; shipping: number; routers: number } }>();
const page = usePage<SharedData>();
const categoryLink = (slug: string) => ({ slug, ...page.props.archiveCategories[slug] });
const groups = [
    {
        name: 'Receiving',
        key: 'receiving' as const,
        icon: ArrowDownToLine,
        links: [categoryLink('vendor-packing-slips'), categoryLink('material-test-reports'), categoryLink('return-merchandise-authorizations')],
    },
    {
        name: 'Shipping',
        key: 'shipping' as const,
        icon: PackageCheck,
        links: [categoryLink('customer-packing-slips'), categoryLink('waybills')],
    },
    { name: 'Job Routers', key: 'routers' as const, icon: ClipboardList, links: [categoryLink('job-routers')] },
];
</script>
<template>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="text-sm font-semibold">Browse categories</h2>
        <p class="mt-1 text-[11px] text-muted-foreground">Jump to a document collection</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <Menu v-for="group in groups" :key="group.key" as="div" class="relative"
                ><MenuButton class="w-full rounded-lg border border-border bg-muted/50 p-3 text-left transition-colors hover:border-primary/40"
                    ><span class="flex items-center gap-2 text-xs font-semibold"
                        ><component :is="group.icon" :size="15" class="text-primary" />{{ group.name }}</span
                    ><span class="mt-2 block text-[10px] text-muted-foreground"
                        >{{ stats[group.key].toLocaleString() }} filed documents</span
                    ></MenuButton
                ><MenuItems class="absolute left-0 z-30 mt-2 min-w-52 rounded-lg border border-border bg-card p-1.5 shadow-2xl"
                    ><MenuItem v-for="link in group.links" :key="link.slug" v-slot="{ active }"
                        ><Link :href="link.href" :class="['block rounded px-3 py-2.5 text-xs', active && 'bg-muted text-primary']">{{
                            link.label
                        }}</Link></MenuItem
                    ></MenuItems
                ></Menu
            >
        </div>
    </section>
</template>
