<template>
  <div class="min-h-[70vh] flex items-center justify-center p-4">
    <div class="glass-card max-w-lg w-full p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xl text-center space-y-6 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl animate-in fade-in zoom-in-95 duration-200">
      <!-- Shield / Lock Badge -->
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-rose-500/10 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/20 shadow-inner">
        <ShieldAlert class="w-10 h-10" />
      </div>

      <!-- Heading & Description -->
      <div class="space-y-2">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40">
          <span>HTTP 403</span>
          <span>•</span>
          <span>Access Restricted</span>
        </div>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
          Module Access Denied
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-sm mx-auto">
          You do not have the required administrative role or permissions to access this module.
        </p>
      </div>

      <!-- Attempted Route & User Info Card -->
      <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 text-left text-xs space-y-2">
        <div v-if="attemptedPath" class="flex items-center justify-between gap-2 overflow-hidden">
          <span class="text-slate-400 dark:text-slate-500 shrink-0 font-medium">Attempted URL:</span>
          <code class="font-mono text-[11px] text-slate-800 dark:text-slate-200 truncate bg-white dark:bg-slate-900 px-2 py-0.5 rounded border border-slate-200/60 dark:border-slate-700/60">
            {{ attemptedPath }}
          </code>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="text-slate-400 dark:text-slate-500 shrink-0 font-medium">Signed in as:</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300 truncate">
            {{ authStore.user?.email || 'Authenticated User' }}
          </span>
        </div>
        <div class="flex items-center justify-between gap-2">
          <span class="text-slate-400 dark:text-slate-500 shrink-0 font-medium">Assigned Roles:</span>
          <div class="flex flex-wrap gap-1 justify-end">
            <span
              v-for="role in (authStore.user?.roles || ['User'])"
              :key="role"
              class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300"
            >
              {{ role }}
            </span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
        <router-link
          to="/app/dashboard"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-md shadow-brand-500/20 transition-all cursor-pointer"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Return to Dashboard</span>
        </router-link>

        <a
          v-if="brandingStore.orgHelpUrl"
          :href="brandingStore.orgHelpUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition cursor-pointer"
        >
          <HelpCircle class="w-4 h-4" />
          <span>Help & Support</span>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useBrandingStore } from '@/stores/branding';
import { ShieldAlert, ArrowLeft, HelpCircle } from 'lucide-vue-next';

const route = useRoute();
const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const attemptedPath = computed(() => route.query.from || '');
</script>
