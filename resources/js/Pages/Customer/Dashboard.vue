<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Clock, Download, Upload, Wifi, AlertCircle, CheckCircle } from 'lucide-vue-next';

const props = defineProps({
    user: Object,
    package: Object,
});

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric'
    });
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES' // This should ideally come from tenant settings
    }).format(amount);
};
</script>

<template>
    <Head title="Dashboard" />

    <CustomerLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Welcome Section -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Welcome back, {{ user.username }}!</h1>
                    <p class="mt-1 text-sm text-gray-500">Here's what's happening with your account today.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Account Status Card -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Account Status</h3>
                                <div :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-medium flex items-center',
                                    user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                ]">
                                    <CheckCircle v-if="user.status === 'active'" class="w-3 h-3 mr-1" />
                                    <AlertCircle v-else class="w-3 h-3 mr-1" />
                                    {{ user.status === 'active' ? 'Active' : 'Suspended' }}
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-500">Expires On</p>
                                    <div class="flex items-center mt-1">
                                        <Clock class="w-4 h-4 text-gray-400 mr-2" />
                                        <span class="text-lg font-semibold text-gray-900">{{ formatDate(user.expires_at) }}</span>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t border-gray-100">
                                    <Link :href="route('customer.renew')" 
                                        class="w-full flex justify-center items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                        Renew Subscription
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Package Card -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 lg:col-span-2">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-medium text-gray-900">Current Plan</h3>
                                <Link :href="route('customer.upgrade')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    Upgrade Plan &rarr;
                                </Link>
                            </div>

                            <div v-if="package" class="bg-indigo-50 rounded-lg p-6 border border-indigo-100">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h4 class="text-xl font-bold text-indigo-900">{{ package.name }}</h4>
                                        <p class="text-indigo-600 mt-1">{{ package.price }} {{ $page.props.tenant?.currency }} / {{ package.duration_unit }}</p>
                                    </div>
                                    <div class="p-2 bg-white rounded-lg shadow-sm">
                                        <Wifi class="w-6 h-6 text-indigo-600" />
                                    </div>
                                </div>

                                <div class="mt-6 grid grid-cols-2 gap-4">
                                    <div class="flex items-center space-x-3 bg-white p-3 rounded-md shadow-sm">
                                        <div class="bg-green-100 p-2 rounded-full">
                                            <Download class="w-4 h-4 text-green-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Download</p>
                                            <p class="font-semibold text-gray-900">{{ package.download_speed }} Mbps</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3 bg-white p-3 rounded-md shadow-sm">
                                        <div class="bg-blue-100 p-2 rounded-full">
                                            <Upload class="w-4 h-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Upload</p>
                                            <p class="font-semibold text-gray-900">{{ package.upload_speed }} Mbps</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <AlertCircle class="mx-auto h-8 w-8 text-gray-400" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Plan</h3>
                                <p class="mt-1 text-sm text-gray-500">You don't have an active subscription.</p>
                                <div class="mt-6">
                                    <Link :href="route('customer.upgrade')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Choose a Plan
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
