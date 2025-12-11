<template>
  <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 shadow-sm rounded-lg p-6 border border-blue-200 dark:border-gray-700">
    <div class="flex items-center mb-4">
      <svg class="w-6 h-6 mr-2 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
      </svg>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
        Admin WireGuard VPN Info
      </h3>
    </div>

    <div class="space-y-4">
      <!-- Requirement Notice -->
      <div class="p-4 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 rounded-lg">
        <p class="text-sm text-blue-900 dark:text-blue-200">
          <strong>📌 Important:</strong> You must be connected to the server's WireGuard VPN to access Remote Winbox.
        </p>
      </div>

      <!-- Server Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Server Endpoint</p>
          <p class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ serverEndpoint }}
          </p>
        </div>

        <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Your Admin VPN IP</p>
          <p class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">
            {{ adminVpnIp || 'Assign from server' }}
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button
          @click="downloadConfig"
          class="flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Download .conf
        </button>

        <button
          @click="showQR = !showQR"
          class="flex items-center justify-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
          </svg>
          {{ showQR ? 'Hide' : 'Show' }} QR Code
        </button>
      </div>

      <!-- QR Code Display -->
      <div v-if="showQR" class="p-4 bg-white dark:bg-gray-700 rounded-lg text-center">
        <div class="inline-block p-4 bg-white rounded-lg">
          <!-- QR code would go here - requires a QR code library like qrcode.vue -->
          <div class="w-48 h-48 flex items-center justify-center border-2 border-dashed border-gray-300">
            <p class="text-sm text-gray-500">QR Code<br/>Install qrcode.vue<br/>for mobile scanning</p>
          </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
          Scan with WireGuard mobile app
        </p>
      </div>

      <!-- Setup Instructions -->
      <details class="bg-white dark:bg-gray-700 rounded-lg">
        <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg">
          📖 Setup Instructions
        </summary>
        <div class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-300 space-y-2">
          <ol class="list-decimal list-inside space-y-1">
            <li>Install WireGuard on your device</li>
            <li>Download the .conf file or scan the QR code</li>
            <li>Import into WireGuard app</li>
            <li>Connect to the VPN</li>
            <li>Now you can access Remote Winbox!</li>
          </ol>
        </div>
      </details>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  serverEndpoint: {
    type: String,
    default: 'vpn.example.com:51820',
  },
  adminVpnIp: {
    type: String,
    default: null,
  },
});

const showQR = ref(false);

const downloadConfig = () => {
  // In a real implementation, this would download a WireGuard .conf file
  // For now, we'll show a placeholder
  alert('Contact your system administrator to get your WireGuard configuration file.');
};
</script>
