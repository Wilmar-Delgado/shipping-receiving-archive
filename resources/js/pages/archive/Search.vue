<script setup lang="ts">
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import ArchivePagination from '@/components/ArchivePagination.vue';
import DocumentTable from '@/components/DocumentTable.vue';
import FilterBar from '@/components/FilterBar.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { DocumentFilters, DocumentPage, DocumentTypeOption, PartyOption } from '@/types/archive';
import { Head } from '@inertiajs/vue3';
defineProps<{ documents: DocumentPage; filters: DocumentFilters; parties: PartyOption[]; documentTypes: DocumentTypeOption[] }>();
</script>
<template>
    <Head title="Global Search" /><ArchiveLayout title="Global Search"
        ><ArchivePageHeading title="Global Search" description="Search every filed document across the archive." /><FilterBar
            :filters="filters"
            :parties="parties"
            :document-types="documentTypes"
            url="/search"
            global />
        <section class="archive-panel mt-4">
            <div class="archive-panel-heading">
                <div>
                    <h2 class="text-sm font-semibold">Search results</h2>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ documents.total }} document(s) match your criteria</p>
                </div>
            </div>
            <DocumentTable :documents="documents.data" variant="search" /><ArchivePagination
                :links="documents.links"
                :total="documents.total"
                :from="documents.from"
                :to="documents.to"
            /></section
    ></ArchiveLayout>
</template>
