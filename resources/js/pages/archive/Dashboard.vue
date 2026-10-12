<script setup lang="ts">
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import DashboardCategories from '@/components/DashboardCategories.vue';
import DashboardStatCard from '@/components/DashboardStatCard.vue';
import DocumentTable from '@/components/DocumentTable.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { SharedData } from '@/types';
import type { FiledDocument } from '@/types/archive';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Archive, ArrowDownToLine, ChevronRight, Inbox, PackageCheck, Search, Upload } from 'lucide-vue-next';
defineProps<{ stats: { filed: number; inbox: number; receiving: number; shipping: number; routers: number }; recentDocuments: FiledDocument[] }>();
const page = usePage<SharedData>();
</script>
<template>
    <Head title="Dashboard" /><ArchiveLayout title="Dashboard"
        ><ArchivePageHeading
            :title="`Welcome back, ${page.props.auth.user.name.split(' ')[0]}`"
            description="Here's what's happening in your document archive."
            ><Link href="/inbox?upload=1" class="archive-button-primary"><Upload :size="15" />Upload documents</Link></ArchivePageHeading
        >
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <DashboardStatCard
                label="Total Filed Documents"
                :value="stats.filed"
                description="Across all archive categories"
                :icon="Archive"
            /><DashboardStatCard
                label="Awaiting Review"
                :value="stats.inbox"
                description="Documents in the Inbox"
                :icon="Inbox"
                pending
            /><DashboardStatCard
                label="Receiving Documents"
                :value="stats.receiving"
                description="Filed receiving records"
                :icon="ArrowDownToLine"
            /><DashboardStatCard label="Shipping Documents" :value="stats.shipping" description="Filed shipping records" :icon="PackageCheck" />
        </div>
        <div class="mt-4 grid gap-4 xl:grid-cols-[1.1fr_1fr]">
            <section class="archive-panel p-5">
                <h2 class="text-sm font-semibold">Quick access</h2>
                <p class="mt-1 text-[11px] text-muted-foreground">Common document workflows</p>
                <Link
                    v-for="action in [
                        { title: 'Upload documents', description: 'Add scanned paperwork to the Inbox', href: '/inbox?upload=1', icon: Upload },
                        {
                            title: 'Search the archive',
                            description: 'Find by PO, job, invoice, tracking, vendor, or filename',
                            href: '/search',
                            icon: Search,
                        },
                    ]"
                    :key="action.href"
                    :href="action.href"
                    class="mt-3 flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-muted"
                    ><span class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        ><component :is="action.icon" :size="17"
                    /></span>
                    <div class="flex-1">
                        <h3 class="text-xs font-semibold">{{ action.title }}</h3>
                        <p class="mt-1 text-[11px] text-muted-foreground">{{ action.description }}</p>
                    </div>
                    <ChevronRight :size="15" class="text-muted-foreground"
                /></Link>
            </section>
            <DashboardCategories :stats="stats" />
        </div>
        <section class="archive-panel mt-4">
            <div class="archive-panel-heading">
                <div>
                    <h2 class="text-sm font-semibold">Recent documents</h2>
                    <p class="mt-1 text-[11px] text-muted-foreground">Recently filed across your workspace</p>
                </div>
                <Link href="/search" class="flex items-center gap-1 text-xs text-primary">View all<ChevronRight :size="13" /></Link>
            </div>
            <DocumentTable :documents="recentDocuments" variant="recent" /></section
    ></ArchiveLayout>
</template>
