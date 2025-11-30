<script setup>
import { ref } from 'vue';
import { Head, usePage } from '@inertiajs/inertia-vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  mikrotik: Object,
});

const form = ref({
  ip_address: props.mikrotik.ip_address || '',
  username: props.mikrotik.username || '',
  password: '',
  port: props.mikrotik.port || 8728,
  api_enabled: props.mikrotik.api_enabled || false,
});

</script>

<template>
    <Head title="Mikrotik Details" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mikrotik Details
            </h2>
        </template>
    </AuthenticatedLayout>
</template>
