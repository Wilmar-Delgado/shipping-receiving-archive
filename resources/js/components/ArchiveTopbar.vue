<script setup lang="ts">
import UserDropdown from '@/components/UserDropdown.vue';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Menu } from 'lucide-vue-next';
defineProps<{ title: string; section?: string; mobileOpen: boolean }>();
const emit = defineEmits<{ toggle: [] }>();
const page = usePage<SharedData>();
</script>
<template>
    <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-border bg-background px-5 sm:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <button
                class="lg:hidden"
                aria-label="Toggle navigation"
                aria-controls="archive-mobile-navigation"
                :aria-expanded="mobileOpen"
                @click="emit('toggle')"
            >
                <Menu :size="20" />
            </button>
            <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-xs">
                <Link href="/dashboard" class="text-muted-foreground hover:text-primary">Archive</Link
                ><ChevronRight :size="12" class="text-muted-foreground" /><span
                    v-if="section && section !== 'Workspace'"
                    class="hidden text-muted-foreground sm:inline"
                    >{{ section }}</span
                ><ChevronRight v-if="section && section !== 'Workspace'" :size="12" class="hidden text-muted-foreground sm:block" /><span
                    class="truncate font-semibold"
                    aria-current="page"
                    >{{ title }}</span
                >
            </nav>
        </div>
        <UserDropdown :user="page.props.auth.user" />
    </header>
</template>
