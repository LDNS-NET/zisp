<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { 
    Users, 
    UserPlus, 
    Shield, 
    Mail, 
    Phone, 
    Trash2, 
    UserX, 
    UserCheck,
    Edit2,
    Lock
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const toast = useToast();
const props = defineProps({
    users: Array,
    roles: Array,
});

const showModal = ref(false);
const editingUser = ref(null);
const staffForm = useForm({
    name: '',
    email: '',
    username: '',
    phone: '',
    role: '',
    password: '',
    password_confirmation: '',
});

const openCreateModal = () => {
    editingUser.value = null;
    staffForm.reset();
    showModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    staffForm.name = user.name;
    staffForm.email = user.email;
    staffForm.username = user.username;
    staffForm.phone = user.phone || '';
    staffForm.role = user.role;
    staffForm.password = '';
    staffForm.password_confirmation = '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    staffForm.reset();
};

const submitStaff = () => {
    if (editingUser.value) {
        staffForm.put(route('settings.staff.update', editingUser.value.id), {
            onSuccess: () => {
                toast.success('Staff member updated successfully');
                closeModal();
            },
        });
    } else {
        staffForm.post(route('settings.staff.store'), {
            onSuccess: () => {
                toast.success('Staff member created successfully');
                closeModal();
            },
        });
    }
};

const deleteStaff = (user) => {
    if (confirm(`Are you sure you want to remove ${user.name}?`)) {
        staffForm.delete(route('settings.staff.destroy', user.id), {
            onSuccess: () => toast.success('Staff member removed'),
        });
    }
};

const toggleStatus = (user) => {
    staffForm.post(route('settings.staff.toggle-status', user.id), {
        onSuccess: () => toast.success('Status updated'),
    });
};
</script>

<template>
    <Head title="Staff Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Staff Management
                </h2>
                <PrimaryButton @click="openCreateModal" class="flex items-center gap-2">
                    <UserPlus class="h-4 w-4" />
                    Add Staff Member
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Member</th>
                                        <th class="px-6 py-3">Role</th>
                                        <th class="px-6 py-3">Contact</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users" :key="user.id" class="border-b dark:border-gray-700">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                                    {{ user.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <div class="font-medium">{{ user.name }}</div>
                                                    <div class="text-xs text-gray-500">@{{ user.username }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                <Shield class="h-3 w-3" />
                                                {{ user.role }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                <div class="flex items-center gap-1">
                                                    <Mail class="h-3 w-3" /> {{ user.email }}
                                                </div>
                                                <div v-if="user.phone" class="flex items-center gap-1">
                                                    <Phone class="h-3 w-3" /> {{ user.phone }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span v-if="user.is_suspended" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                                                Suspended
                                            </span>
                                            <span v-else class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button @click="openEditModal(user)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                                    <Edit2 class="h-4 w-4" />
                                                </button>
                                                <button @click="toggleStatus(user)" :title="user.is_suspended ? 'Activate' : 'Suspend'" class="text-orange-600 hover:text-orange-900 dark:text-orange-400">
                                                    <UserX v-if="!user.is_suspended" class="h-4 w-4" />
                                                    <UserCheck v-else class="h-4 w-4" />
                                                </button>
                                                <button @click="deleteStaff(user)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingUser ? 'Edit Staff Member' : 'Add Staff Member' }}
                </h3>

                <form @submit.prevent="submitStaff" class="mt-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Full Name" />
                            <TextInput id="name" v-model="staffForm.name" type="text" class="mt-1 block w-full" autocomplete="name" />
                            <InputError :message="staffForm.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="username" value="Username" />
                            <TextInput id="username" v-model="staffForm.username" type="text" class="mt-1 block w-full" autocomplete="username" />
                            <InputError :message="staffForm.errors.username" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="email" value="Email Address" />
                            <TextInput id="email" v-model="staffForm.email" type="email" class="mt-1 block w-full" autocomplete="email" />
                            <InputError :message="staffForm.errors.email" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput id="phone" v-model="staffForm.phone" type="text" class="mt-1 block w-full" autocomplete="tel" required />
                            <InputError :message="staffForm.errors.phone" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select id="role" v-model="staffForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-black dark:text-white">
                            <option value="">Select Role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name.replace('_', ' ').toUpperCase() }}
                            </option>
                        </select>
                        <InputError :message="staffForm.errors.role" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="password" :value="editingUser ? 'New Password (Optional)' : 'Password'" />
                            <TextInput id="password" v-model="staffForm.password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <InputError :message="staffForm.errors.password" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password" />
                            <TextInput id="password_confirmation" v-model="staffForm.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <InputError :message="staffForm.errors.password_confirmation" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="staffForm.processing">
                            {{ editingUser ? 'Update Member' : 'Create Member' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
