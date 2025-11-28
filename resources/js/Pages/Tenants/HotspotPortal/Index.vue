<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const tenant = computed(() => page.props.tenant || {});
const packages = computed(() => page.props.packages || []);
const hotspotParams = computed(() => page.props.hotspotParams || {});

// Bind username if Mikrotik passes it in query string
const username = ref(hotspotParams.value.username || '');
const password = ref('');

// Construct the MikroTik login URL (link-login) if provided
const loginUrl = computed(() => hotspotParams.value['link-login'] || '#');

function submitLogin() {
    if (!loginUrl.value || loginUrl.value === '#') return;

    const form = document.createElement('form');
    form.action = loginUrl.value;
    form.method = 'POST';

    const uInput = document.createElement('input');
    uInput.name = 'username';
    uInput.value = username.value;
    form.appendChild(uInput);

    const pInput = document.createElement('input');
    pInput.name = 'password';
    pInput.value = password.value;
    form.appendChild(pInput);

    document.body.appendChild(form);
    form.submit();
}
</script>

<template>
    <Head :title="`${tenant.business_name || 'Hotspot'} Portal`" />

    <div class="min-h-screen bg-gray-900 text-gray-200 flex flex-col items-center p-4">
        <header class="mt-8 text-center space-y-1">
            <h1 class="text-3xl font-extrabold">{{ tenant.business_name }}</h1>
            <p class="text-sm text-gray-400">Support: {{ tenant.phone }}</p>
        </header>

        <section class="mt-10 w-full max-w-3xl grid gap-6">
            <h2 class="text-xl font-bold mb-3">Choose a Package</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="border border-gray-700 rounded-lg p-4 flex flex-col justify-between"
                >
                    <div>
                        <h3 class="font-semibold text-lg mb-1">{{ pkg.name }}</h3>
                        <p class="text-sm text-gray-400 mb-2">Speed: {{ pkg.download_speed }} / {{ pkg.upload_speed }} Mbps</p>
                    </div>
                    <button
                        class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded"
                        @click="username = ''"
                    >
                        Buy &amp; Connect – {{ pkg.price }}
                    </button>
                </div>
            </div>
        </section>

        <section class="mt-10 w-full max-w-sm bg-gray-800 rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Login</h2>
            <form @submit.prevent="submitLogin" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm mb-1">Username</label>
                    <input
                        id="username"
                        v-model="username"
                        class="w-full rounded bg-gray-900 border-gray-700 px-3 py-2"
                        required
                    />
                </div>
                <div>
                    <label for="password" class="block text-sm mb-1">Password</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        class="w-full rounded bg-gray-900 border-gray-700 px-3 py-2"
                        required
                    />
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 py-2 rounded">
                    Connect
                </button>
            </form>
        </section>
    </div>
</template>

<style scoped>
/* Dark mode colors handled via Tailwind classes */
</style>
