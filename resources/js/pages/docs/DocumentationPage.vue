<template>
  <div class="space-y-4">
    <!-- Top Header & Action Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-brand-500/10 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0">
          <BookOpen class="w-5 h-5" />
        </div>
        <div>
          <h1 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Zoom Pool Manager Documentation</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
              GitBook Live Sync
            </span>
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Complete administration guides, Zoom OAuth setup, webhook configurations, and REST API references.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          @click="reloadIframe"
          class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition cursor-pointer"
          title="Reload Documentation"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isReloading }" />
        </button>

        <a
          :href="currentUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
        >
          <span>Open in New Tab</span>
          <ExternalLink class="w-3.5 h-3.5" />
        </a>
      </div>
    </div>

    <!-- Quick Navigation Shortcuts -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
      <button
        v-for="shortcut in shortcuts"
        :key="shortcut.path"
        type="button"
        @click="navigateTo(shortcut.path)"
        class="px-3 py-1.5 rounded-xl border font-medium whitespace-nowrap transition cursor-pointer shrink-0"
        :class="activePath === shortcut.path
          ? 'bg-brand-600 text-white border-brand-600 shadow-xs'
          : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750'"
      >
        {{ shortcut.title }}
      </button>
    </div>

    <!-- Embedded GitBook Frame -->
    <div class="relative w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden" style="height: calc(100vh - 13.5rem); min-height: 520px;">
      <!-- Loading Overlay -->
      <div
        v-if="isLoading"
        class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs flex flex-col items-center justify-center z-10 space-y-3"
      >
        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 flex items-center justify-center border border-brand-200 dark:border-brand-800">
          <Loader2 class="w-5 h-5 animate-spin" />
        </div>
        <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
          Connecting to GitBook documentation...
        </p>
      </div>

      <!-- Iframe -->
      <iframe
        ref="iframeRef"
        :src="currentUrl"
        class="w-full h-full border-0"
        allow="clipboard-write"
        @load="onIframeLoaded"
      ></iframe>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import {
  BookOpen,
  ExternalLink,
  RefreshCw,
  Loader2
} from 'lucide-vue-next';

const baseUrl = 'https://senthilnasa.gitbook.io/zoom-pool-manager-zpm';
const activePath = ref('');
const isLoading = ref(true);
const isReloading = ref(false);
const iframeRef = ref(null);

const shortcuts = [
  { title: 'Overview', path: '' },
  { title: 'Quick Start', path: '/getting-started/quick-start' },
  { title: 'Installation Wizard', path: '/installation/web-setup-wizard' },
  { title: 'Zoom OAuth Setup', path: '/zoom/oauth-app-setup' },
  { title: 'Live Webhook Debugger', path: '/zoom/live-webhook-debugger' },
  { title: 'Resource Pools', path: '/governance/resource-pools' },
  { title: 'Workflows & Rules', path: '/governance/workflows-and-rules' },
  { title: 'REST API Overview', path: '/api/rest-api-overview' },
  { title: 'API Endpoints', path: '/api/api-endpoints' },
  { title: 'Troubleshooting FAQ', path: '/troubleshooting/faq' },
];

const currentUrl = computed(() => {
  if (!activePath.value) {
    return baseUrl;
  }
  return `${baseUrl}${activePath.value.startsWith('/') ? '' : '/'}${activePath.value}`;
});

function navigateTo(path) {
  activePath.value = path;
  isLoading.value = true;
}

function onIframeLoaded() {
  isLoading.value = false;
  isReloading.value = false;
}

function reloadIframe() {
  isReloading.value = true;
  isLoading.value = true;
  if (iframeRef.value) {
    iframeRef.value.src = currentUrl.value;
  }
}
</script>
