<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col items-center py-8 px-4">
    <div class="w-full max-w-md">
      <!-- Branding -->
      <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-50">
          {{ tenant.business_name }}
        </h1>
        <p class="text-gray-500 dark:text-gray-400">{{ tenant.phone }}</p>
      </div>

      <!-- Login Form -->
      <form
        :action="hotspotLoginUrl"
        method="post"
        class="space-y-4 bg-white dark:bg-gray-800 p-6 rounded-lg shadow"
      >
        <input
          v-for="(v, k) in hiddenParams"
          :key="k"
          type="hidden"
          :name="k"
          :value="v"
        />
        <div>
          <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Username</label>
          <input
            v-model="credentials.username"
            type="text"
            name="username"
            required
            class="input"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Password</label>
          <input
            v-model="credentials.password"
            type="password"
            name="password"
            required
            class="input"
          />
        </div>
        <button type="submit" class="btn-primary w-full">Login</button>
      </form>

      <!-- Packages -->
      <div class="mt-10">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-200 text-center">
          Available Packages
        </h2>
        <div class="grid gap-4">
          <div
            v-for="pkg in packages"
            :key="pkg.id"
            class="border dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-800"
          >
            <h3 class="text-lg font-bold mb-2 text-gray-900 dark:text-gray-50">
              {{ pkg.name }}
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Speed: {{ pkg.speed }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Validity: {{ pkg.validity }}
            </p>
            <p class="text-xl font-semibold mt-2 text-indigo-600 dark:text-indigo-400">
              {{ formatCurrency(pkg.price) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, toRefs } from 'vue'
import { usePage } from '@inertiajs/vue3'

const { props } = usePage()
const { tenant, packages, hotspotParams } = props

// Reactive state for form fields
const credentials = reactive({
  username: hotspotParams.username || '',
  password: ''
})

// Build the Mikrotik login URL (link-login param)
const hotspotLoginUrl = computed(() => hotspotParams['link-login'] || '/login')

// Hidden params to send back to router (exclude username & password)
const hiddenParams = computed(() => {
  const clone = { ...hotspotParams }
  delete clone.username
  return clone
})

function formatCurrency(value) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0
  }).format(value)
}
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50;
}
.btn-primary {
  @apply bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow;
}
</style>
