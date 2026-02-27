<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

const props = defineProps({
    categories: Array,
});

const showModal = ref(false);
const editingCategory = ref(null);

const form = useForm({
    name: '',
    display_order: 0,
    is_default: false,
});

const openCreate = () => {
    editingCategory.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.display_order = category.display_order;
    form.is_default = !!category.is_default;
    showModal.value = true;
};

const submit = () => {
    if (editingCategory.value) {
        form.put(route('settings.hotspot.categories.update', editingCategory.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('settings.hotspot.categories.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deleteCategory = (id) => {
    if (confirm('Are you sure you want to delete this category?')) {
        form.delete(route('settings.hotspot.categories.destroy', id));
    }
};
</script>

<template>
    <Layout>
        <Head title="Hotspot Categories" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-semibold">Hotspot Categories</h2>
                            <PrimaryButton @click="openCreate">Add Category</PrimaryButton>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="category in categories" :key="category.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ category.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ category.display_order }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span v-if="category.is_default" class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Default</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button @click="openEdit(category)" class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                            <button @click="deleteCategory(category.id)" class="text-red-600 hover:text-red-900">Delete</button>
                                        </td>
                                    </tr>
                                    <tr v-if="categories.length === 0">
                                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">No categories found. Add some to organize your hotspot packages into tabs.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingCategory ? 'Edit Category' : 'Add Category' }}
                </h2>

                <form @submit.prevent="submit" class="mt-6">
                    <div>
                        <InputLabel for="name" value="Category Name" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="display_order" value="Display Order" />
                        <TextInput id="display_order" type="number" class="mt-1 block w-full" v-model="form.display_order" />
                        <InputError class="mt-2" :message="form.errors.display_order" />
                    </div>

                    <div class="mt-4 flex items-center">
                        <input type="checkbox" id="is_default" v-model="form.is_default" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                        <label for="is_default" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Set as default for packages without category</label>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="showModal = false" class="mr-3 text-gray-600 hover:text-gray-900">Cancel</button>
                        <PrimaryButton :disabled="form.processing">
                            {{ editingCategory ? 'Update' : 'Save' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </Layout>
</template>
