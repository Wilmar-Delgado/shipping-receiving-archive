<script setup lang="ts">
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import { Head } from '@inertiajs/vue3';
import { FolderOpen } from 'lucide-vue-next';
defineProps<{ title: string; section: string; description: string }>();
</script>
<template>
    <Head :title="title" />
    <ArchiveLayout :title="title" :section="section">
        <ArchivePageHeading :title="title" :description="description" :eyebrow="section" />
        <div class="flex min-h-80 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <span class="mb-5 rounded-2xl bg-slate-50 p-5 text-teal-700"><FolderOpen :size="32" /></span>
            <h2 class="text-lg font-semibold">Your archive starts here</h2>
            <p class="mt-3 max-w-md text-sm leading-6 text-slate-500">
                This workspace is ready for a future release. Document filing and retrieval will be available here.
            </p>
        </div>
    </ArchiveLayout>
</template>
