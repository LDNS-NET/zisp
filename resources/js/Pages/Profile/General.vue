<script setup>
import { useToast } from 'vue-toastification';
const toast = useToast();
import { ref, onMounted, watch } from 'vue';
import { router, usePage, Head } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextArea from '@/Components/TextArea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Building2, Phone, MapPin, Globe, Clock } from 'lucide-vue-next';

const page = usePage();
const { setTheme } = useTheme();

const displayCountry = ref(page.props.settings?.country || page.props.country || 'Kenya');
const displayCurrency = ref(page.props.settings?.currency || page.props.currency || 'KES');

const form = ref({
    business_name: '',
    business_type: 'isp',
    logo: '',
    theme: 'system',
    support_email: '',
    support_phone: '',
    whatsapp: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    website: '',
    facebook: '',
    twitter: '',
    instagram: '',
    business_hours:
        'Monday - Friday: 8:00 AM - 6:00 PM\nSaturday: 9:00 AM - 4:00 PM\nSunday: Closed',
    timezone: 'Africa/Nairobi',
    language: 'en',
});

function setFormFromBackend(settings) {
    // Update read-only country and currency
    displayCountry.value = settings.country || page.props.country || 'Kenya';
    displayCurrency.value = settings.currency || page.props.currency || 'KES';
    
    form.value = {
        business_name:
            typeof settings.business_name === 'number'
                ? settings.business_name
                : (settings.business_name ?? ''),
        business_type:
            typeof settings.business_type === 'number'
                ? settings.business_type
                : (settings.business_type ?? 'isp'),
        logo:
            typeof settings.logo === 'number'
                ? settings.logo
                : (settings.logo ?? ''),
        theme:
            typeof settings.theme === 'number'
                ? settings.theme
                : (settings.theme ?? 'system'),
        support_email:
            typeof settings.support_email === 'number'
                ? settings.support_email
                : (settings.support_email ?? ''),
        support_phone:
            typeof settings.support_phone === 'number'
                ? settings.support_phone
                : (settings.support_phone ?? ''),
        whatsapp:
            typeof settings.whatsapp === 'number'
                ? settings.whatsapp
                : (settings.whatsapp ?? ''),
        address:
            typeof settings.address === 'number'
                ? settings.address
                : (settings.address ?? ''),
        city:
            typeof settings.city === 'number'
                ? settings.city
                : (settings.city ?? ''),
        state:
            typeof settings.state === 'number'
                ? settings.state
                : (settings.state ?? ''),
        postal_code:
            typeof settings.postal_code === 'number'
                ? settings.postal_code
                : (settings.postal_code ?? ''),
        website:
            typeof settings.website === 'number'
                ? settings.website
                : (settings.website ?? ''),
        facebook:
            typeof settings.facebook === 'number'
                ? settings.facebook
                : (settings.facebook ?? ''),
        twitter:
            typeof settings.twitter === 'number'
                ? settings.twitter
                : (settings.twitter ?? ''),
        instagram:
            typeof settings.instagram === 'number'
                ? settings.instagram
                : (settings.instagram ?? ''),
        business_hours:
            typeof settings.business_hours === 'number'
                ? settings.business_hours
                : (settings.business_hours ??
                  'Monday - Friday: 8:00 AM - 6:00 PM\nSaturday: 9:00 AM - 4:00 PM\nSunday: Closed'),
        timezone:
            typeof settings.timezone === 'number'
                ? settings.timezone
                : (settings.timezone ?? 'Africa/Nairobi'),
        language:
            typeof settings.language === 'number'
                ? settings.language
                : (settings.language ?? 'en'),
    };
}

onMounted(() => {
    setFormFromBackend(page.props.settings || {});
    if (form.value.theme) setTheme(form.value.theme);
});

const logoFile = ref(null);
const logoPreview = ref('');

// Watch for logo changes from backend
watch(() => form.value.logo, (newLogo) => {
    if (!logoFile.value && newLogo) {
        logoPreview.value = newLogo;
    }
});

onMounted(() => {
    // Sync global theme/color from loaded settings
    if (form.value.theme) setTheme(form.value.theme);
    // if (form.value.primary_color) setPrimaryColor(form.value.primary_color)
});

