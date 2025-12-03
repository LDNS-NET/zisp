<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    tenantName: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 p-4 text-sm font-medium text-green-600 dark:bg-green-900/30 dark:text-green-400">
            {{ status }}
        </div>

        <div class="mb-8 text-center">
            <h2 v-if="tenantName" class="animate-fade-in-down bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-3xl font-bold text-transparent dark:from-blue-400 dark:to-indigo-400">
                {{ tenantName }}
            </h2>
            <p v-if="tenantName" class="animate-fade-in-up mt-2 text-lg text-gray-600 dark:text-gray-300">
                Welcome back!
            </p>
            <h2 v-else class="text-3xl font-bold text-gray-900 dark:text-white">
                Welcome Back
            </h2>
            <p v-if="!tenantName" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Please sign in to your account
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="animate-slide-up" style="animation-delay: 100ms;">
                <InputLabel for="username" value="Username" class="text-gray-700 dark:text-gray-300" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                    v-model="form.username"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Enter your username"
                />
                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div class="animate-slide-up" style="animation-delay: 200ms;">
                <InputLabel for="password" value="Password" class="text-gray-700 dark:text-gray-300" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between animate-slide-up" style="animation-delay: 300ms;">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:ring-offset-gray-800" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Forgot password?
                </Link>
            </div>

            <div class="animate-slide-up" style="animation-delay: 400ms;">
                <PrimaryButton
                    class="w-full justify-center rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:from-indigo-700 hover:to-blue-700 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-800"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Sign in
                </PrimaryButton>
            </div>

            <div class="mt-6 text-center animate-slide-up" style="animation-delay: 500ms;">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Don't have an account?
                    <Link
                        :href="route('register')"
                        class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Create account
                    </Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
.animate-fade-in-down {
    animation: fadeInDown 0.6s ease-out;
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

.animate-slide-up {
    animation: slideUp 0.6s ease-out backwards;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
