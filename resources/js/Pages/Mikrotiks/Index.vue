<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    mikrotiks: Object,
    filters: Object,
});

</script>

<template>
    <Head title="Mikrotiks" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Mikrotiks
            </h2>
        </template>
    
    </AuthenticatedLayout>
</template>