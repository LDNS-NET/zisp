<template>
  <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white">
        Mikrotik API Connection Tester
      </h3>
      <button
        @click="testConnection"
        :disabled="isTesting || !canTest"
        :class="{
          'opacity-50 cursor-not-allowed': isTesting || !canTest,
          'bg-green-600 hover:bg-green-700': !isTesting && canTest,
          'bg-blue-600': isTesting,
        }"
        class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
      >
        <svg
          v-if="isTesting"
          class="-ml-1 mr-2 h-4 w-4 animate-spin text-white"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <span v-else class="flex items-center">
          <svg
            class="-ml-1 mr-2 h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
        </span>
        {{ isTesting ? 'Testing...' : 'Test API Connection' }}
      </button>
    </div>

    <div v-if="testResult" class="mt-4 rounded-md p-4" :class="testResult.success ? 'bg-green-50 dark:bg-green-900/30' : 'bg-red-50 dark:bg-red-900/30'">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg v-if="testResult.success" class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg v-else class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium" :class="testResult.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'">
            {{ testResult.success ? 'Connection Successful!' : 'Connection Failed' }}
          </h3>
          <div class="mt-2 text-sm" :class="testResult.success ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
            <p>{{ testResult.message }}</p>
          </div>
          
          <!-- Show router identity if available -->
          <div v-if="testResult.router_identity" class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-200">Router Identity:</p>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ testResult.router_identity }}</p>
          </div>
          
          <!-- Show system resources if available -->
          <div v-if="testResult.system_resources" class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-200">System Resources:</p>
            <ul class="mt-1 space-y-1 text-sm text-gray-700 dark:text-gray-300">
              <li v-if="testResult.system_resources.cpu_usage">
                CPU: {{ testResult.system_resources.cpu_usage }}%
              </li>
              <li v-if="testResult.system_resources.memory_usage">
                Memory Usage: {{ Math.round(testResult.system_resources.memory_usage) }}%
              </li>
              <li v-if="testResult.system_resources.uptime">
                Uptime: {{ formatUptime(testResult.system_resources.uptime) }}
              </li>
              <li v-if="testResult.system_resources.version">
                Version: {{ testResult.system_resources.version }}
              </li>
              <li v-if="testResult.system_resources.board_name">
                Model: {{ testResult.system_resources.board_name }}
              </li>
            </ul>
          </div>
          
          <!-- Show requirements check results -->
          <div v-if="testResult.requirements" class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-200">Requirements Check:</p>
            <ul class="mt-1 space-y-1">
              <li v-for="(value, key) in testResult.requirements" :key="key" class="flex items-center text-sm">
                <span v-if="value" class="text-green-600 dark:text-green-400">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                <span v-else class="text-red-600 dark:text-red-400">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </span>
                <span class="ml-2 capitalize">{{ key.replace('_', ' ') }}: {{ value ? 'OK' : 'Not Configured' }}</span>
              </li>
            </ul>
          </div>
          
          <!-- Show error details if any -->
          <div v-if="testResult.details && testResult.details.length > 0" class="mt-3 border-t border-gray-200 pt-2 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-200">Error Details:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
              <li v-for="(detail, index) in testResult.details" :key="index">
                {{ detail }}
              </li>
            </ul>
          </div>
          
          <!-- Show troubleshooting tips if connection failed -->
          <div v-if="!testResult.success" class="mt-4 rounded-md bg-yellow-50 p-3 dark:bg-yellow-900/30">
            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Troubleshooting Tips:</h4>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-yellow-700 dark:text-yellow-300">
              <li>Ensure the router is powered on and connected to the network</li>
              <li>Verify the IP address and API port are correct</li>
              <li>Check if the API service is enabled on the router</li>
              <li>Ensure your firewall allows connections to the API port</li>
              <li>Verify the username and password are correct</li>
              <li>Check if the router's IP is reachable from this server</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  routerData: {
    type: Object,
    required: true,
    default: () => ({
      ip_address: '',
      api_port: '',
      router_username: '',
      router_password: '',
    }),
  },
});

const isTesting = ref(false);
const testResult = ref(null);

const canTest = computed(() => {
  return (
    props.routerData.ip_address &&
    props.routerData.api_port &&
    props.routerData.router_username &&
    props.routerData.router_password
  );
});

const testConnection = async () => {
  if (!canTest.value || isTesting.value) return;
  
  isTesting.value = true;
  testResult.value = null;
  
  try {
    const response = await fetch(route('tenant.mikrotik.test'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        host: props.routerData.ip_address,
        api_port: props.routerData.api_port,
        username: props.routerData.router_username,
        password: props.routerData.router_password,
      }),
    });
    
    const data = await response.json();
    testResult.value = data;
    
    if (!response.ok) {
      throw new Error(data.message || 'Failed to test connection');
    }
  } catch (error) {
    console.error('Error testing connection:', error);
    testResult.value = {
      success: false,
      message: error.message || 'An error occurred while testing the connection',
      details: [error.message],
    };
  } finally {
    isTesting.value = false;
  }
};

const formatUptime = (seconds) => {
  if (!seconds) return 'N/A';
  
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  
  const parts = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0) parts.push(`${hours}h`);
  if (minutes > 0 || parts.length === 0) parts.push(`${minutes}m`);
  
  return parts.join(' ');
};
</script>
