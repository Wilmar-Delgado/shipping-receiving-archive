<script setup lang="ts">
import type { User } from '@/types';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Settings } from 'lucide-vue-next';
defineProps<{ user: User }>();
const initials = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
</script>
<template>
    <Menu as="div" class="relative"
        ><MenuButton
            class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-400"
            ><span class="flex size-8 items-center justify-center rounded-full bg-teal-200 text-xs font-bold text-slate-950">{{
                initials(user.name)
            }}</span
            ><span class="hidden max-w-40 truncate font-semibold sm:block">{{ user.name }}</span
            ><ChevronDown :size="14" class="text-muted-foreground" /><span class="sr-only">Open user menu</span></MenuButton
        ><MenuItems class="absolute right-0 z-50 mt-3 w-64 rounded-xl border border-border bg-card p-2 shadow-2xl focus:outline-none"
            ><div class="border-b border-border px-3 py-3">
                <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                <p class="mt-1 truncate text-xs text-muted-foreground">{{ user.email }}</p>
                <p class="mt-1 text-xs capitalize text-muted-foreground">{{ user.role }}</p>
            </div>
            <MenuItem v-slot="{ active }"
                ><Link href="/settings/profile" :class="['mt-2 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm', active && 'bg-muted']"
                    ><Settings :size="16" />Account Settings</Link
                ></MenuItem
            ><MenuItem v-slot="{ active }"
                ><Link
                    href="/logout"
                    method="post"
                    as="button"
                    :class="['flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm', active && 'bg-muted']"
                    ><LogOut :size="16" />Sign Out</Link
                ></MenuItem
            ></MenuItems
        ></Menu
    >
</template>
