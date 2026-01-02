<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    user: Object,
    package: Object,
});

const logout = () => {
    useForm({}).post(route('customer.logout'));
};
</script>

<template>
    <Head title="Customer Dashboard" />

    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <span class="font-bold text-xl text-indigo-600">Customer Portal</span>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="ml-3 relative">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-700">{{ user.username }}</span>
                                <button @click="logout" class="text-sm text-red-600 hover:text-red-900 font-medium">
                                    Log Out
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">My Subscription</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Package Info -->
                            <div class="bg-blue-50 p-6 rounded-xl border border-blue-100">
                                <h4 class="text-sm font-semibold text-blue-900 uppercase tracking-wider">Current Plan</h4>
                                <div class="mt-2 flex items-baseline">
                                    <span class="text-3xl font-bold text-blue-900">{{ package?.name || 'No Package' }}</span>
                                </div>
                                <div class="mt-4 text-blue-700">
                                    <p>{{ package?.download_speed }} Mbps Download</p>
                                    <p>{{ package?.upload_speed }} Mbps Upload</p>
                                </div>
                            </div>

                            <!-- Expiry Info -->
                            <div class="bg-green-50 p-6 rounded-xl border border-green-100">
                                <h4 class="text-sm font-semibold text-green-900 uppercase tracking-wider">Status</h4>
                                <div class="mt-2">
                                    <span v-if="user.expires_at" class="text-3xl font-bold text-green-900">
                                        {{ new Date(user.expires_at).toLocaleDateString() }}
                                    </span>
                                    <span v-else class="text-3xl font-bold text-gray-500">Unlimited</span>
                                </div>
                                <div class="mt-4">
                                    <span v-if="user.online" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Online
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Offline
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 flex flex-col justify-center gap-4">
                                <Link :href="route('customer.renew')" class="w-full text-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Renew Subscription
                                </Link>
                                <Link :href="route('customer.upgrade')" class="w-full text-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Upgrade Plan
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
