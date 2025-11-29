<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const showModal = ref(false);
const selectedHotspot = ref(null);
const phoneNumber = ref('');
const isProcessing = ref(false);
const paymentMessage = ref('');
const paymentError = ref('');

// Packages received from Inertia
const page = usePage();
const hotspots = computed(() => {
    const packages = page.props?.packages || [];
    console.log('Loaded packages:', packages);
    return packages;
});

function openModal(hotspot) {
    console.log('Opening modal for hotspot:', hotspot);
    selectedHotspot.value = hotspot;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
    showModal.value = true;
    console.log('showModal set to:', showModal.value);
}

function closeModal() {
    showModal.value = false;
    selectedHotspot.value = null;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
}

function testClick() {
    console.log('Test click - button is working!');
    alert('Button click is working!');
}

async function processPayment() {
    console.log('Process payment called');
    console.log('Phone number:', phoneNumber.value);
    console.log('Selected hotspot:', selectedHotspot.value);
    
    if (!phoneNumber.value.match(/^2547\d{8}$/)) {
        console.log('Phone validation failed');
        paymentError.value = 'Please enter a valid Safaricom number (2547XXXXXXXX)';
        return;
    }

    console.log('Phone validation passed, starting payment...');
    isProcessing.value = true;
    paymentError.value = '';
    paymentMessage.value = '';

    try {
        const payload = {
            package_id: selectedHotspot.value.id,
            phone: phoneNumber.value
        };
        console.log('Sending payload:', payload);

        const response = await fetch('/hotspot/purchase-stk-push', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        });

        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response result:', result);

        if (result.success) {
            paymentMessage.value = result.message;
            // Close modal after 3 seconds on success
            setTimeout(() => {
                closeModal();
            }, 3000);
        } else {
            paymentError.value = result.message;
        }
    } catch (error) {
        console.error('Payment error:', error);
        paymentError.value = 'Payment failed. Please try again.';
    } finally {
        isProcessing.value = false;
    }
}

function formatPhoneNumber(event) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.startsWith('0') && value.length >= 10) {
        value = '254' + value.substring(1);
    } else if (value.startsWith('7') && value.length >= 9) {
        value = '254' + value;
    }
    phoneNumber.value = value;
}
</script>

<template>
    <Head title="Hotspot" />
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Hotspot Packages</h2>

        <!-- Hotspot list -->
        <div v-if="hotspots.length === 0" class="text-gray-500">No hotspot packages found.</div>
        <div v-for="hotspot in hotspots" :key="hotspot.id" class="mb-2 flex justify-between items-center border p-2 rounded">
            <div class="flex-1">
                <div class="font-medium">{{ hotspot.name }}</div>
                <div class="text-sm text-gray-500">{{ hotspot.duration_value }} {{ hotspot.duration_unit }}</div>
            </div>
            <div class="text-sm font-semibold">@ {{ hotspot.price }} KES</div>
            <PrimaryButton @click="openModal(hotspot)">Buy</PrimaryButton>
        </div>

        <!-- Checkout Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium mb-4">Purchase Hotspot Package</h3>
                
                <div v-if="selectedHotspot" class="space-y-4">
                    <!-- Package Details -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-lg">{{ selectedHotspot.name }}</h4>
                        <p class="text-sm text-gray-600">{{ selectedHotspot.duration_value }} {{ selectedHotspot.duration_unit }}</p>
                        <p class="text-2xl font-bold text-green-600">KES {{ selectedHotspot.price }}</p>
                    </div>

                    <!-- Phone Number Input -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            M-Pesa Phone Number
                        </label>
                        <input
                            id="phone"
                            v-model="phoneNumber"
                            @input="formatPhoneNumber"
                            type="tel"
                            placeholder="2547XXXXXXXX"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            :disabled="isProcessing"
                        />
                        <p class="text-xs text-gray-500 mt-1">Enter Safaricom number in format: 2547XXXXXXXX</p>
                        <p class="text-xs text-blue-500 mt-1">Debug: "{{ phoneNumber }}" - Valid: {{ !!phoneNumber.match(/^2547\d{8}$/) }}</p>
                    </div>

                    <!-- Payment Messages -->
                    <div v-if="paymentMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                        {{ paymentMessage }}
                    </div>

                    <div v-if="paymentError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                        {{ paymentError }}
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closeModal" :disabled="isProcessing">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton 
                        @click="testClick" 
                        :disabled="isProcessing || !phoneNumber.match(/^2547\d{8}$/)"
                    >
                        <span v-if="isProcessing">Processing...</span>
                        <span v-else>Pay with M-Pesa</span>
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
