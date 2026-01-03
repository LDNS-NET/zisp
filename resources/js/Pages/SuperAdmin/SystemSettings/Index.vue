<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Settings } from 'lucide-vue-next';

const props = defineProps({
    settings: Object,
});

// Flatten settings for form
const formData = {};
Object.values(props.settings).flat().forEach(setting => {
    formData[setting.key] = setting.value;
});

const form = useForm(formData);

const save = () => {
    form.post(route('superadmin.system-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Toast handled by layout
        },
    });
};

const getGroupLabel = (group) => {
    return group.charAt(0).toUpperCase() + group.slice(1) + ' Settings';
};
</script>

<template>
    <Head title="System Settings" />

    <SuperAdminLayout>
        <template #header>
            <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                System Settings
            </h2>
        </template>

        <div class="space-y-6">
            <form @submit.prevent="save">
                <div v-for="(groupSettings, groupName) in settings" :key="groupName" class="mb-8 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                        {{ getGroupLabel(groupName) }}
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div v-for="setting in groupSettings" :key="setting.id">
                            <InputLabel :for="setting.key" :value="setting.label" />
                            
                            <div v-if="setting.type === 'boolean'" class="mt-2 flex items-center">
                                <input
                                    :id="setting.key"
                                    type="checkbox"
                                    v-model="form[setting.key]"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ setting.description }}</span>
                            </div>

                            <div v-else>
                                <TextInput
                                    :id="setting.key"
                                    v-model="form[setting.key]"
                                    :type="setting.type === 'integer' ? 'number' : 'text'"
                                    class="mt-1 block w-full"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ setting.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">
                        Save Changes
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
