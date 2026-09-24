<template>
  <header class="h-16 px-6 glass-nav flex items-center justify-between sticky top-0 z-20">
    <div class="flex items-center gap-3">
      <button
        @click="$emit('toggle-sidebar')"
        class="lg:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
      >
        <Menu class="w-5 h-5" />
      </button>

      <div class="flex items-center gap-2.5">
        <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">
          {{ pageTitle }}
        </h1>

        <!-- Module Instructions Help Button -->
        <button
          type="button"
          @click="showHelpModal = true"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold text-brand-700 dark:text-brand-300 bg-brand-500/10 hover:bg-brand-500/20 border border-brand-500/20 hover:border-brand-500/30 transition-all shadow-xs cursor-pointer group"
          title="Open step-by-step instructions for this module"
        >
          <HelpCircle class="w-3.5 h-3.5 text-brand-600 dark:text-brand-400 group-hover:scale-110 transition-transform" />
          <span class="hidden sm:inline font-medium">Guide & Help</span>
        </button>
      </div>
    </div>

    <!-- Center Global Search Input (Desktop) -->
    <div class="hidden md:flex items-center flex-1 max-w-md mx-6">
      <button
        type="button"
        @click="showSearchModal = true"
        class="w-full flex items-center justify-between px-3.5 py-1.5 rounded-xl bg-slate-100/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:border-brand-500/40 transition-all text-xs shadow-xs group"
      >
        <div class="flex items-center gap-2">
          <Search class="w-3.5 h-3.5 text-slate-400 group-hover:text-brand-500 transition-colors" />
          <span>Search modules, users, meetings...</span>
        </div>
        <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono font-semibold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md">
          ⌘K
        </kbd>
      </button>
    </div>

    <div class="flex items-center gap-2.5">
      <!-- Search Trigger Icon for Mobile -->
      <button
        type="button"
        @click="showSearchModal = true"
        class="md:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        title="Search"
      >
        <Search class="w-4 h-4" />
      </button>

      <!-- Theme Switcher (System / Light / Dark) -->
      <ThemeSelector />
    </div>

    <!-- Global Search Modal Popup -->
    <GlobalSearchModal v-model="showSearchModal" />

    <!-- Instructions Modal Popup -->
    <ModuleHelpModal
      :show="showHelpModal"
      :module-key="currentModuleKey"
      @close="showHelpModal = false"
    />
  </header>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import ThemeSelector from '@/components/ThemeSelector.vue';
import ModuleHelpModal from '@/components/ModuleHelpModal.vue';
import GlobalSearchModal from '@/components/GlobalSearchModal.vue';
import { moduleGuides } from '@/data/moduleGuides';
import { Menu, HelpCircle, Search } from 'lucide-vue-next';

defineEmits(['toggle-sidebar']);

const route = useRoute();
const showHelpModal = ref(false);
const showSearchModal = ref(false);

const pageTitle = computed(() => {
  return route.meta?.title || 'Zoom Pool Manager';
});

const currentModuleKey = computed(() => {
  if (route.name && moduleGuides[route.name]) {
    return route.name;
  }
  const cleanPath = (route.path || '').replace(/^\/app\/?/, '');
  if (cleanPath && moduleGuides[cleanPath]) {
    return cleanPath;
  }
  for (const key of Object.keys(moduleGuides)) {
    if (cleanPath.startsWith(key) || (route.name && String(route.name).includes(key))) {
      return key;
    }
  }
  return 'dashboard';
});
</script>
