<script setup>
import { ref } from 'vue';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/inertia-vue3';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

</script>

<template>
    <Head title="Hotspot" />
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Hotspot Management</h2>
        <!-- Hotspot content goes here -->

        <!-- Example Modal for Hotspot actions -->
        <Modal v-if="showModal" @close="showModal = false">
            <template #header>
                <h3 class="text-lg font-medium">Hotspot Action</h3>
            </template>
            <template #body>
                <p>Perform actions related to Hotspots here.</p>
            </template>
            <template #footer>
                <PrimaryButton @click="showModal = false">Close</PrimaryButton>
                <DangerButton @click="/* Add action logic here */">Confirm</DangerButton>
            </template>
        </Modal>
    </div>
</template>