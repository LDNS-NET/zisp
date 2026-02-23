<!-- resources/js/Pages/Inventory/Partials/InventoryDashboard.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {
    Package,
    Barcode,
    Move3d,
    Wrench,
    Cable,
    AlertTriangle,
    CheckCircle,
    Calendar,
    Users,
    TrendingDown,
    DollarSign,
    TrendingUp,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    totalPrice: Number,
    stats: Object,
    onNavigate: Function,
});

const loading = ref(false);
const lowStockItems = ref([]);
const pendingRequests = ref([]);
const maintenanceAlerts = ref([]);

const emit = defineEmits(['navigate']);

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        // Fetch low stock items
        const lowStockResponse = await axios.get(route('equipment.low-stock'));
        lowStockItems.value = lowStockResponse.data;

        // Fetch pending requests
        const requestsResponse = await axios.get(
            route('equipment.requests.pending'),
        );
        pendingRequests.value = requestsResponse.data;

        // Fetch maintenance alerts
        const maintenanceResponse = await axios.get(
            route('equipment.maintenance.alerts'),
        );
        maintenanceAlerts.value = maintenanceResponse.data;
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchDashboardData();
});

const handleNavigate = (view) => {
    emit('navigate', view);
};

const getMaintenanceStatusColor = (status) => {
    switch (status) {
        case 'OVERDUE':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'DUE_SOON':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
        case 'SCHEDULED':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Quick Actions -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <button
                @click="handleNavigate('items')"
                class="flex h-20 flex-col items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg transition-all hover:from-blue-600 hover:to-blue-700"
            >
                <Package class="h-6 w-6" />
                <span class="text-sm font-semibold">Manage Items</span>
            </button>
            <button
                @click="handleNavigate('serials')"
                class="flex h-20 flex-col items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg transition-all hover:from-purple-600 hover:to-purple-700"
            >
                <Barcode class="h-6 w-6" />
                <span class="text-sm font-semibold">Serial Numbers</span>
            </button>
            <button
                @click="handleNavigate('movements')"
                class="flex h-20 flex-col items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg transition-all hover:from-green-600 hover:to-green-700"
            >
                <Move3d class="h-6 w-6" />
                <span class="text-sm font-semibold">Stock Movements</span>
            </button>
            <button
                @click="handleNavigate('requests')"
                class="flex h-20 flex-col items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg transition-all hover:from-orange-600 hover:to-orange-700"
            >
                <Wrench class="h-6 w-6" />
                <span class="text-sm font-semibold">Equipment Requests</span>
            </button>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
            <!-- Total Items -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Total Items
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{
                                stats?.totalItems ||
                                equipment?.data?.length ||
                                0
                            }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <Package
                            class="h-5 w-5 text-blue-600 dark:text-blue-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ stats?.totalSerials || 0 }} serials tracked
                </p>
            </Card>

            <!-- Stock Health -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Stock Health
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ stats?.stockHealth || 95 }}%
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30"
                    >
                        <TrendingUp
                            class="h-5 w-5 text-green-600 dark:text-green-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ lowStockItems.length }} need attention
                </p>
            </Card>

            <!-- Out of Stock -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Out of Stock
                        </p>
                        <p
                            class="text-2xl font-bold text-red-600 dark:text-red-400"
                        >
                            {{ stats?.outOfStock || 0 }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                        <AlertTriangle
                            class="h-5 w-5 text-red-600 dark:text-red-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Immediate action required
                </p>
            </Card>

            <!-- Total Value -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Total Value
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            KES {{ totalPrice?.toLocaleString() || '0' }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30"
                    >
                        <DollarSign
                            class="h-5 w-5 text-amber-600 dark:text-amber-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Current inventory value
                </p>
            </Card>

            <!-- Maintenance -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Maintenance
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ maintenanceAlerts.length }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30"
                    >
                        <Calendar
                            class="h-5 w-5 text-purple-600 dark:text-purple-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Items need maintenance</p>
            </Card>

            <!-- Requests -->
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Requests
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{ pendingRequests.length }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-pink-100 p-2 dark:bg-pink-900/30">
                        <Users
                            class="h-5 w-5 text-pink-600 dark:text-pink-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Pending approvals</p>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Low Stock Alerts -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-yellow-600" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Low Stock Alerts
                        </h3>
                    </div>
                    <p class="text-sm text-gray-500">
                        Items that need restocking
                    </p>
                </template>

                <div class="space-y-3">
                    <div
                        v-for="item in lowStockItems.slice(0, 5)"
                        :key="item.id"
                        class="flex items-center justify-between rounded-lg border p-3 dark:border-gray-700"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'h-3 w-3 rounded-full',
                                    item.status === 'LOW_STOCK'
                                        ? 'bg-yellow-500'
                                        : 'bg-red-500',
                                ]"
                            ></div>
                            <div>
                                <div class="font-medium dark:text-white">
                                    {{ item.name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    Current: {{ item.quantity }} • Min:
                                    {{ item.min_stock || 5 }}
                                </div>
                            </div>
                        </div>
                        <StatusBadge :status="item.status" />
                    </div>

                    <div
                        v-if="!lowStockItems.length"
                        class="py-4 text-center text-gray-500"
                    >
                        <CheckCircle
                            class="mx-auto mb-2 h-8 w-8 text-green-500"
                        />
                        <p>All items are well stocked</p>
                    </div>
                </div>
            </Card>

            <!-- Maintenance Alerts -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Calendar class="h-5 w-5 text-blue-600" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Maintenance Schedule
                        </h3>
                    </div>
                    <p class="text-sm text-gray-500">
                        Upcoming equipment maintenance
                    </p>
                </template>

                <div class="space-y-3">
                    <div
                        v-for="alert in maintenanceAlerts.slice(0, 5)"
                        :key="alert.id"
                        class="flex items-center justify-between rounded-lg border p-3 dark:border-gray-700"
                    >
                        <div class="flex items-center gap-3">
                            <Wrench class="h-4 w-4 text-gray-400" />
                            <div>
                                <div class="font-medium dark:text-white">
                                    {{ alert.item?.name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ Math.abs(alert.daysUntil) }} days
                                    {{
                                        alert.status === 'OVERDUE'
                                            ? 'overdue'
                                            : 'remaining'
                                    }}
                                </div>
                            </div>
                        </div>
                        <span
                            :class="[
                                getMaintenanceStatusColor(alert.status),
                                'rounded-full px-2 py-1 text-xs font-medium',
                            ]"
                        >
                            {{ alert.status.replace('_', ' ') }}
                        </span>
                    </div>

                    <div
                        v-if="!maintenanceAlerts.length"
                        class="py-4 text-center text-gray-500"
                    >
                        <CheckCircle
                            class="mx-auto mb-2 h-8 w-8 text-green-500"
                        />
                        <p>No maintenance scheduled</p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Pending Equipment Requests -->
        <Card>
            <template #header>
                <div class="flex items-center gap-2">
                    <Users class="h-5 w-5 text-purple-600" />
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        Pending Equipment Requests
                    </h3>
                </div>
                <p class="text-sm text-gray-500">
                    Equipment requests awaiting approval
                </p>
            </template>

            <div class="space-y-3">
                <div
                    v-for="request in pendingRequests.slice(0, 5)"
                    :key="request.id"
                    class="rounded-lg border p-3 dark:border-gray-700"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <div class="font-medium dark:text-white">
                            {{
                                request.technician?.name ||
                                `Technician #${request.technician_id}`
                            }}
                        </div>
                        <StatusBadge
                            :status="request.priority"
                            :variant="
                                request.priority === 'URGENT'
                                    ? 'destructive'
                                    : request.priority === 'HIGH'
                                      ? 'default'
                                      : 'secondary'
                            "
                        />
                    </div>
                    <div class="mb-2 text-sm text-gray-500">
                        {{ request.reason }}
                    </div>
                    <div class="text-xs text-gray-400">
                        {{ request.items?.length || 0 }} items •
                        {{ new Date(request.created_at).toLocaleDateString() }}
                    </div>
                </div>

                <div
                    v-if="!pendingRequests.length"
                    class="py-4 text-center text-gray-500"
                >
                    <CheckCircle class="mx-auto mb-2 h-8 w-8 text-green-500" />
                    <p>No pending requests</p>
                </div>
            </div>
        </Card>
    </div>
</template>
