<script setup>
import { Head } from '@inertiajs/vue3';
import { Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextArea from '@/Components/TextArea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const form = useForm({
    name: '',
    content: '',
});
function submit() {
    form.post(route('smstemplates.store'), {
        onSuccess: () => {
            toast.success('Template created successfully');
            form.reset();
        },
        onError: () => {
            toast.error('Failed. Check form for errors.');
        },
    });
}

const availableVariables = [
    { label: 'Full Name', value: '{full_name}' },
    { label: 'Phone', value: '{phone}' },
    { label: 'Account No.', value: '{account_number}' },
    { label: 'Expiry Date', value: '{expiry_date}' },
    { label: 'Package Name', value: '{package}' },
    { label: 'Username', value: '{username}' },
    { label: 'Password', value: '{password}' },
    { label: 'Support No.', value: '{support_number}' },
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
</script>

<template>
    <Head title="Create SMSTemplate"/>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight">SMS Template</h2>
        </template>

        <div class="rounded-xl py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden border border-dashed border-purple-800 bg-gray-200 p-6 px-8 shadow-sm sm:rounded-lg sm:px-4 md:px-8 lg:px-12 xl:px-16 dark:border-blue-600 dark:bg-black"
                >
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label
                                for="name"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Template Name</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                name="name"
                                id="name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="content"
                                class="flex text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Template Content</InputLabel
                            >

                            <TextArea
                                v-model="form.content"
                                name="content"
                                id="content"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            ></TextArea>
                        </div>

                        <div class="px-2 font-semibold text-sm text-gray-700 dark:text-gray-300">
                            Available Variables (Click to insert):
                        </div>
                        <div class="flex flex-wrap gap-2 px-2">
                            <button 
                                v-for="v in availableVariables" 
                                :key="v.value"
                                type="button"
                                @click="insertVariable(v.value)"
                                class="px-2 py-1 bg-white dark:bg-gray-600 border border-gray-200 dark:border-gray-500 rounded text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-300 transition-colors shadow-sm"
                                :title="'Insert ' + v.value"
                            >
                                {{ v.label }}
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <PrimaryButton>
                                    <Link
                                        :href="route('smstemplates.index')"
                                        class="inline-flex items-center"
                                    >
                                        Cancel
                                    </Link>
                                </PrimaryButton>
                            </div>
                            <div>
                                <PrimaryButton
                                    type="submit"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    :disabled="form.processing"
                                >
                                    Save
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
