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
    TrendingUp,
    PieChart,
    Calendar,
    BarChart2,
    Eye,
    EyeOff,
    Filter,
    Download,
    Search,
    ChevronDown,
    MoreVertical,
    Phone,
    Hash,
    CheckCircle2,
    Clock,
    User as UserIcon,
    Package as PackageIcon,
    X,
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
    const term = userSearch.value.toLowerCase().trim();
    if (!term) return props.users.slice(0, 10);
    return props.users.filter(
        (u) =>
            (u.username && u.username.toLowerCase().includes(term)) ||
            (u.full_name && u.full_name.toLowerCase().includes(term)) ||
            (u.account_number && u.account_number.toLowerCase().includes(term)) ||
            (u.phone && u.phone.toLowerCase().includes(term)),
    ).slice(0, 20);
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

// Row Actions Modal (Mikrotik-style)
const showRowActions = ref(false);
const selectedRowPayment = ref(null);
function openRowActions(payment) {
    selectedRowPayment.value = payment;
    showRowActions.value = true;
}
function closeDetailsModal() {
    showDetailsModal.value = false;
    paymentDetails.value = null;
}

const showExport = ref(false);

function generatePaymentConfirmation(p = null) {
    const data = p || paymentDetails.value;
    if (!data) return;

    const doc = new jsPDF();
    // Colored header
    doc.setFillColor(41, 128, 185); // blue
    doc.rect(0, 0, 210, 30, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(20);
    doc.text(
        data.business_name || 'Payment Confirmation',
        14,
        20,
    );
    doc.setFontSize(12);
    doc.setTextColor(44, 62, 80); // dark
    let y = 40;

    // Package info section
    if (data.package) {
        doc.setFont(undefined, 'bold');
        doc.setTextColor(41, 128, 185);
        doc.text('Package:', 14, y);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(44, 62, 80);
        doc.text(`${data.package.type}`, 45, y);
        y += 8;
        doc.setFont(undefined, 'bold');
        doc.setTextColor(41, 128, 185);
        doc.text('Package Price:', 14, y);
        doc.setFont(undefined, 'normal');
        doc.setTextColor(44, 62, 80);
        doc.text(`${currency.value} ${data.package.price}`, 45, y);
        y += 10;
    }

    // Payment details section
    const details = [
        ['User', data.user],
        ['Phone', data.phone],
        ['Receipt Number', data.receipt_number],
        ['Amount', `${currency.value} ${data.amount}`],
        ['Paid At', data.paid_at],
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
        `confirmation_${data.receipt_number || 'payment'}.pdf`,
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

            <!-- Stats Cards Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Daily Income -->
                <div class="group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm border border-slate-100 dark:bg-slate-800 dark:border-slate-700 hover:shadow-md transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-blue-50 p-3 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400 font-bold group-hover:scale-110 transition-transform">
                            <TrendingUp class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate">Daily Income</p>
                            <h3 class="text-xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5 truncate">
                                {{ showStats ? currency + ' ' + dailyIncome.toLocaleString() : '••••••' }}
                            </h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-700/50 flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Select Date</span>
                        <input type="date" v-model="selectedDay" class="w-auto max-w-[130px] border-none bg-blue-50/50 dark:bg-blue-400/5 px-2 py-1 text-[11px] font-extrabold text-blue-600 dark:text-blue-400 focus:ring-1 focus:ring-blue-500/20 rounded-lg cursor-pointer" />
                    </div>
                </div>

                <!-- Weekly Income -->
                <div class="group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm border border-slate-100 dark:bg-slate-800 dark:border-slate-700 hover:shadow-md transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-emerald-50 p-3 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400 font-bold group-hover:scale-110 transition-transform">
                            <BarChart2 class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate">Weekly Income</p>
                            <h3 class="text-xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5 truncate">
                                {{ showStats ? currency + ' ' + weeklyIncome.toLocaleString() : '••••••' }}
                            </h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-700/50 flex items-center justify-between gap-1.5 overflow-x-auto scrollbar-hide">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Period</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <select v-model="selectedWeek" class="border-none bg-emerald-50/50 dark:bg-emerald-400/5 px-2 pr-6 py-1 text-[11px] font-extrabold text-emerald-600 dark:text-emerald-400 focus:ring-1 focus:ring-emerald-500/20 rounded-lg cursor-pointer">
                                <option v-for="w in 52" :key="w" :value="w">Wk {{ w }}</option>
                            </select>
                            <select v-model="selectedWeekYear" class="border-none bg-emerald-50/50 dark:bg-emerald-400/5 px-2 pr-6 py-1 text-[11px] font-extrabold text-emerald-600 dark:text-emerald-400 focus:ring-1 focus:ring-emerald-500/20 rounded-lg cursor-pointer">
                                <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Monthly Income -->
                <div class="group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm border border-slate-100 dark:bg-slate-800 dark:border-slate-700 hover:shadow-md transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-indigo-50 p-3 text-indigo-600 dark:bg-indigo-400/10 dark:text-indigo-400 font-bold group-hover:scale-110 transition-transform">
                            <Calendar class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate">Monthly Income</p>
                            <h3 class="text-xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5 truncate">
                                {{ showStats ? currency + ' ' + monthlyIncome.toLocaleString() : '••••••' }}
                            </h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-700/50 flex items-center justify-between gap-1.5 overflow-x-auto scrollbar-hide">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Month</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <select v-model="selectedMonth" class="border-none bg-indigo-50/50 dark:bg-indigo-400/5 px-2 pr-6 py-1 text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 focus:ring-1 focus:ring-indigo-500/20 rounded-lg cursor-pointer">
                                <option v-for="m in 12" :key="m" :value="m">{{ new Date(2000, m-1, 1).toLocaleString(locale, { month: 'short' }) }}</option>
                            </select>
                            <select v-model="selectedMonthYear" class="border-none bg-indigo-50/50 dark:bg-indigo-400/5 px-2 pr-6 py-1 text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 focus:ring-1 focus:ring-indigo-500/20 rounded-lg cursor-pointer">
                                <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Yearly Income -->
                <div class="group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm border border-slate-100 dark:bg-slate-800 dark:border-slate-700 hover:shadow-md transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-orange-50 p-3 text-orange-600 dark:bg-orange-400/10 dark:text-orange-400 font-bold group-hover:scale-110 transition-transform">
                            <PieChart class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate">Yearly Income</p>
                            <h3 class="text-xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5 truncate">
                                {{ showStats ? currency + ' ' + yearlyIncome.toLocaleString() : '••••••' }}
                            </h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-700/50 flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">Year</span>
                        <select v-model="selectedYear" class="border-none bg-orange-50/50 dark:bg-orange-400/5 px-2 pr-6 py-1 text-[11px] font-extrabold text-orange-600 dark:text-orange-400 focus:ring-1 focus:ring-orange-500/20 rounded-lg cursor-pointer">
                            <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between rounded-2xl bg-white p-4 shadow-sm border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex flex-1 items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="globalSearch" type="text" placeholder="Search user or phone..." 
                            class="w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-4 py-2 text-sm transition-focus focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white dark:focus:border-blue-500" />
                    </div>

                    <!-- Actions Modal Toggle -->
                    <button @click="showFilters = true" 
                        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 transition-all">
                        <Filter class="h-4 w-4" />
                        <span>Filters & Actions</span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Placeholder for any additional right-aligned actions if needed -->
                </div>
            </div>

            <!-- Filters & Actions Modal -->
            <Modal :show="showFilters" @close="showFilters = false" max-width="md">
                <div class="p-6">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Filters & Actions</h3>
                        <button @click="showFilters = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-6">
                        <!-- Filters Section -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400">Filter By</h4>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <InputLabel value="Year" class="mb-1" />
                                    <select v-model="filterYear" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                                        <option v-for="y in 5" :key="y" :value="today.getFullYear() - y + 1">{{ today.getFullYear() - y + 1 }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Month" class="mb-1" />
                                    <select v-model="filterMonth" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                                        <option :value="0">All Months</option>
                                        <option v-for="m in 12" :key="m" :value="m">{{ new Date(2000, m - 1, 1).toLocaleString(locale, { month: 'long' }) }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Disbursement Status" class="mb-1" />
                                    <select v-model="filterStatus" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                                        <option value="">All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Export Section -->
                        <div class="border-t border-slate-100 pt-6 dark:border-slate-700/50">
                            <h4 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">Export Data</h4>
                            <div class="flex items-center gap-3">
                                <select v-model="exportFormat" class="flex-1 rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                                    <option value="csv">CSV (Excel)</option>
                                    <option value="pdf">PDF Report</option>
                                </select>
                                <PrimaryButton @click="exportPayments" class="flex items-center gap-2">
                                    <Download class="h-4 w-4" />
                                    <span>Export</span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <PrimaryButton @click="showFilters = false">Done</PrimaryButton>
                    </div>
                </div>
            </Modal>

            <!-- Bulk Actions 
            <div v-if="selectedTenantPayments.length > 0" class="flex items-center gap-3 animate-in fade-in slide-in-from-left-4 duration-300">
                <span class="text-sm font-medium text-slate-500">{{ selectedTenantPayments.length }} selected</span>
                <DangerButton @click="bulkDelete" class="flex items-center gap-2 rounded-xl">
                    <Trash2 class="h-4 w-4" /> 
                    <span>Bulk Delete</span>
                </DangerButton>
            </div>-->

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
                                    <!-- <th class="px-6 py-4 w-10">
                                        <input type="checkbox" v-model="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700" />
                                    </th>-->
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
                                    <!-- <td class="px-6 py-4" @click.stop>
                                        <input type="checkbox" :value="item.uuid" v-model="selectedTenantPayments"
                                            @change="toggleSelectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700" />
                                    </td> -->
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
                                        <button @click="openRowActions(item)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                                            <MoreVertical class="h-5 w-5" />
                                        </button>
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
                            <div class="flex flex-col items-end gap-1.5" @click.stop>
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase ring-1 ring-inset', getStatusColor(item.disbursement_status)]">
                                    {{ item.disbursement_label }}
                                </span>
                                <button @click="openRowActions(item)" class="flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition-all hover:bg-slate-200 active:scale-95 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">
                                    <MoreVertical class="h-4 w-4" />
                                    <span>Actions</span>
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
                        <Modal :show="showDetailsModal" @close="closeDetailsModal" max-width="lg">
                            <div v-if="paymentDetails" class="p-0 overflow-hidden">
                                <!-- Header -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-xl bg-blue-600 p-2 text-white">
                                                <Banknote class="h-5 w-5" />
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Transaction Details</h3>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ paymentDetails.receipt_number }}</p>
                                            </div>
                                        </div>
                                        <button @click="closeDetailsModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                                            <X class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div class="p-6 space-y-6">
                                    <!-- Amount Card -->
                                    <div class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100/50 dark:border-blue-800/20 text-center">
                                        <p class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-1">Paid Amount</p>
                                        <h4 class="text-3xl font-black text-slate-900 dark:text-white">
                                            {{ currency }} {{ paymentDetails.amount }}
                                        </h4>
                                    </div>

                                    <!-- Details Grid -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-5 gap-x-8">
                                        <div v-if="paymentDetails.business_name">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Business</p>
                                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.business_name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">User</p>
                                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.user }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Phone Number</p>
                                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.phone?.substring(0, 14) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Paid Date</p>
                                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.paid_at }}</p>
                                        </div>
                                        <div v-if="paymentDetails.package">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Service Package</p>
                                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.package.type }} ({{ currency }} {{ paymentDetails.package.price }})</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Verified</p>
                                            <p :class="['text-xs font-bold inline-flex items-center px-2 py-0.5 rounded-full', paymentDetails.checked_label === 'Yes' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300']">
                                                {{ paymentDetails.checked_label }}
                                            </p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Disbursement Status</p>
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ paymentDetails.disbursement_label }}</p>
                                                <span v-if="paymentDetails.disbursement_ref" class="text-[10px] font-mono bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-slate-500 dark:text-slate-400">
                                                    Ref: {{ paymentDetails.disbursement_ref }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="bg-slate-50 dark:bg-slate-900/50 px-6 py-4 flex flex-col sm:flex-row justify-end gap-3">
                                    <PrimaryButton @click="generatePaymentConfirmation" class="w-full sm:w-auto flex items-center justify-center gap-2">
                                        <Download class="h-4 w-4" />
                                        <span>Download Confirmation</span>
                                    </PrimaryButton>
                                    <button @click="closeDetailsModal" class="w-full sm:w-auto px-4 py-2 text-sm font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
                                        Close
                                    </button>
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

        <!-- Add/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false" max-width="lg">
            <form @submit.prevent="submit" class="p-0 overflow-hidden">
                <!-- Header -->
                <div class="bg-slate-50 dark:bg-slate-900/50 px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-blue-600 p-2 text-white">
                                <component :is="editing ? Edit : Plus" class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">
                                    {{ editing ? (form.user_id ? 'Edit Payment Record' : 'Reconcile / Assign Payment') : 'Record New Payment' }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Manual transaction entry</p>
                            </div>
                        </div>
                        <button @click="showModal = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Form Body -->
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2 space-y-2">
                            <InputLabel for="user_id" value="Select User" class="dark:text-slate-300" />
                            
                            <!-- Search Input -->
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                                <TextInput 
                                    v-model="userSearch"
                                    type="text"
                                    class="pl-10 w-full rounded-xl"
                                    placeholder="Search by username, name, account, or phone..."
                                />
                            </div>

                            <!-- User Selection List -->
                            <div class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/40">
                                <button v-for="user in filteredUsers" :key="user.id" 
                                    type="button"
                                    @click="form.user_id = user.id"
                                    :class="['w-full text-left px-4 py-3 transition-colors hover:bg-blue-50 dark:hover:bg-blue-900/10', form.user_id === user.id ? 'bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-500 ring-inset' : '']"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-bold text-slate-900 dark:text-white">
                                                {{ user.username }} 
                                                <span v-if="user.full_name" class="ml-1 font-normal text-slate-500">({{ user.full_name }})</span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 uppercase tracking-tighter">
                                                ACC: {{ user.account_number }} • {{ user.phone }}
                                            </div>
                                        </div>
                                        <div v-if="form.user_id === user.id" class="text-blue-600">
                                            <CheckCircle2 class="h-4 w-4" />
                                        </div>
                                    </div>
                                </button>
                                <div v-if="filteredUsers.length === 0" class="p-4 text-center text-sm text-slate-500 italic">
                                    No matching users found...
                                </div>
                            </div>
                            <InputError :message="form.errors.user_id" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="amount" value="Amount" class="dark:text-slate-300" />
                            <TextInput id="amount" v-model="form.amount" type="number" step="0.01" 
                                class="mt-1 block w-full rounded-xl" placeholder="0.00" required />
                            <InputError :message="form.errors.amount" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Phone Number" class="dark:text-slate-300" />
                            <TextInput id="phone" v-model="form.phone" type="text" 
                                class="mt-1 block w-full rounded-xl" placeholder="254..." required />
                            <InputError :message="form.errors.phone" class="mt-1" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="receipt_number" value="Receipt / Transaction Number" class="dark:text-slate-300" />
                            <TextInput id="receipt_number" v-model="form.receipt_number" type="text" 
                                class="mt-1 block w-full rounded-xl" placeholder="e.g. QWE123RTY" required />
                            <InputError :message="form.errors.receipt_number" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-slate-50 dark:bg-slate-900/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                    <button @click="showModal = false" type="button" class="px-4 py-2 text-sm font-bold text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
                        Cancel
                    </button>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ editing ? 'Update Record' : 'Save Payment' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Row Actions Modal (Mikrotik-style) -->
        <Modal :show="showRowActions" @close="showRowActions = false" max-width="sm">
            <div v-if="selectedRowPayment" class="p-4">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-700">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">Actions</h3>
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider">{{ selectedRowPayment.receipt_number }}</p>
                    </div>
                    <button @click="showRowActions = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-1.5">
                    <button @click="showPaymentDetails(selectedRowPayment); showRowActions = false" 
                        class="flex w-full items-center gap-3 rounded-xl p-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <Eye class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white">View Details</div>
                            <div class="text-xs text-slate-500">Transaction summary & stats</div>
                        </div>
                    </button>

                    <button @click="generatePaymentConfirmation(selectedRowPayment); showRowActions = false" 
                        class="flex w-full items-center gap-3 rounded-xl p-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                            <Download class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white">Get Receipt</div>
                            <div class="text-xs text-slate-500">Download confirmation PDF</div>
                        </div>
                    </button>

                    <template v-if="selectedRowPayment.editable">
                        <div class="my-2 border-t border-slate-100 dark:border-slate-700"></div>

                        <button @click="openEditModal(selectedRowPayment); showRowActions = false" 
                            class="flex w-full items-center gap-3 rounded-xl p-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <Edit class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedRowPayment.user_id === null ? 'Reconcile / Assign' : 'Edit Record' }}</div>
                                <div class="text-xs text-slate-500">{{ selectedRowPayment.user_id === null ? 'Assign this payment to a user' : 'Update transaction info' }}</div>
                            </div>
                        </button>

                        <button @click="confirmPaymentDeletion(selectedRowPayment.uuid); showRowActions = false" 
                            class="flex w-full items-center gap-3 rounded-xl p-3 text-left transition-colors hover:bg-red-50 dark:hover:bg-red-900/20">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                                <Trash2 class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-sm font-bold text-red-600">Delete Permanently</div>
                                <div class="text-xs text-red-400/80">This action cannot be undone</div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
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
