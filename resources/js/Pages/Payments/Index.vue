<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import {
    Plus,
    Edit,
    Trash2,
    Banknote,
    CalendarDays,
    Calendar,
    BarChart2,
    Eye,
} from 'lucide-vue-next';
import { EyeOff } from 'lucide-vue-next';
const showDaily = ref(false);
const showWeekly = ref(false);
const showMonthly = ref(false);
const showYearly = ref(false);
import Card from '@/Components/Card.vue';
// ...existing code...

const page = usePage();
const locale = 'en-KE'; // Could be dynamic too if needed, but keeping fixed for now or could derive from user



const today = new Date();
const filterYear = ref(today.getFullYear());
const filterMonth = ref(0); // 0 means all months
const filterWeek = ref(0); // 0 means all weeks

// Use all payments for summary calculations, not just paginated page
const allPayments = computed(() => props.payments.allData ?? []);

// Daily
const selectedDay = ref(new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10));
const dailyIncome = computed(() => {
    return allPayments.value
        .filter((p) => {
            const paid = new Date(p.paid_at);
            // Compare only date part (yyyy-mm-dd)
            return (
                paid.getFullYear() ===
                new Date(selectedDay.value).getFullYear() &&
                paid.getMonth() === new Date(selectedDay.value).getMonth() &&
                paid.getDate() === new Date(selectedDay.value).getDate()
            );
        })
        .reduce((sum, p) => sum + Number(p.amount), 0);
});

// Weekly
function getWeekOfYear(date) {
    // ISO week: Thursday is always in week
    const d = new Date(
        Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()),
    );
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
}
const selectedWeek = ref(getWeekOfYear(today));
const selectedWeekYear = ref(today.getFullYear());
const weeklyIncome = computed(() => {
    return allPayments.value
        .filter((p) => {
            const paid = new Date(p.paid_at);
            return (
                paid.getFullYear() === selectedWeekYear.value &&
                getWeekOfYear(paid) === selectedWeek.value
            );
        })
        .reduce((sum, p) => sum + Number(p.amount), 0);
});

// Monthly
const selectedMonth = ref(today.getMonth() + 1);
const selectedMonthYear = ref(today.getFullYear());
const monthlyIncome = computed(() => {
    return allPayments.value
        .filter((p) => {
            const paid = new Date(p.paid_at);
            return (
                paid.getFullYear() === selectedMonthYear.value &&
                paid.getMonth() + 1 === selectedMonth.value
            );
        })
        .reduce((sum, p) => sum + Number(p.amount), 0);
});

// Yearly
const selectedYear = ref(today.getFullYear());
const yearlyIncome = computed(() => {
    return allPayments.value
        .filter((p) => {
            const paid = new Date(p.paid_at);
            // From Jan 1 to Dec 31 of selected year
            return paid.getFullYear() === selectedYear.value;
        })
        .reduce((sum, p) => sum + Number(p.amount), 0);
});

// Advanced filters and export
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const exportFormat = ref('csv'); // 'csv' or 'pdf'

function exportPayments() {
    isLoading.value = true;
    if (exportFormat.value === 'csv') {
        const rows = [
            [
                'User',
                'Phone',
                'Receipt',
                'Amount',
                'Checked',
                'Paid At',
                'Disbursement',
            ],
            ...paymentsData.value.map((p) => [
                p.user,
                p.phone,
                p.receipt_number,
                p.amount,
                p.checked_label,
                p.paid_at,
                p.disbursement_label,
            ]),
        ];
        // Add extra commas to pad left and align right
        const paddedRows = rows.map((r) => ['', '', '', '', '', '', '', ...r]);
        const csv = paddedRows
            .map((r) => r.map((x) => `"${x}"`).join(','))
            .join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        saveAs(blob, 'payments_export.csv');
    } else if (exportFormat.value === 'pdf') {
        const doc = new jsPDF();
        doc.text('Payments Export', 14, 16);
        autoTable(doc, {
            head: [
                [
                    'User',
                    'Phone',
                    'Receipt',
                    'Amount',
                    'Checked',
                    'Paid At',
                    'Disbursement',
                ],
            ],
            body: paymentsData.value.map((p) => [
                p.user,
                p.phone,
                p.receipt_number,
                p.amount,
                p.checked_label,
                p.paid_at,
                p.disbursement_label,
            ]),
            styles: { halign: 'right' },
            headStyles: { halign: 'right' },
        });
        doc.save('payments_export.pdf');
    }
    isLoading.value = false;
}

