<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
    renewals: Object, // Paginated renewals
});

import { Wallet, History, CreditCard, User, FileText, Activity } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';

const showModal = ref(false);
const editing = ref(null);
const activeTab = ref('general');

const form = useForm({
    full_name: '',
    username: '',
    password: '',
    phone: '',
    // email: '',
    location: '',
    package_id: '',
    type: 'hotspot',
    comment: '',
});


function openEdit(user) {
    editing.value = user.uuid;
    form.full_name = user.full_name ?? '';
    form.username = user.username ?? '';
    form.password = ''; // Don't show current password
    form.phone = user.phone ?? '';
    // form.email = user.email ?? '';
    form.location = user.location ?? '';
    form.package_id = user.package_id ?? '';
    form.type = user.type ?? 'PPPoE';
    form.comment = user.comment ?? '';
    showModal.value = true;
}
function submitWithoutPassword() {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            toast.success(editing.value ? 'User updated successfully' : 'User created successfully');
            form.reset();
        },
        onError: () => {
            toast.error('Please check the form for errors.');
        },
    };

    if (editing.value) {
        form.put(route('users.update', editing.value), options);
    } else {
        form.post(route('users.store'), options);
    }
}

function confirmSubmit() {
    form.admin_password = passwordForm.password;
    
    const options = {
        onSuccess: () => {
            showModal.value = false;
            showPasswordModal.value = false;
            toast.success(editing.value ? 'User updated successfully' : 'User created successfully');
            passwordForm.reset();
            form.reset();
        },
        onError: (errors) => {
            if (errors.admin_password) {
                toast.error(errors.admin_password);
                passwordForm.setError('password', errors.admin_password); // key might be admin_password, but we show on password field
                // actually we can just show toast and keep password modal open?
                // If it's a password error, we keep password modal open.
                // If it's a form error (e.g. username taken), we should close password modal?
                // If admin_password error, keep password modal open.
            } else {
                // If other errors, close password modal to show form errors
                showPasswordModal.value = false;
                toast.error('Please check the form for errors.');
            }
        },
    };

    if (editing.value) {
        form.put(route('users.update', editing.value), options);
    } else {
        form.post(route('users.store'), options);
    }
}

const processingPayment = ref(null);

