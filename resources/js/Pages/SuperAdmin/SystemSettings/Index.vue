<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { 
    Settings, 
    Shield, 
    Globe, 
    Mail, 
    CreditCard, 
    Gauge,
    Save
} from 'lucide-vue-next';

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
    form.post(route('superadmin.settings.system.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Toast handled by layout
        },
    });
};

const getGroupLabel = (group) => {
    return group.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') + ' Settings';
};

const getGroupIcon = (group) => {
    switch (group) {
        case 'general': return Globe;
        case 'auth': return Shield;
        case 'mail': return Mail;
        case 'payment': return CreditCard;
        case 'rate_limits': return Gauge;
        default: return Settings;
    }
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
                <div v-for="(groupSettings, groupName) in settings" :key="groupName" class="mb-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50/50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/50">
                        <component :is="getGroupIcon(groupName)" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ getGroupLabel(groupName) }}
                        </h3>
                    </div>
                    
                    <div class="p-6">
                    
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
            </div>

            <div class="flex justify-end pt-4">
                    <PrimaryButton :disabled="form.processing" class="flex items-center gap-2">
                        <Save class="h-4 w-4" />
                        Save All Changes
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