const globalSearch = ref('');
const filterStatus = ref('');
const isLoading = ref(false);

const paymentsData = computed(() => {
    let all = props.payments.data || [];
    if (filterYear.value) {
        all = all.filter((p) => {
            const paid = new Date(p.paid_at);
            return paid.getFullYear() === filterYear.value;
        });
    }
    if (filterMonth.value) {
        all = all.filter((p) => {
            const paid = new Date(p.paid_at);
            return paid.getMonth() + 1 === filterMonth.value;
        });
    }
    if (filterWeek.value) {
        all = all.filter((p) => {
            const paid = new Date(p.paid_at);
            return getWeek(paid) === filterWeek.value;
        });
    }
    if (globalSearch.value) {
        const term = globalSearch.value.toLowerCase();
        all = all.filter((p) => {
            const user = p.user?.toLowerCase?.() || '';
            const phone = p.phone?.toLowerCase?.() || '';
            return user.includes(term) || phone.includes(term);
        });
    }
    if (filterStatus.value) {
        all = all.filter((p) => p.disbursement_type === filterStatus.value);
    }
    // Fix checked_label to show 'No' if checked is false
    all = all.map((p) => ({
        ...p,
        checked_label:
            p.checked === true || p.checked === 'true' ? 'Yes' : 'No',
    }));
    return all;
});

