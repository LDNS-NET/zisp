<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { countries } from '@/Data/countries';
import { Globe, Check, X, Search } from 'lucide-vue-next';

const props = defineProps({
    settings: Array,
});

const search = ref('');

// Helper to check if a gateway is enabled for a country
const isGatewayEnabled = (countryCode, gateway) => {
    const setting = props.settings.find(
        (s) => s.country_code === countryCode && s.gateway === gateway
    );
    // Default to true if no setting exists (or whatever default you prefer)
    // The migration set default to true, but if no record exists, what should it be?
    // If we want to "enable" them, we should probably assume they are enabled by default 
    // OR assume disabled if not explicitly enabled. 
    // Given the user said "enable a gateway", maybe default is disabled?
    // But currently countries.js lists them as "payment_methods", implying they ARE enabled.
    // So let's assume enabled unless explicitly disabled in DB.
    return setting ? setting.is_active : true;
};

const toggleGateway = (countryCode, gateway, currentValue) => {
    router.post(route('superadmin.settings.payment-gateways.toggle'), {
        country_code: countryCode,
        gateway: gateway,
        is_active: !currentValue,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const filteredCountries = computed(() => {
    if (!search.value) return countries;
    const q = search.value.toLowerCase();
    return countries.filter(c => 
        c.name.toLowerCase().includes(q) || 
        c.code.toLowerCase().includes(q)
    );
});

// Get unique list of all possible gateways across all countries to show as columns?
// Or just show the list for each country?
// Showing list for each country is better as they vary wildy.

</script>

<template>
    <Head title="Payment Gateways" />

    <SuperAdminLayout>
        <template #header>
            <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                Payment Gateways
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Search -->
            <div class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative flex-1 max-w-md">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search countries..."
                        class="block w-full rounded-lg border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    />
                </div>
            </div>

            <!-- Countries Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="country in filteredCountries" :key="country.code" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-900/5 dark:ring-gray-700 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-900/50">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-lg">
                                {{ country.emoji || '🌍' }}
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ country.name }}</h3>
                                <p class="text-xs text-gray-500">{{ country.code }} • {{ country.currency }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 space-y-3">
                        <div v-if="country.payment_methods.length === 0" class="text-sm text-gray-500 italic">
                            No payment methods defined in configuration.
                        </div>
                        <div v-for="gateway in country.payment_methods" :key="gateway" class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">
                                {{ gateway.replace(/_/g, ' ') }}
                            </span>
                            
                            <button 
                                @click="toggleGateway(country.code, gateway, isGatewayEnabled(country.code, gateway))"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2',
                                    isGatewayEnabled(country.code, gateway) ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'
                                ]"
                            >
                                <span class="sr-only">Use setting</span>
                                <span
                                    aria-hidden="true"
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        isGatewayEnabled(country.code, gateway) ? 'translate-x-5' : 'translate-x-0'
                                    ]"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
