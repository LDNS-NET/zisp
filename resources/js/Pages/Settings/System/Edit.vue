<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useToast } from 'vue-toastification';
import Layout from '../Layout.vue';

const toast = useToast();

const props = defineProps({
    settings: Object,
});

const form = useForm({
    require_password_for_user_management: props.settings?.require_password_for_user_management ?? true,
});

function submit() {
    form.post(route('settings.system.update'), {
        onSuccess: () => {
            toast.success('System settings updated successfully');
        },
        onError: () => {
            toast.error('Failed to update settings');
        },
    });
}
</script>

<template>
    <Head title="System Settings" />
    <Layout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <Settings class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        System Settings
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configure system-wide administrative controls
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50">
                    <div class="flex items-center gap-3">
                        <Shield class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Security & Access Control
                        </h3>
                    </div>
                </div>

                <!-- Settings Form -->
                <form @submit.prevent="submit" class="p-6 space-y-6">
                    <!-- Password Requirement Toggle -->
                    <div class="flex items-start justify-between p-4 bg-gray-50 dark:bg-slate-900/50 rounded-lg border border-gray-200 dark:border-slate-700">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <Lock class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                <label for="require_password" class="text-sm font-medium text-gray-900 dark:text-white">
                                    Require Admin Password for User Management
                                </label>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 ml-6">
                                When enabled, administrators must enter their password to create or update network users. This adds an extra layer of security to prevent unauthorized changes.
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <button
                                type="button"
                                @click="form.require_password_for_user_management = !form.require_password_for_user_management"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    form.require_password_for_user_management
                                        ? 'bg-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-700'
                                ]"
                                role="switch"
                                :aria-checked="form.require_password_for_user_management"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        form.require_password_for_user_management ? 'translate-x-5' : 'translate-x-0'
                                    ]"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Current Status:</span>
                        <span :class="[
                            'px-2 py-1 rounded-full text-xs font-medium',
                            form.require_password_for_user_management
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                        ]">
                            {{ form.require_password_for_user_management ? 'Password Required' : 'Password Not Required' }}
                        </span>
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-slate-700">
                        <PrimaryButton 
                            :disabled="form.processing"
                            :class="{ 'opacity-25': form.processing }"
                        >
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Settings</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Info Card -->
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <Shield class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-1">
                            Security Best Practices
                        </h4>
                        <p class="text-sm text-blue-700 dark:text-blue-400">
                            Keeping password requirements enabled is recommended for production environments. This ensures that all user management actions are authorized and traceable.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
