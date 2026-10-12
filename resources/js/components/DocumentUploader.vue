<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { fileSize, type DocumentTypeOption } from '@/types/archive';
import { useForm } from '@inertiajs/vue3';
import { Camera, LoaderCircle, UploadCloud, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
const props = defineProps<{
    documentTypes: DocumentTypeOption[];
    limits: { maxKb: number; maxFiles: number; maxRequestBytes: number | null };
    initialCategoryId?: number | null;
    initiallyOpen?: boolean;
}>();
const expanded = ref(!!props.initiallyOpen);
const form = useForm<{ document_type_id: string; files: File[] }>({
    document_type_id: props.initialCategoryId ? String(props.initialCategoryId) : '',
    files: [],
});
const picker = ref<HTMLInputElement | null>(null);
const camera = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const localError = ref('');
const errors = computed(() => [...new Set(Object.values(form.errors))]);
const totalSize = computed(() => form.files.reduce((total, file) => total + file.size, 0));
function addFiles(files: FileList | File[] | null) {
    if (!files || form.processing) return;
    localError.value = '';
    expanded.value = true;
    const selected = Array.from(files);
    if (form.files.length + selected.length > props.limits.maxFiles) {
        localError.value = `Choose no more than ${props.limits.maxFiles} files per upload.`;
        return;
    }
    for (const file of selected) {
        if (!/\.(pdf|jpe?g|png)$/i.test(file.name) || (file.type && !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type))) {
            localError.value = `${file.name}: choose a PDF, JPG, or PNG file.`;
            return;
        }
        if (!file.size) {
            localError.value = `${file.name}: this file is empty.`;
            return;
        }
        if (file.size > props.limits.maxKb * 1024) {
            localError.value = `${file.name}: the per-file limit is ${fileSize(props.limits.maxKb * 1024)}.`;
            return;
        }
    }
    if (
        props.limits.maxRequestBytes &&
        totalSize.value + selected.reduce((sum, file) => sum + file.size, 0) + 1024 * 1024 > props.limits.maxRequestBytes
    ) {
        localError.value = 'This batch exceeds the server request limit. Upload fewer files at a time.';
        return;
    }
    form.clearErrors();
    form.files = [...form.files, ...selected];
}
function picked(event: Event) {
    const input = event.target as HTMLInputElement;
    addFiles(input.files);
    input.value = '';
}
function dropped(event: DragEvent) {
    dragging.value = false;
    addFiles(event.dataTransfer?.files || null);
}
function submit() {
    localError.value = '';
    if (!form.files.length) {
        localError.value = 'Choose at least one file.';
        return;
    }
    form.post('/inbox', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('files');
            expanded.value = false;
        },
    });
}
function open() {
    expanded.value = true;
    picker.value?.click();
}
defineExpose({ open });
</script>
<template>
    <section>
        <form @submit.prevent="submit">
            <div
                :class="[
                    'flex flex-wrap items-center gap-4 rounded-xl border border-dashed p-5 transition-colors',
                    dragging ? 'border-primary bg-primary/10' : 'border-input bg-card/40',
                ]"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="dropped"
            >
                <span class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"><UploadCloud :size="21" /></span
                ><button type="button" :disabled="form.processing" class="min-w-0 flex-1 text-left" @click="open">
                    <span class="block text-sm font-semibold">Drop documents here to upload</span
                    ><span class="mt-1 block text-xs text-muted-foreground"
                        >or click to browse · PDF, JPG, PNG up to {{ fileSize(limits.maxKb * 1024) }} per file</span
                    ></button
                ><button
                    type="button"
                    :disabled="form.processing"
                    class="archive-button"
                    @click="
                        expanded = true;
                        camera?.click();
                    "
                >
                    <Camera :size="15" />Take photo</button
                ><input
                    ref="picker"
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    class="hidden"
                    aria-label="Choose scanned documents"
                    @change="picked"
                /><input
                    ref="camera"
                    type="file"
                    accept="image/jpeg,image/png"
                    capture="environment"
                    class="hidden"
                    aria-label="Take a document photo"
                    @change="picked"
                />
            </div>
            <div v-if="expanded || form.files.length || localError || errors.length" class="mt-3 rounded-xl border border-border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="w-full sm:max-w-md">
                        <label for="upload-category" class="archive-filter-label">Document category</label
                        ><select
                            id="upload-category"
                            v-model="form.document_type_id"
                            required
                            :disabled="form.processing"
                            class="archive-field w-full"
                        >
                            <option value="" disabled>Select a category</option>
                            <option v-for="type in documentTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option>
                        </select>
                    </div>
                    <button type="button" :disabled="form.processing" class="archive-button" @click="open">Choose files</button>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Each file becomes a separate Inbox document. Up to {{ limits.maxFiles }} files per batch.
                </p>
                <ul v-if="form.files.length" class="mt-4 divide-y divide-border">
                    <li v-for="(file, index) in form.files" :key="index" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ file.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ fileSize(file.size) }}</p>
                        </div>
                        <button
                            type="button"
                            :disabled="form.processing"
                            :aria-label="'Remove ' + file.name"
                            class="archive-icon-button"
                            @click="form.files.splice(index, 1)"
                        >
                            <X :size="16" />
                        </button>
                    </li>
                </ul>
                <InputError v-if="localError" :message="localError" class="mt-4" /><InputError
                    v-for="error in errors"
                    :key="error"
                    :message="error"
                    class="mt-2"
                />
                <div v-if="form.progress" role="status" class="mt-4">
                    <p class="mb-2 text-xs text-muted-foreground">
                        {{ form.progress.percentage === 100 ? 'Saving documents...' : `Uploading ${form.progress.percentage}%` }}
                    </p>
                    <progress :value="form.progress.percentage" max="100" class="h-2 w-full accent-teal-500" />
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">{{ form.files.length }} file(s) · {{ fileSize(totalSize) }}</p>
                    <button :disabled="form.processing || !form.files.length" class="archive-button-primary">
                        <LoaderCircle v-if="form.processing" class="animate-spin" :size="16" />{{
                            form.processing ? 'Uploading...' : 'Upload to Inbox'
                        }}
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>
