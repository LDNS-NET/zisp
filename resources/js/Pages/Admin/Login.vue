<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Lock, Mail, ArrowRight, Loader2, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('admin.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Admin Login" />

    <div class="min-h-screen bg-slate-900 flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] bg-indigo-500/10 rounded-full blur-[120px]"></div>
            <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] bg-blue-500/10 rounded-full blur-[100px]"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <!-- Logo / Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white mb-4 shadow-lg shadow-indigo-500/30">
                    <ShieldCheck class="w-8 h-8" />
                </div>
                <h1 class="text-3xl font-black text-white tracking-tight">Admin Portal</h1>
                <p class="text-slate-400 mt-2 font-medium">Secure access for administrators</p>
            </div>

            <!-- Login Card -->
            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Email Address</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 group-focus-within:text-indigo-400 transition-colors">
                                <Mail class="w-5 h-5" />
                            </div>
                            <input 
                                v-model="form.email"
                                type="email" 
                                class="w-full pl-12 pr-4 py-4 bg-slate-900/50 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                placeholder="admin@example.com"
                                required
                                autofocus
                            />
                        </div>
                        <p v-if="form.errors.email" class="text-red-400 text-sm font-medium mt-1 ml-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Password</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 group-focus-within:text-indigo-400 transition-colors">
                                <Lock class="w-5 h-5" />
                            </div>
                            <input 
                                v-model="form.password"
                                type="password" 
                                class="w-full pl-12 pr-4 py-4 bg-slate-900/50 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                placeholder="••••••••"
                                required
                            />
                        </div>
                        <p v-if="form.errors.password" class="text-red-400 text-sm font-medium mt-1 ml-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.remember" class="w-5 h-5 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-offset-slate-900 focus:ring-indigo-500 transition-all">
                            <span class="ml-3 text-sm font-medium text-slate-400">Keep me logged in</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold text-lg shadow-lg shadow-indigo-500/20 transition-all transform active:scale-[0.98] flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="form.processing" class="w-5 h-5 animate-spin" />
                        <span v-else>Sign In</span>
                        <ArrowRight v-if="!form.processing" class="w-5 h-5" />
                    </button>
                </form>
            </div>
            
            <div class="mt-8 text-center">
                <p class="text-slate-500 text-sm">
                    &copy; {{ new Date().getFullYear() }} ZISP. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
