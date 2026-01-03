<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { countries } from '@/Data/countries';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Plus, Pencil, Trash2, Search } from 'lucide-vue-next';

const props = defineProps({
    plans: Array,
});

const search = ref('');
const showModal = ref(false);
const editingPlan = ref(null);

const form = useForm({
    id: null,
    country_code: '',
    currency: '',
    pppoe_price_per_month: 0,
    hotspot_price_percentage: 3.00,
    minimum_pay: 0,
    exchange_rate: 1.0,
    is_active: true,
});

const openModal = (plan = null) => {
    editingPlan.value = plan;
    if (plan) {
        form.id = plan.id;
        form.country_code = plan.country_code;
        form.currency = plan.currency;
        form.pppoe_price_per_month = plan.pppoe_price_per_month;
        form.hotspot_price_percentage = plan.hotspot_price_percentage;
        form.minimum_pay = plan.minimum_pay;
        form.exchange_rate = plan.exchange_rate;
        form.is_active = plan.is_active;
    } else {
        form.reset();
        form.id = null;
        form.country_code = 'KE'; // Default
        form.currency = 'KES';
        form.pppoe_price_per_month = 0;
        form.hotspot_price_percentage = 3.00;
        form.minimum_pay = 0;
        form.exchange_rate = 1.0;
        form.is_active = true;
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingPlan.value = null;
};

const save = () => {
    form.post(route('superadmin.pricing-plans.store'), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
    });
};

const deletePlan = (id) => {
    if (confirm('Are you sure you want to delete this pricing plan?')) {
        router.delete(route('superadmin.pricing-plans.destroy', id));
    }
};

// Auto-fill currency when country changes
const onCountryChange = () => {
    const country = countries.find(c => c.code === form.country_code);
    if (country) {
        form.currency = country.currency;
        // Set default exchange rate if available in CountryService/Data
        form.exchange_rate = country.exchange_rate || 1.0;
    }
};

const filteredPlans = computed(() => {
    if (!search.value) return props.plans;
    const q = search.value.toLowerCase();
    return props.plans.filter(p => 
        p.country_code.toLowerCase().includes(q) || 
        p.currency.toLowerCase().includes(q)
    );
});

const getCountryName = (code) => {
    const c = countries.find(country => country.code === code);
    return c ? c.name : code;
};
</script>

<template>
    <Head title="Pricing Plans" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    Pricing Plans
                </h2>
                <PrimaryButton @click="openModal()">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Plan
                </PrimaryButton>
            </div>
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
                        placeholder="Search plans..."
                        class="block w-full rounded-lg border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    />
                </div>
            </div>

            <!-- Plans Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Country</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Currency</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">PPPoE Rate</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Hotspot %</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Min Pay</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Ex. Rate (Local/KES)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        <tr v-if="filteredPlans.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No pricing plans found.
                            </td>
                        </tr>
                        <tr v-for="plan in filteredPlans" :key="plan.id">
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ getCountryName(plan.country_code) }}
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ plan.currency }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ plan.pppoe_price_per_month }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ plan.hotspot_price_percentage }}%
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ plan.minimum_pay }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ plan.exchange_rate }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span :class="[
                                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                                    plan.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                ]">
                                    {{ plan.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <button @click="openModal(plan)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button @click="deletePlan(plan.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingPlan ? 'Edit Pricing Plan' : 'Add Pricing Plan' }}
                </h2>

                <div class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="country" value="Country" />
                        <select
                            id="country"
                            v-model="form.country_code"
                            @change="onCountryChange"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option v-for="country in countries" :key="country.code" :value="country.code">
                                {{ country.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.country_code" class="mt-1 text-sm text-red-600">{{ form.errors.country_code }}</div>
                    </div>

                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <TextInput
                            id="currency"
                            v-model="form.currency"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <div v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="pppoe" value="PPPoE Rate (per user)" />
                            <TextInput
                                id="pppoe"
                                v-model="form.pppoe_price_per_month"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <div v-if="form.errors.pppoe_price_per_month" class="mt-1 text-sm text-red-600">{{ form.errors.pppoe_price_per_month }}</div>
                        </div>

                        <div>
                            <InputLabel for="hotspot" value="Hotspot Rate (%)" />
                            <TextInput
                                id="hotspot"
                                v-model="form.hotspot_price_percentage"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <div v-if="form.errors.hotspot_price_percentage" class="mt-1 text-sm text-red-600">{{ form.errors.hotspot_price_percentage }}</div>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="minimum" value="Minimum Pay" />
                        <TextInput
                            id="minimum"
                            v-model="form.minimum_pay"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <div v-if="form.errors.minimum_pay" class="mt-1 text-sm text-red-600">{{ form.errors.minimum_pay }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="exchange_rate" value="Exchange Rate (Local per 1 KES)" />
                            <TextInput
                                id="exchange_rate"
                                v-model="form.exchange_rate"
                                type="number"
                                step="0.00000001"
                                class="mt-1 block w-full"
                                required
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                e.g. 30.00 for UGX (1 KES = 30 UGX)
                            </p>
                            <div v-if="form.errors.exchange_rate" class="mt-1 text-sm text-red-600">{{ form.errors.exchange_rate }}</div>
                        </div>

                        <div class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-900/20">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                KES Equivalent Preview
                            </h4>
                            <div class="mt-2 space-y-1">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    PPPoE: <span class="font-bold text-gray-900 dark:text-white">KES {{ (form.pppoe_price_per_month / (form.exchange_rate || 1)).toFixed(2) }}</span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Min Pay: <span class="font-bold text-gray-900 dark:text-white">KES {{ (form.minimum_pay / (form.exchange_rate || 1)).toFixed(2) }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton @click="save" :disabled="form.processing">
                        {{ editingPlan ? 'Update Plan' : 'Create Plan' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>
