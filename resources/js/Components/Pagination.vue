<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-vue-next'

const props = defineProps({
  links: Array,
  perPage: {
    type: [Number, String],
    default: 10,
  },
  total: {
    type: Number,
    default: 0,
  },
  from: {
    type: Number,
    default: 0,
  },
  to: {
    type: Number,
    default: 0,
  }
})

const perPageOptions = [10, 25, 50, 100]
const selectedPerPage = ref(props.perPage)

// Handle changes in selected per-page
watch(selectedPerPage, (value) => {
  const url = new URL(window.location.href)
  url.searchParams.set('per_page', value)
  url.searchParams.set('page', 1)

  router.visit(url.toString(), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
})

// Sync selectedPerPage when props change
watch(() => props.perPage, (val) => {
  selectedPerPage.value = val
})

function navigate(url) {
    if (!url) return;
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
  <div class="flex flex-col md:flex-row justify-between items-center mt-8 py-4 px-2 gap-6 bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm transition-all duration-300">
    <!-- Meta Info & Per Page -->
    <div class="flex flex-col sm:flex-row items-center gap-6 w-full md:w-auto">
      <div v-if="total > 0" class="text-sm text-gray-500 dark:text-gray-400 font-medium">
        Showing 
        <span class="text-gray-900 dark:text-white font-bold">{{ from }}</span> 
        to 
        <span class="text-gray-900 dark:text-white font-bold">{{ to }}</span> 
        of 
        <span class="text-gray-900 dark:text-white font-bold">{{ total }}</span> 
        results
      </div>

      <div class="flex items-center gap-3 bg-gray-50 dark:bg-slate-900/50 p-1.5 rounded-lg border border-gray-200 dark:border-slate-700">
        <label for="perPage" class="text-[11px] uppercase tracking-wider font-bold text-gray-400 dark:text-gray-500 ml-2">
            Show
        </label>
        <select
          id="perPage"
          v-model="selectedPerPage"
          class="bg-transparent border-none text-sm font-bold text-gray-700 dark:text-gray-200 focus:ring-0 cursor-pointer min-w-[60px]"
        >
          <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
      </div>
    </div>

    <!-- Pagination Links -->
    <nav v-if="links.length > 3" class="flex items-center" aria-label="Pagination">
      <ul class="flex items-center gap-1.5">
        <li v-for="(link, key) in links" :key="key">
          <!-- Disabled / Non-link -->
          <div
            v-if="!link.url"
            class="w-10 h-10 flex items-center justify-center rounded-xl text-gray-300 dark:text-gray-600 transition-colors"
          >
            <ChevronLeft v-if="link.label.includes('Previous')" class="w-5 h-5" />
            <ChevronRight v-else-if="link.label.includes('Next')" class="w-5 h-5" />
            <span v-else class="text-sm font-semibold">{{ link.label }}</span>
          </div>

          <!-- Active Link -->
          <button
            v-else
            @click="navigate(link.url)"
            class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold transition-all duration-300 shadow-sm"
            :class="[
              link.active
                ? 'bg-blue-600 text-white shadow-blue-200 dark:shadow-none scale-105'
                : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-500'
            ]"
          >
            <ChevronLeft v-if="link.label.includes('Previous')" class="w-5 h-5" />
            <ChevronRight v-else-if="link.label.includes('Next')" class="w-5 h-5" />
            <span v-else v-html="link.label"></span>
          </button>
        </li>
      </ul>
    </nav>
  </div>
</template>
