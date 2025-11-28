<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/inertia-vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const showModal = ref(false);
const selectedHotspot = ref(null);

// Example hotspot list (replace with real data from Inertia)
const hotspots = ref([
  { id: 1, name: 'Hotspot 1' },
  { id: 2, name: 'Hotspot 2' },
  { id: 3, name: 'Hotspot 3' },
]);

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
        <h2 class="text-2xl font-semibold mb-4">Hotspot Management</h2>

        <!-- Hotspot list -->
        <div v-for="hotspot in hotspots" :key="hotspot.id" class="mb-2 flex justify-between items-center border p-2 rounded">
            <span>{{ hotspot.name }}</span>
            <PrimaryButton @click="openModal(hotspot)">Manage</PrimaryButton>
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
