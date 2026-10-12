<script setup lang="ts">
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import ArchivePagination from '@/components/ArchivePagination.vue';
import DocumentStatusBadge from '@/components/DocumentStatusBadge.vue';
import DocumentTable from '@/components/DocumentTable.vue';
import DocumentUploader from '@/components/DocumentUploader.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { SharedData } from '@/types';
import type { DocumentPage, DocumentTypeOption, InboxDocument } from '@/types/archive';
import { Head, usePage } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
import { ref } from 'vue';
defineProps<{
    documents: DocumentPage<InboxDocument>;
    documentTypes: DocumentTypeOption[];
    uploadLimits: { maxKb: number; maxFiles: number; maxRequestBytes: number | null };
    initialCategoryId: number | null;
    openUpload: boolean;
}>();
const uploader = ref<InstanceType<typeof DocumentUploader> | null>(null);
const page = usePage<SharedData & { flash: { upload_warnings?: string[] } }>();
</script>
<template>
    <Head title="Inbox" /><ArchiveLayout title="Inbox"
        ><ArchivePageHeading title="Inbox" description="Review and file newly uploaded documents."
            ><button class="archive-button-primary" @click="uploader?.open()"><Upload :size="15" />Upload documents</button></ArchivePageHeading
        >
        <div
            v-if="page.props.flash.upload_warnings?.length"
            role="status"
            class="archive-warning mb-5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-xs"
        >
            <p class="font-semibold">Possible duplicate uploads</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li v-for="(warning, index) in page.props.flash.upload_warnings" :key="index">{{ warning }}</li>
            </ul>
        </div>
        <DocumentUploader
            ref="uploader"
            :document-types="documentTypes"
            :limits="uploadLimits"
            :initial-category-id="initialCategoryId"
            :initially-open="openUpload" />
        <section class="archive-panel mt-4">
            <div class="archive-panel-heading">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold">Awaiting review</h2>
                        <DocumentStatusBadge :label="`${documents.total} pending`" tone="pending" />
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">Documents that need metadata before filing</p>
                </div>
            </div>
            <DocumentTable :documents="documents.data" variant="inbox" /><ArchivePagination
                :links="documents.links"
                :total="documents.total"
                :from="documents.from"
                :to="documents.to"
            /></section
    ></ArchiveLayout>
</template>
