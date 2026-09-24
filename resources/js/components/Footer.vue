<template>
  <footer class="mt-auto py-5 px-6 border-t border-slate-200/60 dark:border-slate-800/60 bg-white/40 dark:bg-slate-900/40 backdrop-blur-md">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
      <div class="flex items-center gap-2">
        <span class="font-medium text-slate-700 dark:text-slate-300">{{ brandingStore.orgName }}</span>
        <span>•</span>
        <span>v{{ authStore.appVersion }}</span>
      </div>

      <!-- Custom Institutional Footer / Copyright Text -->
      <div class="text-center font-medium text-slate-600 dark:text-slate-400">
        <span>{{ brandingStore.orgFooterText || ('© ' + currentYear + ' ' + brandingStore.orgName + '. All rights reserved.') }}</span>
      </div>

      <!-- Legal Links -->
      <div class="flex items-center gap-4">
        <!-- Privacy Policy -->
        <a
          v-if="brandingStore.privacyPolicy.type === 'url' && brandingStore.privacyPolicy.url"
          :href="brandingStore.privacyPolicy.url"
          target="_blank"
          rel="noopener noreferrer"
          class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
        >
          Privacy Policy
        </a>
        <router-link
          v-else
          to="/app/privacy-policy"
          class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
        >
          Privacy Policy
        </router-link>

        <!-- Terms of Service -->
        <a
          v-if="brandingStore.terms.type === 'url' && brandingStore.terms.url"
          :href="brandingStore.terms.url"
          target="_blank"
          rel="noopener noreferrer"
          class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
        >
          Terms of Service
        </a>
        <router-link
          v-else
          to="/app/terms-of-service"
          class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
        >
          Terms of Service
        </router-link>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useBrandingStore } from '@/stores/branding';

const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const currentYear = computed(() => new Date().getFullYear());
</script>
