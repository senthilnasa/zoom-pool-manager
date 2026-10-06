<template>
  <div class="space-y-6 max-w-4xl mx-auto py-4">
    <!-- Header Banner -->
    <div class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden bg-gradient-to-br from-white via-slate-50 to-brand-50/20 dark:from-slate-900 dark:via-slate-900 dark:to-brand-950/20">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative z-10">
        <div class="flex items-start sm:items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-500 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-lg shadow-brand-500/25">
            <BookOpen class="w-7 h-7" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                Zoom Pool Manager Documentation
              </h1>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                Online GitBook
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xl leading-relaxed">
              Official deployment guides, Zoom Server-to-Server OAuth setup, webhook integration, scheduling algorithms, and REST API references.
            </p>
          </div>
        </div>

        <div class="shrink-0">
          <a
            :href="docUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold shadow-md shadow-brand-500/20 transition-all hover:scale-[1.02] cursor-pointer"
          >
            <span>Launch GitBook Documentation</span>
            <ExternalLink class="w-4 h-4" />
          </a>
        </div>
      </div>
    </div>

    <!-- Chapter Quick Launch Cards -->
    <div class="space-y-3">
      <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-1">
        Browse by Topic
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <a
          v-for="chapter in chapters"
          :key="chapter.path"
          :href="getUrl(chapter.path)"
          target="_blank"
          rel="noopener noreferrer"
          class="glass-card p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 hover:border-brand-500/40 dark:hover:border-brand-500/40 hover:shadow-md transition-all group flex items-start justify-between gap-3 bg-white dark:bg-slate-900"
        >
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 group-hover:bg-brand-500/10 group-hover:text-brand-600 dark:group-hover:text-brand-400 flex items-center justify-center shrink-0 transition-colors">
              <component :is="chapter.icon" class="w-4 h-4" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors flex items-center gap-1.5">
                <span>{{ chapter.title }}</span>
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                {{ chapter.desc }}
              </div>
            </div>
          </div>
          <ExternalLink class="w-3.5 h-3.5 text-slate-400 group-hover:text-brand-500 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0 mt-0.5" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useBrandingStore } from '@/stores/branding';
import {
  BookOpen,
  Code,
  ExternalLink,
  HelpCircle,
  Key,
  Layers,
  Radio,
  Rocket,
  Shield,
  Sliders,
  Headphones,
} from 'lucide-vue-next';

const brandingStore = useBrandingStore();
const docUrl = computed(() => brandingStore.orgHelpUrl || 'https://senthilnasa.gitbook.io/zoom-pool-manager-zpm');

const getUrl = (path) => {
  const base = docUrl.value.replace(/\/$/, '');
  return path ? `${base}/${path.replace(/^\//, '')}` : base;
};

const chapters = [
  {
    title: 'Quick Start Guide',
    desc: 'Get Zoom Pool Manager up and running with initial setup steps and architecture overview.',
    path: 'getting-started/quick-start',
    icon: Rocket,
  },
  {
    title: 'Installation & Wizard',
    desc: 'Step-by-step database provisioning, environment variables, and admin account setup.',
    path: 'installation/web-setup-wizard',
    icon: Sliders,
  },
  {
    title: 'Zoom OAuth Integration',
    desc: 'Configure Server-to-Server OAuth credentials, account scopes, and token renewal.',
    path: 'zoom/oauth-app-setup',
    icon: Key,
  },
  {
    title: 'Live Webhook Debugger',
    desc: 'Set up Zoom webhook subscriptions, CRC challenge verification, and event intake.',
    path: 'zoom/live-webhook-debugger',
    icon: Radio,
  },
  {
    title: 'Resource Pools Governance',
    desc: 'Understand pooling strategies (least-hours, round-robin, capacity) and allocation rules.',
    path: 'governance/resource-pools',
    icon: Layers,
  },
  {
    title: 'Workflows & Auto-Approval',
    desc: 'Build priority-based evaluation policies, department quotas, and emergency overrides.',
    path: 'governance/workflows-and-rules',
    icon: Shield,
  },
  {
    title: 'REST API Overview & Tokens',
    desc: 'Comprehensive OpenAPI documentation and endpoints for programmatic booking orchestration.',
    path: 'api/rest-api-overview',
    icon: Code,
  },
  {
    title: 'Zoho Desk Integration',
    desc: 'Install the marketplace extension, configure ticket reply templates, and auto-close resolved tickets.',
    path: 'integrations/zoho-desk',
    icon: Headphones,
  },
  {
    title: 'Troubleshooting & FAQ',
    desc: 'Common diagnostics, Zoom licensing sync, rate limiting, and resolution runbooks.',
    path: 'troubleshooting/faq',
    icon: HelpCircle,
  },
];
</script>
