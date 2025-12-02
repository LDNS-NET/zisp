<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import { useToast } from 'vue-toastification';
import Card from '@/Components/Card.vue';

const toast = useToast();

const props = defineProps({
    user: Object,
    lifetimeTotal: Number,
    paymentReliability: Number,
    clientValue: Number,
    tenant: Object,
    filters: Object,
    payments: Array,
    sessions: Array, // Session history from RADIUS
});

const showModal = ref(false);
const editing = ref(null);
const activeTab = ref('general');

const form = useForm({
    full_name: '',
    username: '',
    password: '',
    phone: '',
    email: '',
    location: '',
    package_id: '',
    type: 'hotspot',
    expires_at: '',
});

function openEdit(user) {
    editing.value = user.id;
    form.full_name = user.full_name ?? '';
    form.username = user.username ?? '';
    form.password = '';
    form.phone = user.phone ?? '';
    form.email = user.email ?? '';
    form.location = user.location ?? '';
    form.package_id = user.package_id ?? '';
    form.type = user.type ?? 'hotspot';
    form.expires_at = user.expires_at ? user.expires_at.slice(0, 16) : '';
    showModal.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('users.update', { user: editing.value }), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('User updated successfully');
            },
            onError: () => toast.error('Failed to update user'),
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('User created successfully');
            },
            onError: () => toast.error('Failed to create user'),
        });
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <!-- Page Header -->
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="flex items-center gap-2 text-xl font-extrabold text-blue-800"
                >
                    {{ props.user.full_name }}
                    <span class="text-gray-500"
                        >({{ props.user.username }})</span
                    >
                </h2>
                <PrimaryButton @click="openEdit(props.user)">
                    Edit User
                </PrimaryButton>
            </div>
        </template>

        <!-- Tabs -->
        <div class="mt-6 px-4 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        @click="activeTab = 'general'"
                        :class="[
                            activeTab === 'general'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        ]"
                    >
                        General Information
                    </button>

                    <button
                        @click="activeTab = 'payments'"
                        :class="[
                            activeTab === 'payments'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        ]"
                    >
                        Payments
                    </button>

                    <button
                        @click="activeTab = 'reports'"
                        :class="[
                            activeTab === 'reports'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        ]"
                    >
                        Reports
                    </button>

                    <button
                        @click="activeTab = 'sessions'"
                        :class="[
                            activeTab === 'sessions'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        ]"
                    >
                        Sessions
                    </button>
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <!-- General Info Tab -->
            <div v-if="activeTab === 'general'">
                <div class="rounded-2xl bg-white p-6 shadow dark:bg-gray-900">
                    <h3
                        class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        <svg
                            class="h-5 w-5 text-blue-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5.121 17.804A13.937 13.937 0 0112 15c2.485 0 
                   4.797.63 6.879 1.804M15 11a3 3 0 
                   11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        User Information
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Personal details & account info
                    </p>

                    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div
                            v-for="(value, label) in {
                                'Full Name': props.user.full_name,
                                Username: props.user.username,
                                Email: props.user.email ?? '—',
                                Password: props.user.password ?? '—',
                                Account: props.user.account_number ?? '—',
                                Phone: props.user.phone ?? '—',
                                Location: props.user.location ?? '—',
                                'User Type': props.user.type,
                                'Package ID': props.user.package_id ?? '—',
                                'Expires At': props.user.expires_at
                                    ? new Date(
                                          props.user.expires_at,
                                      ).toLocaleString()
                                    : '—',
                            }"
                            :key="label"
                            class="flex flex-col rounded-xl bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <span
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                                >{{ label }}</span
                            >
                            <span
                                class="mt-1 text-sm font-medium capitalize text-gray-900 dark:text-gray-100"
                            >
                                {{ value }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments Tab -->
            <div
                v-if="activeTab === 'payments'"
                class="rounded-2xl bg-white p-6 shadow dark:bg-gray-900"
            >
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Payments
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    All payments related to this user
                </p>

                <!-- Payment Summary Cards -->
                <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <!-- Lifetime Total -->
                    <div
                        class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Lifetime Total
                        </h3>
                        <p
                            class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ lifetimeTotal }}
                        </p>
                    </div>

                    <!-- Payment Reliability -->
                    <div
                        class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Payment Reliability
                        </h3>
                        <p
                            class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400"
                        >
                            {{ paymentReliability }}%
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            (Average speed of payment after expiry)
                        </p>
                    </div>

                    <!-- Client Value -->
                    <div
                        class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-700 dark:text-gray-300"
                        >
                            Client Value
                        </h3>
                        <p
                            class="mt-2 text-2xl font-bold text-purple-600 dark:text-purple-400"
                        >
                            {{ clientValue }} / 100
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Compared to all clients
                        </p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 border dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Amount
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Phone
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Receipt #
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Checked
                                </th>
                                <th
                                    class="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Paid At
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900"
                        >
                            <tr
                                v-for="payment in props.payments"
                                :key="payment.id"
                            >
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ payment.amount }}
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ payment.phone ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ payment.receipt_number ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                        :class="
                                            payment.checked
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                        "
                                    >
                                        {{ payment.checked ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        payment.paid_at
                                            ? new Date(
                                                  payment.paid_at,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !props.payments ||
                                    props.payments.length === 0
                                "
                            >
                                <td
                                    colspan="5"
                                    class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    No payments found for this user.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports Tab -->
            <div
                v-if="activeTab === 'reports'"
                class="rounded-2xl bg-white p-6 shadow dark:bg-gray-900"
            >
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Reports
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    User reports and usage statistics.
                </p>
            </div>

            <!-- Sessions Tab -->
            <div
                v-if="activeTab === 'sessions'"
                class="rounded-2xl bg-white p-6 shadow dark:bg-gray-900"
            >
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Session History
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    RADIUS session history for this user (20 per page)
                </p>

                <div class="mt-6 overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 border dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    Start Time
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    Stop Time
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    Duration
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    Data Used
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    Termination
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                                >
                                    IP Address
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900"
                        >
                            <tr
                                v-for="session in props.sessions.data"
                                :key="session.session_id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        session.start_time
                                            ? new Date(
                                                  session.start_time,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        session.stop_time
                                            ? new Date(
                                                  session.stop_time,
                                              ).toLocaleString()
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100"
                                >
                                    <span
                                        :class="
                                            session.duration === 'Active'
                                                ? 'text-green-600 dark:text-green-400'
                                                : ''
                                        "
                                    >
                                        {{ session.duration }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{
                                            session.data_used
                                        }}</span>
                                        <span
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            ↓{{ session.data_in }} ↑{{
                                                session.data_out
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="
                                            session.termination_cause === 'N/A'
                                                ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                : session.termination_cause.includes(
                                                        'User-Request',
                                                    )
                                                  ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                  : session.termination_cause.includes(
                                                          'Session-Timeout',
                                                      )
                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        "
                                    >
                                        {{ session.termination_cause }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ session.framed_ip ?? '—' }}
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !props.sessions ||
                                    !props.sessions.data ||
                                    props.sessions.data.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    No session history found for this user.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="props.sessions && props.sessions.data && props.sessions.data.length > 0"
                    class="mt-4 flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700"
                >
                    <div class="flex flex-1 justify-between sm:hidden">
                        <a
                            v-if="props.sessions.prev_page_url"
                            :href="props.sessions.prev_page_url"
                            class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Previous
                        </a>
                        <a
                            v-if="props.sessions.next_page_url"
                            :href="props.sessions.next_page_url"
                            class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Next
                        </a>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing
                                <span class="font-medium">{{ props.sessions.from }}</span>
                                to
                                <span class="font-medium">{{ props.sessions.to }}</span>
                                of
                                <span class="font-medium">{{ props.sessions.total }}</span>
                                sessions
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <a
                                    v-if="props.sessions.prev_page_url"
                                    :href="props.sessions.prev_page_url"
                                    class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:ring-gray-600 dark:hover:bg-gray-700"
                                >
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <a
                                    v-for="link in props.sessions.links.slice(1, -1)"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-700',
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold',
                                    ]"
                                >
                                    {{ link.label }}
                                </a>
                                <a
                                    v-if="props.sessions.next_page_url"
                                    :href="props.sessions.next_page_url"
                                    class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:ring-gray-600 dark:hover:bg-gray-700"
                                >
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="space-y-4 p-6">
                <div>
                    <label class="block text-sm font-medium">Full Name</label>
                    <TextInput v-model="form.full_name" type="text" />
                    <InputError :message="form.errors.full_name" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Username</label>
                    <TextInput v-model="form.username" type="text" />
                    <InputError :message="form.errors.username" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Password</label>
                    <TextInput v-model="form.password" type="password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Phone</label>
                    <TextInput v-model="form.phone" type="text" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Email</label>
                    <TextInput v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Location</label>
                    <TextInput v-model="form.location" type="text" />
                    <InputError :message="form.errors.location" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Package</label>
                    <TextInput v-model="form.package_id" type="text" />
                    <InputError :message="form.errors.package_id" />
                </div>

                <div>
                    <label class="block text-sm font-medium">Type</label>
                    <TextInput v-model="form.type" type="text" />
                    <InputError :message="form.errors.type" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <DangerButton @click="showModal = false" type="button"
                        >Cancel</DangerButton
                    >
                    <PrimaryButton :disabled="form.processing">
                        {{ editing ? 'Update' : 'Save' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
