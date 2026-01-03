<script setup>
import { Head } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Line, Bar, Pie } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
);

const props = defineProps({
    mrrData: Array,
    tenantGrowth: Array,
    tenantStatus: Array,
});

// MRR Chart Data
const mrrChartData = {
  labels: props.mrrData.map(d => d.month),
  datasets: [
    {
      label: 'Monthly Recurring Revenue (KES)',
      backgroundColor: '#4f46e5',
      borderColor: '#4f46e5',
      data: props.mrrData.map(d => d.total),
      tension: 0.4
    }
  ]
};

const mrrChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    title: { display: true, text: 'Revenue Trend (Last 6 Months)' }
  }
};

// Tenant Growth Chart Data
const growthChartData = {
  labels: props.tenantGrowth.map(d => d.month),
  datasets: [
    {
      label: 'New Tenants',
      backgroundColor: '#10b981',
      data: props.tenantGrowth.map(d => d.count)
    }
  ]
};

const growthChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    title: { display: true, text: 'New Tenant Signups' }
  }
};

// Tenant Status Chart Data
const statusChartData = {
  labels: props.tenantStatus.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
  datasets: [
    {
      backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
      data: props.tenantStatus.map(d => d.total)
    }
  ]
};

const statusChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' },
    title: { display: true, text: 'Tenant Status Distribution' }
  }
};
</script>

<template>
    <Head title="Reports & Analytics" />

    <SuperAdminLayout>
        <template #header>
            <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                Reports & Analytics
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Top Row: MRR and Growth -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- MRR Chart -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="h-80">
                        <Line :data="mrrChartData" :options="mrrChartOptions" />
                    </div>
                </div>

                <!-- Growth Chart -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="h-80">
                        <Bar :data="growthChartData" :options="growthChartOptions" />
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Status Distribution and Summary -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Status Chart -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="h-64">
                        <Pie :data="statusChartData" :options="statusChartOptions" />
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="lg:col-span-2 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700 flex flex-col justify-center items-center">
                        <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Total Revenue (Last 6 Months)</h3>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                            KES {{ props.mrrData.reduce((acc, curr) => acc + parseFloat(curr.total), 0).toLocaleString() }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700 flex flex-col justify-center items-center">
                        <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Total New Tenants</h3>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ props.tenantGrowth.reduce((acc, curr) => acc + curr.count, 0) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