function exportToExcel() {
    isLoading.value = true;
    // Simple CSV export for demo
    const rows = [
        [
            'User',
            'Phone',
            'Receipt',
            'Amount',
            'Checked',
            'Paid At',
            'Disbursement',
        ],
        ...paymentsData.value.map((p) => [
            p.user,
            p.phone,
            p.receipt_number,
            p.amount,
            p.checked_label,
            p.paid_at,
            p.disbursement_label,
        ]),
    ];
    const csv = rows.map((r) => r.map((x) => `"${x}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    saveAs(blob, 'payments_export.csv');
    isLoading.value = false;
}
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    payments: Object,
    filters: Object,
    users: Array, // [{ id, username, phone }]
    currency: String,
});

const currency = computed(() => props.currency || page.props.tenant?.currency || 'KES');

const showModal = ref(false);
const editing = ref(null);
const selectedTenantPayments = ref([]);
const selectAll = ref(false);

// Select all/deselect all logic
watch(selectAll, (val) => {
    if (val) {
        selectedTenantPayments.value = props.payments.data.map((p) => p.id);
    } else {
        selectedTenantPayments.value = [];
    }
});

// Individual selection logic
const toggleSelectAll = () => {
    if (selectedTenantPayments.value.length === props.payments.data.length) {
        selectAll.value = true;
    } else {
        selectAll.value = false;
    }
};
// Allowed disbursement values
const DISBURSEMENT_OPTIONS = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
];

const getStatusColor = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'processing':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    }
};

const form = useForm({
    user_id: '',
    receipt_number: '',
    amount: '',
    checked: false, // boolean in DB
    paid_at: '',
    disbursement_status: 'pending', // default option
    phone: '', // auto-filled, readonly
});

// User search filter for dropdown
const userSearch = ref('');
const filteredUsers = computed(() => {
    if (!userSearch.value) return props.users;
    const term = userSearch.value.toLowerCase();
    return props.users.filter(
        (u) =>
            (u.username && u.username.toLowerCase().includes(term)) ||
            (u.phone && u.phone.toLowerCase().includes(term)),
    );
});

function openAddModal() {
    form.reset();
    editing.value = null;
    showModal.value = true;
}

function normalizeToDatetimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    if (!isNaN(d.getTime())) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
    return value.replace(' ', 'T').slice(0, 16);
}

function openEditModal(payment) {
    form.user_id = payment.user_id ?? '';
    form.receipt_number = payment.receipt_number;
    form.amount = payment.amount;
    // Use strict boolean for checked
    form.checked =
        payment.checked === true || payment.checked === 'true' ? true : false;
    form.paid_at = normalizeToDatetimeLocal(payment.paid_at);
    form.disbursement_status = payment.disbursement_status ?? 'pending';
    form.phone = payment.phone ?? '';
    editing.value = payment.id;
    showModal.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('payments.update', editing.value), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('Payment updated successfully');
            },
        });
    } else {
        form.post(route('payments.store'), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('Payment created successfully');
            },
            onError: () => {
                toast.error('Failed, check the form for errors.');
            },
        });
    }
}

const confirmPaymentDeletion = (id) => {
    if (confirm('Are you sure you want to delete this payment?')) {
        router.delete(route('payments.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Payment deleted successfully');
            },
        });
    }
};

//bulk deletion
const bulkDelete = () => {
    if (selectedTenantPayments.value.length === 0) return;

    if (confirm('Please confirm deletion of selected payments?')) {
        router.delete(route('payments.bulk-delete'), {
            data: { ids: selectedTenantPayments.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedTenantPayments.value = [];
                selectAll.value = false;
                toast.success('Payments deleted successfully');
            },
        });
    }
};

// Autofill phone when user_id changes
watch(
    () => form.user_id,
    (val) => {
        const uid = Number(val);
        if (uid) {
            const u = props.users.find((user) => Number(user.id) === uid);
            form.phone = u?.phone ?? '';
        } else {
            form.phone = '';
        }
    },
);

// Select all checkboxes
watch(selectAll, (val) => {
    selectedTenantPayments.value = val
        ? props.payments.data.map((p) => p.id)
        : [];
});

const allIds = computed(() => props.payments.data.map((p) => p.id));

// Payment Details Modal logic
const showDetailsModal = ref(false);
const paymentDetails = ref(null);
function showPaymentDetails(payment) {
    paymentDetails.value = payment;
    showDetailsModal.value = true;
}
function closeDetailsModal() {
    showDetailsModal.value = false;
    paymentDetails.value = null;
}

const showFilters = ref(false);
const showExport = ref(false);

function generatePaymentConfirmation() {
    if (!paymentDetails.value) return;
    const doc = new jsPDF();
    // Colored header
    doc.setFillColor(41, 128, 185); // blue
    doc.rect(0, 0, 210, 30, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(20);
    doc.text(
        paymentDetails.value.business_name || 'Payment Confirmation',
        14,
        20,
    );
    doc.setFontSize(12);
    doc.setTextColor(44, 62, 80); // dark
    let y = 40;

    // Package info section
    if (paymentDetails.value.package) {
        doc.setFont(undefined, 'bold');
        doc.setTextColor(41, 128, 185);
        doc.text('Package:', 14, y);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(44, 62, 80);
        doc.text(`${paymentDetails.value.package.type}`, 45, y);
        y += 8;
        doc.setFont(undefined, 'bold');
        doc.setTextColor(41, 128, 185);
        doc.text('Package Price:', 14, y);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(44, 62, 80);
        doc.text(`${currency.value} ${paymentDetails.value.package.price}`, 45, y);
        y += 10;
    }
    // Payment details section
    const details = [
        ['User', paymentDetails.value.user],
        ['Phone', paymentDetails.value.phone],
        ['Receipt Number', paymentDetails.value.receipt_number],
        ['Amount', `${currency.value} ${paymentDetails.value.amount}`],
        ['Paid At', paymentDetails.value.paid_at],
    ];
    details.forEach(([label, value]) => {
        doc.setFont(undefined, 'bold');
        doc.setTextColor(41, 128, 185);
        doc.text(label + ':', 14, y);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(44, 62, 80);
        // Add extra spacing for receipt number
        if (label === 'Receipt Number') {
            doc.text(value ? String(value) : '', 45, y + 4);
            y += 12;
        } else {
            doc.text(value ? String(value) : '', 45, y);
            y += 8;
        }
    });
    // Footer
    doc.setTextColor(127, 140, 141);
    doc.setFontSize(10);
    doc.text('Thank you for your payment!', 14, y + 10);
    doc.save(
        `confirmation_${paymentDetails.value.receipt_number || 'payment'}.pdf`,
    );
}
</script>

<template>
    <AuthenticatedLayout>

        <Head title="Payments" />

        <div class="mx-auto max-w-7xl space-y-6 p-6 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Banknote class="h-7 w-7 text-blue-600 dark:text-blue-400" />
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Payments</h2>
                </div>
                <PrimaryButton @click="openAddModal" class="flex items-center gap-2">
                    <Plus class="h-5 w-5" /> Record Payment
                </PrimaryButton>
            </div>

            <br />

            <div class="mb-2 flex justify-between gap-2">
                <button @click="showFilters = !showFilters" class="rounded bg-blue-600 px-4 py-2 text-white shadow hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600">
                    {{ showFilters ? 'Hide Filters' : 'Filters' }}
                </button>
                <button @click="showExport = !showExport" class="rounded bg-green-600 px-4 py-2 text-white shadow hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600">
                    {{ showExport ? 'Hide Export' : 'Export' }}
                </button>
            </div>
            <div v-if="showFilters" class="mb-4 flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow dark:bg-slate-800">
                <label class="font-semibold text-gray-700 dark:text-gray-300">General Filter:</label>
                <select v-model="filterYear" class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white">
                    <option v-for="y in [
                        today.getFullYear(),
                        today.getFullYear() - 1,
                        today.getFullYear() - 2,
                        today.getFullYear() - 3,
                        today.getFullYear() - 4,
                    ]" :key="y" :value="y">
                        {{ y }}
                    </option>
                </select>
                <select v-model="filterMonth" class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white">
                    <option :value="0">All Months</option>
                    <option v-for="m in 12" :key="m" :value="m">
                        {{
                            new Date(2000, m - 1, 1).toLocaleString('en-KE', {
                                month: 'long',
                            })
                        }}
                    </option>
                </select>
                <select v-model="filterWeek" class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white">
                    <option :value="0">All Weeks</option>
                    <option v-for="w in 52" :key="w" :value="w">
                        Week {{ w }}
                    </option>
                </select>
                <label class="ml-4 font-semibold text-gray-700 dark:text-gray-300">User/Phone:</label>
                <input v-model="globalSearch" type="text" placeholder="Type to filter..."
                    class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white dark:placeholder-gray-400" />
                <label class="ml-4 font-semibold text-gray-700 dark:text-gray-300">Status:</label>
                <select v-model="filterStatus" class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="disbursed">Disbursed</option>
                    <option value="withheld">Withheld</option>
                </select>
            </div>
            <div v-if="showExport" class="mb-4 flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow dark:bg-slate-800">
                <label class="ml-4 font-semibold text-gray-700 dark:text-gray-300">Export Format:</label>
                <select v-model="exportFormat" class="rounded border border-gray-300 bg-white px-2 py-1 dark:border-gray-600 dark:bg-slate-700 dark:text-white">
                    <option value="csv">CSV</option>
                    <option value="pdf">PDF</option>
                </select>
                <button @click="exportPayments" class="ml-4 rounded bg-green-600 px-3 py-1 text-white shadow hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600">
                    Export
                </button>
                <span v-if="isLoading" class="ml-2 text-xs text-gray-500 dark:text-gray-400">Loading...</span>
            </div>
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <!-- Daily Card -->
                <Card :title="'Daily Income'" :icon="CalendarDays" :subtitle="selectedDay">
                    <template #value>
                        <div class="flex items-center justify-between">
                            <span>{{
                                showDaily
                                    ? dailyIncome.toLocaleString('en-KE', {
                                        style: 'currency',
                                        currency: currency,
                                    })
                                    : '******'
                            }}</span>
                            <button @click="showDaily = !showDaily"
                                class="ml-2 text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white">
                                <component :is="showDaily ? Eye : EyeOff" class="h-5 w-5" />
                            </button>
                        </div>
                    </template>
                    <template #extra>
                        <input type="date" v-model="selectedDay" class="rounded border px-2 py-1 text-xs" />
                    </template>
                </Card>
                <!-- Weekly Card -->
                <Card :title="'Weekly Income'" :icon="Calendar"
                    :subtitle="'Week ' + selectedWeek + ', ' + selectedWeekYear">
                    <template #value>
                        <div class="flex items-center justify-between">
                            <span>{{
                                showWeekly
                                    ? weeklyIncome.toLocaleString('en-KE', {
                                        style: 'currency',
                                        currency: currency,
                                    })
                                    : '******'
                            }}</span>
                            <button @click="showWeekly = !showWeekly"
                                class="ml-2 text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white">
                                <component :is="showWeekly ? Eye : EyeOff" class="h-5 w-5" />
                            </button>
                        </div>
                    </template>
                    <template #extra>
                        <select v-model="selectedWeek" class="rounded border px-2 py-1 text-xs">
                            <option v-for="w in 52" :key="w" :value="w">
                                Week {{ w }}
                            </option>
                        </select>
                        <select v-model="selectedWeekYear" class="ml-2 rounded border px-2 py-1 text-xs">
                            <option v-for="y in [
                                today.getFullYear(),
                                today.getFullYear() - 1,
                                today.getFullYear() - 2,
                                today.getFullYear() - 3,
                                today.getFullYear() - 4,
                            ]" :key="y" :value="y">
                                {{ y }}
                            </option>
                        </select>
                    </template>
                </Card>
                <!-- Monthly Card -->
                <Card :title="'Monthly Income'" :icon="Calendar" :subtitle="new Date(
                    selectedMonthYear,
                    selectedMonth - 1,
                    1,
                ).toLocaleString('en-KE', {
                    month: 'long',
                    year: 'numeric',
                })
                    ">
                    <template #value>
                        <div class="flex items-center justify-between">
                            <span>{{
                                showMonthly
                                    ? monthlyIncome.toLocaleString('en-KE', {
                                        style: 'currency',
                                        currency: currency,
                                    })
                                    : '******'
                            }}</span>
                            <button @click="showMonthly = !showMonthly"
                                class="ml-2 text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white">
                                <component :is="showMonthly ? Eye : EyeOff" class="h-5 w-5" />
                            </button>
                        </div>
                    </template>
                    <template #extra>
                        <select v-model="selectedMonth" class="rounded border px-2 py-1 text-xs">
                            <option v-for="m in 12" :key="m" :value="m">
                                {{
                                    new Date(2000, m - 1, 1).toLocaleString(
                                        'en-KE',
                                        { month: 'long' },
                                    )
                                }}
                            </option>
                        </select>
                        <select v-model="selectedMonthYear" class="ml-2 rounded border px-2 py-1 text-xs">
                            <option v-for="y in [
                                today.getFullYear(),
                                today.getFullYear() - 1,
                                today.getFullYear() - 2,
                                today.getFullYear() - 3,
                                today.getFullYear() - 4,
                            ]" :key="y" :value="y">
                                {{ y }}
                            </option>
                        </select>
                    </template>
                </Card>
                <!-- Yearly Card -->
                <Card :title="'Yearly Income'" :icon="BarChart2" :subtitle="selectedYear.toString()">
                    <template #value>
                        <div class="flex items-center justify-between">
                            <span>{{
                                showYearly
                                    ? yearlyIncome.toLocaleString('en-KE', {
                                        style: 'currency',
                                        currency: currency,
                                    })
                                    : '******'
                            }}</span>
                            <button @click="showYearly = !showYearly"
                                class="ml-2 text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white">
                                <component :is="showYearly ? Eye : EyeOff" class="h-5 w-5" />
                            </button>
                        </div>
                    </template>
                    <template #extra>
                        <select v-model="selectedYear" class="rounded border px-2 py-1 text-xs">
                            <option v-for="y in [
                                today.getFullYear(),
                                today.getFullYear() - 1,
                                today.getFullYear() - 2,
                                today.getFullYear() - 3,
                                today.getFullYear() - 4,
                            ]" :key="y" :value="y">
                                {{ y }}
                            </option>
                        </select>
                    </template>
                </Card>
            </div>

            <div v-if="selectedTenantPayments.length > 0" class="mt-4 flex">
                <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                    <Trash2 class="h-4 w-4" /> Bulk Delete ({{
                        selectedTenantPayments.length
                    }})
                </DangerButton>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow dark:border-gray-700 dark:bg-slate-800">
                <table class="w-full">
                    <thead class="bg-gray-50 text-left dark:bg-slate-700">
                        <tr>
                            <td class="px-4 py-3">
                                <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 dark:border-gray-600 dark:bg-slate-700" />
                            </td>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">User</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Phone</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Receipt</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Amount</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Checked</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Paid At</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Disbursement</th>
                            <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800">
                        <tr v-for="item in paymentsData" :key="item.id" class="border-t border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-3">
                                <input type="checkbox" :value="item.id" v-model="selectedTenantPayments"
                                    @change="toggleSelectAll" class="rounded border-gray-300 dark:border-gray-600 dark:bg-slate-700" />
                            </td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.user }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.phone }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.receipt_number }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.amount }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.checked_label }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.paid_at }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(item.disbursement_status)]">
                                    {{ item.disbursement_label }}
                                </span>
                            </td>
                            <td class="space-x-2 px-4 py-2">
                                <button v-if="item.editable" @click="openEditModal(item)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    <Edit class="h-4 w-4" />
                                </button>
                                <button v-if="item.editable" @click="confirmPaymentDeletion(item.id)" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                                <button @click="showPaymentDetails(item)" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300">
                                    <Eye class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <!-- Payment Details Modal -->
                        <Modal :show="showDetailsModal" @close="closeDetailsModal">
                            <div v-if="paymentDetails" class="space-y-2 p-6">
                                <h2 class="mb-2 text-lg font-bold">
                                    Payment Details
                                </h2>
                                <div v-if="paymentDetails.business_name">
                                    <strong>Business:</strong>
                                    {{ paymentDetails.business_name }}
                                </div>
                                <div v-if="paymentDetails.package">
                                    <strong>Package:</strong>
                                    {{ paymentDetails.package.type }}<br />
                                    <strong>Package Price:</strong> {{ currency }}
                                    {{ paymentDetails.package.price }}
                                </div>
                                <div>
                                    <strong>User:</strong> {{ paymentDetails.user }}
                                </div>
                                <div>
                                    <strong>Phone:</strong>
                                    {{ paymentDetails.phone }}
                                </div>
                                <div>
                                    <strong>Receipt Number:</strong>
                                    {{ paymentDetails.receipt_number }}
                                </div>
                                <div>
                                    <strong>Amount:</strong>
                                    {{ paymentDetails.amount }}
                                </div>
                                <div>
                                    <strong>Checked:</strong>
                                    {{ paymentDetails.checked_label }}
                                </div>
                                <div>
                                    <strong>Paid At:</strong>
                                    {{ paymentDetails.paid_at }}
                                </div>
                                <div>
                                    <strong>Disbursement:</strong>
                                    {{ paymentDetails.disbursement_label }}
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <PrimaryButton @click="generatePaymentConfirmation">Generate Payment
                                        Confirmation</PrimaryButton>
                                    <PrimaryButton @click="closeDetailsModal">Close</PrimaryButton>
                                </div>
                            </div>
                        </Modal>
                    </tbody>
                </table>
            </div>

            <Pagination :links="payments.links" />
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="space-y-4 p-6">
                <h2 class="mb-4 text-xl font-semibold">
                    {{ editing ? 'Edit Payment' : 'Add Payment' }}
                </h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- User select with filter -->
                    <div>
                        <InputLabel for="user_id" value="User" />

                        <!-- Search Input -->
                        <TextInput v-model="userSearch" type="text" placeholder="Search user by name or phone..." class="mb-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm 
           focus:border-blue-500 focus:ring-blue-500
           dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400" />

                        <!-- Select Dropdown -->
                        <select v-model="form.user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
           focus:border-blue-500 focus:ring-blue-500
           dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400">
                            <option value="" class="bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                Select User
                            </option>
                            <option v-for="u in filteredUsers" :key="u.id" :value="u.id"
                                class="bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ u.username }} ({{ u.phone }})
                            </option>
                        </select>

                        <InputError :message="form.errors.user_id" class="mt-2" />
                    </div>


                    <!-- Auto-filled phone (readonly) -->
                    <div>
                        <InputLabel for="phone" value="Phone" />
                        <TextInput v-model="form.phone" id="phone" class="mt-1 block w-full" readonly />
                    </div>

                    <div>
                        <InputLabel for="receipt_number" value="Receipt Number" />
                        <TextInput v-model="form.receipt_number" id="receipt_number" class="mt-1 block w-full" />
                        <InputError :message="form.errors.receipt_number" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="amount" value="Amount" />
                        <TextInput v-model="form.amount" id="amount" type="number" step="0.01"
                            class="mt-1 block w-full" />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>

                    <!-- Checked select (Yes/No as boolean) -->
                    <div>
                        <InputLabel for="checked" value="Checked" />

                        <select v-model="form.checked" id="checked" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
           focus:border-blue-500 focus:ring-blue-500
           dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400
           transition-colors duration-200 ease-in-out">
                            <option :value="true" class="bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                Yes
                            </option>
                            <option :value="false" class="bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                No
                            </option>
                        </select>

                        <InputError :message="form.errors.checked" class="mt-2" />
                    </div>


                    <div>
                        <InputLabel for="paid_at" value="Paid At" />
                        <TextInput v-model="form.paid_at" id="paid_at" type="datetime-local"
                            class="mt-1 block w-full" />
                        <InputError :message="form.errors.paid_at" class="mt-2" />
                    </div>

                    <!-- Disbursement select -->
                    <div>
                        <InputLabel for="disbursement_status" value="Disbursement Status" />

                        <select v-model="form.disbursement_status" id="disbursement_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
           focus:border-blue-500 focus:ring-blue-500
           dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400
           transition-colors duration-200 ease-in-out">
                            <option v-for="opt in DISBURSEMENT_OPTIONS" :key="opt.value" :value="opt.value"
                                class="bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ opt.label }}
                            </option>
                        </select>

                        <InputError :message="form.errors.disbursement_status" class="mt-2" />
                    </div>

                </div>

                <div class="mt-4 text-right">
                    <PrimaryButton :disabled="form.processing">
                        {{ editing ? 'Update' : 'Save' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
select {
    padding-right: 2rem;
    min-width: 120px;
    appearance: none;
    background: url('data:image/svg+xml;utf8,<svg fill="none" stroke="%23333" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>') no-repeat right 0.75rem center/1rem 1rem;
}
</style>
