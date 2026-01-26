<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label
        class="block bg-gradient-to-r from-gray-700 to-gray-900 bg-clip-text text-base font-semibold text-transparent transition-all duration-200 dark:from-white dark:to-gray-300"
    >
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
