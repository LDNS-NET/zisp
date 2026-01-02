<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Wifi, 
    User, 
    Lock, 
    ArrowRight,
    Loader2,
    ShieldCheck
} from 'lucide-vue-next';

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('customer.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Customer Login" />

    <div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
        <!-- Background Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-100 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-1/2 -right-24 w-80 h-80 bg-violet-100 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute -bottom-24 left-1/2 w-64 h-64 bg-slate-200 rounded-full blur-3xl opacity-30"></div>
        </div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
            <div class="flex justify-center mb-8">
                <div class="w-16 h-16 bg-indigo-600 rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-indigo-200">
                    <Wifi class="w-10 h-10 text-white" />
                </div>
            </div>
            <h2 class="text-center text-4xl font-black text-slate-900 tracking-tight">
                Welcome Back
            </h2>
            <p class="mt-3 text-center text-slate-500 font-medium">
                Sign in to manage your internet account
            </p>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
            <div class="bg-white py-10 px-6 shadow-2xl shadow-slate-200 sm:rounded-[2.5rem] sm:px-10 border border-slate-100">
                <form @submit.prevent="submit" class="space-y-8">
                    <div>
                        <label for="username" class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-3">
                            Username / Phone
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <User class="h-5 w-5 text-slate-400" />
                            </div>
                            <input
                                id="username"
                                type="text"
                                v-model="form.username"
                                required
                                autofocus
                                class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-900 font-bold placeholder:text-slate-400 focus:outline-none focus:ring-0 focus:border-indigo-600 transition-all"
                                placeholder="Enter your username"
                            />
                        </div>
                        <p v-if="form.errors.username" class="mt-2 text-sm font-bold text-red-600">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-3">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <Lock class="h-5 w-5 text-slate-400" />
                            </div>
                            <input
                                id="password"
                                type="password"
                                v-model="form.password"
                                required
                                class="block w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-900 font-bold placeholder:text-slate-400 focus:outline-none focus:ring-0 focus:border-indigo-600 transition-all"
                                placeholder="••••••••"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-sm font-bold text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                id="remember_me"
                                type="checkbox"
                                v-model="form.remember"
                                class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded-lg transition-all cursor-pointer"
                            />
                            <label for="remember_me" class="ml-3 block text-sm font-bold text-slate-600 cursor-pointer">
                                Remember me
                            </label>
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex justify-center items-center gap-3 py-4 px-4 border border-transparent rounded-2xl shadow-xl shadow-indigo-100 text-lg font-black text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition-all active:scale-[0.98] disabled:opacity-50"
                        >
                            <Loader2 v-if="form.processing" class="animate-spin h-6 w-6" />
                            <span v-else>Sign In</span>
                            <ArrowRight v-if="!form.processing" class="h-6 w-6" />
                        </button>
                    </div>
                </form>

                <div class="mt-10">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-slate-100"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-slate-400 font-bold uppercase tracking-widest text-[10px]">
                                Secure Access
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-center gap-2 text-slate-400">
                        <ShieldCheck class="w-4 h-4" />
                        <span class="text-xs font-bold">Encrypted Connection</span>
                    </div>
                </div>
            </div>
            
            <p class="mt-8 text-center text-xs text-slate-400 font-bold uppercase tracking-widest">
                &copy; {{ new Date().getFullYear() }} ZISP. All rights reserved.
            </p>
        </div>
    </div>
</template>

<style scoped>
input:focus {
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}
</style>
