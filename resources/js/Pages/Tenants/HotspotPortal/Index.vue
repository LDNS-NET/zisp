<script setup>
import { ref } from 'vue';

const props = defineProps({
  tenant: { type: Object, required: true },
  packages: { type: Array, required: true },
  hotspotParams: { type: Object, required: true },
});

const loginForm = ref({
  username: props.hotspotParams.username || '',
  password: '',
});

const loginUrl = props.hotspotParams['link-login'] || props.hotspotParams['link-login-only'] || '/';

function submitLogin() {
  const url = new URL(loginUrl, window.location.origin);
  url.searchParams.set('username', loginForm.value.username);
  url.searchParams.set('password', loginForm.value.password);
  window.location.href = url.toString();
}
</script>

<template>
  <div class="min-h-screen w-full bg-gray-950 text-gray-100 flex flex-col items-center py-10 px-4">
    <h1 class="text-3xl font-extrabold mb-1 text-center">{{ tenant.business_name }}</h1>
    <p class="text-sm text-gray-400 mb-6 text-center">Support: {{ tenant.phone }}</p>

    <section class="w-full max-w-md space-y-4">
      <input v-model="loginForm.username" placeholder="Username" class="w-full rounded bg-gray-800 p-3 focus:outline-none focus:ring" />
      <input v-model="loginForm.password" type="password" placeholder="Password" class="w-full rounded bg-gray-800 p-3 focus:outline-none focus:ring" />
      <button @click="submitLogin" class="w-full rounded bg-blue-600 hover:bg-blue-700 p-3 font-semibold">
        Connect
      </button>
    </section>

    <section class="mt-10 w-full max-w-4xl">
      <h2 class="text-xl font-semibold mb-4 text-center">Available Packages</h2>
      <div class="grid gap-6 md:grid-cols-3">
        <div v-for="pkg in packages" :key="pkg.id" class="rounded border border-gray-800 p-5 flex flex-col">
          <h3 class="font-bold text-lg mb-2">{{ pkg.name }}</h3>
          <p class="text-gray-400 mb-4">Ksh {{ pkg.price }}</p>
          <button class="mt-auto rounded bg-green-600 hover:bg-green-700 py-2 px-4">
            Buy
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/***** No additional styles needed due to Tailwind *****/
</style>
