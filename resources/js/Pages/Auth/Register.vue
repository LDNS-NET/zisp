<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { countries } from '@/Data/countries';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    username: '',
    password: '',
    country: '',
    country_code: '',
    currency: '',
    currency_name: '',
    dial_code: '',
    password_confirmation: '',
});

const selectedCountry = () => {
    const country = countries.find(
        (c) => c.name === form.country
    );
    if (country) {
        form.country_code = country.code;
        form.currency = country.currency;
        form.currency_name = country.currency_name;
        form.dial_code = country.dial_code;
    } else {
        form.country_code = '';
        form.currency = '';
        form.currency_name = '';
        form.dial_code = '';
    }
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />
        <div class="text-blue-600">
            <h2 class="mb-4 text-center text-2xl font-bold">
                Create Admin Account
            </h2>
        </div>
        <form
            @submit.prevent="submit"
            class="mt-6 rounded-lg border border-gray-900 p-6 pt-6 shadow dark:border-blue-400 dark:bg-gray-800"
        >
            <div>
                <InputLabel for="name" value="Business Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="username" value="Username" />

                <TextInput
                    id="username"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.username"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="phone" value="Phone" />

                <TextInput
                    id="phone"
                    type="string"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    required
                    autocomplete="phone"
                />

                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>
            <div class="mt-4">
                <InputLabel for="country" value="Country" />

                <select
                    id="country"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300"
                    v-model="form.country"
                    @change="selectedCountry"
                    required
                >
                    <option value="" disabled>Select your country</option>
                    <option
                        v-for="country in countries"
                        :key="country.code"
                        :value="country.name"
                    >
                        {{ country.name }}
                    </option>
                </select>

                <InputError class="mt-2" :message="form.errors.country" />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md px-1 py-1 text-sm text-gray-900 underline hover:text-gray-900 dark:bg-gray-300"
                >
                    Or login?
                </Link>

                <PrimaryButton
                    class="ms-4 dark:bg-blue-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
