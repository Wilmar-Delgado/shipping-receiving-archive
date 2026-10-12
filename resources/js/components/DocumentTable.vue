<script setup lang="ts">
import DocumentActions from '@/components/DocumentActions.vue';
import DocumentFileIcon from '@/components/DocumentFileIcon.vue';
import DocumentPreviewModal from '@/components/DocumentPreviewModal.vue';
import DocumentStatusBadge from '@/components/DocumentStatusBadge.vue';
import {
    businessDate,
    fileSize,
    referenceLabels,
    uploadDate,
    type FiledDocument,
    type InboxDocument,
    type MetadataConfiguration,
} from '@/types/archive';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const preview = ref<{ uuid: string; filename: string; hasFile: boolean } | null>(null);
const props = withDefaults(
    defineProps<{
        documents: (FiledDocument | InboxDocument)[];
        configuration?: MetadataConfiguration;
        showCategory?: boolean;
        emptyMessage?: string;
        variant?: 'archive' | 'recent' | 'inbox' | 'search';
    }>(),
    { variant: 'archive', showCategory: true },
);
const isInbox = computed(() => props.variant === 'inbox');
const showParty = computed(() => ['archive', 'search'].includes(props.variant) && (!props.configuration || !!props.configuration.partyKinds.length));
const showHandled = computed(() => ['archive', 'search'].includes(props.variant) && (!props.configuration || props.configuration.showHandled));
function metadata(document: FiledDocument | InboxDocument): FiledDocument | null {
    return 'references' in document ? document : null;
}
</script>
<template>
    <div v-if="!documents.length" class="px-6 py-14 text-center">
        <h3 class="text-sm font-semibold">{{ isInbox ? 'Your Inbox is clear' : 'No filed documents found' }}</h3>
        <p class="mt-2 text-xs text-muted-foreground">
            {{
                emptyMessage ||
                (isInbox ? 'Upload scanned documents to start reviewing them.' : 'Adjust your filters, or file a document from the Inbox.')
            }}
        </p>
    </div>
    <div v-else class="overflow-x-auto" tabindex="0" role="region" aria-label="Document table">
        <table class="archive-table">
            <thead>
                <tr>
                    <th class="w-[27%]">Document</th>
                    <th v-if="showCategory" class="w-[15%]">Category</th>
                    <th v-if="!isInbox" class="w-[16%]">Reference</th>
                    <th v-if="showParty">Vendor / customer</th>
                    <th v-if="!isInbox">Document date</th>
                    <th v-if="showHandled">{{ configuration?.handledLabel || 'Handled' }}</th>
                    <th v-if="configuration?.showCarrier" class="hidden 2xl:table-cell">Carrier</th>
                    <th v-if="isInbox">Uploaded</th>
                    <th v-if="isInbox || variant === 'archive'" :class="isInbox ? '' : 'hidden 2xl:table-cell'">Uploaded by</th>
                    <th :class="['text-right', isInbox ? 'w-40 sm:w-52' : 'w-[104px]']">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="document in documents" :key="document.uuid">
                    <td>
                        <div class="flex items-center gap-3">
                            <DocumentFileIcon :mime="document.file?.mime" />
                            <div class="min-w-0">
                                <Link :href="`/documents/${document.uuid}`" class="break-words font-semibold leading-5 hover:text-primary">{{
                                    document.file?.original_name || `${document.is_demo ? 'DEMO · ' : ''}${document.category}`
                                }}</Link>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">
                                    {{
                                        document.file
                                            ? `${fileSize(document.file.size)} · ${document.file.mime === 'application/pdf' ? 'PDF' : 'Image'}`
                                            : 'Original unavailable'
                                    }}
                                </p>
                                <p v-if="metadata(document)?.connections?.length" class="mt-1 text-[10px] text-primary">
                                    Connected by
                                    {{
                                        metadata(document)
                                            ?.connections?.map((reference) => `${referenceLabels[reference.kind]} ${reference.value}`)
                                            .join(', ')
                                    }}
                                </p>
                                <p v-if="variant === 'archive'" class="mt-1 text-[10px] text-muted-foreground 2xl:hidden">
                                    {{ document.uploaded_by }} · {{ uploadDate(document.created_at) }}
                                </p>
                                <p
                                    v-if="configuration?.showCarrier && metadata(document)?.carrier"
                                    class="mt-1 text-[10px] text-muted-foreground 2xl:hidden"
                                >
                                    Carrier: {{ metadata(document)?.carrier }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td v-if="showCategory"><DocumentStatusBadge :label="document.category" :tone="isInbox ? 'teal' : 'neutral'" /></td>
                    <td v-if="!isInbox" class="max-w-44">
                        <div
                            v-for="(reference, index) in metadata(document)?.references"
                            :key="index"
                            class="break-words font-mono text-[11px] leading-5 text-secondary-foreground"
                        >
                            <span class="text-muted-foreground">{{ referenceLabels[reference.kind] }}</span> {{ reference.value }}
                        </div>
                        <span v-if="!metadata(document)?.references.length" class="text-muted-foreground">—</span>
                    </td>
                    <td v-if="showParty" class="max-w-40 text-secondary-foreground">{{ metadata(document)?.party?.name || '—' }}</td>
                    <td v-if="!isInbox" class="whitespace-nowrap text-secondary-foreground">
                        {{ businessDate(metadata(document)?.document_date || null) }}
                    </td>
                    <td v-if="showHandled" class="whitespace-nowrap text-muted-foreground">
                        {{ businessDate(metadata(document)?.handled_on || null) }}
                    </td>
                    <td v-if="configuration?.showCarrier" class="hidden text-secondary-foreground 2xl:table-cell">
                        {{ metadata(document)?.carrier || '—' }}
                    </td>
                    <td v-if="isInbox" class="text-secondary-foreground">{{ uploadDate(document.created_at) }}</td>
                    <td v-if="isInbox || variant === 'archive'" :class="['text-secondary-foreground', !isInbox && 'hidden 2xl:table-cell']">
                        <p>{{ document.uploaded_by }}</p>
                        <p v-if="!isInbox" class="mt-1 text-[10px] text-muted-foreground">{{ uploadDate(document.created_at) }}</p>
                    </td>
                    <td>
                        <DocumentActions
                            :uuid="document.uuid"
                            :filename="document.file?.original_name || document.category"
                            :has-file="!!document.file"
                            :review="isInbox"
                            @preview="
                                preview = {
                                    uuid: document.uuid,
                                    filename: document.file?.original_name || document.category,
                                    hasFile: !!document.file,
                                }
                            "
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <DocumentPreviewModal :document="preview" @close="preview = null" />
</template>
