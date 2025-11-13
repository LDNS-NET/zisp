<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    mikrotik: Object,
});

const form = ref({
    name: props.mikrotik.name,
    hostname: props.mikrotik.hostname || '',
    ip_address: props.mikrotik.ip_address || '',
    api_port: props.mikrotik.api_port || 8728,
    api_username: props.mikrotik.api_username || '',
    api_password: '',
});

const loading = ref(false);

const handleSubmit = () => {
    loading.value = true;
    router.post(route('mikrotiks.update', props.mikrotik.id), form.value, {
        method: 'patch',
        onFinish: () => {
            loading.value = false;
        },
    });
};

const goBack = () => {
    router.visit(route('mikrotiks.show', props.mikrotik.id));
};
</script>

<template>
    <Head title="Edit Device" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Edit {{ mikrotik.name }}
                </h2>
                <SecondaryButton @click="goBack">
                    ← Back
                </SecondaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white rounded-lg shadow p-6">
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Device Name -->
                        <div>
                            <InputLabel for="name" value="Device Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="e.g., Main Router"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>

                        <!-- Hostname -->
                        <div>
                            <InputLabel for="hostname" value="Hostname (Optional)" />
                            <TextInput
                                id="hostname"
                                v-model="form.hostname"
                                type="text"
                                placeholder="e.g., router.example.com"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <!-- IP Address -->
                        <div>
                            <InputLabel for="ip_address" value="IP Address (Optional)" />
                            <TextInput
                                id="ip_address"
                                v-model="form.ip_address"
                                type="text"
                                placeholder="e.g., 192.168.1.1"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <!-- API Port -->
                        <div>
                            <InputLabel for="api_port" value="API Port" />
                            <TextInput
                                id="api_port"
                                v-model.number="form.api_port"
                                type="number"
                                min="1"
                                max="65535"
                                class="mt-1 block w-full"
                            />
                            <p class="text-sm text-gray-500 mt-1">Default: 8728</p>
                        </div>

                        <!-- API Username -->
                        <div>
                            <InputLabel for="api_username" value="API Username (Optional)" />
                            <TextInput
                                id="api_username"
                                v-model="form.api_username"
                                type="text"
                                placeholder="e.g., admin"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <!-- API Password -->
                        <div>
                            <InputLabel for="api_password" value="API Password (Optional)" />
                            <TextInput
                                id="api_password"
                                v-model="form.api_password"
                                type="password"
                                placeholder="Leave empty to keep current password"
                                class="mt-1 block w-full"
                            />
                            <p class="text-sm text-gray-500 mt-1">Leave empty if not changing</p>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-2 pt-6">
                            <PrimaryButton type="submit" :disabled="loading">
                                {{ loading ? 'Saving...' : 'Save Changes' }}
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="goBack">
                                Cancel
                            </SecondaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
