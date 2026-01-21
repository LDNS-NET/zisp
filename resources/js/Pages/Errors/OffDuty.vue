<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Phone, Mail, LogOut } from 'lucide-vue-next';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    user: Object,
    schedule: Object,
});
</script>

<template>
    <Head title="Shift Ended" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col items-center justify-center p-6 text-center">
        <div class="max-w-md w-full bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 border border-gray-100 dark:border-gray-800">
            <div class="h-20 w-20 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <Clock class="h-10 w-10" />
            </div>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Well Done for Today, {{ user.name }}!</h1>
            <p class="text-gray-500 dark:text-gray-400 mb-8">
                Your scheduled working hours for today have concluded. The system is currently restricted to active shift personnel only.
            </p>

            <div v-if="schedule" class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 mb-8 text-left">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Today's Schedule</h4>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300">Shift Window</span>
                    <span class="font-bold text-gray-900 dark:text-white">{{ schedule.start }} — {{ schedule.end }}</span>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-xs text-gray-400 mb-4">Need emergency access? Contact your administrator:</p>
                <div class="flex flex-col gap-2">
                    <a href="#" class="flex items-center justify-center gap-2 text-sm text-blue-600 hover:underline">
                        <Phone class="h-4 w-4" />
                        Management Support
                    </a>
                </div>
            </div>

            <div class="mt-10 pt-6 border-t border-gray-100 dark:border-gray-800">
                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button" 
                    class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg font-bold hover:opacity-90 transition-opacity"
                >
                    <LogOut class="h-4 w-4" />
                    Secure Logout
                </Link>
            </div>
        </div>
        
        <p class="mt-8 text-sm text-gray-400">
            &copy; {{ new Date().getFullYear() }} ZISP Digital Infrastructure
        </p>
    </div>
</template>
