<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

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

watch(() => props.perPage, (val) => {
  selectedPerPage.value = val
})

function navigate(url) {
  if (!url) return
  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <div class="flex flex-col md:flex-row justify-between items-center mt-8 py-4 px-2 gap-6 
              bg-white dark:bg-slate-900 
              border border-gray-200 dark:border-slate-700 
              rounded-xl shadow-sm transition-all duration-200">

    <!-- Left: Meta Info + Per Page -->
    <div class="flex flex-col sm:flex-row items-center gap-6 w-full md:w-auto">
      
      <div v-if="total > 0" class="text-sm text-gray-600 dark:text-gray-300 font-medium">
        Showing 
        <span class="text-gray-900 dark:text-white font-bold">{{ from }}</span> 
        to 
        <span class="text-gray-900 dark:text-white font-bold">{{ to }}</span> 
        of 
        <span class="text-gray-900 dark:text-white font-bold">{{ total }}</span> 
        results
      </div>

      <div class="flex items-center gap-3 
                  bg-gray-50 dark:bg-slate-800 
                  p-1.5 rounded-lg 
                  border border-gray-200 dark:border-slate-700">

        <label class="text-[11px] uppercase tracking-wider font-bold text-gray-500 dark:text-gray-400 ml-2">
          Show
        </label>

        <select
          v-model="selectedPerPage"
          class="bg-transparent border-none text-sm font-bold 
                 text-gray-700 dark:text-gray-200 
                 focus:ring-0 cursor-pointer min-w-[60px]"
        >
          <option 
            v-for="option in perPageOptions" 
            :key="option" 
            :value="option"
            class="text-gray-900 dark:text-white bg-white dark:bg-slate-800"
          >
            {{ option }}
          </option>
        </select>

      </div>
    </div>

    <!-- Right: Pagination -->
    <nav v-if="links.length > 3" class="flex items-center" aria-label="Pagination">
      <ul class="flex items-center gap-1.5">
        <li v-for="(link, key) in links" :key="key">

          <!-- Disabled -->
          <div
            v-if="!link.url"
            class="w-10 h-10 flex items-center justify-center rounded-xl 
                   text-gray-300 dark:text-gray-600"
          >
            <ChevronLeft v-if="link.label.includes('Previous')" class="w-5 h-5" />
            <ChevronRight v-else-if="link.label.includes('Next')" class="w-5 h-5" />
            <span v-else class="text-sm font-semibold">{{ link.label }}</span>
          </div>

          <!-- Buttons -->
<button
  v-else
  @click="navigate(link.url)"
  class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold 
         transition-all duration-200 border"
  :class="link.active
    ? 'bg-blue-600 text-white border-blue-600 scale-105'
    : 'bg-white dark:bg-slate-900 text-gray-700 dark:text-gray-300 
       border-gray-200 dark:border-slate-700 
       hover:bg-gray-100 dark:hover:bg-slate-700'"
>
  <ChevronLeft
    v-if="link.label.includes('Previous')"
    class="w-5 h-5"
  />
  <ChevronRight
    v-else-if="link.label.includes('Next')"
    class="w-5 h-5"
  />
  <span v-else v-html="link.label"></span>
</button>


        </li>
      </ul>
    </nav>
  </div>
</template>
