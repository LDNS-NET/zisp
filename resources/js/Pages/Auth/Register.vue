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
        
        <div class="mb-8 text-center">
            <h2 class="animate-fade-in-down bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-3xl font-bold text-transparent dark:from-blue-400 dark:to-indigo-400">
                Create Account
            </h2>
            <p class="animate-fade-in-up mt-2 text-sm text-gray-600 dark:text-gray-400">
                Join us and start managing your network today
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Business Name -->
            <div class="animate-slide-up" style="animation-delay: 100ms;">
                <InputLabel for="name" value="Business Name" class="text-gray-700 dark:text-gray-300" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Enter your business name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <!-- Username -->
                <div class="animate-slide-up" style="animation-delay: 200ms;">
                    <InputLabel for="username" value="Username" class="text-gray-700 dark:text-gray-300" />
                    <TextInput
                        id="username"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.username"
                        required
                        autocomplete="username"
                        placeholder="Choose a username"
                    />
                    <InputError class="mt-2" :message="form.errors.username" />
                </div>

                <!-- Email -->
                <div class="animate-slide-up" style="animation-delay: 250ms;">
                    <InputLabel for="email" value="Email" class="text-gray-700 dark:text-gray-300" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.email"
                        required
                        autocomplete="email"
                        placeholder="name@company.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <!-- Phone -->
                <div class="animate-slide-up" style="animation-delay: 300ms;">
                    <InputLabel for="phone" value="Phone" class="text-gray-700 dark:text-gray-300" />
                    <TextInput
                        id="phone"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.phone"
                        required
                        autocomplete="tel"
                        placeholder="+1 234 567 890"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <!-- Country -->
                <div class="animate-slide-up" style="animation-delay: 350ms;">
                    <InputLabel for="country" value="Country" class="text-gray-700 dark:text-gray-300" />
                    <select
                        id="country"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
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
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <!-- Password -->
                <div class="animate-slide-up" style="animation-delay: 400ms;">
                    <InputLabel for="password" value="Password" class="text-gray-700 dark:text-gray-300" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div class="animate-slide-up" style="animation-delay: 450ms;">
                    <InputLabel for="password_confirmation" value="Confirm Password" class="text-gray-700 dark:text-gray-300" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white/50 px-4 py-3 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="animate-slide-up pt-2" style="animation-delay: 500ms;">
                <PrimaryButton
                    class="w-full justify-center rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:from-indigo-700 hover:to-blue-700 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-800"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Create Account
                </PrimaryButton>
            </div>

            <div class="mt-6 text-center animate-slide-up" style="animation-delay: 600ms;">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <Link
                        :href="route('login')"
                        class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Log in
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