function payWithBalance(renewal) {
    if (!confirm('Are you sure you want to deduct the package price from this user\'s wallet balance?')) {
        return;
    }
    
    processingPayment.value = renewal.id;
    
    router.post(route('users.renewals.pay-with-balance', { user: props.user.id, renewal: renewal.id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // Toast will be shown from the flash messages if present, or we can just rely on the session flash
            processingPayment.value = null;
        },
        onError: () => {
            // the toast is handled via page props usually, or we can leave it
            processingPayment.value = null;
        },
        onFinish: () => {
            processingPayment.value = null;
        }
    });
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

                    <button
                        @click="activeTab = 'renewals'"
                        :class="[
                            activeTab === 'renewals'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        ]"
                    >
                        Renewals History
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
                                // Email: props.user.email ?? '—',
                                Password: props.user.password ?? '—',
                                Account: props.user.account_number ?? '—',
                                Phone: props.user.phone ?? '—',
                                Location: props.user.location ?? '—',
                                'User Type': props.user.type,
                                'Package Name': props.user.package ? props.user.package.name : '—',
                                'Wallet Balance': 'KES ' + (props.user.wallet_balance || '0.00'),
                                'Expires At': props.user.expires_at
                                    ? new Date(
                                          props.user.expires_at,
                                      ).toLocaleString()
                                    : '—',
                                'Comments / Notes': props.user.comment ?? '—',
                            }"
                            :key="label"
                            class="flex flex-col rounded-xl bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <span
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400"
                                >{{ label }}</span
                            >
                            <span
                                class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100"
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
                <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-4">
                    <!-- Wallet Balance -->
                    <div
                        class="rounded-2xl bg-white p-6 shadow border-2 border-green-500 dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
                        >
                            <Wallet class="w-5 h-5 text-green-500" />
                            Wallet Balance
                        </h3>
                        <p
                            class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400"
                        >
                            KES {{ props.user.wallet_balance || '0.00' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Available credit for next renewal
                        </p>
                    </div>
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
                                :key="payment.uuid"
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
                    Last 15 RADIUS sessions for this user
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
                                v-for="session in props.sessions"
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
                                    props.sessions.length === 0
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
            </div>

            <!-- Renewals Tab -->
            <div
                v-if="activeTab === 'renewals'"
                class="rounded-2xl bg-white p-6 shadow dark:bg-gray-900"
            >
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                            <History class="w-5 h-5 text-blue-600" />
                            Package Renewals History
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Historical log of package activations and extensions
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-800">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Package</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Amount Paid</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Type</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Period</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-transparent">
                            <tr v-for="renewal in props.renewals.data" :key="renewal.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ renewal.package_name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                    KES {{ Number(renewal.amount).toLocaleString() }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': renewal.type === 'renewal',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400': renewal.type === 'extension',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': renewal.type === 'compensation'
                                        }"
                                    >
                                        {{ renewal.type || 'renewal' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex flex-col gap-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] uppercase font-bold text-gray-400">Start:</span>
                                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                                {{ renewal.started_at ? new Date(renewal.started_at).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] uppercase font-bold text-gray-400">End:</span>
                                            <span class="font-bold text-blue-600 dark:text-blue-400">
                                                {{ renewal.expires_at ? new Date(renewal.expires_at).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex flex-col gap-2 items-start">
                                        <div class="flex items-center gap-2">
                                            <span v-if="renewal.expires_at && new Date(renewal.expires_at) < new Date()"
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold transition-colors duration-200 shadow-sm bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-900/50">
                                                Expired
                                            </span>
                                            <span v-else class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold transition-colors duration-200 shadow-sm" 
                                                :class="renewal.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 ring-1 ring-green-200 dark:ring-green-900/50' : 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-gray-700'">
                                                {{ renewal.status.charAt(0).toUpperCase() + renewal.status.slice(1) }}
                                            </span>
                                        </div>
                                        <button v-if="Number(renewal.amount) === 0" 
                                                @click.prevent="payWithBalance(renewal)"
                                                :disabled="processingPayment === renewal.id"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 rounded-lg text-xs font-bold transition-colors disabled:opacity-50 border border-indigo-100 dark:border-indigo-500/20 shadow-sm mt-1">
                                            <Wallet class="w-3.5 h-3.5" />
                                            <span>{{ processingPayment === renewal.id ? 'Processing...' : 'Pay via Wallet' }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!props.renewals.data || props.renewals.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <History class="w-12 h-12 text-gray-200 dark:text-gray-700 mx-auto mb-3" />
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No renewal history found for this user.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination for Renewals -->
                <Pagination 
                    v-if="props.renewals.links.length > 3"
                    :links="props.renewals.links" 
                    :from="props.renewals.from" 
                    :to="props.renewals.to" 
                    :total="props.renewals.total" 
                    class="mt-6"
                />
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit User' : 'Create New User' }}
                </h3>
                <form @submit.prevent="submitWithoutPassword" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="full_name" value="Full Name" />
                            <TextInput v-model="form.full_name" id="full_name" class="mt-1 block w-full" />
                            <InputError :message="form.errors.full_name" />
                        </div>
                        <div>
                            <InputLabel for="username" value="Username" />
                            <TextInput v-model="form.username" id="username" class="mt-1 block w-full" />
                            <InputError :message="form.errors.username" />
                        </div>
                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput v-model="form.password" id="password" type="password" class="mt-1 block w-full" placeholder="Leave empty to keep current" autocomplete="new-password" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput v-model="form.phone" id="phone" class="mt-1 block w-full" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="location" value="Location" />
                            <TextInput v-model="form.location" id="location" class="mt-1 block w-full" />
                            <InputError :message="form.errors.location" />
                        </div>
                        <div>
                            <InputLabel for="type" value="User Type" />
                            <select v-model="form.type" id="type" class="mt-1 block w-full border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                <option value="hotspot">Hotspot</option>
                                <option value="pppoe">PPPoE</option>
                                <option value="static">Static</option>
                            </select>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div>
                            <InputLabel for="package_id" value="Package" />
                            <select v-model="form.package_id" id="package_id" class="mt-1 block w-full border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                <option value="">Select Package</option>
                                <option v-for="pkg in packagesByType" :key="pkg.id" :value="pkg.id">
                                    {{ pkg.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.package_id" />
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel for="comment" value="Comments / Notes (Required)" />
                            <textarea
                                id="comment"
                                v-model="form.comment"
                                class="mt-1 block w-full border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                                rows="3"
                                placeholder="Add any private notes about this user..."
                                required
                            ></textarea>
                            <InputError :message="form.errors.comment" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showModal = false">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">{{ editing ? 'Update User' : 'Create User' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
