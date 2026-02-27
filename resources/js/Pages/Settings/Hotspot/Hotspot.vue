<script setup>
import { ref } from 'vue';
import { useForm, usePage, Head, router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import { Plus, Edit, Trash2, Layers, Settings, List } from 'lucide-vue-next';
const props = defineProps({
    settings: Object,
    categories: Array,
});

const activeTab = ref('general');

// Create useForm with server values (same pattern as your profile)
const form = useForm({
    portal_template: props.settings?.portal_template ?? 'default',
    logo_url: props.settings?.logo_url ?? '',
    user_prefix: props.settings?.user_prefix ?? '',
    prune_inactive_days: props.settings?.prune_inactive_days ?? '',
});

// Category Management
const showCategoryModal = ref(false);
const editingCategory = ref(null);
const categoryForm = useForm({
    name: '',
    display_order: 0,
    is_default: false,
});

const openCreateCategory = () => {
    editingCategory.value = null;
    categoryForm.reset();
    showCategoryModal.value = true;
};

const openEditCategory = (category) => {
    editingCategory.value = category;
    categoryForm.name = category.name;
    categoryForm.display_order = category.display_order;
    categoryForm.is_default = !!category.is_default;
    showCategoryModal.value = true;
};

const submitCategory = () => {
    if (editingCategory.value) {
        categoryForm.put(route('settings.hotspot.categories.update', editingCategory.value.id), {
            onSuccess: () => {
                showCategoryModal.value = false;
                categoryForm.reset();
            },
        });
    } else {
        categoryForm.post(route('settings.hotspot.categories.store'), {
            onSuccess: () => {
                showCategoryModal.value = false;
                categoryForm.reset();
            },
        });
    }
};

const deleteCategory = (id) => {
    if (confirm('Are you sure you want to delete this category?')) {
        router.delete(route('settings.hotspot.categories.destroy', id));
    }
};
</script>

<template>
    <Layout>
        <Head title="Hotspot Settings" />

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- Tab Navigation -->
                <div class="flex items-center gap-1 mb-6 p-1 bg-gray-100 dark:bg-slate-900 rounded-xl w-fit">
                    <button 
                        @click="activeTab = 'general'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all',
                            activeTab === 'general' 
                                ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        ]"
                    >
                        <Settings class="w-4 h-4" />
                        General Settings
                    </button>
                    <button 
                        @click="activeTab = 'categories'"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all',
                            activeTab === 'categories' 
                                ? 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        ]"
                    >
                        <Layers class="w-4 h-4" />
                        Package Categories
                    </button>
                </div>

                <!-- General Settings Tab -->
                <div v-if="activeTab === 'general'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <header class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Settings class="w-5 h-5 text-blue-600" />
                                Hotspot Settings
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Configure your hotspot appearance and behavior.
                            </p>
                        </header>

                        <form @submit.prevent="form.post(route('settings.hotspot.update'))" class="space-y-6 max-w-2xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="portal_template" value="Captive Portal Template" />
                                    <select
                                        id="portal_template"
                                        v-model="form.portal_template"
                                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-gray-100 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="default">Classic Professional</option>
                                        <option value="modern-dark">Modern SaaS (Dark)</option>
                                        <option value="vibrant-gradient">Vibrant Energy</option>
                                        <option value="glassmorphism">Glassmorphism Pro</option>
                                        <option value="minimalist-clean">Minimalist Clean</option>
                                        <option value="corporate-split">Professional Split</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.portal_template" />
                                </div>

                                <div>
                                    <InputLabel for="prune_inactive_days" value="Prune Inactive (days)" />
                                    <TextInput id="prune_inactive_days" type="number" v-model="form.prune_inactive_days" class="mt-1 block w-full" placeholder="e.g. 30" />
                                    <InputError class="mt-2" :message="form.errors.prune_inactive_days" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="logo_url" value="Logo URL" />
                                <TextInput id="logo_url" v-model="form.logo_url" class="mt-1 block w-full" placeholder="https://your-logo-url.com/logo.png" />
                                <InputError class="mt-2" :message="form.errors.logo_url" />
                            </div>

                            <div>
                                <InputLabel for="user_prefix" value="User Prefix" />
                                <TextInput id="user_prefix" v-model="form.user_prefix" class="mt-1 block w-full" placeholder="e.g. WIFI-" />
                                <InputError class="mt-2" :message="form.errors.user_prefix" />
                            </div>

                            <div class="flex items-center justify-between pt-6 border-t border-gray-100 dark:border-slate-700">
                                <p v-if="form.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    Settings saved.
                                </p>
                                <div v-else></div>
                                <PrimaryButton :disabled="form.processing" class="flex items-center gap-2">
                                    Update Settings
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories Tab -->
                <div v-if="activeTab === 'categories'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-slate-700">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <header>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Layers class="w-5 h-5 text-blue-600" />
                                    Package Categories
                                </h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Organize your hotspot packages into tabs.
                                </p>
                            </header>
                            <PrimaryButton @click="openCreateCategory" class="flex items-center gap-2">
                                <Plus class="w-4 h-4" />
                                Add Category
                            </PrimaryButton>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead class="bg-gray-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Order</th>
                                        <th class="px-6 py-3 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Default</th>
                                        <th class="px-6 py-3 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                    <tr v-for="category in categories" :key="category.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ category.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ category.display_order }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="category.is_default" class="px-2 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-md">DEFAULT</span>
                                            <span v-else class="text-gray-300 dark:text-slate-600 text-xs">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                                            <button @click="openEditCategory(category)" class="p-1.5 text-slate-400 hover:text-blue-600 transition-colors rounded-lg hover:bg-white dark:hover:bg-slate-800 shadow-sm border border-transparent hover:border-slate-100 dark:hover:border-slate-700">
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button @click="deleteCategory(category.id)" class="p-1.5 text-slate-400 hover:text-red-600 transition-colors rounded-lg hover:bg-white dark:hover:bg-slate-800 shadow-sm border border-transparent hover:border-slate-100 dark:hover:border-slate-700">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="categories.length === 0">
                                        <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <Layers class="w-10 h-10 text-gray-200 mb-3" />
                                                <p>No categories found.</p>
                                                <p class="text-xs">Add categories to organize your hotspot packages.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Modal -->
        <Modal :show="showCategoryModal" @close="showCategoryModal = false" maxWidth="md">
            <div class="p-6">
                <header class="mb-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Layers class="w-5 h-5 text-blue-600" />
                        {{ editingCategory ? 'Edit Category' : 'Add Category' }}
                    </h2>
                </header>

                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Category Name" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="categoryForm.name" required autofocus placeholder="e.g. Daily, Monthly" />
                        <InputError class="mt-2" :message="categoryForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="display_order" value="Display Order" />
                            <TextInput id="display_order" type="number" class="mt-1 block w-full" v-model="categoryForm.display_order" />
                            <InputError class="mt-2" :message="categoryForm.errors.display_order" />
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" v-model="categoryForm.is_default" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                <span class="text-sm font-medium text-slate-600 dark:text-slate-400 group-hover:text-blue-600 transition-colors">Set as Default</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-slate-700">
                        <button type="button" @click="showCategoryModal = false" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Cancel</button>
                        <PrimaryButton :disabled="categoryForm.processing">
                            {{ editingCategory ? 'Update Category' : 'Save Category' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </Layout>
</template>
