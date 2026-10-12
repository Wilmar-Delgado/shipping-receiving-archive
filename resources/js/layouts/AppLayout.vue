<script setup lang="ts">
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { BreadcrumbItem } from '@/types';
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), { breadcrumbs: () => [] });
</script>
<template><ArchiveLayout :title="breadcrumbs[breadcrumbs.length - 1]?.title || 'Settings'" section="Account"><slot /></ArchiveLayout></template>
