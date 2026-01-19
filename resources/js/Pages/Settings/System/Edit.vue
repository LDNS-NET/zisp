<script setup>
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import { router, usePage, useForm, Head } from '@inertiajs/vue3'; // Use router instead of Inertia
import Layout from '../Layout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Shield, Lock } from 'lucide-vue-next';

const page = usePage();
const toast = useToast();

const settings = ref(page.props.settings || {});

const form = useForm({
    require_password_for_user_management: settings.value.require_password_for_user_management ?? true,
});

const loading = ref(false);

function submit() {
    loading.value = true;
    form.post(route('settings.system.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('System settings updated successfully.');
            loading.value = false;
        },
        onError: () => {
            toast.error('Failed to update settings.');
            loading.value = false;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}
</script>

<template>
    <Layout>
        <Head title="System Settings" />

        <div class="mx-auto max-w-4xl rounded-lg border border-blue-500 bg-gray-300 p-6 shadow-sm dark:bg-gray-800">
            <div class="mb-6">
                <h2 class="flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    <Shield class="h-6 w-6 text-blue-600" />
                    System Settings
                </h2>
                <p class="mt-1 text-gray-600 dark:text-blue-200">
                    Configure system-wide behavior and security controls.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Security Section -->
                <div class="rounded-lg bg-gray-100 p-6 dark:bg-black">
                    <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-blue-100">
                        <Lock class="h-5 w-5 text-blue-600" />
                        Security Controls
                    </h3>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-base font-medium text-gray-900 dark:text-gray-100">Require Password for User Actions</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                If enabled, admin password confirmation will be required when creating or updating network users.
                            </p>
                        </div>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input
                                type="checkbox"
                                name="require_password"
                                id="require_password"
                                v-model="form.require_password_for_user_management"
                                class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                                :class="{ 'right-0 border-green-400': form.require_password_for_user_management }"
                            />
                            <label
                                for="require_password"
                                class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"
                                :class="{ 'bg-green-400': form.require_password_for_user_management }"
                            ></label>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end border-t border-blue-900 pt-6">
                    <PrimaryButton
                        type="submit"
                        :disabled="loading"
                        class="flex items-center gap-2 px-4 py-2"
                    >
                        <span v-if="loading">Saving...</span>
                        <span v-else>Save Changes</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Layout>
</template>

<style scoped>
.toggle-checkbox:checked {
    right: 0;
    border-color: #68D391;
}
.toggle-checkbox:checked + .toggle-label {
    background-color: #68D391;
}
.toggle-checkbox {
    right: 0; /* Default position */
    transition: all 0.3s;
}
</style>
