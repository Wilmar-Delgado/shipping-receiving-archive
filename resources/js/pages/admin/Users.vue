<script setup lang="ts">
import ArchiveModal from '@/components/ArchiveModal.vue';
import ArchivePageHeading from '@/components/ArchivePageHeading.vue';
import ArchivePagination from '@/components/ArchivePagination.vue';
import DocumentStatusBadge from '@/components/DocumentStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import ArchiveLayout from '@/layouts/ArchiveLayout.vue';
import type { DocumentPage } from '@/types/archive';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
}
defineProps<{ users: DocumentPage<User> }>();
const currentId = (usePage().props.auth as { user: User }).user.id;
const form = useForm({ name: '', email: '', role: 'staff', password: '' });
const creating = ref(false);
const createButton = ref<HTMLButtonElement | null>(null);
const busy = ref<number | null>(null);
function close() {
    if (form.processing) return;
    creating.value = false;
    form.reset();
    form.clearErrors();
}
const submit = () =>
    form
        .transform((data) => ({ ...data, password_confirmation: data.password }))
        .post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => {
                creating.value = false;
                form.reset();
                form.clearErrors();
                void nextTick(() => createButton.value?.focus());
            },
        });
const toggle = (user: User) => {
    busy.value = user.id;
    router.patch('/admin/users/' + user.id, { is_active: !user.is_active }, { preserveScroll: true, onFinish: () => (busy.value = null) });
};
</script>
<template>
    <Head title="Users" />
    <ArchiveLayout title="Users" section="Administration">
        <ArchivePageHeading
            title="Manage your team"
            description="Create accounts and control access to the archive. Only administrators can manage users."
            eyebrow="Administration"
        >
            <button ref="createButton" type="button" class="archive-button-primary" @click="creating = true">
                <UserPlus :size="16" />Create User
            </button>
        </ArchivePageHeading>
        <section class="archive-panel">
            <header class="archive-panel-heading">
                <h2 class="text-sm font-semibold">Team members</h2>
                <p class="text-xs text-muted-foreground">{{ users.total }} accounts</p>
            </header>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Team members table">
                <table class="archive-table min-w-[580px]">
                    <thead>
                        <tr>
                            <th class="w-[40%]">User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td>
                                <p class="font-semibold">{{ user.name }}</p>
                                <p class="mt-1 break-words text-xs text-muted-foreground">{{ user.email }}</p>
                            </td>
                            <td class="capitalize text-secondary-foreground">{{ user.role }}</td>
                            <td>
                                <DocumentStatusBadge :label="user.is_active ? 'Active' : 'Inactive'" :tone="user.is_active ? 'teal' : 'neutral'" />
                            </td>
                            <td class="text-right">
                                <span v-if="user.id === currentId" class="text-xs text-muted-foreground">You</span
                                ><button
                                    v-else
                                    type="button"
                                    :disabled="busy !== null"
                                    class="archive-button"
                                    :aria-label="(user.is_active ? 'Deactivate ' : 'Activate ') + user.name"
                                    @click="toggle(user)"
                                >
                                    {{ user.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ArchivePagination :links="users.links" :total="users.total" :from="users.from" :to="users.to" />
        </section>
        <ArchiveModal
            :open="creating"
            title="Create User"
            description="New accounts are active immediately. Share the initial password through your approved internal channel."
            @close="close"
        >
            <form class="space-y-4 p-5" @submit.prevent="submit">
                <div>
                    <label for="create-name" class="mb-1.5 block text-xs font-medium">Full name</label
                    ><input
                        id="create-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        autocomplete="name"
                        class="archive-field w-full"
                        :aria-invalid="!!form.errors.name"
                        aria-describedby="create-name-error"
                    /><InputError id="create-name-error" :message="form.errors.name" />
                </div>
                <div>
                    <label for="create-email" class="mb-1.5 block text-xs font-medium">Email</label
                    ><input
                        id="create-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="archive-field w-full"
                        :aria-invalid="!!form.errors.email"
                        aria-describedby="create-email-error"
                    /><InputError id="create-email-error" :message="form.errors.email" />
                </div>
                <div>
                    <label for="create-role" class="mb-1.5 block text-xs font-medium">Role</label
                    ><select id="create-role" v-model="form.role" class="archive-field w-full">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option></select
                    ><InputError id="create-role-error" :message="form.errors.role" />
                </div>
                <div>
                    <label for="create-password" class="mb-1.5 block text-xs font-medium">Initial password</label
                    ><input
                        id="create-password"
                        v-model="form.password"
                        type="password"
                        required
                        minlength="12"
                        autocomplete="new-password"
                        class="archive-field w-full"
                        :aria-invalid="!!form.errors.password"
                        aria-describedby="create-password-help create-password-error"
                    />
                    <p id="create-password-help" class="mt-1 text-xs text-muted-foreground">At least 12 characters.</p>
                    <InputError id="create-password-error" :message="form.errors.password" />
                </div>
                <div class="flex justify-end gap-2 border-t border-border pt-4">
                    <button type="button" class="archive-button" :disabled="form.processing" @click="close">Cancel</button
                    ><button :disabled="form.processing" class="archive-button-primary">{{ form.processing ? 'Creating…' : 'Create User' }}</button>
                </div>
            </form>
        </ArchiveModal>
    </ArchiveLayout>
</template>
