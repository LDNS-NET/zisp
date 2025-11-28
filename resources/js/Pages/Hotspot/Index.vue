<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const showModal = ref(false);
const selectedHotspot = ref(null);

// Packages received from Inertia
const page = usePage();
const hotspots = computed(() => page.props?.packages || []);

function openModal(hotspot) {
    selectedHotspot.value = hotspot;
    showModal.value = true;
}

function confirmAction() {
    // Example: perform an action with Inertia.post or API
    console.log('Action confirmed for', selectedHotspot.value);
    showModal.value = false;
}
</script>

<template>
    <Head title="Hotspot" />
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Hotspot Packages</h2>

        <!-- Hotspot list -->
        <div v-if="hotspots.length === 0" class="text-gray-500">No hotspot packages found.</div>
        <div v-for="hotspot in hotspots" :key="hotspot.id" class="mb-2 flex justify-between items-center border p-2 rounded">
            <div>
                <div class="font-medium">{{ hotspot.name }}</div>
                <div class="text-sm text-gray-500">{{ hotspot.duration_value }} {{ hotspot.duration_unit }} @ {{ hotspot.price }} KES</div>
            </div>
            <PrimaryButton @click="openModal(hotspot)">Buy</PrimaryButton>
        </div>

        <!-- Modal -->
        <Modal v-if="showModal" @close="showModal = false">
            <template #header>
                <h3 class="text-lg font-medium">Manage Hotspot: {{ selectedHotspot?.name }}</h3>
            </template>

            <template #body>
                <p>Perform actions for this hotspot here.</p>
            </template>

            <template #footer>
                <PrimaryButton @click="showModal = false">Close</PrimaryButton>
                <DangerButton @click="confirmAction">Confirm</DangerButton>
            </template>
        </Modal>
    </div>
</template>
