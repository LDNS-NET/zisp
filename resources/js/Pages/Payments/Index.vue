<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
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
    EyeOff,
    Filter,
    Download,
    Search,
    ChevronDown,
    MoreHorizontal,
    Phone,
    Hash,
    CheckCircle2,
    Clock,
    User as UserIcon,
    Package as PackageIcon,
} from 'lucide-vue-next';
import Card from '@/Components/Card.vue';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useToast } from 'vue-toastification';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const props = defineProps({
    payments: { type: Object, default: () => ({ data: [], allData: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    currency: String,
});

const toast = useToast();

const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 768;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

const page = usePage();
const locale = 'en-KE'; // Could be dynamic too if needed, but keeping fixed for now or could derive from user



const today = new Date();
const filterYear = ref(Number(props.filters.year) || today.getFullYear());
const filterMonth = ref(Number(props.filters.month) || 0); // 0 means all months
const filterWeek = ref(0); // 0 means all weeks
const filterStatus = ref(props.filters.disbursement || '');
const showFilters = ref(false);

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return '?';
    return name.split(' ').filter(Boolean).map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

// Use all payments for summary calculations, not just paginated page
const allPayments = computed(() => props.payments?.allData ?? []);
const showStats = ref(true);

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
            return paid.getFullYear() === selectedYear.value;
        })
        .reduce((sum, p) => sum + Number(p.amount), 0);
});

const isLoading = ref(false);
const globalSearch = ref(props.filters.search || '');

