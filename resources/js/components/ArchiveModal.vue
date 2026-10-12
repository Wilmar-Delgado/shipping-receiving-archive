<script setup lang="ts">
import { Dialog, DialogDescription, DialogPanel, DialogTitle } from '@headlessui/vue';
import { X } from 'lucide-vue-next';
withDefaults(defineProps<{ open: boolean; title: string; description?: string; wide?: boolean }>(), { wide: false });
defineEmits<{ close: [] }>();
</script>
<template>
    <Dialog :open="open" class="archive-shell relative z-50" @close="$emit('close')">
        <div class="fixed inset-0 bg-black/75" aria-hidden="true" />
        <div class="fixed inset-0 overflow-y-auto p-3 sm:p-6">
            <div class="flex min-h-full items-center justify-center">
                <DialogPanel
                    :class="['w-full rounded-xl border border-border bg-card text-card-foreground shadow-2xl', wide ? 'max-w-6xl' : 'max-w-lg']"
                >
                    <header class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                        <div class="min-w-0">
                            <DialogTitle class="break-words text-base font-semibold">{{ title }}</DialogTitle>
                            <DialogDescription v-if="description" class="mt-1 text-xs text-muted-foreground">{{ description }}</DialogDescription>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <slot name="actions" />
                            <button type="button" class="archive-icon-button" aria-label="Close dialog" title="Close" @click="$emit('close')">
                                <X :size="20" />
                            </button>
                        </div>
                    </header>
                    <slot />
                </DialogPanel>
            </div>
        </div>
    </Dialog>
</template>
