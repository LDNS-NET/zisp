<!-- resources/js/Pages/Inventory/Partials/DepreciationManager.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import Card from '@/Components/Card.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {
    TrendingDown,
    DollarSign,
    Calendar,
    AlertTriangle,
    RefreshCw,
    Download,
    X,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
});

const permanentItems = ref([]);
const depreciationData = ref({});
const schedules = ref({});
const loading = ref(false);
const selectedItem = ref(null);
const showSchedule = ref(false);

onMounted(() => {
    fetchDepreciationData();
});

const fetchDepreciationData = async () => {
    loading.value = true;
    try {
        // Filter permanent equipment
        const allItems = props.equipment?.data || [];
        permanentItems.value = allItems.filter(
            (item) =>
                item.equipment_type === 'PERMANENT' && item.purchase_price,
        );

        // Fetch depreciation data for each item
        const depData = {};
        const schedData = {};

        for (const item of permanentItems.value) {
            try {
                const [depreciation, schedule] = await Promise.all([
                    axios.get(
                        route('equipment.depreciation.calculate', item.id),
                    ),
                    axios.get(
                        route('equipment.depreciation.schedule', item.id),
                    ),
                ]);
                depData[item.id] = depreciation.data;
                schedData[item.id] = schedule.data;
            } catch (error) {
                console.error(
                    `Error fetching data for item ${item.id}:`,
                    error,
                );
            }
        }

        depreciationData.value = depData;
        schedules.value = schedData;
    } catch (error) {
        console.error('Error fetching depreciation data:', error);
    } finally {
        loading.value = false;
    }
};

const handleApplyDepreciation = async (itemId) => {
    try {
        await axios.post(route('equipment.depreciation.apply', itemId));
        await fetchDepreciationData();
    } catch (error) {
        console.error('Error applying depreciation:', error);
    }
};

const getDepreciationPercentage = (item) => {
    const depData = depreciationData.value[item.id];
    if (!depData) return 0;
    const purchasePrice = item.purchase_price || 0;
    return purchasePrice
        ? (depData.depreciation_amount / purchasePrice) * 100
        : 0;
};

const getReplacementPriority = (item) => {
    const depData = depreciationData.value[item.id];
    if (!depData)
        return { priority: 'LOW', variant: 'secondary', label: 'Good' };

    const depreciationPct = getDepreciationPercentage(item);
    const installationDate = item.installation_date
        ? new Date(item.installation_date)
        : null;
    const ageInMonths = installationDate
        ? Math.floor(
              (new Date() - installationDate) / (1000 * 60 * 60 * 24 * 30),
          )
        : 0;

    if (depreciationPct >= 80 || ageInMonths >= 60) {
        return {
            priority: 'HIGH',
            variant: 'destructive',
            label: 'Replace Soon',
            icon: AlertTriangle,
        };
    }
    if (depreciationPct >= 60 || ageInMonths >= 36) {
        return {
            priority: 'MEDIUM',
            variant: 'default',
            label: 'Monitor',
            icon: Calendar,
        };
    }
    return {
        priority: 'LOW',
        variant: 'secondary',
        label: 'Good',
        icon: TrendingDown,
    };
};

const calculateTotalValues = computed(() => {
    let totalPurchase = 0;
    let totalCurrent = 0;
    let totalDepreciated = 0;

    permanentItems.value.forEach((item) => {
        const purchasePrice = item.purchase_price || 0;
        const currentValue =
            depreciationData.value[item.id]?.current_value || purchasePrice;

        totalPurchase += purchasePrice;
        totalCurrent += currentValue;
        totalDepreciated += purchasePrice - currentValue;
    });

    return { totalPurchase, totalCurrent, totalDepreciated };
});

