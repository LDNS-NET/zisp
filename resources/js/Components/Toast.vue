<script setup>
import { ref, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

const props = defineProps({
    autoClose: {
        type: Boolean,
        default: true
    },
    duration: {
        type: Number,
        default: 5000
    }
});

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref('success'); // success, error, warning, info

const icons = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info
};

const colors = {
    success: 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800',
    error: 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
    warning: 'bg-yellow-50 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800',
    info: 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'
};

const iconColors = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-yellow-500',
    info: 'text-blue-500'
};

let timeout = null;

const close = () => {
    show.value = false;
    if (timeout) clearTimeout(timeout);
};

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        type.value = 'success';
        message.value = flash.success;
        show.value = true;
    } else if (flash?.error) {
        type.value = 'error';
        message.value = flash.error;
        show.value = true;
    } else if (flash?.warning) {
        type.value = 'warning';
        message.value = flash.warning;
        show.value = true;
    } else if (flash?.info) {
        type.value = 'info';
        message.value = flash.info;
        show.value = true;
    }

    if (show.value && props.autoClose) {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => {
            close();
        }, props.duration);
    }
}, { deep: true });
</script>

<template>
    <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div 
            v-if="show" 
            class="fixed bottom-4 right-4 z-50 max-w-sm w-full shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
            :class="[colors[type], 'border']"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <component :is="icons[type]" class="h-6 w-6" :class="iconColors[type]" aria-hidden="true" />
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium">
                            {{ message }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button 
                            @click="close" 
                            class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="[
                                type === 'success' ? 'text-green-500 hover:bg-green-100 focus:ring-green-600 dark:hover:bg-green-900/50' :
                                type === 'error' ? 'text-red-500 hover:bg-red-100 focus:ring-red-600 dark:hover:bg-red-900/50' :
                                type === 'warning' ? 'text-yellow-500 hover:bg-yellow-100 focus:ring-yellow-600 dark:hover:bg-yellow-900/50' :
                                'text-blue-500 hover:bg-blue-100 focus:ring-blue-600 dark:hover:bg-blue-900/50'
                            ]"
                        >
                            <span class="sr-only">Close</span>
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
