<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import InputError from '@/components/InputError.vue';
interface User { id: number; name: string; email: string; role: string; is_active: boolean }
defineProps<{ users: { data: User[]; links: { url: string | null; label: string; active: boolean }[] } }>();
const currentId = (usePage().props.auth as { user: User }).user.id;
const form = useForm({ name: '', email: '', role: 'staff', password: '', password_confirmation: '' });
const busy = ref<number | null>(null);
const submit = () => form.post('/admin/users', { onSuccess: () => form.reset() });
const toggle = (user: User) => {
 busy.value = user.id;
 router.patch('/admin/users/' + user.id, { is_active: !user.is_active }, { preserveScroll: true, onFinish: () => busy.value = null });
};
</script>
<template>
 <Head title="Users" />
 <ArchiveLayout title="Users" section="Administration">
  <ArchivePageHeading title="Manage your team" description="Create accounts and control access to the archive. Only administrators can manage users." eyebrow="Administration" />
  <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
   <section class="overflow-hidden rounded-xl border border-slate-200 bg-white"><h2 class="border-b border-slate-100 px-6 py-5 font-semibold">Team members</h2><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-6 py-4">User</th><th class="p-4">Role</th><th class="p-4">Status</th><th class="p-4"><span class="sr-only">Actions</span></th></tr></thead><tbody><tr v-for="user in users.data" :key="user.id" class="border-t border-slate-100"><td class="px-6 py-5"><p class="font-medium">{{ user.name }}</p><p class="mt-1 text-xs text-slate-500">{{ user.email }}</p></td><td class="p-4 capitalize text-slate-500">{{ user.role }}</td><td class="p-4"><span :class="['rounded-full px-2.5 py-1 text-xs', user.is_active ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-500']">{{ user.is_active ? 'Active' : 'Inactive' }}</span></td><td class="p-4"><span v-if="user.id === currentId" class="text-xs text-slate-400">You</span><button v-else :disabled="busy !== null" class="text-xs font-medium text-teal-700 disabled:opacity-50" :aria-label="(user.is_active ? 'Deactivate ' : 'Activate ') + user.name" @click="toggle(user)">{{ user.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr></tbody></table></div>
   <nav v-if="users.links.length > 3" aria-label="User pagination" class="flex flex-wrap gap-2 border-t border-slate-100 p-4"><template v-for="(link, index) in users.links" :key="index"><Link v-if="link.url" :href="link.url" :class="['rounded px-3 py-2 text-xs', link.active ? 'bg-teal-700 text-white' : 'bg-slate-50 text-slate-600']"><span v-html="link.label" /></Link><span v-else class="px-3 py-2 text-xs text-slate-300" v-html="link.label" /></template></nav></section>
   <section class="rounded-xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Create user</h2><p class="mt-2 text-xs leading-5 text-slate-500">New accounts are active immediately. Share the initial password through your approved internal channel.</p><form class="mt-6 space-y-4" @submit.prevent="submit">
    <div><label for="name" class="text-xs font-medium">Full name</label><input id="name" v-model="form.name" required maxlength="255" autocomplete="name" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm" /><InputError :message="form.errors.name" /></div>
    <div><label for="email" class="text-xs font-medium">Email</label><input id="email" v-model="form.email" type="email" required autocomplete="email" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm" /><InputError :message="form.errors.email" /></div>
    <div><label for="role" class="text-xs font-medium">Role</label><select id="role" v-model="form.role" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm"><option value="staff">Staff</option><option value="admin">Admin</option></select><InputError :message="form.errors.role" /></div>
    <div><label for="password" class="text-xs font-medium">Initial password</label><input id="password" v-model="form.password" type="password" required minlength="12" autocomplete="new-password" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm" /><p class="mt-1 text-xs text-slate-400">At least 12 characters.</p><InputError :message="form.errors.password" /></div>
    <div><label for="password_confirmation" class="text-xs font-medium">Confirm password</label><input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm" /></div>
    <button :disabled="form.processing" class="w-full rounded-lg bg-teal-700 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">{{ form.processing ? 'Creating…' : 'Create user' }}</button>
   </form></section>
  </div>
 </ArchiveLayout>
</template>
