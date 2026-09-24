<template>
  <div class="max-w-4xl mx-auto space-y-6 py-4">
    <!-- Header Card -->
    <div class="glass-card p-6 sm:p-8 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div v-if="docData.org_logo_url" class="w-12 h-12 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 p-1 flex items-center justify-center shrink-0 shadow-sm overflow-hidden">
          <img :src="docData.org_logo_url" :alt="docData.org_name" class="w-full h-full object-contain" />
        </div>
        <div v-else class="w-12 h-12 rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 flex items-center justify-center text-white font-black text-xl shrink-0 shadow-md shadow-brand-500/20">
          {{ docData.org_name ? docData.org_name.charAt(0).toUpperCase() : 'Z' }}
        </div>

        <div>
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400 bg-brand-500/10 px-2 py-0.5 rounded-md">
              Legal & Compliance
            </span>
            <span class="text-xs text-slate-400">•</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ docData.org_name }}</span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1">
            {{ docData.title }}
          </h1>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="printDocument"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Print Document"
        >
          <Printer class="w-4 h-4" />
          <span>Print</span>
        </button>

        <router-link
          to="/app/dashboard"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 shadow-sm transition"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Dashboard</span>
        </router-link>
      </div>
    </div>

    <!-- Meta Details Bar -->
    <div class="glass-card px-6 py-3 rounded-xl border border-slate-200/60 dark:border-slate-800/60 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
      <div class="flex items-center gap-3">
        <span v-if="docData.updated_at">
          <strong>Last Updated:</strong> {{ formatDate(docData.updated_at) }}
        </span>
        <span v-if="docData.org_support_email">
          <strong>Contact:</strong>
          <a :href="'mailto:' + docData.org_support_email" class="text-brand-600 dark:text-brand-400 underline ml-1">
            {{ docData.org_support_email }}
          </a>
        </span>
      </div>

      <div v-if="docData.type === 'url' && docData.url" class="flex items-center gap-2">
        <span class="text-amber-600 dark:text-amber-400">Configured as External Resource:</span>
        <a
          :href="docData.url"
          target="_blank"
          rel="noopener noreferrer"
          class="font-bold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1"
        >
          <span>Open External Page</span>
          <ExternalLink class="w-3.5 h-3.5" />
        </a>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="glass-card p-12 text-center text-slate-400 rounded-2xl">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm font-semibold">Loading document content...</p>
    </div>

    <!-- Custom Content Body -->
    <div
      v-else-if="docData.content"
      class="glass-card p-6 sm:p-10 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 text-slate-800 dark:text-slate-200"
    >
      <div class="custom-prose text-sm leading-relaxed" v-html="docData.content"></div>
    </div>

    <!-- External URL card if type is url and no inline content -->
    <div
      v-else-if="docData.type === 'url' && docData.url"
      class="glass-card p-10 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 text-center space-y-4"
    >
      <Globe class="w-12 h-12 mx-auto text-brand-500 opacity-80" />
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">External Institutional Document</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
        The official {{ docData.title }} is hosted externally by {{ docData.org_name }}. Click the link below to access the authoritative document.
      </p>
      <div>
        <a
          :href="docData.url"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition"
        >
          <span>Visit Official {{ docData.title }}</span>
          <ExternalLink class="w-4 h-4" />
        </a>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="glass-card p-12 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 text-center text-slate-500 dark:text-slate-400 space-y-3"
    >
      <FileText class="w-12 h-12 mx-auto text-slate-400 opacity-60" />
      <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">
        No Custom {{ docData.title }} Published
      </h3>
      <p class="text-xs max-w-md mx-auto">
        An administrator has not yet configured or published custom content for this document. For official institutional policies, please reach out to
        <a :href="'mailto:' + docData.org_support_email" class="text-brand-600 dark:text-brand-400 underline">{{ docData.org_support_email }}</a>.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import {
  Printer,
  ArrowLeft,
  ExternalLink,
  Globe,
  FileText,
  RefreshCw,
} from 'lucide-vue-next';

const route = useRoute();
const loading = ref(true);

const documentType = computed(() => {
  return route.path.includes('privacy') ? 'privacy' : 'terms';
});

const docData = ref({
  title: documentType.value === 'privacy' ? 'Privacy Policy' : 'Terms of Service',
  type: 'none',
  url: '',
  content: '',
  updated_at: '',
  org_name: 'Zoom Pool Manager',
  org_logo_url: '',
  org_support_email: 'support@zoompoolmanager.org',
});

const fetchDocument = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/spa/legal/${documentType.value}`);
    docData.value = { ...res.data };
  } catch (err) {
    console.error('Failed to load legal document', err);
  } finally {
    loading.value = false;
  }
};

const formatDate = (isoString) => {
  if (!isoString) return '';
  try {
    const d = new Date(isoString);
    return d.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  } catch (e) {
    return isoString;
  }
};

const printDocument = () => {
  window.print();
};

watch(
  () => route.path,
  () => {
    fetchDocument();
  }
);

onMounted(fetchDocument);
</script>

<style>
.custom-prose h1 {
  font-size: 1.75rem;
  font-weight: 800;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}
.custom-prose h2 {
  font-size: 1.35rem;
  font-weight: 700;
  margin-top: 1.25rem;
  margin-bottom: 0.5rem;
}
.custom-prose h3 {
  font-size: 1.15rem;
  font-weight: 600;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}
.custom-prose p {
  margin-bottom: 1rem;
  line-height: 1.7;
}
.custom-prose ul {
  list-style-type: disc;
  margin-left: 1.5rem;
  margin-bottom: 1rem;
}
.custom-prose ol {
  list-style-type: decimal;
  margin-left: 1.5rem;
  margin-bottom: 1rem;
}
.custom-prose li {
  margin-bottom: 0.25rem;
}
.custom-prose blockquote {
  border-left: 4px solid #0ea5e9;
  padding-left: 1rem;
  font-style: italic;
  margin: 1rem 0;
  opacity: 0.85;
}
.custom-prose a {
  color: #0284c7;
  text-decoration: underline;
}
.dark .custom-prose a {
  color: #38bdf8;
}
.custom-prose hr {
  border: 0;
  border-top: 1px solid rgba(148, 163, 184, 0.3);
  margin: 2rem 0;
}
</style>
