<script setup lang="ts">
import { fileType } from '@/types/archive';
import { FileText, Image } from 'lucide-vue-next';
defineProps<{ mime?: string }>();
</script>
<template>
    <span class="flex h-10 w-8 shrink-0 flex-col items-center justify-center gap-0.5 rounded border border-primary/25 bg-primary/10 text-primary"
        ><Image v-if="mime?.startsWith('image/')" :size="15" /><FileText v-else :size="15" /><span class="text-[7px] font-bold">{{
            mime ? fileType(mime) : 'DOC'
        }}</span></span
    >
</template>
