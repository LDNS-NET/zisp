<script setup>
import { ref, computed } from 'vue';
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
    gateway: { type: Object, default: () => ({}) },
});

const showDetailsModal = ref(false);
const detailsGateway = ref({});

// Supported providers
const allProviders = [
    { provider: 'talksasa', label: 'TALKSASA (System Default)' },
    { provider: 'celcom', label: 'Celcom SMS' },
    { provider: 'africastalking', label: 'Africa\'s Talking' },
    { provider: 'twilio', label: 'Twilio' },
    { provider: 'advanta', label: 'Advanta SMS' },
    { provider: 'bulksms', label: 'BulkSMS.com' },
    { provider: 'clicksend', label: 'ClickSend' },
    { provider: 'infobip', label: 'Infobip' },
];

// Initialize form with actual gateway data (decrypted values from backend)
const form = useForm({
    provider: props.gateway?.provider || 'talksasa',
    label: props.gateway?.label || '',
    // Talksasa
    talksasa_api_key: props.gateway?.talksasa_api_key || '',
    talksasa_sender_id: props.gateway?.talksasa_sender_id || '',
    // Celcom
    celcom_partner_id: props.gateway?.celcom_partner_id || '',
    celcom_api_key: props.gateway?.celcom_api_key || '',
    celcom_sender_id: props.gateway?.celcom_sender_id || '',
    // Africa's Talking
    africastalking_username: props.gateway?.africastalking_username || '',
    africastalking_api_key: props.gateway?.africastalking_api_key || '',
    africastalking_sender_id: props.gateway?.africastalking_sender_id || '',
    // Twilio
    twilio_account_sid: props.gateway?.twilio_account_sid || '',
    twilio_auth_token: props.gateway?.twilio_auth_token || '',
    twilio_from_number: props.gateway?.twilio_from_number || '',
    // Advanta
    advanta_partner_id: props.gateway?.advanta_partner_id || '',
    advanta_api_key: props.gateway?.advanta_api_key || '',
    advanta_shortcode: props.gateway?.advanta_shortcode || '',
    // BulkSMS
    bulksms_username: props.gateway?.bulksms_username || '',
    bulksms_password: props.gateway?.bulksms_password || '',
    // ClickSend
    clicksend_username: props.gateway?.clicksend_username || '',
    clicksend_api_key: props.gateway?.clicksend_api_key || '',
    // Infobip
    infobip_api_key: props.gateway?.infobip_api_key || '',
    infobip_base_url: props.gateway?.infobip_base_url || 'https://api.infobip.com',
    infobip_sender_id: props.gateway?.infobip_sender_id || '',
    is_active: props.gateway?.is_active ?? true,
});

// Show provider-specific fields
const showTalksasaFields = computed(() => form.provider === 'talksasa');
const showCelcomFields = computed(() => form.provider === 'celcom');
const showAfricasTalkingFields = computed(() => form.provider === 'africastalking');
const showTwilioFields = computed(() => form.provider === 'twilio');
const showAdvantaFields = computed(() => form.provider === 'advanta');
const showBulkSMSFields = computed(() => form.provider === 'bulksms');
const showClickSendFields = computed(() => form.provider === 'clicksend');
const showInfobipFields = computed(() => form.provider === 'infobip');

// Save gateway settings
function save() {
    const provider = form.provider || 'Unknown';
    const loading = toast.info(`Saving ${provider} settings...`, toastOptions);

    router.post(route('settings.sms.update'), form, {
        onSuccess: () => {
            toast.dismiss(loading);
            toast.success('SMS Gateway saved successfully', toastOptions);
            router.reload({ only: ['gateway'] });
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

// Show details
function openDetails() {
    detailsGateway.value = props.gateway || {};
    showDetailsModal.value = true;
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

            <!-- Provider Selection -->
            <label class="mb-2 block font-semibold text-indigo-600">
                Select SMS Provider
            </label>
            <select
                v-model="form.provider"
                class="input input-bordered mb-6 w-full focus:ring-2 focus:ring-indigo-400 dark:bg-gray-700"
            >
                <option
                    v-for="p in allProviders"
                    :key="p.provider"
                    :value="p.provider"
                >
                    {{ p.label }}
                </option>
            </select>

            <!-- Dynamic Provider Fields -->
            <transition name="fade">
                <div v-if="form.provider" class="space-y-4">
                    <!-- Talksasa Fields -->
                    <template v-if="showTalksasaFields">
                        <div class="rounded-lg bg-blue-50 p-4 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                            <p class="font-semibold">System Default Gateway</p>
                            <p class="text-sm">Using platform-managed Talksasa account.</p>
                        </div>
                    </template>

                    <!-- Celcom Fields -->
                    <template v-if="showCelcomFields">
                        <InputField 
                            label="Partner ID" 
                            v-model="form.celcom_partner_id"
                        />
                        <InputField
                            label="API Key"
                            v-model="form.celcom_api_key"
                            type="password"
                        />
                        <InputField
                            label="Sender ID / Shortcode"
                            v-model="form.celcom_sender_id"
                        />
                    </template>

                    <!-- Africa's Talking Fields -->
                    <template v-if="showAfricasTalkingFields">
                        <InputField label="Username" v-model="form.africastalking_username" />
                        <InputField
                            label="API Key"
                            v-model="form.africastalking_api_key"
                            type="password"
                        />
                        <InputField
                            label="Sender ID"
                            v-model="form.africastalking_sender_id"
                        />
                    </template>

                    <!-- Twilio Fields -->
                    <template v-if="showTwilioFields">
                        <InputField
                            label="Account SID"
                            v-model="form.twilio_account_sid"
                        />
                        <InputField
                            label="Auth Token"
                            v-model="form.twilio_auth_token"
                            type="password"
                        />
                        <InputField
                            label="From Number"
                            v-model="form.twilio_from_number"
                        />
                    </template>

                    <!-- Advanta SMS Fields -->
                    <template v-if="showAdvantaFields">
                        <InputField
                            label="Partner ID"
                            v-model="form.advanta_partner_id"
                            type="password"
                        />
                        <InputField
                            label="API Key"
                            v-model="form.advanta_api_key"
                            type="password"
                        />
                        <InputField
                            label="Shortcode / Sender ID"
                            v-model="form.advanta_shortcode"
                        />
                    </template>

                    <!-- BulkSMS Fields -->
                    <template v-if="showBulkSMSFields">
                        <InputField
                            label="Username"
                            v-model="form.bulksms_username"
                        />
                        <InputField
                            label="Password"
                            v-model="form.bulksms_password"
                            type="password"
                        />
                    </template>

                    <!-- ClickSend Fields -->
                    <template v-if="showClickSendFields">
                        <InputField
                            label="Username"
                            v-model="form.clicksend_username"
                        />
                        <InputField
                            label="API Key"
                            v-model="form.clicksend_api_key"
                            type="password"
                        />
                    </template>

                    <!-- Infobip Fields -->
                    <template v-if="showInfobipFields">
                        <InputField
                            label="API Key"
                            v-model="form.infobip_api_key"
                            type="password"
                        />
                        <InputField
                            label="Base URL"
                            v-model="form.infobip_base_url"
                        />
                        <InputField
                            label="Sender ID"
                            v-model="form.infobip_sender_id"
                        />
                    </template>

                    <!-- Active Toggle -->
                    <div class="flex items-center gap-2 py-2">
                        <input 
                            type="checkbox" 
                            v-model="form.is_active" 
                            class="checkbox checkbox-primary"
                        />
                        <span class="font-medium">Active (Use this gateway for sending)</span>
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

                    <!-- Details Modal -->
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
