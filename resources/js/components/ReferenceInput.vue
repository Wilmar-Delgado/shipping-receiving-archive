<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { referenceLabels, type DocumentReference, type ReferenceKind } from '@/types/archive';
import { Plus, X } from 'lucide-vue-next';
const props = defineProps<{
    modelValue: DocumentReference[];
    primaryKind?: ReferenceKind | null;
    requiredKind?: ReferenceKind | null;
    errors: Record<string, string>;
    disabled?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [DocumentReference[]] }>();
function change(index: number, field: 'kind' | 'value', event: Event) {
    const references = props.modelValue.map((reference) => ({ ...reference }));
    const value = (event.target as HTMLInputElement).value;
    if (field === 'kind') references[index].kind = value as ReferenceKind;
    else references[index].value = value;
    emit('update:modelValue', references);
}
</script>
<template>
    <fieldset :disabled="disabled" class="space-y-3">
        <legend class="mb-2 text-sm font-semibold">References</legend>
        <p class="text-xs text-muted-foreground">
            Add each PO, job, invoice, or tracking number separately. Formatting is preserved.<span v-if="requiredKind">
                At least one {{ referenceLabels[requiredKind] }} reference is required to file.</span
            >
        </p>
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div v-for="(reference, index) in modelValue" :key="index">
                <div class="flex items-start gap-2">
                    <select
                        :id="`reference-kind-${index}`"
                        :value="reference.kind"
                        :aria-label="`Reference ${index + 1} kind`"
                        class="archive-field w-28 shrink-0"
                        @change="change(index, 'kind', $event)"
                    >
                        <option v-for="(label, kind) in referenceLabels" :key="kind" :value="kind">{{ label }}</option>
                    </select>
                    <input
                        :id="`reference-value-${index}`"
                        :value="reference.value"
                        :aria-label="`Reference ${index + 1} value`"
                        maxlength="255"
                        class="archive-field min-w-0 flex-1"
                        placeholder="Reference number"
                        @input="change(index, 'value', $event)"
                    />
                    <button
                        type="button"
                        :aria-label="`Remove reference ${index + 1}`"
                        class="p-2 text-muted-foreground hover:text-red-500"
                        @click="
                            emit(
                                'update:modelValue',
                                modelValue.filter((_, position) => position !== index),
                            )
                        "
                    >
                        <X :size="16" />
                    </button>
                </div>
                <InputError :message="errors[`references.${index}.kind`]" /><InputError :message="errors[`references.${index}.value`]" />
            </div>
        </div>
        <InputError :message="errors.references" />
        <button
            type="button"
            :disabled="modelValue.length >= 100"
            class="inline-flex items-center gap-2 text-xs font-medium text-primary disabled:opacity-50"
            @click="emit('update:modelValue', [...modelValue, { kind: primaryKind || 'po', value: '' }])"
        >
            <Plus :size="14" />Add reference
        </button>
    </fieldset>
</template>
