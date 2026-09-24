<template>
  <div class="glass-card transition-all duration-300 hover:shadow-lg dark:hover:shadow-2xl overflow-hidden" :class="[padding ? 'p-6' : '', customClass]">
    <div v-if="$slots.header || title" class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 dark:border-slate-800/80">
      <div>
        <h3 v-if="title" class="text-base font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <slot name="icon" />
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          {{ subtitle }}
        </p>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
    
    <slot />

    <div v-if="$slots.footer" class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800/80">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  padding: {
    type: Boolean,
    default: true,
  },
  customClass: {
    type: String,
    default: '',
  },
});
</script>