const exportDepreciationReport = () => {
    const report = {
        generated: new Date().toISOString(),
        summary: calculateTotalValues.value,
        items: permanentItems.value.map((item) => {
            const depData = depreciationData.value[item.id];
            const replacement = getReplacementPriority(item);
            return {
                name: item.name,
                purchasePrice: item.purchase_price,
                currentValue: depData?.current_value,
                depreciationAmount: depData?.depreciation_amount,
                depreciationPercentage: getDepreciationPercentage(item),
                replacementPriority: replacement.priority,
                installationDate: item.installation_date,
                ageInMonths: item.installation_date
                    ? Math.floor(
                          (new Date() - new Date(item.installation_date)) /
                              (1000 * 60 * 60 * 24 * 30),
                      )
                    : 0,
            };
        }),
    };

    const blob = new Blob([JSON.stringify(report, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `depreciation-report-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Asset Depreciation & Replacement
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Track equipment values and replacement recommendations
                </p>
            </div>
            <div class="flex gap-2">
                <SecondaryButton @click="exportDepreciationReport">
                    <Download class="mr-2 h-4 w-4" />
                    Export Report
                </SecondaryButton>
                <PrimaryButton @click="fetchDepreciationData" variant="outline">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Refresh
                </PrimaryButton>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Total Purchase Value
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            KES
                            {{
                                calculateTotalValues.totalPurchase.toLocaleString()
                            }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <DollarSign
                            class="h-5 w-5 text-blue-600 dark:text-blue-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Original equipment cost
                </p>
            </Card>

            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Current Value
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            KES
                            {{
                                calculateTotalValues.totalCurrent.toLocaleString()
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30"
                    >
                        <TrendingDown
                            class="h-5 w-5 text-green-600 dark:text-green-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{
                        (
                            (calculateTotalValues.totalDepreciated /
                                calculateTotalValues.totalPurchase) *
                            100
                        ).toFixed(1)
                    }}% depreciated
                </p>
            </Card>

            <Card class="p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Replacement Priority
                        </p>
                        <p class="text-2xl font-bold dark:text-white">
                            {{
                                permanentItems.filter(
                                    (item) =>
                                        getReplacementPriority(item)
                                            .priority === 'HIGH',
                                ).length
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30"
                    >
                        <AlertTriangle
                            class="h-5 w-5 text-orange-600 dark:text-orange-400"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Items needing replacement
                </p>
            </Card>
        </div>

        <!-- Depreciation Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Asset Depreciation Overview
                </h3>
                <p class="text-sm text-gray-500">
                    Current values and replacement recommendations for permanent
                    equipment
                </p>
            </template>

            <div v-if="loading" class="flex justify-center py-8">
                <div
                    class="h-8 w-8 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                ></div>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50"
                        >
                            <th class="px-6 py-3 text-left font-semibold">
                                Equipment
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Purchase Price
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Current Value
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Depreciation
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Age
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Replacement
                            </th>
                            <th class="px-6 py-3 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="item in permanentItems"
                            :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ item.name }}
                                </div>
                                <div
                                    v-if="item.description"
                                    class="text-sm text-gray-500"
                                >
                                    {{ item.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <DollarSign class="h-4 w-4 text-gray-400" />
                                    <span class="dark:text-white"
                                        >KES
                                        {{
                                            (
                                                item.purchase_price || 0
                                            ).toLocaleString()
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <DollarSign
                                        class="h-4 w-4 text-green-400"
                                    />
                                    <span class="dark:text-white"
                                        >KES
                                        {{
                                            (
                                                depreciationData[item.id]
                                                    ?.current_value || 0
                                            ).toLocaleString()
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div
                                        class="text-sm font-medium dark:text-white"
                                    >
                                        {{
                                            getDepreciationPercentage(
                                                item,
                                            ).toFixed(1)
                                        }}%
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        KES
                                        {{
                                            (
                                                depreciationData[item.id]
                                                    ?.depreciation_amount || 0
                                            ).toLocaleString()
                                        }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm dark:text-white">
                                    {{
                                        item.installation_date
                                            ? Math.floor(
                                                  (new Date() -
                                                      new Date(
                                                          item.installation_date,
                                                      )) /
                                                      (1000 *
                                                          60 *
                                                          60 *
                                                          24 *
                                                          30),
                                              )
                                            : 0
                                    }}
                                    months
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="
                                        getReplacementPriority(item).variant
                                    "
                                    class="flex items-center gap-1"
                                >
                                    <component
                                        :is="getReplacementPriority(item).icon"
                                        class="h-3 w-3"
                                    />
                                    {{ getReplacementPriority(item).label }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="
                                            selectedItem = item;
                                            showSchedule = true;
                                        "
                                        class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                        title="View Schedule"
                                    >
                                        <Calendar class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="
                                            handleApplyDepreciation(item.id)
                                        "
                                        class="rounded p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                                        title="Apply Depreciation"
                                    >
                                        <RefreshCw class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!permanentItems.length">
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <TrendingDown
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No permanent equipment found</p>
                                <p class="mt-1 text-sm">
                                    Add permanent assets to track depreciation
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Depreciation Schedule Modal -->
        <Modal
            :show="showSchedule"
            @close="showSchedule = false"
            maxWidth="4xl"
        >
            <div class="bg-white p-6 dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold dark:text-white">
                            Depreciation Schedule
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            5-year depreciation forecast for
                            {{ selectedItem?.name }}
                        </p>
                    </div>
                    <button
                        @click="showSchedule = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="px-4 py-2 text-left font-semibold">
                                    Date
                                </th>
                                <th class="px-4 py-2 text-left font-semibold">
                                    Value
                                </th>
                                <th class="px-4 py-2 text-left font-semibold">
                                    Monthly Depreciation
                                </th>
                                <th class="px-4 py-2 text-left font-semibold">
                                    Cumulative Depreciation
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(schedule, index) in schedules[
                                    selectedItem?.id
                                ]"
                                :key="index"
                                class="border-b dark:border-gray-700"
                            >
                                <td class="px-4 py-2">
                                    {{
                                        new Date(
                                            schedule.date,
                                        ).toLocaleDateString()
                                    }}
                                </td>
                                <td class="px-4 py-2">
                                    KES {{ schedule.value.toLocaleString() }}
                                </td>
                                <td class="px-4 py-2">
                                    KES
                                    {{ schedule.depreciation.toLocaleString() }}
                                </td>
                                <td class="px-4 py-2">
                                    KES
                                    {{
                                        (
                                            (selectedItem?.purchase_price ||
                                                0) - schedule.value
                                        ).toLocaleString()
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showSchedule = false"
                        >Close</SecondaryButton
                    >
                </div>
            </div>
        </Modal>
    </div>
</template>
