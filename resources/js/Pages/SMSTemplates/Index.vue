<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    FileText,
    Plus,
    Search,
    MoreVertical,
    Edit,
    Trash2,
    Save,
    XCircle,
    CheckCircle
} from 'lucide-vue-next';
import { useToast } from 'vue-toastification';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    templates: Object,
    perPage: Number,
});

const toast = useToast();

// State
const showModal = ref(false);
const showActionsModal = ref(false);
const editing = ref(null);
const selectedTemplate = ref(null);
const selectedItems = ref([]);
const selectAll = ref(false);

const form = useForm({
    name: '',
    content: '',
});

// Bulk Selection Logic
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.templates.data.map(t => t.id);
    } else {
        selectedItems.value = [];
    }
};

watch(selectedItems, (val) => {
    selectAll.value = val.length === props.templates.data.length && props.templates.data.length > 0;
});

// Actions
const openCreateModal = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (template) => {
    editing.value = template.id;
    form.name = template.name;
    form.content = template.content;
    form.clearErrors();
    showModal.value = true;
    showActionsModal.value = false;
};

const openActions = (template) => {
    selectedTemplate.value = template;
    showActionsModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editing.value = null;
    form.reset();
};

const submit = () => {
    if (editing.value) {
        form.put(route('smstemplates.update', editing.value), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Template updated successfully');
                closeModal();
            },
            onError: () => {
                toast.error('Failed to update template');
            },
        });
    } else {
        form.post(route('smstemplates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Template created successfully');
                closeModal();
            },
            onError: () => {
                toast.error('Failed to create template');
            },
        });
    }
};

const availableVariables = [
    '{full_name}',
    '{phone}',
    '{account_number}',
    '{expiry_date}',
    '{package}',
    '{username}',
    '{password}',
    '{support_number}'
];

const insertVariable = (variable) => {
    const textarea = document.getElementById('content');
    if (textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = form.content;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);
        form.content = before + variable + after;
        
        // Restore focus and cursor position
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(start + variable.length, start + variable.length);
        }, 0);
    } else {
        form.content += variable;
    }
};

const deleteTemplate = (template) => {
    if (confirm('Are you sure you want to delete this template?')) {
        router.delete(route('smstemplates.destroy', template.id), {
            preserveScroll: true,
            onSuccess: () => {
                showActionsModal.value = false;
                toast.success('Template deleted successfully');
            },
            onError: () => {
                toast.error('Failed to delete template');
            },
        });
    }
};

const bulkDelete = () => {
    if (!selectedItems.value.length) return;
    
    if (confirm(`Are you sure you want to delete ${selectedItems.value.length} templates?`)) {
        router.delete(route('smstemplates.bulk-delete'), {
            data: { ids: selectedItems.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
                toast.success('Selected templates deleted successfully');
            },
        });
    }
};
</script>

<template>
    <Head title="SMS Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <FileText class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        SMS Templates
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage reusable SMS message templates
                    </p>
                </div>
                <PrimaryButton @click="openCreateModal" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>New Template</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Main Content -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <!-- Bulk Actions Toolbar -->
                <div v-if="selectedItems.length > 0" class="bg-blue-50 dark:bg-blue-900/20 px-4 py-3 flex items-center justify-between border-b border-blue-100 dark:border-blue-800">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                        {{ selectedItems.length }} selected
                    </span>
                    <button 
                        @click="bulkDelete"
                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm font-medium flex items-center gap-2"
                    >
                        <Trash2 class="w-4 h-4" />
                        Delete Selected
                    </button>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left">
                                    <Checkbox 
                                        :checked="selectAll"
                                        @update:checked="val => { selectAll = val; toggleSelectAll() }"
                                    />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Content</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="template in templates.data" :key="template.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Checkbox 
                                        :value="template.id"
                                        v-model:checked="selectedItems"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ template.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-300 max-w-lg truncate" :title="template.content">
                                        {{ template.content }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(template)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="templates.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-4 mb-4">
                                            <FileText class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No templates found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create templates to reuse common messages.</p>
                                        <div class="mt-6">
                                            <PrimaryButton @click="openCreateModal">
                                                <Plus class="w-4 h-4 mr-2" />
                                                New Template
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden">
                    <div v-if="selectedItems.length > 0" class="p-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <Checkbox 
                            :checked="selectAll"
                            @update:checked="val => { selectAll = val; toggleSelectAll() }"
                        >
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All</span>
                        </Checkbox>
                    </div>
                    
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="template in templates.data" :key="template.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <Checkbox 
                                    :value="template.id"
                                    v-model:checked="selectedItems"
                                    class="mt-1"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ template.name }}
                                        </h3>
                                        <button @click="openActions(template)" class="text-gray-400">
                                            <MoreVertical class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">
                                        {{ template.content }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Empty State Mobile -->
                        <div v-if="templates.data.length === 0" class="p-8 text-center">
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-4 mb-4 inline-flex">
                                <FileText class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No templates</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create your first template.</p>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="templates.total > templates.per_page" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 sm:px-6">
                    <Pagination :links="templates.links" />
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ editing ? 'Edit Template' : 'Create Template' }}
                    </h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Template Name" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g., Payment Reminder"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="content" value="Template Content" />
                            <textarea
                                id="content"
                                v-model="form.content"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="Type your template content here..."
                                required
                            ></textarea>
                            <InputError :message="form.errors.content" class="mt-2" />
                            <div class="mt-2 flex justify-between items-start">
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <p class="mb-1 font-medium">Available Variables (Click to insert):</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button 
                                            v-for="variable in availableVariables" 
                                            :key="variable"
                                            type="button"
                                            @click="insertVariable(variable)"
                                            class="px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-xs text-gray-700 dark:text-gray-300 transition-colors border border-gray-200 dark:border-gray-600"
                                        >
                                            {{ variable }}
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap ml-4">
                                    {{ form.content.length }} characters
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeModal">
                            Cancel
                        </DangerButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <Save class="w-4 h-4 mr-2" />
                            {{ editing ? 'Update Template' : 'Save Template' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal (Mobile/Desktop) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-gray-800" v-if="selectedTemplate">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Actions for {{ selectedTemplate.name }}
                </h3>
                <div class="space-y-3">
                    <button 
                        @click="openEditModal(selectedTemplate)"
                        class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        <Edit class="w-4 h-4 mr-3 text-blue-500" />
                        Edit Template
                    </button>
                    
                    <button 
                        @click="deleteTemplate(selectedTemplate)"
                        class="w-full flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                    >
                        <Trash2 class="w-4 h-4 mr-3" />
                        Delete Template
                    </button>
                </div>
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="showActionsModal = false"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
