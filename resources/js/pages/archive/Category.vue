<script setup lang="ts">
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import ArchivePagination from '@/components/ArchivePagination.vue';
import DocumentTable from '@/components/DocumentTable.vue';
import FilterBar from '@/components/FilterBar.vue';
import { archiveCategories } from '@/config/archiveCategories';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { DocumentFilters, DocumentPage, MetadataConfiguration, PartyOption } from '@/types/archive';
import { Head, Link } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
defineProps<{
    category: { name: string; label: string; slug: string; section: string; metadata: MetadataConfiguration };
    documents: DocumentPage;
    filters: DocumentFilters;
    parties: PartyOption[];
}>();
</script>
<template>
    <Head :title="category.name" /><ArchiveLayout :title="category.label"
        ><ArchivePageHeading
            :title="category.name"
            :description="archiveCategories[category.slug]?.description || 'Filed documents in this category.'"
            ><Link :href="`/inbox?category=${category.slug}&upload=1`" class="archive-button-primary"
                ><Upload :size="15" />Upload document</Link
            ></ArchivePageHeading
        >
        <section class="archive-panel">
            <FilterBar
                :filters="filters"
                :parties="parties"
                :url="'/archive/' + category.slug"
                :placeholder="archiveCategories[category.slug]?.searchPlaceholder"
                :party-kinds="category.metadata.partyKinds"
                :show-handled="category.metadata.showHandled"
            />
            <div class="archive-panel-heading">
                <div>
                    <h2 class="text-sm font-semibold">All documents</h2>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ documents.total }} filed record(s)</p>
                </div>
            </div>
            <DocumentTable :documents="documents.data" :configuration="category.metadata" /><ArchivePagination
                :links="documents.links"
                :total="documents.total"
                :from="documents.from"
                :to="documents.to"
            /></section
    ></ArchiveLayout>
</template>
