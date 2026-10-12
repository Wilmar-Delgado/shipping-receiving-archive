<script setup lang="ts">
import ArchiveSidebar from '@/components/ArchiveSidebar.vue';
import ArchiveTopbar from '@/components/ArchiveTopbar.vue';
import type { SharedData } from '@/types';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
defineProps<{ title: string; section?: string }>();
const page = usePage<SharedData & { flash: { success?: string } }>();
const mobileOpen = ref(false);
watch(
    () => page.url,
    () => (mobileOpen.value = false),
);
// The navigation drawer uses Headless UI's focus trap, Escape, and outside-click behavior.
onUnmounted(() => (mobileOpen.value = false));
</script>
<template>
    <div class="archive-shell min-h-screen overflow-x-clip bg-background text-foreground">
        <div class="hidden lg:block"><ArchiveSidebar :mobile-open="true" @close="mobileOpen = false" /></div>
        <Dialog :open="mobileOpen" class="archive-shell relative z-50 lg:hidden" @close="mobileOpen = false"
            ><div class="fixed inset-0 bg-black/60" aria-hidden="true" />
            <DialogTitle class="sr-only">Main navigation</DialogTitle
            ><DialogPanel
                ><ArchiveSidebar navigation-id="archive-mobile-navigation" :mobile-open="mobileOpen" @close="mobileOpen = false" /></DialogPanel
        ></Dialog>
        <div class="lg:pl-[var(--archive-sidebar-width)]">
            <ArchiveTopbar :title="title" :section="section" :mobile-open="mobileOpen" @toggle="mobileOpen = !mobileOpen" />
            <main class="mx-auto max-w-[1680px] px-5 py-7 sm:px-8 sm:py-8">
                <div
                    v-if="page.props.flash?.success"
                    role="status"
                    class="mb-5 rounded-lg border border-primary/25 bg-primary/10 px-4 py-3 text-sm text-primary"
                >
                    {{ page.props.flash.success }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
