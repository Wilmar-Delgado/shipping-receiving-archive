<script setup lang="ts">
import ArchiveBrand from '@/components/ArchiveBrand.vue';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowDownToLine, ClipboardList, FileText, House, Inbox, PackageCheck, Search, Truck, Undo2, Users, X } from 'lucide-vue-next';
withDefaults(defineProps<{ mobileOpen: boolean; navigationId?: string }>(), { navigationId: 'archive-navigation' });
const emit = defineEmits<{ close: [] }>();
const page = usePage<SharedData & { inboxCount: number }>();
const categoryLink = (slug: string, icon: typeof FileText) => ({
    title: page.props.archiveCategories[slug].label,
    href: page.props.archiveCategories[slug].href,
    icon,
});
const groups = [
    {
        title: 'Workspace',
        items: [
            { title: 'Dashboard', href: '/dashboard', icon: House },
            { title: 'Inbox', href: '/inbox', icon: Inbox },
            { title: 'Global Search', href: '/search', icon: Search },
        ],
    },
    {
        title: 'Receiving',
        items: [
            categoryLink('vendor-packing-slips', ArrowDownToLine),
            categoryLink('material-test-reports', FileText),
            categoryLink('return-merchandise-authorizations', Undo2),
        ],
    },
    {
        title: 'Shipping',
        items: [categoryLink('customer-packing-slips', PackageCheck), categoryLink('waybills', Truck)],
    },
    { title: 'Other', items: [categoryLink('job-routers', ClipboardList)] },
];
const active = (href: string) => page.url.split('?')[0] === href;
</script>
<template>
    <aside
        :id="navigationId"
        :class="[
            'archive-sidebar fixed inset-y-0 left-0 z-40 w-[var(--archive-sidebar-width)] border-r border-border bg-card lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <div class="flex h-20 items-center justify-between px-5">
            <ArchiveBrand /><button class="lg:hidden" aria-label="Close navigation" @click="emit('close')"><X :size="18" /></button>
        </div>
        <nav aria-label="Main navigation" class="px-3 pb-6">
            <section v-for="group in groups" :key="group.title" class="mb-4">
                <h2 class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-[.12em] text-muted-foreground">{{ group.title }}</h2>
                <Link
                    v-for="item in group.items"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="active(item.href) ? 'page' : undefined"
                    :class="['archive-nav-item', active(item.href) && 'is-active']"
                    @click="emit('close')"
                    ><component :is="item.icon" :size="15" /><span>{{ item.title }}</span
                    ><span
                        v-if="item.href === '/inbox' && page.props.inboxCount"
                        class="ml-auto rounded-full bg-primary/15 px-1.5 text-[10px] text-primary"
                        >{{ page.props.inboxCount }}</span
                    ></Link
                >
            </section>
            <section v-if="page.props.auth.user.role === 'admin'">
                <h2 class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-[.12em] text-muted-foreground">Administration</h2>
                <Link href="/admin/users" :class="['archive-nav-item', active('/admin/users') && 'is-active']" @click="emit('close')"
                    ><Users :size="15" />Users</Link
                >
            </section>
        </nav>
    </aside>
</template>
