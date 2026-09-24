<template>
  <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <transition-group
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl border backdrop-blur-xl shadow-lg"
        :class="{
          'bg-emerald-50/90 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200': toast.type === 'success',
          'bg-rose-50/90 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200': toast.type === 'error',
          'bg-amber-50/90 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200': toast.type === 'warning',
          'bg-slate-50/90 dark:bg-slate-900/80 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200': toast.type === 'info',
        }"
      >
        <CheckCircle v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
        <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
        <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
        <Info v-else class="w-5 h-5 text-brand-500 shrink-0 mt-0.5" />

        <div class="flex-1 text-sm font-medium">
          {{ toast.message }}
        </div>

        <button
          @click="toastStore.remove(toast.id)"
          class="shrink-0 p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 opacity-70 hover:opacity-100 transition-opacity"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToastStore } from '@/stores/toast';
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

const toastStore = useToastStore();
</script>
