<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const form = ref({
    name: '',
});

const loading = ref(false);

const handleSubmit = () => {
    loading.value = true;
    router.post(route('mikrotiks.store'), form.value, {
        onFinish: () => {
            loading.value = false;
        },
    });
};

const goBack = () => {
    router.visit(route('mikrotiks.index'));
};
</script>

<template>
    <Head title="Add Mikrotik Device" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Add New Mikrotik Device
                </h2>
                <SecondaryButton @click="goBack">
                    ← Back
                </SecondaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Getting Started</h3>
                    <ol class="list-decimal list-inside text-sm text-gray-700 space-y-2">
                        <li>Enter a name for your Mikrotik device</li>
                        <li>Click "Create Device"</li>
                        <li>Download the generated onboarding script</li>
                        <li>Run the script on your Mikrotik device via terminal</li>
                        <li>The device will automatically connect and appear as "Online"</li>
                    </ol>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <!-- Device Name -->
                        <div>
                            <InputLabel for="name" value="Device Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="e.g., Main Router, Branch Office Router"
                                class="mt-1 block w-full"
                                required
                                autofocus
                            />
                            <p class="text-sm text-gray-500 mt-1">Give your device a memorable name for easy identification</p>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-2 pt-6">
                            <PrimaryButton type="submit" :disabled="loading || !form.name.trim()">
                                {{ loading ? 'Creating...' : 'Create Device' }}
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
