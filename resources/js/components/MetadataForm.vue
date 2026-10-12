<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ReferenceInput from '@/components/ReferenceInput.vue';
import { type DocumentReference, type MetadataDocumentType, type PartyOption, type ReviewDocument } from '@/types/archive';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
const props = defineProps<{ document: ReviewDocument; documentTypes: MetadataDocumentType[]; parties: PartyOption[] }>();
function values(document: ReviewDocument) {
    return {
        document_type_id: document.document_type_id,
        party_id: document.party_id as number | null,
        document_date: document.document_date || '',
        handled_on: document.handled_on || '',
        carrier: document.carrier || '',
        notes: document.notes || '',
        references: document.references.map((reference) => ({ ...reference })) as DocumentReference[],
        new_party_name: '',
        new_party_kind: null as 'vendor' | 'customer' | null,
    };
}
const form = useForm(values(props.document));
const addingParty = ref(false);
const action = ref<'save' | 'file'>('save');
const type = computed(() => props.documentTypes.find((type) => type.id === Number(form.document_type_id)));
const configuration = computed(() => type.value?.metadata);
const parties = computed(() => props.parties.filter((party) => configuration.value?.partyKinds.includes(party.kind)));
const partyLabel = computed(() => (configuration.value?.partyKinds.length === 1 ? configuration.value.partyKinds[0] : 'vendor/customer'));
watch(
    () => props.document,
    (document) => {
        form.defaults(values(document));
        form.reset();
        addingParty.value = false;
    },
);
watch(
    () => form.document_type_id,
    () => {
        if (form.party_id && !parties.value.some((party) => party.id === form.party_id)) form.party_id = null;
        if (addingParty.value) {
            form.new_party_kind = configuration.value?.partyKinds[0] || null;
            if (!configuration.value?.partyKinds.length) {
                addingParty.value = false;
                form.new_party_name = '';
            }
        }
    },
);
function newParty() {
    addingParty.value = true;
    form.party_id = null;
    form.new_party_kind = configuration.value?.partyKinds[0] || null;
}
function cancelParty() {
    addingParty.value = false;
    form.new_party_name = '';
    form.new_party_kind = null;
}
function save(file = false) {
    action.value = file ? 'file' : 'save';
    const url = '/documents/' + props.document.uuid;
    if (file) form.post(url + '/file', { preserveScroll: true });
    else form.patch(url, { preserveScroll: true });
}
</script>
<template>
    <section class="rounded-xl border border-border bg-card p-5">
        <h2 class="text-sm font-semibold">Document details</h2>
        <p class="mt-2 text-xs leading-5 text-muted-foreground">
            {{
                document.status === 'filed'
                    ? 'Corrections keep this document filed. The original stays unchanged.'
                    : 'Save incomplete work as a draft, or file it when the required details are ready.'
            }}
        </p>
        <form class="mt-4 space-y-4" @submit.prevent="save(false)">
            <fieldset :disabled="!document.can_update || form.processing" class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="metadata-category" class="text-xs font-medium">Document category</label
                    ><select id="metadata-category" v-model="form.document_type_id" class="archive-field mt-2 w-full">
                        <option v-for="type in documentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select
                    ><InputError :message="form.errors.document_type_id" />
                </div>
                <div v-if="configuration?.partyKinds.length">
                    <label for="metadata-party" class="text-xs font-medium capitalize"
                        >{{ partyLabel
                        }}<span v-if="configuration.partyRequired" class="ml-2 text-xs font-normal text-muted-foreground"
                            >Required to file</span
                        ></label
                    >
                    <template v-if="!addingParty"
                        ><select id="metadata-party" v-model="form.party_id" class="archive-field mt-2 w-full">
                            <option :value="null">Not selected</option>
                            <option v-for="party in parties" :key="party.id" :value="party.id">{{ party.name }} ({{ party.kind }})</option></select
                        ><button type="button" class="mt-2 text-xs text-primary" @click="newParty">Add a new {{ partyLabel }}</button></template
                    >
                    <div v-else class="mt-2 space-y-2 rounded-lg border border-border p-3">
                        <input
                            id="metadata-party"
                            v-model="form.new_party_name"
                            maxlength="255"
                            class="archive-field w-full"
                            :placeholder="`New ${partyLabel} name`"
                        /><select
                            v-if="configuration.partyKinds.length > 1"
                            v-model="form.new_party_kind"
                            aria-label="New party kind"
                            class="archive-field w-full"
                        >
                            <option v-for="kind in configuration.partyKinds" :key="kind" :value="kind">{{ kind }}</option></select
                        ><button type="button" class="text-xs text-muted-foreground" @click="cancelParty">Choose an existing party instead</button
                        ><InputError :message="form.errors.new_party_name" /><InputError :message="form.errors.new_party_kind" />
                    </div>
                    <InputError :message="form.errors.party_id" />
                </div>
                <div>
                    <label for="document-date" class="text-xs font-medium">Document date</label
                    ><input id="document-date" v-model="form.document_date" type="date" class="archive-field mt-2 w-full" />
                    <p class="mt-1 text-xs text-muted-foreground">The date printed on the document, if known.</p>
                    <InputError :message="form.errors.document_date" />
                </div>
                <div v-if="configuration?.showHandled">
                    <label for="handled-date" class="text-xs font-medium"
                        >{{ configuration.handledLabel
                        }}<span v-if="configuration.requiresHandled" class="ml-2 text-xs font-normal text-muted-foreground"
                            >Required to file</span
                        ></label
                    ><input id="handled-date" v-model="form.handled_on" type="date" class="archive-field mt-2 w-full" />
                    <p class="mt-1 text-xs text-muted-foreground">The actual material receiving or pickup date. Enter it explicitly.</p>
                    <InputError :message="form.errors.handled_on" />
                </div>
                <div v-if="configuration?.showCarrier">
                    <label for="carrier" class="text-xs font-medium"
                        >Carrier<span v-if="configuration.carrierRequired" class="ml-2 text-xs font-normal text-muted-foreground"
                            >Required to file</span
                        ></label
                    ><input id="carrier" v-model="form.carrier" maxlength="255" class="archive-field mt-2 w-full" /><InputError
                        :message="form.errors.carrier"
                    />
                </div>
                <ReferenceInput
                    class="sm:col-span-2"
                    v-model="form.references"
                    :primary-kind="configuration?.primaryKind"
                    :required-kind="configuration?.requiredReference"
                    :errors="form.errors as Record<string, string>"
                />
                <div class="sm:col-span-2">
                    <label for="metadata-notes" class="text-xs font-medium">Notes</label
                    ><textarea
                        id="metadata-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="10000"
                        class="archive-field mt-2 w-full"
                        placeholder="Optional document notes"
                    /><InputError :message="form.errors.notes" />
                </div>
            </fieldset>
            <div v-if="document.can_update" class="flex flex-wrap gap-3">
                <button
                    :disabled="form.processing"
                    class="flex-1 rounded-lg border border-border px-4 py-2.5 text-sm font-semibold disabled:opacity-50"
                >
                    {{ form.processing && action === 'save' ? 'Saving...' : document.status === 'filed' ? 'Save corrections' : 'Save Draft' }}</button
                ><button
                    v-if="document.can_file"
                    type="button"
                    :disabled="form.processing"
                    class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    @click="save(true)"
                >
                    {{ form.processing && action === 'file' ? 'Filing...' : 'File Document' }}
                </button>
            </div>
        </form>
    </section>
</template>