function onLogoChange(e) {
    const file = e.target.files[0];
    if (file) {
        logoFile.value = file;
        const reader = new FileReader();
        reader.onload = (ev) => {
            logoPreview.value = ev.target.result;
        };
        reader.readAsDataURL(file);
    }
}
function removeLogo() {
    logoFile.value = null;
    logoPreview.value = '';
    form.value.logo = '';
    form.value.remove_logo = true;
}

const success = ref(page.props.flash?.success || '');
const error = ref('');
const loading = ref(false);

function submit() {
    loading.value = true;
    error.value = '';
    success.value = '';

    const formData = new FormData();
    for (const [key, value] of Object.entries(form.value)) {
        if (typeof value !== 'undefined' && value !== null) {
            formData.append(key, value);
        }
    }
    if (logoFile.value) {
        formData.append('logo', logoFile.value);
    }

    router.post(route('settings.general.update'), formData, {
        forceFormData: true,
        onSuccess: (page) => {
            loading.value = false;
            logoFile.value = null;
            logoPreview.value = '';
            if (page?.props?.settings) {
                setFormFromBackend(page.props.settings);
                if (form.value.theme) setTheme(form.value.theme);
            }
            toast.success('General settings updated successfully.');
        },
        onError: (errs) => {
            error.value = Object.values(errs).flat().join(' ');
            loading.value = false;
            toast.error(error.value);
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}
</script>

<template>
    <Head title="General Settings" />

    <div
        class="mx-auto max-w-4xl rounded-lg border border-blue-500 bg-gray-300 p-6 shadow-sm dark:bg-gray-800"
    >
        <div class="mb-6">
            <h2
                class="flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-gray-100"
            >
                <Building2 class="h-6 w-6 text-blue-600" />
                General Settings
            </h2>
            <p class="mt-1 text-gray-600 dark:text-blue-200">
                Manage your business information, contact details, and general
                preferences.
            </p>
        </div>

        <!-- Success/Error Messages removed, Toastify will handle notifications -->

        <form @submit.prevent="submit" class="space-y-8">
            <!-- Business Information Section -->
            <div class="rounded-lg bg-gray-100 p-6 dark:bg-black">
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-blue-100"
                >
                    <Building2 class="h-5 w-5 text-blue-600" />
                    Business Information
                </h3>

                <!-- Business Logo Upload -->
                <div class="mb-6">
                    <InputLabel for="logo" value="Business Logo" />
                    <div class="mt-2 flex items-center gap-6">
                        <div v-if="form.logo || logoPreview" class="relative">
                            <img
                                :src="logoPreview || form.logo"
                                alt="Logo Preview"
                                class="h-20 w-20 rounded border object-contain shadow"
                            />
                            <button
                                type="button"
                                @click="removeLogo"
                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 shadow"
                            >
                                &times;
                            </button>
                        </div>
                        <input
                            type="file"
                            id="logo"
                            accept="image/*"
                            @change="onLogoChange"
                            class="block"
                        />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Upload your business logo (max 2MB, PNG/JPG/GIF/SVG).
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="md:col-span-2"></div>

                    <!-- Business Description section removed -->

                    <div>
                        <InputLabel for="business_type" value="Business Type" />
                        <select
                            id="business_type"
                            v-model="form.business_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-black"
                        >
                            <option value="isp">
                                Internet Service Provider
                            </option>
                            <option value="wisp">
                                Wireless Internet Service Provider
                            </option>
                            <option value="telecom">Telecommunications</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Business Registration Number and Tax/VAT Number sections removed -->
                </div>

                <!-- Theme and Color Customization -->
                <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel for="theme" value="Theme" />
                        <select
                            id="theme"
                            v-model="form.theme"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-black"
                        >
                            <option value="system">System Default</option>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <!-- Primary Color section removed -->
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="rounded-lg bg-gray-50 p-6 dark:bg-black">
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-green-100"
                >
                    <Phone class="h-5 w-5 text-green-600" />
                    Contact Information
                </h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel for="support_email" value="Support Email" />
                        <TextInput
                            id="support_email"
                            v-model="form.support_email"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="support@yourisp.com"
                        />
                    </div>
                    <div>
                        <InputLabel for="support_phone" value="Support Phone" />
                        <TextInput
                            id="support_phone"
                            v-model="form.support_phone"
                            class="mt-1 block w-full"
                            placeholder="+254 700 000 001"
                        />
                    </div>
                    <div>
                        <InputLabel for="whatsapp" value="WhatsApp Number" />
                        <TextInput
                            id="whatsapp"
                            v-model="form.whatsapp"
                            class="mt-1 block w-full"
                            placeholder="+254 700 000 000"
                        />
                    </div>
                </div>
            </div>

            <!-- Address Information Section -->
            <div class="rounded-lg bg-gray-50 p-6 dark:bg-black">
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-red-100"
                >
                    <MapPin class="h-5 w-5 text-red-600" />
                    Business Address
                </h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <InputLabel for="address" value="Street Address" />
                        <TextInput
                            id="address"
                            v-model="form.address"
                            class="mt-1 block w-full"
                            placeholder="123 Main Street, Building A"
                        />
                    </div>

                    <div>
                        <InputLabel for="city" value="City" />
                        <TextInput
                            id="city"
                            v-model="form.city"
                            class="mt-1 block w-full"
                            placeholder="Nairobi"
                        />
                    </div>

                    <div>
                        <InputLabel for="state" value="State/Region" />
                        <TextInput
                            id="state"
                            v-model="form.state"
                            class="mt-1 block w-full"
                            placeholder="Nairobi County"
                        />
                    </div>

                    <div>
                        <InputLabel for="postal_code" value="Postal Code" />
                        <TextInput
                            id="postal_code"
                            v-model="form.postal_code"
                            class="mt-1 block w-full"
                            placeholder="00100"
                        />
                    </div>

                    <div>
                        <InputLabel for="country" value="Country" />
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
                            {{ displayCountry }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Read-only field from your profile</p>
                    </div>
                </div>
            </div>

            <!-- Online Presence Section -->
            <div class="rounded-lg bg-gray-50 p-6 dark:bg-black">
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-blue-100"
                >
                    <Globe class="h-5 w-5 text-purple-600" />
                    Online Presence
                </h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel for="website" value="Website URL" />
                        <TextInput
                            id="website"
                            v-model="form.website"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://www.yourisp.com"
                        />
                    </div>

                    <div>
                        <InputLabel for="facebook" value="Facebook Page" />
                        <TextInput
                            id="facebook"
                            v-model="form.facebook"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://facebook.com/yourisp"
                        />
                    </div>

                    <div>
                        <InputLabel for="twitter" value="Twitter/X Profile" />
                        <TextInput
                            id="twitter"
                            v-model="form.twitter"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://twitter.com/yourisp"
                        />
                    </div>

                    <div>
                        <InputLabel for="instagram" value="Instagram Profile" />
                        <TextInput
                            id="instagram"
                            v-model="form.instagram"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://instagram.com/yourisp"
                        />
                    </div>
                </div>
            </div>

            <!-- Business Hours & Preferences Section -->
            <div class="rounded-lg bg-gray-50 p-6 dark:bg-black">
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900"
                >
                    <Clock class="h-5 w-5 text-orange-600" />
                    Business Hours & Preferences
                </h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="md:col-span-3">
                        <InputLabel
                            for="business_hours"
                            value="Business Hours"
                        />
                        <TextArea
                            id="business_hours"
                            v-model="form.business_hours"
                            class="mt-1 block w-full"
                            rows="4"
                            placeholder="Monday - Friday: 8:00 AM - 6:00 PM&#10;Saturday: 9:00 AM - 4:00 PM&#10;Sunday: Closed"
                        />
                    </div>

                    <div>
                        <InputLabel for="timezone" value="Timezone" />
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-black"
                        >
                            <option value="Africa/Nairobi">
                                Africa/Nairobi / SSP (EAT)
                            </option>
                            <option value="Africa/Kampala">
                                Africa/Kampala (EAT)
                            </option>
                            <option value="Africa/Dar_es_Salaam">
                                Africa/Dar_es_Salaam (EAT)
                            </option>
                            <option value="Africa/Kigali">
                                Africa/Kigali (CAT)
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
                            {{ displayCurrency }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Read-only field from your profile</p>
                    </div>

                    <div>
                        <InputLabel for="language" value="Language" />
                        <select
                            id="language"
                            v-model="form.language"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-black"
                        >
                            <option value="en">English</option>
                            <option value="sw">Swahili</option>
                            <option value="fr">French</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end border-t border-blue-900 pt-6">
                <PrimaryButton
                    type="submit"
                    :disabled="loading"
                    class="flex items-center gap-2 px-2 py-2"
                >
                    <span v-if="loading">Saving...</span>
                    <span v-else>Save Changes</span>
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
