<template>
  <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
        Remote Winbox Manager
      </h3>
      <span
        :class="[
          'px-3 py-1 rounded-full text-sm font-medium',
          isOnline
            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        ]"
      >
        {{ isOnline ? 'Online' : 'Offline' }}
      </span>
    </div>

    <!-- WireGuard IP Display -->
    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-600 dark:text-gray-400">WireGuard IP</p>
          <p class="text-lg font-mono font-semibold text-gray-900 dark:text-gray-100">
            {{ wgIp || 'Not configured' }}
          </p>
        </div>
        <button
          @click="checkStatus"
          :disabled="isChecking"
          class="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
          title="Refresh status"
        >
          <svg
            :class="['w-5 h-5 text-gray-600 dark:text-gray-400', isChecking && 'animate-spin']"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
        </button>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <!-- Enable Winbox Button -->
      <button
        @click="enableWinbox"
        :disabled="isEnabling || !wgIp"
        class="flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors"
      >
        <svg
          v-if="isEnabling"
          class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M5 13l4 4L19 7"
          />
        </svg>
        {{ isEnabling ? 'Enabling...' : 'Enable Remote Winbox' }}
      </button>

      <!-- Open Winbox Button -->
      <button
        @click="openWinbox"
        :disabled="!isOnline || !wgIp"
        class="flex items-center justify-center px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
          />
        </svg>
        Open Winbox
      </button>
    </div>

    <!-- Warning Message -->
    <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
      <p class="text-sm text-yellow-800 dark:text-yellow-200">
        <strong>Note:</strong> You must be connected to the WireGuard VPN to use Remote Winbox.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  mikrotik: {
    type: Object,
    required: true,
  },
});

const isOnline = ref(false);
const isChecking = ref(false);
const isEnabling = ref(false);
const pollingInterval = ref(null);

const wgIp = computed(() => props.mikrotik.wireguard_address);
const winboxUrl = computed(() => `winbox://${wgIp.value}:8291`);

// Check router status
const checkStatus = async () => {
  if (!wgIp.value || isChecking.value) return;

  isChecking.value = true;
  try {
    const response = await axios.get(route('mikrotiks.winbox.ping', props.mikrotik.id));
    isOnline.value = response.data.online;
  } catch (error) {
    console.error('Failed to check router status:', error);
    isOnline.value = false;
  } finally {
    isChecking.value = false;
  }
};

// Enable Winbox
const enableWinbox = async () => {
  if (!wgIp.value || isEnabling.value) return;

  isEnabling.value = true;
  try {
    const response = await axios.post(route('mikrotiks.winbox.enable', props.mikrotik.id));
    
    if (response.data.success) {
      // Show success toast (assuming you have a toast composable)
      if (window.toast) {
        window.toast.success(response.data.message || 'Remote Winbox enabled successfully');
      }
      
      // Recheck status
      await checkStatus();
    } else {
      throw new Error(response.data.message || 'Failed to enable Remote Winbox');
    }
  } catch (error) {
    console.error('Failed to enable Winbox:', error);
    
    if (window.toast) {
      window.toast.error(error.response?.data?.message || error.message || 'Failed to enable Remote Winbox');
    }
  } finally {
    isEnabling.value = false;
  }
};

// Open Winbox
const openWinbox = () => {
  if (!wgIp.value) return;
  
  window.location.href = winboxUrl.value;
};

// Start polling
const startPolling = () => {
  // Initial check
  checkStatus();
  
  // Poll every 4 seconds
  pollingInterval.value = setInterval(() => {
    checkStatus();
  }, 4000);
};

// Stop polling
const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
};

// Lifecycle
onMounted(() => {
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});
</script>
