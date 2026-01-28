<script setup>

import { ref, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import Layout from '@/Pages/Settings/Layout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const toast = useToast();
const toastOptions = {
    position: 'top-right',
    timeout: 4000,
    closeOnClick: true,
    pauseOnHover: true,
    maxToasts: 1,
};

const props = defineProps({
    gateways: { type: Array, default: () => [] },
});

const showDetailsModal = ref(false);
const detailsGateway = ref({});
const savedGateways = ref({}); // Map of provider -> gateway data

// Default + supported gateways
const defaultGateway = {
    provider: 'talksasa',
    label: 'TALKSASA (Default)',
    username: '',
    sender_id: '',
    api_key: '',
    api_secret: '',
    is_active: true,
    is_default: true,
};

const allGateways = [
    { provider: 'talksasa', label: 'TALKSASA (Default)', is_default: true },
    { provider: 'africastalking', label: 'Africa’s Talking' },
    { provider: 'celcom', label: 'Celcom SMS' },
    { provider: 'twilio', label: 'Twilio' },
];

// ✅ Form setup
const form = useForm({
    id: '',
    provider: '',
    label: '',
    username: '',
    sender_id: '',
    api_key: '',
    api_secret: '',
    is_active: true,
    is_default: true,
});

// ✅ Fill form with gateway data
function applyGatewayToForm(gateway) {
    // If we are applying a saved gateway, use its values
    // If it's a bare config object from 'allGateways', keys might be missing -> empty string
    Object.assign(form, {
        id: gateway.id || '',
        provider: gateway.provider || '',
        label: gateway.label || '',
        username: gateway.username || '',
        sender_id: gateway.sender_id || '',
        api_key: gateway.api_key || '',
        api_secret: gateway.api_secret || '',
        is_active: !!gateway.is_active,
        is_default: !!gateway.is_default,
    });
}

// ✅ Fetch latest saved gateways from backend on mount
onMounted(async () => {
    try {
        const response = await fetch(route('settings.sms.json'));
        if (!response.ok) throw new Error('Failed to load saved gateways');
        const data = await response.json();

        if (data.gateways && Array.isArray(data.gateways)) {
            // Map gateways by provider for easy lookup
            data.gateways.forEach(g => {
                savedGateways.value[g.provider] = g;
            });
            
            // Find active gateway
            const active = data.gateways.find(g => g.is_active);
            if (active) {
                applyGatewayToForm(active);
            } else {
                 // Fallback to default if nothing is active
                applyGatewayToForm(defaultGateway);
            }
        } else {
            savedGateways.value = {};
            applyGatewayToForm(defaultGateway);
        }
    } catch (err) {
        console.error('Gateway load failed:', err);
        applyGatewayToForm(defaultGateway);
    }
});

// ✅ Watch provider and sync
watch(
    () => form.provider,
    (provider) => {
        if (!provider) return;
        
        // Check if we have saved settings for this provider
        const saved = savedGateways.value[provider];
        
        if (saved) {
             applyGatewayToForm(saved);
        } else {
            // Reset to default structure but keep the provider selected
            const defaults = allGateways.find(g => g.provider === provider) || defaultGateway;
            // We want to clear keys but keep provider structure
            form.id = '';
            form.username = '';
            form.api_key = '';
            form.api_secret = '';
            form.sender_id = '';
            form.is_active = false;
            // is_default is deprecated/not used the same way, but we can set false
            form.is_default = false; 
        }
    },
);

// ✅ Show current saved gateway details
function openDetails() {
    detailsGateway.value = currentGateway.value || defaultGateway;
    showDetailsModal.value = true;
}

// ✅ Save gateway
function save() {
    const provider = form.provider || 'Unknown';
    const loading = toast.info(`Saving ${provider} settings...`, toastOptions);

    router.post(route('settings.sms.update'), form, {
        onSuccess: () => {
            toast.dismiss(loading);
            toast.success('SMS Gateway saved successfully', toastOptions);
            router.reload({ only: ['gateways'] });
        },
        onError: (errors) => {
            toast.dismiss(loading);
            toast.error(
                Object.values(errors).flat().join(' ') || 'Save failed',
                {
                    ...toastOptions,
                    timeout: 7000,
                },
            );
        },
    });
}

</script>

<template>
    <Layout>
        <Head title="SMS Gateway" />
        <div
            class="w-full max-w-2xl rounded-xl border border-indigo-100 bg-white p-8 shadow-lg dark:border-blue-700 dark:bg-gray-800"
        >
            <header class="mb-6 flex items-center">
                <svg
                    class="mr-3 h-8 w-8 text-indigo-500"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M17 8h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2v-8a2 2 0 012-2h2"
                    />
                    <rect width="12" height="8" x="6" y="4" rx="2" />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6 12h.01M6 16h.01"
                    />
                </svg>
                <h3 class="text-2xl font-bold text-indigo-700">
                    SMS Gateway Settings
                </h3>
            </header>

            <!-- Select Provider -->
            <label class="mb-2 block font-semibold text-indigo-600">
                Select SMS Gateway
            </label>
            <select
                v-model="form.provider"
                class="input input-bordered mb-6 w-full focus:ring-2 focus:ring-indigo-400 dark:bg-gray-700"
            >
                <option
                    v-for="g in allGateways"
                    :key="g.provider"
                    :value="g.provider"
                >
                    {{ g.label }} 
                    <span v-if="savedGateways[g.provider]?.is_active" class="font-bold text-green-600"> (Active)</span>
                    <span v-else-if="savedGateways[g.provider]" class="text-gray-500"> (Saved)</span>
                </option>
            </select>

            <!-- Dynamic Fields -->
            <transition name="fade">
                <div v-if="form.provider" class="space-y-4">
                    <template v-if="form.provider === 'talksasa'">
                        <div class="rounded-lg bg-blue-50 p-4 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                            <p class="font-semibold">Using System Default Gateway</p>
                            <p class="text-sm">No API keys required. Messages will be sent using the system's configured Talksasa account.</p>
                        </div>
                    </template>

                    <template v-else-if="form.provider === 'africastalking'">
                        <InputField label="Username" v-model="form.username" />
                        <InputField
                            label="API Key"
                            v-model="form.api_key"
                            type="password"
                        />
                        <InputField
                            label="Sender ID"
                            v-model="form.sender_id"
                        />
                    </template>

                    <template v-else-if="form.provider === 'celcom'">
                        <InputField label="Partner ID" v-model="form.username" />
                        <InputField
                            label="API Key"
                            v-model="form.api_key"
                            type="password"
                        />
                        <InputField
                            label="Sender ID / Shortcode"
                            v-model="form.sender_id"
                        />
                    </template>

                    <template v-else-if="form.provider === 'twilio'">
                        <InputField
                            label="Account SID"
                            v-model="form.username"
                        />
                        <InputField
                            label="Auth Token"
                            v-model="form.api_secret"
                            type="password"
                        />
                        <InputField
                            label="From Number"
                            v-model="form.sender_id"
                        />
                    </template>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2 py-2">
                        <input 
                            type="checkbox" 
                            v-model="form.is_active" 
                            class="checkbox checkbox-primary"
                        />
                        <span class="font-medium">Set as Active SMS Gateway</span>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex justify-between">
                        <PrimaryButton
                            class="btn btn-outline btn-info"
                            @click="openDetails"
                        >
                            Show Details
                        </PrimaryButton>
                        <PrimaryButton
                            class="btn btn-indigo btn-lg shadow hover:scale-105"
                            @click="save"
                        >
                            Save Gateway
                        </PrimaryButton>
                    </div>

                    <!-- Modal -->
                    <Modal
                        :show="showDetailsModal"
                        @close="showDetailsModal = false"
                    >
                        <template #header>
                            <h3 class="text-xl font-bold text-indigo-700">
                                Current SMS Gateway Details
                            </h3>
                        </template>

                        <div
                            class="space-y-3 rounded-lg bg-gradient-to-br from-blue-50 to-indigo-100 p-6 shadow"
                        >
                            <Detail
                                label="Provider"
                                :value="detailsGateway.provider"
                            />
                            <Detail
                                label="Label"
                                :value="detailsGateway.label"
                            />
                            <Detail
                                label="Username"
                                :value="detailsGateway.username"
                            />
                            <Detail
                                label="Sender ID"
                                :value="detailsGateway.sender_id"
                            />
                            <Detail
                                label="Status"
                                :value="
                                    detailsGateway.is_active
                                        ? 'Active'
                                        : 'Inactive'
                                "
                            />
                        </div>

                        <template #footer>
                            <button
                                class="btn btn-outline btn-lg"
                                @click="showDetailsModal = false"
                            >
                                Close
                            </button>
                        </template>
                    </Modal>
                </div>
            </transition>
        </div>
    </Layout>
</template>

<script>
export default {
    components: {
        InputField: {
            props: ['label', 'modelValue', 'type'],
            emits: ['update:modelValue'],
            template: `
        <div>
          <label class="block font-semibold text-gray-700">{{ label }}</label>
          <input
            :type="type || 'text'"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            class="input input-bordered w-full dark:bg-gray-700"
          />
        </div>
      `,
        },
        Detail: {
            props: ['label', 'value'],
            template: `
        <div v-if="value" class="flex items-center gap-2">
          <span class="font-semibold text-gray-700">{{ label }}:</span>
          <span class="rounded bg-indigo-100 px-2 py-1 text-sm text-indigo-700">{{ value }}</span>
        </div>
      `,
        },
    },
};
</script>
