<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';

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
</script>

<template>
    <Head title="Customer Dashboard" />

    <CustomerLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- User Info -->
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Account Details</h3>
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Username</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ user.username }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1 text-sm">
                                            <span :class="user.online ? 'text-green-600' : 'text-gray-500'">
                                                {{ user.online ? 'Online' : 'Offline' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Expires At</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(user.expires_at) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Package Info -->
                            <div class="bg-indigo-50 p-6 rounded-lg">
                                <h3 class="text-lg font-medium text-indigo-900 mb-4">Current Plan</h3>
                                <div v-if="package">
                                    <p class="text-2xl font-bold text-indigo-700">{{ package.name }}</p>
                                    <p class="mt-2 text-sm text-indigo-600">
                                        {{ package.download_speed }} Mbps Download / {{ package.upload_speed }} Mbps Upload
                                    </p>
                                    <p class="mt-1 text-sm text-indigo-600">
                                        {{ package.price }} {{ $page.props.tenant?.currency }} / {{ package.duration_unit }}
                                    </p>
                                </div>
                                <div v-else>
                                    <p class="text-gray-500">No active package.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-8 flex flex-col sm:flex-row gap-4">
                            <Link :href="route('customer.renew')" class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Renew Subscription
                            </Link>
                            <Link :href="route('customer.upgrade')" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Upgrade Plan
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
