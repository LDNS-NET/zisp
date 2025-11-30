<script setup>
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    mikrotikDetails: Object,
    tenantId: Number,
});

const form = ref({
    ipAddress: props.mikrotikDetails ? props.mikrotikDetails.ip_address : '',
    username: props.mikrotikDetails ? props.mikrotikDetails.username : '',
    password: '',
    port: props.mikrotikDetails ? props.mikrotikDetails.port : 8728,
});

</script>

<template>
    <AuthenticatedLayout>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow">
        <h2 class="text-2xl font-semibold mb-6">Mikrotik Details</h2>
        <form @submit.prevent="submitForm">
            <div class="mb-4">
                <label for="ipAddress" class="block text-sm font-medium text-gray-700">IP Address</label>
                <TextInput id="ipAddress" v-model="form.ipAddress" type="text" class="mt-1 block w-full" required />
            </div>
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <TextInput id="username" v-model="form.username" type="text" class="mt-1 block w-full" required />
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
            </div>
            <div class="mb-4">
                <label for="port" class="block text-sm font-medium text-gray-700">Port</label>
                <TextInput id="port" v-model="form.port" type="number" class="mt-1 block w-full" required />
            </div>
            <div class="flex justify-end">
                <PrimaryButton type="submit">Save</PrimaryButton>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>