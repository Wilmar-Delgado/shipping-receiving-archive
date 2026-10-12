<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import type { DocumentFilters, DocumentTypeOption, PartyOption } from '@/types/archive';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
const props = defineProps<{
    filters: DocumentFilters;
    parties: PartyOption[];
    url: string;
    global?: boolean;
    documentTypes?: DocumentTypeOption[];
    placeholder?: string;
    partyKinds?: ('vendor' | 'customer')[];
    showHandled?: boolean;
}>();
const form = useForm({ ...props.filters, document_type_id: props.filters.document_type_id || '' });
const expanded = ref(!!props.filters.from || !!props.filters.to || props.filters.date_field !== 'document_date');
const datePreset = ref(props.filters.from || props.filters.to ? 'custom' : 'all');
const partyChoices = computed(() => (props.partyKinds ? props.parties.filter((party) => props.partyKinds?.includes(party.kind)) : props.parties));
const errors = computed(() => Object.values(form.errors));
watch(
    () => props.filters,
    (filters) => {
        form.defaults({ ...filters, document_type_id: filters.document_type_id || '' });
        if (!form.hasErrors) {
            form.reset();
            datePreset.value = filters.from || filters.to ? 'custom' : 'all';
            if (filters.from || filters.to || filters.date_field !== 'document_date') expanded.value = true;
        }
    },
);
function apply() {
    form.get(props.url, { preserveState: true, preserveScroll: true, replace: true });
}
function clear() {
    form.defaults({ q: '', party_id: '', document_type_id: '', from: '', to: '', date_field: 'document_date', sort: 'newest' });
    form.clearErrors();
    form.reset();
    datePreset.value = 'all';
    expanded.value = false;
    apply();
}
function preset() {
    if (datePreset.value === 'all') {
        form.from = '';
        form.to = '';
        apply();
    } else expanded.value = true;
}
</script>
<template>
    <form :class="global ? 'archive-panel p-4 sm:p-5' : 'border-b border-border p-3'" @submit.prevent="apply">
        <div
            :class="
                global ? 'mb-4 flex items-center gap-3 rounded-lg border border-input bg-background px-3 py-1.5' : 'flex flex-wrap items-center gap-2'
            "
        >
            <div :class="['relative flex-1', global ? 'min-w-0' : 'min-w-48']">
                <Search :size="global ? 18 : 15" class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-primary" /><label
                    for="filter-q"
                    class="sr-only"
                    >{{ global ? 'Search all filed documents' : 'Reference search' }}</label
                ><input
                    id="filter-q"
                    v-model="form.q"
                    maxlength="255"
                    :class="['archive-field w-full pl-8', global && 'border-0 bg-transparent focus:ring-0']"
                    :placeholder="
                        placeholder || (global ? 'Search by PO, job, invoice, tracking number, vendor, or filename...' : 'Search references...')
                    "
                />
            </div>
            <button v-if="global" :disabled="form.processing" class="archive-button-primary">
                {{ form.processing ? 'Searching...' : 'Search' }}
            </button>
            <template v-if="!global"
                ><label for="date-preset" class="sr-only">Date range</label
                ><select id="date-preset" v-model="datePreset" class="archive-field w-32 text-xs" @change="preset">
                    <option value="all">All dates</option>
                    <option value="custom">Date range</option></select
                ><label for="filter-sort" class="sr-only">Sort order</label
                ><select id="filter-sort" v-model="form.sort" class="archive-field w-36 text-xs" @change="apply">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option></select
                ><button type="submit" :disabled="form.processing" class="archive-button">
                    <Search :size="14" /><span class="sr-only">Apply reference search</span>
                </button></template
            >
            <button
                v-if="!global"
                type="button"
                class="archive-button"
                :aria-expanded="expanded"
                aria-controls="advanced-filters"
                @click="expanded = !expanded"
            >
                <SlidersHorizontal :size="14" />Filters
            </button>
        </div>
        <div v-if="global" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1.2fr_1fr_1fr_auto_auto]">
            <div>
                <label for="filter-category" class="archive-filter-label">Category</label
                ><select id="filter-category" v-model="form.document_type_id" class="archive-field w-full text-xs" @change="apply">
                    <option value="">All categories</option>
                    <option v-for="type in documentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
            </div>
            <div>
                <label for="filter-party" class="archive-filter-label">Vendor / customer</label
                ><select id="filter-party" v-model="form.party_id" class="archive-field w-full text-xs" @change="apply">
                    <option value="">All vendors &amp; customers</option>
                    <option v-for="party in partyChoices" :key="party.id" :value="party.id">{{ party.name }}</option>
                </select>
            </div>
            <div>
                <label for="date-preset" class="archive-filter-label">Date range</label
                ><select id="date-preset" v-model="datePreset" class="archive-field w-full text-xs" @change="preset">
                    <option value="all">Any time</option>
                    <option value="custom">Custom range</option>
                </select>
            </div>
            <div>
                <label for="filter-sort" class="archive-filter-label">Sort order</label
                ><select id="filter-sort" v-model="form.sort" class="archive-field w-full text-xs" @change="apply">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </div>
            <button type="button" class="archive-button" :aria-expanded="expanded" aria-controls="advanced-filters" @click="expanded = !expanded">
                <SlidersHorizontal :size="14" />More filters<ChevronDown :size="12" /></button
            ><button type="button" class="text-xs text-muted-foreground hover:text-primary" @click="clear">Clear</button>
        </div>
        <div v-if="expanded" id="advanced-filters" class="mt-4 grid items-end gap-3 border-t border-border pt-4 sm:grid-cols-2 xl:grid-cols-5">
            <div v-if="!global && (partyChoices.length || form.party_id)">
                <label for="filter-party" class="archive-filter-label">Vendor / customer</label
                ><select id="filter-party" v-model="form.party_id" class="archive-field w-full text-xs">
                    <option value="">All parties</option>
                    <option v-for="party in partyChoices" :key="party.id" :value="party.id">{{ party.name }}</option>
                </select>
            </div>
            <div>
                <label for="filter-date-field" class="archive-filter-label">Date to filter</label
                ><select id="filter-date-field" v-model="form.date_field" class="archive-field w-full text-xs">
                    <option value="document_date">Document date</option>
                    <option v-if="showHandled !== false || form.date_field === 'handled_on'" value="handled_on">Handled date</option>
                    <option value="created_at">Upload date (UTC)</option>
                </select>
            </div>
            <div>
                <label for="filter-from" class="archive-filter-label">From</label
                ><input id="filter-from" v-model="form.from" type="date" class="archive-field w-full text-xs" />
            </div>
            <div>
                <label for="filter-to" class="archive-filter-label">To</label
                ><input id="filter-to" v-model="form.to" type="date" class="archive-field w-full text-xs" />
            </div>
            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="archive-button-primary">Apply filters</button
                ><button v-if="!global" type="button" aria-label="Clear all filters" class="archive-icon-button" @click="clear">
                    <X :size="16" />
                </button>
            </div>
        </div>
        <div v-if="errors.length" role="alert" class="mt-3"><InputError v-for="error in errors" :key="error" :message="error" /></div>
        <button
            v-if="!global && !expanded && (form.q || form.party_id || form.from || form.to)"
            type="button"
            class="mt-2 text-xs text-primary"
            @click="clear"
        >
            Clear filters
        </button>
    </form>
</template>
