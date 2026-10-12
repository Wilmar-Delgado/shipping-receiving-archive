<script setup lang="ts">
import ArchiveModal from '@/components/ArchiveModal.vue';
import ArchivePagination from '@/components/ArchivePagination.vue';
import DocumentPreview from '@/components/DocumentPreview.vue';
import DocumentStatusBadge from '@/components/DocumentStatusBadge.vue';
import DocumentTable from '@/components/DocumentTable.vue';
import InputError from '@/components/InputError.vue';
import MetadataForm from '@/components/MetadataForm.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { SharedData } from '@/types';
import {
    businessDate,
    fileSize,
    fileType,
    uploadDate,
    type DocumentPage,
    type MetadataDocumentType,
    type PartyOption,
    type ReviewDocument,
} from '@/types/archive';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
const props = defineProps<{ document: ReviewDocument; documentTypes: MetadataDocumentType[]; parties: PartyOption[]; related: DocumentPage }>();
const discard = useForm({});
const page = usePage<SharedData>();
const confirming = ref(false);
const cancelling = ref<HTMLButtonElement | null>(null);
function discardDocument() {
    discard.post(`/documents/${props.document.uuid}/discard`, { onSuccess: () => (confirming.value = false) });
}
async function confirmDiscard() {
    confirming.value = true;
    await nextTick();
    cancelling.value?.focus();
}
</script>
<template>
    <Head :title="document.file?.original_name || 'Document details'" />
    <ArchiveLayout
        title="Document details"
        :section="document.status === 'inbox' ? 'Inbox' : page.props.archiveCategories[document.category_slug]?.label || document.category"
    >
        <Link
            :href="document.status === 'inbox' ? '/inbox' : '/archive/' + document.category_slug"
            class="mb-4 inline-flex items-center gap-2 text-xs font-medium text-primary"
            ><ArrowLeft :size="15" />{{ document.status === 'inbox' ? 'Back to Inbox' : 'Back to category' }}</Link
        >
        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="break-words text-[25px] font-semibold leading-tight">{{ document.file?.original_name || 'Original file unavailable' }}</h1>
                <p class="mt-2 text-xs text-secondary-foreground">{{ document.category }}</p>
            </div>
            <DocumentStatusBadge
                :label="document.status === 'inbox' ? 'Awaiting review' : 'Filed'"
                :tone="document.status === 'inbox' ? 'pending' : 'teal'"
            />
        </header>
        <div class="mb-5 grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(220px,1fr)_minmax(0,2fr)]">
            <section class="archive-panel p-5">
                <h2 class="text-sm font-semibold">Upload information</h2>
                <p class="mt-2 text-xs text-muted-foreground">Read-only upload and saved document information.</p>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <dt class="text-xs text-muted-foreground">Uploaded by</dt>
                        <dd class="mt-1 break-words font-medium">{{ document.uploaded_by }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Upload date</dt>
                        <dd class="mt-1 text-secondary-foreground">{{ uploadDate(document.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">File type / size</dt>
                        <dd class="mt-1 text-secondary-foreground">
                            {{ document.file ? `${fileType(document.file.mime)} · ${fileSize(document.file.size)}` : 'Unavailable' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Document date</dt>
                        <dd class="mt-1 text-secondary-foreground">{{ businessDate(document.document_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Handled date</dt>
                        <dd class="mt-1 text-secondary-foreground">{{ businessDate(document.handled_on) }}</dd>
                    </div>
                    <div v-if="document.reviewed_by">
                        <dt class="text-xs text-muted-foreground">Filed by</dt>
                        <dd class="mt-1 text-secondary-foreground">
                            {{ document.reviewed_by }} · {{ document.reviewed_at ? uploadDate(document.reviewed_at) : '' }}
                        </dd>
                    </div>
                </dl>
            </section>
            <MetadataForm :document="document" :document-types="documentTypes" :parties="parties" />
        </div>
        <DocumentPreview :uuid="document.uuid" :filename="document.file?.original_name || document.category" :available="document.file_available" />
        <section v-if="document.can_discard" class="archive-panel mt-5 flex flex-wrap items-center justify-between gap-4 p-5">
            <div>
                <h2 class="text-sm font-semibold">Incorrect upload?</h2>
                <p class="mt-2 text-xs text-muted-foreground">Discard it to remove it from the active Inbox. The original file will be retained.</p>
            </div>
            <button type="button" class="archive-button text-red-700 dark:text-red-400" @click="confirmDiscard">
                <Trash2 :size="15" />Discard document
            </button>
        </section>
        <section class="archive-panel mt-5">
            <div class="archive-panel-heading">
                <div>
                    <h2 class="text-sm font-semibold">Related filed documents</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Connected by a shared reference of the same kind.</p>
                </div>
                <span class="text-xs text-muted-foreground">{{ related.total }} result(s)</span>
            </div>
            <DocumentTable :documents="related.data" show-category empty-message="No other filed documents share a reference with this document." />
            <ArchivePagination :links="related.links" :total="related.total" :from="related.from" :to="related.to" />
        </section>
        <ArchiveModal
            :open="confirming"
            title="Discard this document?"
            description="It will leave the active Inbox and its preview will no longer be available. The original file will be retained."
            @close="!discard.processing && (confirming = false)"
        >
            <div class="p-5">
                <InputError v-for="error in discard.errors" :key="error" :message="error" />
                <div class="flex justify-end gap-3">
                    <button ref="cancelling" type="button" :disabled="discard.processing" class="archive-button" @click="confirming = false">
                        Cancel</button
                    ><button type="button" :disabled="discard.processing" class="archive-button bg-red-700 text-white" @click="discardDocument">
                        {{ discard.processing ? 'Discarding...' : 'Discard document' }}
                    </button>
                </div>
            </div>
        </ArchiveModal>
    </ArchiveLayout>
</template>
