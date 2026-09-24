<template>
  <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
    <!-- Left: Results Counter & Rows Per Page -->
    <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
      <div v-if="total > 0" class="font-medium text-slate-600 dark:text-slate-300">
        Showing <span class="font-bold text-slate-900 dark:text-white">{{ fromRecord }}</span> to
        <span class="font-bold text-slate-900 dark:text-white">{{ toRecord }}</span> of
        <span class="font-bold text-slate-900 dark:text-white">{{ total }}</span> records
      </div>
      <div v-else class="text-slate-400">
        0 records
      </div>

      <div class="flex items-center gap-1.5 ml-2">
        <label for="perPageSelect" class="text-[11px] text-slate-400 hidden xs:inline whitespace-nowrap">Per page:</label>
        <select
          id="perPageSelect"
          :value="perPage"
          @change="$emit('per-page-change', parseInt($event.target.value))"
          class="rounded-lg px-2 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs font-semibold focus:ring-1 focus:ring-brand-500 cursor-pointer"
        >
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="100">100</option>
        </select>
      </div>
    </div>

    <!-- Right: Page Navigation Buttons -->
    <div v-if="lastPage > 1" class="flex items-center gap-1 w-full sm:w-auto justify-center sm:justify-end">
      <!-- First Page -->
      <button
        type="button"
        :disabled="currentPage <= 1"
        @click="$emit('page-change', 1)"
        class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition"
        title="First page"
      >
        <ChevronsLeft class="w-3.5 h-3.5" />
      </button>

      <!-- Previous Page -->
      <button
        type="button"
        :disabled="currentPage <= 1"
        @click="$emit('page-change', currentPage - 1)"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition text-xs font-medium"
      >
        <ChevronLeft class="w-3.5 h-3.5" />
        <span>Prev</span>
      </button>

      <!-- Current / Total indicator -->
      <span class="px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200">
        {{ currentPage }} / {{ lastPage }}
      </span>

      <!-- Next Page -->
      <button
        type="button"
        :disabled="currentPage >= lastPage"
        @click="$emit('page-change', currentPage + 1)"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition text-xs font-medium"
      >
        <span>Next</span>
        <ChevronRight class="w-3.5 h-3.5" />
      </button>

      <!-- Last Page -->
      <button
        type="button"
        :disabled="currentPage >= lastPage"
        @click="$emit('page-change', lastPage)"
        class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition"
        title="Last page"
      >
        <ChevronsRight class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
} from 'lucide-vue-next';

const props = defineProps({
  currentPage: {
    type: Number,
    default: 1,
  },
  lastPage: {
    type: Number,
    default: 1,
  },
  perPage: {
    type: Number,
    default: 25,
  },
  total: {
    type: Number,
    default: 0,
  },
});

defineEmits(['page-change', 'per-page-change']);

const fromRecord = computed(() => {
  if (props.total === 0) return 0;
  return (props.currentPage - 1) * props.perPage + 1;
});

const toRecord = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.total);
});
</script>
