<template>
  <div class="inline-flex items-center p-1 bg-slate-100/80 dark:bg-slate-800/80 backdrop-blur-md rounded-xl border border-slate-200/60 dark:border-slate-700/60 text-xs">
    <button
      type="button"
      @click="themeStore.setTheme('light')"
      :class="[
        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-all duration-200',
        themeStore.currentTheme === 'light'
          ? 'bg-white text-brand-600 shadow-sm'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
      ]"
      title="Light Mode"
    >
      <Sun class="w-3.5 h-3.5" />
      <span class="hidden sm:inline">Light</span>
    </button>

    <button
      type="button"
      @click="themeStore.setTheme('system')"
      :class="[
        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-all duration-200',
        themeStore.currentTheme === 'system'
          ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-400 shadow-sm'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
      ]"
      title="System (Default) - Automatically matches OS preference"
    >
      <Monitor class="w-3.5 h-3.5" />
      <span class="hidden sm:inline">System</span>
    </button>

    <button
      type="button"
      @click="themeStore.setTheme('dark')"
      :class="[
        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-all duration-200',
        themeStore.currentTheme === 'dark'
          ? 'bg-slate-700 text-brand-400 shadow-sm'
          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
      ]"
      title="Dark Mode"
    >
      <Moon class="w-3.5 h-3.5" />
      <span class="hidden sm:inline">Dark</span>
    </button>
  </div>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme';
import { Sun, Moon, Monitor } from 'lucide-vue-next';

const themeStore = useThemeStore();
</script>
