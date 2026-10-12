<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Download, Eye } from 'lucide-vue-next';
defineProps<{ uuid: string; filename: string; hasFile: boolean; review?: boolean }>();
defineEmits<{ preview: [] }>();
</script>
<template>
    <div class="flex items-center justify-end gap-2">
        <button
            type="button"
            @click="$emit('preview')"
            :class="review ? 'flex items-center gap-1.5 text-xs text-secondary-foreground hover:text-primary' : 'archive-icon-button'"
            :aria-label="`Preview ${filename}`"
            title="Preview"
        >
            <Eye :size="15" /><span v-if="review" class="hidden sm:inline">Preview</span></button
        ><a
            v-if="hasFile"
            :href="`/documents/${uuid}/download`"
            class="archive-icon-button"
            :aria-label="`Download ${filename}`"
            title="Download original"
            ><Download :size="15"
        /></a>
        <button
            v-else
            type="button"
            disabled
            class="archive-icon-button opacity-50"
            :aria-label="`Download unavailable for ${filename}: no original file`"
            title="No original file attached"
        >
            <Download :size="15" />
        </button>
        <Link v-if="review" :href="`/documents/${uuid}`" class="archive-button" :aria-label="`Review ${filename}`"
            >Review<ChevronRight :size="14"
        /></Link>
    </div>
</template>
