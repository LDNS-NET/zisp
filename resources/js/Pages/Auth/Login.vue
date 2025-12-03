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

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>
        <div class="text-blue-800">
            <h2 v-if="tenantName" class="mb-2 text-center text-xl font-bold">
                {{ tenantName }}
            </h2>
            <h3 v-if="tenantName" class="mb-4 text-center text-lg">
                Welcome back!
            </h3>
            <h2 v-else class="mb-4 text-center text-2xl font-extrabold">Login</h2>
        </div>

        <form
            @submit.prevent="submit"
            class="rounded-xl border border-black bg-gray-300 px-3 py-2 dark:border-blue-400 dark:bg-black"
        >
            <div class="mt-4">
                <InputLabel for="username" value="Usename" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.username"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-900 dark:text-white"
                        >Remember me</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div
                    class="rounded-xl bg-gray-200 px-2 text-blue-800 underline hover:bg-black hover:text-white"
                >
                    <Link :href="route('register')" class="">
                        create account?
                    </Link>
                </div>

                <div class="hover:gb-black/60 hover:text-white">
                    <PrimaryButton
                        class="ms-4 hover:bg-green-600 dark:bg-blue-400"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Log in
                    </PrimaryButton>
                </div>
            </div>
            <div
                class="m-4 justify-end hover:text-blue-500 dark:text-gray-100 dark:hover:text-green-400"
            >
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm underline"
                >
                    Forgot your password?
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
