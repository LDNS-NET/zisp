<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { 
    Shield, 
    ShieldAlert, 
    Globe, 
    Layers, 
    Settings, 
    CheckCircle2, 
    AlertCircle,
    RotateCcw
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const toast = useToast();
const props = defineProps({
    settings: Object,
    mikrotiks: Array,
});

const form = useForm({
    enabled: props.settings.enabled ?? false,
    categories: props.settings.categories ?? [],
    blacklist: props.settings.blacklist ?? [],
    whitelist: props.settings.whitelist ?? [],
    dns_address: props.settings.dns_address ?? '1.1.1.3',
});

const categoriesList = [
    { id: 'adult', name: 'Adult Content (Pornography)', description: 'Blocks access to sexually explicit websites.' },
    { id: 'gambling', name: 'Gambling', description: 'Blocks betting and online casino websites.' },
    { id: 'social', name: 'Social Media', description: 'Blocks popular social networking sites.' },
    { id: 'gaming', name: 'Online Gaming', description: 'Blocks gaming platforms and servers.' },
    { id: 'video', name: 'Video Streaming', description: 'Blocks high-bandwidth video sites (Netflix, YouTube).' },
    { id: 'advertising', name: 'Ads & Trackers', description: 'Blocks common ad networks and tracking pixels.' },
];

const submitSettings = () => {
    form.post(route('settings.content-filter.update'), {
        onSuccess: () => toast.success('Content filtering settings saved'),
    });
};

const applyToRouter = (routerId) => {
    form.post(route('settings.content-filter.apply', routerId), {
        onSuccess: () => toast.success('Policies pushed to router'),
    });
};

const toggleCategory = (catId) => {
    const index = form.categories.indexOf(catId);
    if (index === -1) {
        form.categories.push(catId);
    } else {
        form.categories.splice(index, 1);
    }
};
</script>

<template>
    <Head title="Content Filtering" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Content Filtering & Parental Controls
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Global Configuration -->
                    <div class="md:col-span-2 space-y-6">
                        <div class="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-2">
                                    <Shield class="h-6 w-6 text-blue-600" />
                                    <h3 class="text-lg font-medium">Filtering Status</h3>
                                </div>
                                <button 
                                    @click="form.enabled = !form.enabled"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                                        form.enabled ? 'bg-green-600' : 'bg-gray-200 dark:bg-gray-700'
                                    ]"
                                >
                                    <span :class="[form.enabled ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']"></span>
                                </button>
                            </div>

                            <div class="space-y-6">
                                <div>
                                    <InputLabel for="dns_address" value="Parental Control DNS" />
                                    <div class="mt-1 flex gap-4">
                                        <TextInput id="dns_address" v-model="form.dns_address" type="text" class="block w-full" placeholder="1.1.1.3" />
                                        <div class="flex-shrink-0">
                                            <PrimaryButton @click="form.dns_address = '1.1.1.3'" type="button" class="text-xs">Cloudflare</PrimaryButton>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Suggested: 1.1.1.3 (Cloudflare Family) or 208.67.222.123 (OpenDNS Family).</p>
                                </div>

                                <div class="border-t pt-6 dark:border-gray-700">
                                    <h4 class="mb-4 text-md font-semibold flex items-center gap-2">
                                        <Layers class="h-4 w-4" />
                                        Category-Based Blocking
                                    </h4>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div 
                                            v-for="cat in categoriesList" 
                                            :key="cat.id"
                                            @click="toggleCategory(cat.id)"
                                            :class="[
                                                'cursor-pointer rounded-lg border p-4 transition-all',
                                                form.categories.includes(cat.id) 
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' 
                                                    : 'border-gray-200 hover:border-blue-300 dark:border-gray-700'
                                            ]"
                                        >
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <div class="font-medium text-sm">{{ cat.name }}</div>
                                                    <div class="text-xs text-gray-500 mt-1">{{ cat.description }}</div>
                                                </div>
                                                <div v-if="form.categories.includes(cat.id)">
                                                    <CheckCircle2 class="h-4 w-4 text-blue-600" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <PrimaryButton @click="submitSettings" :disabled="form.processing">
                                        Save Configuration
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status & Apply -->
                    <div class="space-y-6">
                        <div class="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                            <h3 class="text-lg font-medium mb-4 flex items-center gap-2">
                                <RotateCcw class="h-5 w-5 text-orange-600" />
                                Active Routers
                            </h3>
                            <div v-if="mikrotiks.length === 0" class="text-sm text-gray-500 text-center py-4">
                                No online routers found to apply policies.
                            </div>
                            <div class="space-y-2">
                                <div v-for="router in mikrotiks" :key="router.id" class="flex items-center justify-between p-3 rounded-md bg-gray-50 dark:bg-gray-700">
                                    <div class="flex items-center gap-2">
                                        <Globe class="h-4 w-4 text-green-600" />
                                        <span class="text-sm font-medium">{{ router.name }}</span>
                                    </div>
                                    <button 
                                        @click="applyToRouter(router.id)"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                    >
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 dark:bg-blue-900/20">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <AlertCircle class="h-5 w-5 text-blue-400" />
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Content filtering settings are defined per-tenant and can be applied to any synchronized MikroTik router.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
