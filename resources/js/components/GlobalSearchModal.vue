<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-start justify-center pt-14 sm:pt-20 px-4 bg-slate-950/70 backdrop-blur-md transition-all animate-in fade-in duration-150"
    @click.self="close"
  >
    <div
      class="w-full max-w-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-2xl shadow-slate-950/50 overflow-hidden flex flex-col max-h-[84vh] transition-all transform animate-in zoom-in-95 duration-150"
      @keydown.esc="close"
      @keydown.down.prevent="navigateDown"
      @keydown.up.prevent="navigateUp"
      @keydown.enter.prevent="handleEnter"
    >
      <!-- Search Input Header -->
      <form
        autocomplete="off"
        @submit.prevent="handleEnter"
        class="p-4 sm:p-4.5 border-b border-slate-200/80 dark:border-slate-800 flex items-center gap-3 bg-slate-50/70 dark:bg-slate-900/80 relative"
      >
        <!-- Search / Loading Icon -->
        <div class="relative flex items-center justify-center w-6 h-6 shrink-0">
          <RefreshCw
            v-if="loading"
            class="w-5 h-5 text-brand-500 animate-spin"
          />
          <Search
            v-else
            class="w-5 h-5 text-slate-400 dark:text-slate-500"
          />
        </div>

        <!-- Input Field with Anti-Autofill Attributes -->
        <input
          ref="searchInputRef"
          v-model="query"
          type="search"
          name="zpm_global_search_q"
          id="zpm-global-search-input"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          data-lpignore="true"
          data-1p-ignore="true"
          data-form-type="other"
          placeholder="Search modules, users, meetings, pools... (e.g. 'Senthil', 'Settings')"
          class="flex-1 bg-transparent border-0 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-0 focus:outline-none text-sm sm:text-base font-medium"
          @input="onInput"
        />

        <!-- Action Controls in Input -->
        <div class="flex items-center gap-2 shrink-0">
          <!-- Clear Button -->
          <button
            v-if="query"
            type="button"
            @click="clearSearch"
            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800 transition-colors"
            title="Clear search"
          >
            <X class="w-4 h-4" />
          </button>

          <!-- Search Now Button -->
          <button
            v-if="query.trim().length >= 2"
            type="button"
            @click="executeSearchImmediately"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-brand-600 hover:bg-brand-700 text-white shadow-sm transition-all cursor-pointer"
          >
            <span>Search</span>
            <kbd class="text-[10px] font-mono opacity-80">↵</kbd>
          </button>

          <!-- ESC Badge -->
          <kbd class="hidden sm:inline-flex items-center px-2 py-1 text-[11px] font-mono font-medium text-slate-500 dark:text-slate-400 bg-slate-200/60 dark:bg-slate-800 border border-slate-300/60 dark:border-slate-700 rounded-lg">
            ESC
          </kbd>
        </div>
      </form>

      <!-- Debounce Notice when user is typing -->
      <div
        v-if="isDebouncing && query.trim().length >= 2"
        class="px-4 py-1.5 bg-brand-500/10 border-b border-brand-500/20 text-[11px] text-brand-700 dark:text-brand-300 flex items-center justify-between animate-in fade-in duration-100"
      >
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
          Press <strong>Enter ↵</strong> to search instantly, or wait a moment...
        </span>
        <button
          type="button"
          @click="executeSearchImmediately"
          class="font-semibold underline hover:no-underline cursor-pointer"
        >
          Search now
        </button>
      </div>

      <!-- Quick Shortcuts / Empty State -->
      <div v-if="!query.trim()" class="p-6 overflow-y-auto space-y-4">
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-2">
          <span>Suggested Discoveries</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
          <button
            v-for="sug in suggestions"
            :key="sug.path"
            type="button"
            @click="navigate(sug.path)"
            class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-800/30 hover:bg-brand-500/10 hover:border-brand-500/25 text-left text-slate-700 dark:text-slate-300 transition-all group cursor-pointer"
          >
            <div class="w-8 h-8 rounded-lg bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform border border-brand-200/50 dark:border-brand-800/50">
              <component :is="sug.icon" class="w-4 h-4" />
            </div>
            <div class="truncate">
              <div class="font-semibold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">{{ sug.title }}</div>
              <div class="text-[11px] text-slate-400 truncate mt-0.5">{{ sug.desc }}</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Loading State (when no previous results) -->
      <div v-else-if="loading && totalCount === 0" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500 opacity-80" />
        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Searching resources for "{{ query }}"...</p>
        <p class="text-xs text-slate-400 mt-1">Checking users, meetings, resource pools, and system modules</p>
      </div>

      <!-- No Results Found -->
      <div v-else-if="!loading && !isDebouncing && totalCount === 0" class="p-10 text-center text-slate-400">
        <AlertCircle class="w-9 h-9 mx-auto mb-2.5 text-slate-300 dark:text-slate-600" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No results found for "{{ query }}"</p>
        <p class="text-xs text-slate-400 mt-1">Try searching by user email, meeting title, public ID, or department.</p>
      </div>

      <!-- Search Results List -->
      <div v-else class="p-3.5 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
        <!-- 1. Application Modules & Features -->
        <div v-if="results.modules && results.modules.length">
          <div class="px-2 mb-2 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <LayoutGrid class="w-3.5 h-3.5 text-brand-500" />
              <span>Modules & Navigation</span>
            </span>
            <span class="px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold">{{ results.modules.length }}</span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="item in results.modules"
              :key="item.path"
              :class="isActiveIndex(item.flatIndex) ? 'bg-brand-500/10 border-brand-500/30 dark:bg-brand-500/15' : 'hover:bg-slate-100/80 dark:hover:bg-slate-800/60 border-transparent'"
              class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group"
              @click="navigate(item.path)"
              @mouseenter="setActiveIndex(item.flatIndex)"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 flex items-center justify-center shrink-0 border border-brand-200/50 dark:border-brand-800/50">
                  <LayoutGrid class="w-4 h-4" />
                </div>
                <div class="truncate">
                  <div class="text-xs font-bold text-slate-900 dark:text-white" v-html="highlight(item.title)"></div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5" v-html="highlight(item.description)"></div>
                </div>
              </div>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 shrink-0 ml-3 border border-slate-200/60 dark:border-slate-700/60">
                {{ item.category }}
              </span>
            </div>
          </div>
        </div>

        <!-- 2. Users & Directory -->
        <div v-if="results.users && results.users.length">
          <div class="px-2 mb-2 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <Users class="w-3.5 h-3.5 text-sky-500" />
              <span>Users & Institutional Accounts</span>
            </span>
            <span class="px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold">{{ results.users.length }}</span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="item in results.users"
              :key="item.id"
              :class="isActiveIndex(item.flatIndex) ? 'bg-brand-500/10 border-brand-500/30 dark:bg-brand-500/15' : 'hover:bg-slate-100/80 dark:hover:bg-slate-800/60 border-transparent'"
              class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group"
              @click="navigate(item.path)"
              @mouseenter="setActiveIndex(item.flatIndex)"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-sky-400 to-brand-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
                  {{ (item.name || 'U').charAt(0).toUpperCase() }}
                </div>
                <div class="truncate">
                  <div class="text-xs font-bold text-slate-900 dark:text-white" v-html="highlight(item.name)"></div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5" v-html="highlight(item.email)"></div>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0 ml-3">
                <span
                  v-if="item.department && item.department !== 'No Department'"
                  class="text-[10px] text-slate-600 dark:text-slate-300 font-medium px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 hidden sm:inline"
                >
                  {{ item.department }}
                </span>
                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 border border-brand-200/50 dark:border-brand-800/50">
                  {{ item.role }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Meetings & Schedules -->
        <div v-if="results.meetings && results.meetings.length">
          <div class="px-2 mb-2 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <Video class="w-3.5 h-3.5 text-indigo-500" />
              <span>Meetings & Scheduled Sessions</span>
            </span>
            <span class="px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold">{{ results.meetings.length }}</span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="item in results.meetings"
              :key="item.id"
              :class="isActiveIndex(item.flatIndex) ? 'bg-brand-500/10 border-brand-500/30 dark:bg-brand-500/15' : 'hover:bg-slate-100/80 dark:hover:bg-slate-800/60 border-transparent'"
              class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group"
              @click="navigate(item.path)"
              @mouseenter="setActiveIndex(item.flatIndex)"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                  <Video class="w-4 h-4" />
                </div>
                <div class="truncate">
                  <div class="text-xs font-bold text-slate-900 dark:text-white" v-html="highlight(item.title)"></div>
                  <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 mt-0.5">ID: {{ item.public_id }}</div>
                </div>
              </div>
              <span
                :class="getStatusBadgeClass(item.status)"
                class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shrink-0 ml-3"
              >
                {{ item.status }}
              </span>
            </div>
          </div>
        </div>

        <!-- 4. Resource Pools -->
        <div v-if="results.pools && results.pools.length">
          <div class="px-2 mb-2 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <Layers class="w-3.5 h-3.5 text-emerald-500" />
              <span>Zoom Resource Pools</span>
            </span>
            <span class="px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold">{{ results.pools.length }}</span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="item in results.pools"
              :key="item.id"
              :class="isActiveIndex(item.flatIndex) ? 'bg-brand-500/10 border-brand-500/30 dark:bg-brand-500/15' : 'hover:bg-slate-100/80 dark:hover:bg-slate-800/60 border-transparent'"
              class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer group"
              @click="navigate(item.path)"
              @mouseenter="setActiveIndex(item.flatIndex)"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                  <Layers class="w-4 h-4" />
                </div>
                <div class="truncate">
                  <div class="text-xs font-bold text-slate-900 dark:text-white" v-html="highlight(item.name)"></div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">Strategy: {{ item.strategy || 'least_hours_today' }}</div>
                </div>
              </div>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                Resource Pool
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Guide -->
      <div class="px-4 py-3 bg-slate-50/90 dark:bg-slate-900/90 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <div class="flex items-center gap-4">
          <span class="inline-flex items-center gap-1.5">
            <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-mono shadow-xs">↑↓</kbd>
            <span>Navigate</span>
          </span>
          <span class="inline-flex items-center gap-1.5">
            <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-mono shadow-xs">↵</kbd>
            <span>Select / Search</span>
          </span>
          <span class="inline-flex items-center gap-1.5">
            <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-mono shadow-xs">esc</kbd>
            <span>Dismiss</span>
          </span>
        </div>
        <div class="text-[11px] text-slate-400">
          Global Search &bull; Zoom Pool Manager
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import {
  Search,
  LayoutGrid,
  Users,
  Video,
  Layers,
  FolderSync,
  Clock,
  ShieldCheck,
  RefreshCw,
  AlertCircle,
  X,
} from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const router = useRouter();
const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const query = ref('');
const loading = ref(false);
const isDebouncing = ref(false);
const searchInputRef = ref(null);
const activeIndex = ref(0);
let abortController = null;
let lastExecutedQuery = '';

const results = ref({
  modules: [],
  users: [],
  meetings: [],
  pools: [],
});

const suggestions = [
  { title: 'User Management', desc: 'Browse staff accounts and assign roles', path: '/app/users', icon: Users },
  { title: 'Role & Permissions Matrix', desc: 'Manage core and custom permissions', path: '/app/users?tab=roles', icon: ShieldCheck },
  { title: 'Directory & AD Sync', desc: 'Microsoft Entra, Google Workspace & LDAP', path: '/app/settings/directory-sync', icon: FolderSync },
  { title: 'Scheduled Jobs & Cron', desc: 'Background tasks cadence & execution', path: '/app/settings/jobs', icon: Clock },
];

let debounceTimer = null;
const DEBOUNCE_DELAY_MS = 800; // 800ms debounce while typing (or instant on Enter)

const flatResultsList = computed(() => {
  const list = [];
  let index = 0;
  if (results.value.modules) {
    results.value.modules.forEach((item) => {
      item.flatIndex = index++;
      list.push(item);
    });
  }
  if (results.value.users) {
    results.value.users.forEach((item) => {
      item.flatIndex = index++;
      list.push(item);
    });
  }
  if (results.value.meetings) {
    results.value.meetings.forEach((item) => {
      item.flatIndex = index++;
      list.push(item);
    });
  }
  if (results.value.pools) {
    results.value.pools.forEach((item) => {
      item.flatIndex = index++;
      list.push(item);
    });
  }
  return list;
});

const totalCount = computed(() => flatResultsList.value.length);

function isActiveIndex(index) {
  return activeIndex.value === index;
}

function setActiveIndex(index) {
  activeIndex.value = index;
}

function navigateDown() {
  if (totalCount.value === 0) return;
  activeIndex.value = (activeIndex.value + 1) % totalCount.value;
}

function navigateUp() {
  if (totalCount.value === 0) return;
  activeIndex.value = (activeIndex.value - 1 + totalCount.value) % totalCount.value;
}

function handleEnter() {
  const trimmed = query.value.trim();

  // If debounce is still pending or query has changed since last search, execute immediately!
  if (isDebouncing.value || (trimmed.length >= 2 && trimmed !== lastExecutedQuery)) {
    executeSearchImmediately();
    return;
  }

  // Otherwise, if results are already loaded, select the active item
  if (totalCount.value > 0) {
    selectActive();
  } else if (trimmed.length >= 2) {
    executeSearchImmediately();
  }
}

function selectActive() {
  if (totalCount.value === 0) return;
  const item = flatResultsList.value[activeIndex.value];
  if (item && item.path) {
    navigate(item.path);
  }
}

function navigate(path) {
  close();
  router.push(path);
}

function close() {
  clearTimeout(debounceTimer);
  isDebouncing.value = false;
  isOpen.value = false;
  query.value = '';
  results.value = { modules: [], users: [], meetings: [], pools: [] };
}

function clearSearch() {
  clearTimeout(debounceTimer);
  isDebouncing.value = false;
  query.value = '';
  results.value = { modules: [], users: [], meetings: [], pools: [] };
  loading.value = false;
  nextTick(() => {
    searchInputRef.value?.focus();
  });
}

function onInput() {
  clearTimeout(debounceTimer);
  activeIndex.value = 0;

  const trimmed = query.value.trim();
  if (!trimmed) {
    results.value = { modules: [], users: [], meetings: [], pools: [] };
    loading.value = false;
    isDebouncing.value = false;
    return;
  }

  if (trimmed.length < 2) {
    isDebouncing.value = false;
    loading.value = false;
    return;
  }

  isDebouncing.value = true;
  loading.value = true;
  debounceTimer = setTimeout(() => {
    isDebouncing.value = false;
    performSearch();
  }, DEBOUNCE_DELAY_MS);
}

function executeSearchImmediately() {
  clearTimeout(debounceTimer);
  isDebouncing.value = false;
  performSearch();
}

async function performSearch() {
  const searchTarget = query.value.trim();
  if (!searchTarget || searchTarget.length < 2) {
    results.value = { modules: [], users: [], meetings: [], pools: [] };
    loading.value = false;
    return;
  }

  // Cancel prior in-flight search request
  if (abortController) {
    abortController.abort();
  }
  abortController = new AbortController();

  loading.value = true;
  lastExecutedQuery = searchTarget;

  try {
    const res = await axios.get('/spa/search', {
      params: { q: searchTarget },
      signal: abortController.signal,
    });
    results.value = res.data.results || { modules: [], users: [], meetings: [], pools: [] };
    activeIndex.value = 0;
  } catch (err) {
    if (axios.isCancel(err) || err.name === 'CanceledError' || err.name === 'AbortError') {
      return; // Ignore canceled requests
    }
    console.error('Global search error', err);
  } finally {
    loading.value = false;
    isDebouncing.value = false;
  }
}

function highlight(text) {
  if (!text) return '';
  const q = query.value.trim();
  if (!q) return escapeHtml(text);
  const regex = new RegExp(`(${escapeRegex(q)})`, 'gi');
  return escapeHtml(text).replace(
    regex,
    '<mark class="bg-brand-500/15 text-brand-700 dark:text-brand-300 font-semibold px-1 py-0.5 rounded">$1</mark>'
  );
}

function escapeHtml(string) {
  const entityMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  };
  return String(string).replace(/[&<>"']/g, (s) => entityMap[s]);
}

function escapeRegex(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function getStatusBadgeClass(status) {
  const s = String(status || '').toLowerCase();
  if (s === 'scheduled') {
    return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60';
  }
  if (s === 'started' || s === 'in_progress') {
    return 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60';
  }
  if (s === 'completed') {
    return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700';
  }
  if (s === 'cancelled') {
    return 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60';
  }
  return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
}

watch(isOpen, (newVal) => {
  if (newVal) {
    nextTick(() => {
      searchInputRef.value?.focus();
    });
  }
});

function handleGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    isOpen.value = !isOpen.value;
  } else if (e.key === '/' && !isOpen.value && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
    e.preventDefault();
    isOpen.value = true;
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  if (abortController) {
    abortController.abort();
  }
});
</script>
