<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { countries } from '@/Data/countries';
import Modal from '@/Components/Modal.vue';
import { 
    User, 
    Mail, 
    Phone, 
    Globe, 
    Lock, 
    Building2, 
    ArrowRight, 
    CheckCircle2, 
    X,
    MessageSquare
} from 'lucide-vue-next';

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

const showOnboardingModal = ref(false);
const showSuccessToast = ref(false);

const onboardingForm = useForm({
    name: '',
    email: '',
    isp_name: '',
    country: '',
    message: '',
});

const submitOnboarding = () => {
    onboardingForm.post(route('onboarding-requests.store'), {
        onSuccess: () => {
            showOnboardingModal.value = false;
            onboardingForm.reset();
            showSuccessToast.value = true;
            setTimeout(() => {
                showSuccessToast.value = false;
            }, 5000);
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <!-- Success Toast -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showSuccessToast" class="fixed top-5 right-5 z-[100] max-w-sm w-full bg-emerald-500/90 backdrop-blur-xl border border-emerald-400/50 rounded-2xl shadow-2xl p-4 flex items-center gap-4 text-white">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                    <CheckCircle2 class="w-6 h-6" />
                </div>
                <div>
                    <p class="font-bold">Request Sent!</p>
                    <p class="text-sm text-emerald-50/90">We've received your request and will contact you soon.</p>
                </div>
                <button @click="showSuccessToast = false" class="ml-auto text-emerald-100 hover:text-white">
                    <X class="w-5 h-5" />
                </button>
            </div>
        </Transition>
        
        <div class="mb-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-500/10 mb-6 border border-indigo-500/20">
                <User class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                Create Your Account
            </h2>
            <p class="mt-3 text-gray-500 dark:text-gray-400 leading-relaxed">
                Join Zyraaf Cloud and start managing your ISP network with ease.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Business Name -->
            <div class="space-y-2">
                <InputLabel for="name" value="Business Name" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Building2 class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                    </div>
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="e.g. Zyraaf Networks"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Username -->
                <div class="space-y-2">
                    <InputLabel for="username" value="Username" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <User class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <TextInput
                            id="username"
                            type="text"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                            v-model="form.username"
                            required
                            autocomplete="username"
                            placeholder="mikethedev"
                        />
                    </div>
                    <div v-if="form.username" class="mt-2 px-1 animate-fade-in">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <Globe class="w-3 h-3" />
                            Your subdomain: 
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                {{ form.username.toLowerCase().replace(/[^a-z0-9]/g, '') }}.zyraaf.cloud
                            </span>
                        </p>
                    </div>
                    <InputError class="mt-2" :message="form.errors.username" />
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <InputLabel for="email" value="Email Address" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Mail class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <TextInput
                            id="email"
                            type="email"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                            v-model="form.email"
                            required
                            autocomplete="email"
                            placeholder="mike@example.com"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Phone -->
                <div class="space-y-2">
                    <InputLabel for="phone" value="Phone Number" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Phone class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <TextInput
                            id="phone"
                            type="text"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                            v-model="form.phone"
                            required
                            autocomplete="tel"
                            placeholder="+254 700 000 000"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <!-- Country -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between ml-1">
                        <InputLabel for="country" value="Country" class="text-sm font-semibold text-gray-700 dark:text-gray-300" />
                        <button 
                            type="button"
                            @click="showOnboardingModal = true"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                        >
                            Country not listed?
                        </button>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Globe class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <select
                            id="country"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
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
                                {{ country.flag }} {{ country.name }}
                            </option>
                        </select>
                    </div>
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Password -->
                <div class="space-y-2">
                    <InputLabel for="password" value="Password" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Lock class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <TextInput
                            id="password"
                            type="password"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <!-- Confirm Password -->
                <div class="space-y-2">
                    <InputLabel for="password_confirmation" value="Confirm Password" class="text-sm font-semibold text-gray-700 dark:text-gray-300 ml-1" />
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <Lock class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                        </div>
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="block w-full pl-11 rounded-xl border-gray-200 bg-gray-50/50 px-4 py-3.5 text-gray-900 shadow-sm transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-gray-900"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="pt-4">
                <PrimaryButton
                    class="w-full justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 px-8 py-4 text-base font-bold text-white shadow-xl shadow-indigo-500/20 transition-all hover:from-indigo-700 hover:to-blue-700 hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-indigo-500/20 disabled:opacity-50"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Create Account
                    <ArrowRight class="ml-2 h-5 w-5" />
                </PrimaryButton>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <Link
                        :href="route('login')"
                        class="font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                    >
                        Log in here
                    </Link>
                </p>
            </div>
        </form>

        <!-- Onboarding Request Modal -->
        <Modal :show="showOnboardingModal" @close="showOnboardingModal = false">
            <div class="p-8 bg-gray-900 rounded-2xl border border-white/10">
                <div class="flex items-center justify-between mb-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                        <Globe class="w-6 h-6 text-emerald-500" />
                    </div>
                    <button @click="showOnboardingModal = false" class="text-gray-400 hover:text-white transition-colors">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <h3 class="text-2xl font-bold text-white mb-2 tracking-tight">Request Country Support</h3>
                <p class="text-sm text-gray-400 mb-8 leading-relaxed">
                    Don't see your country? Tell us about your ISP, and we'll work on bringing Zyraaf Cloud to your region.
                </p>

                <form @submit.prevent="submitOnboarding" class="space-y-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 ml-1">Full Name</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <User class="h-5 w-5 text-gray-500 group-focus-within:text-emerald-500 transition-colors" />
                                </div>
                                <input 
                                    v-model="onboardingForm.name"
                                    type="text" 
                                    required
                                    class="w-full pl-11 rounded-xl bg-white/5 border-white/10 text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all"
                                    placeholder="Michael The Dev"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 ml-1">Email Address</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Mail class="h-5 w-5 text-gray-500 group-focus-within:text-emerald-500 transition-colors" />
                                </div>
                                <input 
                                    v-model="onboardingForm.email"
                                    type="email" 
                                    required
                                    class="w-full pl-11 rounded-xl bg-white/5 border-white/10 text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all"
                                    placeholder="mikethedev@gmail.com"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 ml-1">ISP Name</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Building2 class="h-5 w-5 text-gray-500 group-focus-within:text-emerald-500 transition-colors" />
                                </div>
                                <input 
                                    v-model="onboardingForm.isp_name"
                                    type="text" 
                                    required
                                    class="w-full pl-11 rounded-xl bg-white/5 border-white/10 text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all"
                                    placeholder="Your ISP Name"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 ml-1">Country</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <Globe class="h-5 w-5 text-gray-500 group-focus-within:text-emerald-500 transition-colors" />
                                </div>
                                <input 
                                    v-model="onboardingForm.country"
                                    type="text" 
                                    required
                                    class="w-full pl-11 rounded-xl bg-white/5 border-white/10 text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all"
                                    placeholder="Your Country"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-300 ml-1">Message (Optional)</label>
                        <div class="relative group">
                            <div class="absolute top-3 left-4 pointer-events-none">
                                <MessageSquare class="h-5 w-5 text-gray-500 group-focus-within:text-emerald-500 transition-colors" />
                            </div>
                            <textarea 
                                v-model="onboardingForm.message"
                                rows="4"
                                class="w-full pl-11 rounded-xl bg-white/5 border-white/10 text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all"
                                placeholder="Tell us more about your needs..."
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button 
                            type="button"
                            @click="showOnboardingModal = false"
                            class="flex-1 px-6 py-4 rounded-xl border border-white/10 text-gray-300 font-bold hover:bg-white/5 transition active:scale-95"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="onboardingForm.processing"
                            class="flex-1 px-6 py-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold shadow-xl shadow-emerald-500/20 hover:scale-[1.02] active:scale-[0.98] transition disabled:opacity-50"
                        >
                            {{ onboardingForm.processing ? 'Submitting...' : 'Submit Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </GuestLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
