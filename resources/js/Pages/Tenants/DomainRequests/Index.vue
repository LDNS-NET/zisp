<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { 
    Globe, 
    Plus, 
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Info,
    X
} from 'lucide-vue-next';

const props = defineProps({
    requests: Object,
});

const showRequestModal = ref(false);
const requestType = ref('custom');

const form = useForm({
    type: 'custom',
    requested_domain: '',
});

const openRequestModal = (type) => {
    requestType.value = type;
    form.type = type;
    form.requested_domain = '';
    showRequestModal.value = true;
};

const submitRequest = () => {
    form.post(route('domain-requests.store'), {
        onSuccess: () => {
            showRequestModal.value = false;
            form.reset();
        }
    });
};

const getStatusColor = (status) => {
    switch (status) {
        case 'pending': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
        case 'accepted': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400';
        case 'rejected': return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400';
        default: return 'bg-gray-100 text-gray-700';
    }
};
</script>

<template>
    <Head title="Domain Requests" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-white">
                    Domain Settings
                </h2>
                <div class="flex gap-3">
                    <button 
                        @click="openRequestModal('custom')"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        <Plus class="w-4 h-4 mr-2" />
                        Request Custom Domain
                    </button>
                    <button 
                        @click="openRequestModal('transfer')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        <Globe class="w-4 h-4 mr-2" />
                        Domain Transfer
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Info Card -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-4 flex gap-4 items-start text-blue-800 dark:text-blue-300">
                    <Info class="w-6 h-6 flex-shrink-0" />
                    <div>
                        <h4 class="font-bold mb-1">Domain Management</h4>
                        <p class="text-sm leading-relaxed">
                            You can request a custom domain (e.g., wifi.yourbrand.com) or transfer an existing domain to our system. 
                            Our team will review your request and provide instructions for DNS configuration once accepted.
                        </p>
                    </div>
                </div>

                <!-- Requests Table -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-slate-800">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                            <Clock class="w-5 h-5 text-gray-400" />
                            Request History
                        </h3>

                        <div v-if="requests.data.length === 0" class="text-center py-12">
                            <Globe class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                            <p class="text-gray-500 dark:text-gray-400">No domain requests found.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-slate-800">
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Requested Domain</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                    <tr v-for="request in requests.data" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="px-4 py-4">
                                            <span class="text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                                                {{ request.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-mono text-gray-600 dark:text-gray-300">
                                                {{ request.requested_domain }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span :class="['px-2.5 py-1 rounded-full text-xs font-medium capitalize w-fit', getStatusColor(request.status)]">
                                                    {{ request.status }}
                                                </span>
                                                <div v-if="request.status === 'rejected' && request.rejection_reason" class="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 p-2 rounded-lg mt-1 border border-red-100 dark:border-red-900/30">
                                                    <span class="font-bold">Reason:</span> {{ request.rejection_reason }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ new Date(request.created_at).toLocaleDateString() }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="requests.links.length > 3" class="mt-6 flex justify-center">
                            <nav class="flex gap-1">
                                <template v-for="(link, k) in requests.links" :key="k">
                                    <div v-if="link.url === null" class="px-4 py-2 text-sm text-gray-400 border border-gray-200 dark:border-slate-800 rounded-lg" v-html="link.label"></div>
                                    <Link v-else :href="link.url" class="px-4 py-2 text-sm border border-gray-200 dark:border-slate-800 rounded-lg transition-colors" :class="{'bg-blue-600 text-white border-blue-600': link.active, 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800': !link.active}" v-html="link.label"></Link>
                                </template>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Modal -->
        <Modal :show="showRequestModal" @close="showRequestModal = false" maxWidth="md">
            <div class="p-6 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Globe class="w-6 h-6 text-blue-600" />
                        {{ requestType === 'custom' ? 'Request Custom Domain' : 'Domain Transfer Request' }}
                    </h3>
                    <button @click="showRequestModal = false" class="text-gray-400 hover:text-gray-500">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitRequest" class="space-y-6">
                    <div>
                        <InputLabel for="requested_domain" :value="requestType === 'custom' ? 'Desired Domain Name' : 'Existing Domain Name'" />
                        <TextInput 
                            id="requested_domain"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.requested_domain"
                            :placeholder="requestType === 'custom' ? 'e.g. wifi.yourbrand.com' : 'e.g. yourdomain.com'"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.requested_domain" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ requestType === 'custom' 
                                ? 'Enter the full domain or subdomain you wish to use for your portal.' 
                                : 'Enter the domain you currently own that you want to transfer to our system.' 
                            }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <SecondaryButton @click="showRequestModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Submit Request
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
