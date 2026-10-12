<script setup lang="ts">
import ArchiveModal from '@/components/ArchiveModal.vue';
import DocumentPreview from '@/components/DocumentPreview.vue';
defineProps<{ document: { uuid: string; filename: string; hasFile: boolean } | null }>();
defineEmits<{ close: [] }>();
</script>
<template>
    <ArchiveModal
        :open="!!document"
        :title="document?.filename || 'Document preview'"
        description="Original document preview"
        wide
        @close="$emit('close')"
    >
        <DocumentPreview v-if="document" :uuid="document.uuid" :filename="document.filename" :available="document.hasFile" :heading="false" />
    </ArchiveModal>
</template>
