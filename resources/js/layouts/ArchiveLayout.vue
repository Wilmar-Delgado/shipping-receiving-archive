<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Archive, LayoutDashboard, Inbox, Search, Users, Menu, X, LogOut, ChevronRight } from 'lucide-vue-next';
defineProps<{ title: string; section?: string }>();
const page = usePage<import('@/types').SharedData & { flash: { success?: string } }>();
const mobileOpen = ref(false);
const user = computed(() => page.props.auth.user);
const groups = [
 { name: 'Workspace', items: [{ name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }, { name: 'Inbox', href: '/inbox', icon: Inbox }, { name: 'Global Search', href: '/search', icon: Search }] },
 { name: 'Receiving', items: [{ name: 'Vendor Packing Slips', href: '/archive/vendor-packing-slips' }, { name: 'Material Test Reports', href: '/archive/material-test-reports' }, { name: 'RMAs', href: '/archive/rmas' }] },
 { name: 'Shipping', items: [{ name: 'Customer Packing Slips', href: '/archive/customer-packing-slips' }, { name: 'Waybills', href: '/archive/waybills' }] },
 { name: 'Production', items: [{ name: 'Job Routers', href: '/archive/job-routers' }] },
];
const active = (href: string) => page.url.split('?')[0] === href;
</script>
<template>
 <div class="min-h-screen bg-slate-50 text-slate-900">
  <button v-if="mobileOpen" aria-label="Close navigation" class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" @click="mobileOpen = false" />
  <aside :class="['fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0', mobileOpen ? 'translate-x-0' : '-translate-x-full']">
   <Link href="/dashboard" class="flex h-24 items-center gap-3 border-b border-slate-100 px-6">
    <span class="rounded-xl bg-teal-700 p-2.5 text-white"><Archive :size="22" /></span>
    <span class="text-sm font-semibold leading-5">Shipping &amp; Receiving<span class="block text-xs font-normal text-slate-500">Document archive</span></span>
   </Link>
   <nav aria-label="Main navigation" class="flex-1 space-y-6 overflow-y-auto px-4 py-6">
    <section v-for="group in groups" :key="group.name">
     <h2 class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ group.name }}</h2>
     <Link v-for="item in group.items" :key="item.href" :href="item.href" :aria-current="active(item.href) ? 'page' : undefined" :class="['my-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors', active(item.href) ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600 hover:bg-slate-100']" @click="mobileOpen = false">
      <component :is="'icon' in item ? item.icon : ChevronRight" :size="17" />{{ item.name }}
     </Link>
    </section>
    <section v-if="user.role === 'admin'">
     <h2 class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Administration</h2>
     <Link href="/admin/users" :class="['flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm', active('/admin/users') ? 'bg-teal-50 font-semibold text-teal-800' : 'text-slate-600 hover:bg-slate-100']"><Users :size="17" />Users</Link>
    </section>
   </nav>
   <div class="border-t border-slate-100 p-5">
    <p class="truncate text-sm font-semibold">{{ user.name }}</p><p class="mt-1 text-xs capitalize text-slate-500">{{ user.role }}</p><Link href="/settings/profile" class="mt-3 block text-xs text-slate-500 hover:text-teal-700">Account settings</Link>
    <Link href="/logout" method="post" as="button" class="mt-4 flex items-center gap-2 text-xs text-slate-500 hover:text-slate-900"><LogOut :size="14" />Sign out</Link>
   </div>
  </aside>
  <div class="lg:pl-64">
   <header class="flex h-20 items-center justify-between border-b border-slate-200 bg-white px-5 sm:px-10">
    <div class="flex items-center gap-4"><button class="lg:hidden" :aria-expanded="mobileOpen" aria-label="Toggle navigation" @click="mobileOpen = !mobileOpen"><X v-if="mobileOpen" :size="22" /><Menu v-else :size="22" /></button><span class="text-sm text-slate-500">{{ section || 'Workspace' }} <span class="mx-2 text-slate-300">/</span> <span class="font-medium text-slate-800">{{ title }}</span></span></div>
    <span class="hidden rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-500 sm:block">Shipping &amp; Receiving Archive</span>
   </header>
   <main class="mx-auto max-w-7xl px-5 py-8 sm:px-10 sm:py-10">
    <div v-if="page.props.flash?.success" role="status" class="mb-6 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">{{ page.props.flash.success }}</div>
    <slot />
   </main>
  </div>
 </div>
</template>
