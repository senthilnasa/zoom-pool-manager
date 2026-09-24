<template>
  <teleport to="body">
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="close" />

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
          <transition
            enter-active-class="transition ease-out duration-300 transform"
            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="transition ease-in duration-200 transform"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <div
              v-if="show"
              class="relative transform overflow-hidden rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl p-4 sm:p-6 text-left shadow-2xl transition-all my-4 sm:my-8 w-[94vw] sm:w-full border border-slate-200/80 dark:border-slate-800"
              :class="maxWidthClass"
            >
              <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100 dark:border-slate-800 gap-2">
                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-slate-100 truncate">
                  {{ title }}
                </h3>
                <button
                  type="button"
                  @click="close"
                  class="rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                  <X class="w-5 h-5" />
                </button>
              </div>

              <div>
                <slot />
              </div>

              <div v-if="$slots.footer" class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <slot name="footer" />
              </div>
            </div>
          </transition>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  maxWidth: {
    type: String,
    default: 'md',
  },
});

const emit = defineEmits(['close']);

const close = () => {
  emit('close');
};

const maxWidthClass = computed(() => {
  return {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
  }[props.maxWidth] || 'sm:max-w-md';
});
</script>
