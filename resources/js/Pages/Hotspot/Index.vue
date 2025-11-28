<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/inertia-vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const showModal = ref(false);
const selectedHotspot = ref(null);

function openModal(hotspot) {
    selectedHotspot.value = hotspot;
    showModal.value = true;
}

function confirmAction() {
    // Add your hotspot action logic here
    console.log('Action confirmed for', selectedHotspot.value);
    showModal.value = false;
}
</script>


<template>
    <Head title="Hotspot" />
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Hotspot Management</h2>
        <!-- Hotspot content goes here -->

        <!-- Example Modal for Hotspot actions -->
    </div>
</template>