// Synchronize filters with server
watch([globalSearch, filterYear, filterMonth, filterStatus], ([search, year, month, status]) => {
    router.get(route('payments.index'), {
        search: search,
        year: year,
        month: month,
        disbursement: status
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
});

// Advanced filters and export

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

const paymentsData = computed(() => {
    // Controller already handles search, status, year, month on the server
    // We only map the display labels here
    return (props.payments?.data || []).map((p) => ({
        ...p,
        checked_label: p.checked === true || p.checked === 'true' ? 'Yes' : 'No',
    }));
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

const currency = computed(() => props.currency || page.props.tenant?.currency || 'KES');

const showModal = ref(false);
const editing = ref(null);
const selectedTenantPayments = ref([]);
const selectAll = ref(false);

// Select all/deselect all logic
watch(selectAll, (val) => {
    if (val) {
        selectedTenantPayments.value = props.payments.data.map((p) => p.uuid);
    } else {
        selectedTenantPayments.value = [];
    }
});

// Individual selection logic
const toggleSelectAll = () => {
    const dataCount = props.payments?.data?.length || 0;
    if (dataCount > 0 && selectedTenantPayments.value.length === dataCount) {
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
    { value: 'testing', label: 'Testing Mode' },
];

const getStatusColor = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'processing':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'testing':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
    }
};

const form = useForm({
    user_id: '',
    receipt_number: '',
    amount: '',
    paid_at: '',
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
    form.paid_at = normalizeToDatetimeLocal(payment.paid_at);
    form.phone = payment.phone ?? '';
    editing.value = payment.uuid;
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
    selectedTenantPayments.value = (val && props.payments?.data)
        ? props.payments.data.map((p) => p.uuid)
        : [];
});

const allIds = computed(() => props.payments?.data?.map((p) => p.uuid) || []);

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

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-blue-600/10 p-2.5 dark:bg-blue-400/10">
                            <Banknote class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Payments</h1>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage and track all customer transactions</p>
                        </div>
                    </div>
                </div>
                <PrimaryButton @click="openAddModal" class="group flex items-center justify-center gap-2 rounded-xl px-5 py-3 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]">
                    <Plus class="h-5 w-5 transition-transform group-hover:rotate-90" />
                    <span>Record Payment</span>
                </PrimaryButton>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Daily Income -->
                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-sm transition-all hover:shadow-md dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-2xl bg-blue-50 p-3 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-bold group-hover:scale-110 transition-transform">
                            <CalendarDays class="h-6 w-6" />
                        </div>
                        <button @click="showStats = !showStats" class="text-slate-400 hover:text-blue-500">
                            <component :is="showStats ? Eye : EyeOff" class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Daily Income</p>
                        <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ showStats ? currency + ' ' + dailyIncome.toLocaleString() : '••••••' }}
                        </h3>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-slate-50 pt-4 dark:border-slate-700/50">
                        <input type="date" v-model="selectedDay" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-blue-600 focus:ring-0 dark:text-blue-400" />
                    </div>
                </div>

                <!-- Weekly Income -->
                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-sm transition-all hover:shadow-md dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-2xl bg-emerald-50 p-3 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold group-hover:scale-110 transition-transform">
                            <BarChart2 class="h-6 w-6" />
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">W{{ selectedWeek }}</span>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Weekly Income</p>
                        <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ showStats ? currency + ' ' + weeklyIncome.toLocaleString() : '••••••' }}
                        </h3>
                    </div>
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-50 pt-4 dark:border-slate-700/50">
                         <select v-model="selectedWeek" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-emerald-600 focus:ring-0 dark:text-emerald-400">
                            <option v-for="w in 52" :key="w" :value="w">Week {{ w }}</option>
                        </select>
                        <select v-model="selectedWeekYear" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-emerald-600 focus:ring-0 dark:text-emerald-400">
                            <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                        </select>
                    </div>
                </div>

                <!-- Monthly Income -->
                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-sm transition-all hover:shadow-md dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-2xl bg-indigo-50 p-3 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 font-bold group-hover:scale-110 transition-transform">
                            <Calendar class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Monthly Income</p>
                        <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ showStats ? currency + ' ' + monthlyIncome.toLocaleString() : '••••••' }}
                        </h3>
                    </div>
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-50 pt-4 dark:border-slate-700/50">
                        <select v-model="selectedMonth" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-indigo-600 focus:ring-0 dark:text-indigo-400">
                            <option v-for="m in 12" :key="m" :value="m">{{ new Date(2000, m-1, 1).toLocaleString(locale, { month: 'long' }) }}</option>
                        </select>
                        <select v-model="selectedMonthYear" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-indigo-600 focus:ring-0 dark:text-indigo-400">
                            <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                        </select>
                    </div>
                </div>

                <!-- Yearly Income -->
                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-sm transition-all hover:shadow-md dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-2xl bg-orange-50 p-3 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 font-bold group-hover:scale-110 transition-transform">
                            <BarChart2 class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Yearly Income</p>
                        <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ showStats ? currency + ' ' + yearlyIncome.toLocaleString() : '••••••' }}
                        </h3>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-slate-50 pt-4 dark:border-slate-700/50">
                        <select v-model="selectedYear" class="h-7 border-none bg-transparent p-0 text-xs font-bold text-orange-600 focus:ring-0 dark:text-orange-400">
                            <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Actions Bar: Filters & Export -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-2xl bg-white p-4 shadow-sm border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="globalSearch" type="text" placeholder="Search user or phone..." 
                            class="w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-4 py-2 text-sm transition-focus focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white dark:focus:border-blue-500" />
                    </div>

                    <!-- Filter Toggle Toggle (Mobile Only) -->
                    <button @click="showFilters = !showFilters" 
                        class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900/50 lg:hidden">
                        <Filter class="h-4 w-4" />
                        Filters
                    </button>

                    <!-- Desktop Filters -->
                    <div :class="['flex-wrap items-center gap-3', showFilters ? 'flex w-full' : 'hidden lg:flex']">
                        <select v-model="filterYear" class="rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                            <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                        </select>
                        <select v-model="filterMonth" class="rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                            <option :value="0">All Months</option>
                            <option v-for="m in 12" :key="m" :value="m">{{ new Date(2000, m - 1, 1).toLocaleString(locale, { month: 'short' }) }}</option>
                        </select>
                        <select v-model="filterStatus" class="rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex items-center overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <select v-model="exportFormat" class="border-none bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:ring-0 dark:bg-slate-900/50 dark:text-slate-300">
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                        <button @click="exportPayments" 
                            class="flex items-center gap-2 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
                            <Download class="h-4 w-4" />
                            <span>Export</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div v-if="selectedTenantPayments.length > 0" class="flex items-center gap-3 animate-in fade-in slide-in-from-left-4 duration-300">
                <span class="text-sm font-medium text-slate-500">{{ selectedTenantPayments.length }} selected</span>
                <DangerButton @click="bulkDelete" class="flex items-center gap-2 rounded-xl">
                    <Trash2 class="h-4 w-4" /> 
                    <span>Bulk Delete</span>
                </DangerButton>
            </div>

            <!-- Data Display -->
            <div class="relative min-h-[400px]">
                <!-- Loading State (Optional Overlay) -->
                <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-sm dark:bg-slate-900/50">
                    <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
                </div>

                <!-- Desktop Table View -->
                <div v-if="!isMobile" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/50 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 w-10">
                                        <input type="checkbox" v-model="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700" />
                                    </th>
                                    <th class="px-6 py-4 min-w-[200px]">User</th>
                                    <th class="px-6 py-4 hidden md:table-cell">Transaction</th>
                                    <th class="px-6 py-4">Amount</th>
                                    <th class="px-6 py-4 hidden sm:table-cell">Status</th>
                                    <th class="px-6 py-4 hidden lg:table-cell min-w-[140px]">Date</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-for="item in paymentsData" :key="item.uuid" 
                                    class="group cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50"
                                    @click="showPaymentDetails(item)">
                                    <td class="px-6 py-4" @click.stop>
                                        <input type="checkbox" :value="item.uuid" v-model="selectedTenantPayments"
                                            @change="toggleSelectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700" />
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 font-bold text-white shadow-sm transition-transform group-hover:scale-105">
                                                {{ getInitials(item.user) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate max-w-[150px]" :title="item.user">{{ item.user }}</div>
                                                <div class="flex items-center gap-1.5 text-xs text-slate-500 truncate max-w-[140px]" :title="item.phone">
                                                    <Phone class="h-3 w-3 shrink-0" />
                                                    <span class="truncate">{{ item.phone }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 hidden md:table-cell">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2 font-mono text-xs font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[120px]" :title="item.receipt_number">
                                                <Hash class="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                                <span class="truncate">{{ item.receipt_number }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-slate-400">
                                                <PackageIcon class="h-3 w-3" />
                                                {{ item.checked_label === 'Yes' ? 'Confirmed' : 'Unchecked' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-black text-slate-900 dark:text-white">{{ item.amount }}</div>
                                        <div class="sm:hidden mt-0.5">
                                             <span :class="['inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset', getStatusColor(item.disbursement_status)]">
                                                {{ item.disbursement_label }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 hidden sm:table-cell">
                                        <span :class="['inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 ring-inset', getStatusColor(item.disbursement_status)]">
                                            <component :is="item.disbursement_status === 'completed' ? CheckCircle2 : Clock" class="h-3.5 w-3.5" />
                                            {{ item.disbursement_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 hidden lg:table-cell">
                                        <div class="flex flex-col whitespace-nowrap">
                                            <span class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ item.paid_at?.split(' ')[0] || 'N/A' }}</span>
                                            <span class="text-[10px] text-slate-400">{{ item.paid_at?.split(' ')[1] || '' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right" @click.stop>
                                        <div class="flex justify-end items-center">
                                            <Dropdown align="right" width="48">
                                                <template #trigger>
                                                    <button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                                        <MoreHorizontal class="h-5 w-5" />
                                                    </button>
                                                </template>
                                                <template #content>
                                                    <DropdownLink @click="showPaymentDetails(item)" class="flex items-center gap-2">
                                                        <Eye class="h-4 w-4 text-emerald-500" /> Details
                                                    </DropdownLink>
                                                    <DropdownLink v-if="item.editable" @click="openEditModal(item)" class="flex items-center gap-2">
                                                        <Edit class="h-4 w-4 text-blue-500" /> Edit
                                                    </DropdownLink>
                                                    <div v-if="item.editable" class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                                    <DropdownLink v-if="item.editable" @click="confirmPaymentDeletion(item.uuid)" class="flex items-center gap-2 text-red-600">
                                                        <Trash2 class="h-4 w-4" /> Delete
                                                    </DropdownLink>
                                                </template>
                                            </Dropdown>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div v-else class="space-y-4">
                    <div v-for="item in paymentsData" :key="item.uuid" 
                        class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all active:scale-[0.98] dark:border-slate-700 dark:bg-slate-800"
                        @click="showPaymentDetails(item)">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 font-bold text-white shadow-sm">
                                    {{ getInitials(item.user) }}
                                </div>
                                <div>
                                    <div class="font-black text-slate-900 dark:text-white truncate max-w-[180px]" :title="item.user">{{ item.user }}</div>
                                    <div class="flex items-center gap-1 text-xs text-slate-500 truncate max-w-[160px]" :title="item.receipt_number">
                                        <Hash class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{ item.receipt_number }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase ring-1 ring-inset', getStatusColor(item.disbursement_status)]">
                                    {{ item.disbursement_label }}
                                </span>
                                <button @click="showPaymentDetails(item)" class="flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    <Eye class="h-3.5 w-3.5" /> Details
                                </button>
                                <button v-if="item.editable" @click="openEditModal(item)" class="rounded-lg bg-slate-100 p-1.5 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    <Edit class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="paymentsData.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="rounded-full bg-slate-100 p-4 dark:bg-slate-800">
                        <Search class="h-10 w-10 text-slate-400" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">No payments found</h3>
                    <p class="mt-1 text-slate-500">Try adjusting your filters or search terms</p>
                </div>
            </div>
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
                                <div v-if="paymentDetails.disbursement_ref">
                                    <strong>Disbursement Ref:</strong>
                                    {{ paymentDetails.disbursement_ref }}
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <PrimaryButton @click="generatePaymentConfirmation">Generate Payment
                                        Confirmation</PrimaryButton>
                                    <PrimaryButton @click="closeDetailsModal">Close</PrimaryButton>
                                </div>
                            </div>
                        </Modal>
            </div>

            <div v-show="payments.total > 0" class="flex justify-center mt-6">
                <Pagination 
                    :links="payments.links" 
                    :per-page="payments.per_page"
                    :total="payments.total"
                    :from="payments.from"
                    :to="payments.to"
                />
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

                    <!-- Note: Checked is automatically set to 'true' for manual payments -->

                    <div>
                        <InputLabel for="paid_at" value="Paid At" />
                        <TextInput v-model="form.paid_at" id="paid_at" type="datetime-local"
                            class="mt-1 block w-full" />
                        <InputError :message="form.errors.paid_at" class="mt-2" />
                    </div>

                    <!-- Note: Disbursement status is automatically set to 'completed' for manual payments -->

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
