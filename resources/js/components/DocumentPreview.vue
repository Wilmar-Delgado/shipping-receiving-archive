<script setup lang="ts">
import { Download, ExternalLink, LoaderCircle, RotateCcw, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
const props = withDefaults(defineProps<{ uuid: string; filename: string; available: boolean; heading?: boolean }>(), { heading: true });
const mime = ref('');
const preparing = ref(false);
const loadingViewer = ref(false);
const ready = ref(false);
const error = ref('');
const zoom = ref(100);
const imageWidth = ref(0);
const previewUrl = computed(() => `/documents/${props.uuid}/preview`);
let controller: AbortController | null = null;
async function load() {
    controller?.abort();
    ready.value = false;
    error.value = '';
    zoom.value = 100;
    imageWidth.value = 0;
    loadingViewer.value = false;
    if (!props.available) {
        preparing.value = false;
        error.value = 'The original file is unavailable. Contact an administrator for help.';
        return;
    }
    const request = new AbortController();
    controller = request;
    preparing.value = true;
    try {
        // HEAD checks access/type without buffering a PDF or disabling native range requests.
        const response = await fetch(previewUrl.value, { method: 'HEAD', credentials: 'same-origin', cache: 'no-store', signal: request.signal });
        const type = response.headers.get('Content-Type')?.split(';')[0] || '';
        if (!response.ok || !['application/pdf', 'image/jpeg', 'image/png'].includes(type)) {
            throw new Error(
                response.status === 404
                    ? 'The original file is unavailable. Contact an administrator for help.'
                    : 'The preview could not be loaded. Check your access or try again.',
            );
        }
        if (request.signal.aborted) return;
        mime.value = type;
        loadingViewer.value = true;
        ready.value = true;
    } catch (exception) {
        if (!request.signal.aborted) error.value = exception instanceof Error ? exception.message : 'The preview could not be loaded.';
    } finally {
        if (!request.signal.aborted) preparing.value = false;
    }
}
// Metadata edits do not recreate the viewer: watch only immutable identity and availability.
watch([() => props.uuid, () => props.available], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());
function imageLoaded(event: Event) {
    imageWidth.value = (event.target as HTMLImageElement).naturalWidth;
    loadingViewer.value = false;
}
function failed() {
    loadingViewer.value = false;
    ready.value = false;
    error.value = 'Your browser could not display this original. Try opening it separately or downloading it.';
}
</script>
<template>
    <section :class="heading ? 'archive-panel' : ''">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
            <h2 v-if="heading" class="text-sm font-semibold">Document preview</h2>
            <p v-else class="text-xs text-muted-foreground">Original document</p>
            <div class="flex flex-wrap items-center gap-2">
                <template v-if="ready && mime.startsWith('image/')">
                    <button
                        type="button"
                        class="archive-icon-button"
                        aria-label="Zoom out"
                        title="Zoom out"
                        :disabled="loadingViewer || zoom <= 50"
                        @click="zoom = Math.max(50, zoom - 25)"
                    >
                        <ZoomOut :size="16" />
                    </button>
                    <span class="min-w-10 text-center text-xs text-muted-foreground" aria-live="polite">{{ zoom }}%</span>
                    <button
                        type="button"
                        class="archive-icon-button"
                        aria-label="Zoom in"
                        title="Zoom in"
                        :disabled="loadingViewer || zoom >= 200"
                        @click="zoom = Math.min(200, zoom + 25)"
                    >
                        <ZoomIn :size="16" />
                    </button>
                    <button type="button" class="archive-button" :disabled="loadingViewer" @click="zoom = 100">Fit</button>
                </template>
                <template v-if="available">
                    <a :href="previewUrl" target="_blank" rel="noopener noreferrer" class="archive-button"
                        ><ExternalLink :size="14" />Open separately</a
                    >
                    <a :href="`/documents/${uuid}/download`" class="archive-button-primary"><Download :size="14" />Download</a>
                </template>
            </div>
        </header>
        <div class="relative bg-muted/30" :aria-busy="preparing || loadingViewer">
            <div v-if="preparing" role="status" class="flex items-center justify-center gap-2 px-5 py-16 text-sm text-muted-foreground">
                <LoaderCircle class="animate-spin" :size="18" />Loading preview…
            </div>
            <div v-else-if="error" class="px-5 py-12 text-center">
                <p role="status" class="text-sm text-secondary-foreground">{{ error }}</p>
                <button v-if="available" type="button" class="archive-button mt-4" @click="load"><RotateCcw :size="14" />Retry preview</button>
            </div>
            <template v-else-if="ready">
                <p v-if="loadingViewer" role="status" class="px-4 py-2 text-xs text-muted-foreground">Loading document viewer…</p>
                <iframe
                    v-if="mime === 'application/pdf'"
                    :src="previewUrl"
                    :title="`PDF preview: ${filename}`"
                    class="h-[60dvh] max-h-[760px] min-h-[280px] w-full border-0"
                    @load="loadingViewer = false"
                    @error="failed"
                />
                <div v-else class="max-h-[65dvh] overflow-auto p-3" tabindex="0" role="region" aria-label="Image preview">
                    <img
                        :src="previewUrl"
                        :alt="`Original scan: ${filename}`"
                        class="mx-auto h-auto"
                        :style="
                            zoom === 100 ? { maxWidth: '100%', maxHeight: '60dvh' } : { width: `${(imageWidth * zoom) / 100}px`, maxWidth: 'none' }
                        "
                        @load="imageLoaded"
                        @error="failed"
                    />
                </div>
            </template>
        </div>
        <p v-if="available" class="border-t border-border px-4 py-3 text-xs leading-5 text-muted-foreground">
            {{ mime === 'application/pdf' ? 'Page navigation and zoom use your browser’s PDF viewer, when supported. ' : '' }}If the embedded viewer
            does not work, open the original separately or download it.
        </p>
    </section>
</template>
