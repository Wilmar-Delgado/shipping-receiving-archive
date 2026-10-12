<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, ClipboardList, ArrowRight, FolderArchive } from 'lucide-vue-next';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
const sections = [
 { title: 'Receiving', description: 'Supplier paperwork, material certificates, and returns.', icon: ArrowDownLeft, links: [{ name: 'Vendor Packing Slips', slug: 'vendor-packing-slips' }, { name: 'Material Test Reports (MTRs)', slug: 'material-test-reports' }, { name: 'RMAs', slug: 'rmas' }] },
 { title: 'Shipping', description: 'Customer shipments and courier documentation.', icon: ArrowUpRight, links: [{ name: 'Customer Packing Slips', slug: 'customer-packing-slips' }, { name: 'Waybills', slug: 'waybills' }] },
 { title: 'Job Routers', description: 'Production paperwork, organized in its own archive.', icon: ClipboardList, links: [{ name: 'Job Routers', slug: 'job-routers' }] },
];
</script>
<template>
 <Head title="Dashboard" />
 <ArchiveLayout title="Dashboard">
  <ArchivePageHeading title="A home for your paperwork." description="Keep receiving, shipping, and production records organized in one place. Choose a workspace to get started." eyebrow="Document workspace" />
  <div class="mb-8 flex items-center gap-4 rounded-xl border border-teal-100 bg-teal-50 p-5"><FolderArchive class="shrink-0 text-teal-700" :size="24" /><div><h2 class="text-sm font-semibold text-teal-900">Your archive foundation is ready</h2><p class="mt-1 text-sm text-teal-800">Document uploads, filing, and search are coming in future releases.</p></div></div>
  <div class="grid gap-5 xl:grid-cols-3">
   <section v-for="section in sections" :key="section.title" class="rounded-xl border border-slate-200 bg-white p-6">
    <span class="mb-6 inline-flex rounded-lg bg-slate-50 p-3 text-teal-700"><component :is="section.icon" :size="24" /></span><h2 class="text-lg font-semibold">{{ section.title }}</h2><p class="mt-2 min-h-12 text-sm leading-6 text-slate-500">{{ section.description }}</p>
    <div class="mt-6 border-t border-slate-100 pt-3"><Link v-for="link in section.links" :key="link.slug" :href="'/archive/' + link.slug" class="flex items-center justify-between gap-3 rounded-lg py-3 text-sm text-slate-700 hover:text-teal-700">{{ link.name }}<ArrowRight :size="16" /></Link></div>
   </section>
  </div>
  <div class="mt-8 grid gap-5 sm:grid-cols-2"><Link href="/inbox" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-teal-300"><h2 class="font-semibold">Inbox <span class="ml-2 text-xs font-normal text-slate-400">Coming soon</span></h2><p class="mt-2 text-sm text-slate-500">Review incoming scans before they enter the archive.</p></Link><Link href="/search" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-teal-300"><h2 class="font-semibold">Global Search <span class="ml-2 text-xs font-normal text-slate-400">Coming soon</span></h2><p class="mt-2 text-sm text-slate-500">Find paperwork by PO, job, invoice, or tracking reference.</p></Link></div>
 </ArchiveLayout>
</template>
