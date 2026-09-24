<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Institutional Meeting Templates
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Pre-configured session archetypes (Lectures, Exams, Interviews, Webinars) with bound security profiles
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchTemplates"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.can('template.manage') || authStore.isAdmin"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Template</span>
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search templates by name, code, description..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Templates Grid -->
    <div v-if="loading && !templates.length" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm">Loading meeting templates...</p>
    </div>

    <div v-else-if="!filteredTemplates.length" class="p-12 text-center text-slate-400 glass-card rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
      <FileText class="w-10 h-10 mx-auto mb-3 opacity-40" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
        {{ searchQuery ? 'No Matching Templates' : 'No Templates Found' }}
      </p>
      <p class="text-xs text-slate-400 mt-1">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Create your first archetype template to standardize Zoom session options.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="tpl in filteredTemplates"
        :key="tpl.id"
        class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4 hover:border-brand-500/40 transition flex flex-col justify-between"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ tpl.name }}</h3>
              <code class="text-[10px] font-mono text-slate-400">{{ tpl.code }}</code>
            </div>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
              :class="tpl.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
            >
              {{ tpl.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ tpl.description || 'Standard institutional meeting template.' }}
          </p>

          <div class="space-y-1.5 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Security Profile:</span>
              <span class="font-semibold text-brand-600 dark:text-brand-400">{{ tpl.security_profile?.name || 'Default' }}</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Max Duration:</span>
              <span>{{ tpl.max_duration_minutes || 60 }} mins</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Capacity:</span>
              <span>{{ tpl.max_participants || 100 }} participants</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Recording Mode:</span>
              <span class="capitalize font-mono text-[11px]">{{ tpl.recording_mode }}</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Approval Required:</span>
              <span :class="tpl.requires_approval ? 'text-amber-600 font-bold' : 'text-slate-500'">{{ tpl.requires_approval ? 'Yes' : 'Auto' }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button
            v-if="authStore.can('template.manage') || authStore.isAdmin"
            @click="editTemplate(tpl)"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Edit Template
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ editingItem ? 'Edit Meeting Template' : 'New Meeting Template' }}</h2>
          <button @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveTemplate" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Template Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Template Code *</label>
            <input
              v-model="form.code"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Security Profile *</label>
            <select
              v-model="form.security_profile_id"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="sp in profiles" :key="sp.id" :value="sp.id">{{ sp.name }} ({{ sp.code }})</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Duration (m)</label>
              <input
                v-model.number="form.default_duration_minutes"
                type="number"
                min="15"
                max="480"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Duration (m)</label>
              <input
                v-model.number="form.max_duration_minutes"
                type="number"
                min="15"
                max="480"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Participants</label>
              <input
                v-model.number="form.max_participants"
                type="number"
                min="2"
                max="1000"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Recording Mode</label>
              <select
                v-model="form.recording_mode"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              >
                <option value="none">No Recording</option>
                <option value="cloud">Cloud Recording</option>
                <option value="local">Host Local Only</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-4 pt-2">
            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.requires_approval"
                class="rounded text-brand-600 focus:ring-brand-500"
              />
              <span>Requires Sign-off Approval</span>
            </label>

            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.is_active"
                class="rounded text-brand-600 focus:ring-brand-500"
              />
              <span>Template Active</span>
            </label>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="2"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="modalOpen = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Template' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  FileText,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const templates = ref([]);
const profiles = ref([]);
const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const searchQuery = ref('');

const filteredTemplates = computed(() => {
  if (!searchQuery.value.trim()) return templates.value;
  const q = searchQuery.value.toLowerCase().trim();
  return templates.value.filter((t) => {
    return (
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.code && t.code.toLowerCase().includes(q)) ||
      (t.description && t.description.toLowerCase().includes(q))
    );
  });
});

const modalOpen = ref(false);
const editingItem = ref(null);
const form = ref({
  id: null,
  name: '',
  code: '',
  description: '',
  security_profile_id: 1,
  default_duration_minutes: 60,
  max_duration_minutes: 180,
  max_participants: 100,
  requires_approval: false,
  recording_mode: 'cloud',
  ai_companion_policy: 'ALLOWED',
  series_mode: 'SINGLE_RESOURCE',
  is_active: true,
});

const fetchTemplates = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/spa/templates');
    templates.value = res.data?.templates || [];
    profiles.value = res.data?.profiles || [];
  } catch (err) {
    console.error('Failed to load templates', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingItem.value = null;
  form.value = {
    id: null,
    name: '',
    code: '',
    description: '',
    security_profile_id: profiles.value[0]?.id || 1,
    default_duration_minutes: 60,
    max_duration_minutes: 180,
    max_participants: 100,
    requires_approval: false,
    recording_mode: 'cloud',
    ai_companion_policy: 'ALLOWED',
    series_mode: 'SINGLE_RESOURCE',
    is_active: true,
  };
  modalOpen = true;
};

const editTemplate = (tpl) => {
  editingItem.value = tpl;
  form.value = {
    id: tpl.id,
    name: tpl.name,
    code: tpl.code,
    description: tpl.description || '',
    security_profile_id: tpl.security_profile_id,
    default_duration_minutes: tpl.default_duration_minutes || 60,
    max_duration_minutes: tpl.max_duration_minutes || 180,
    max_participants: tpl.max_participants || 100,
    requires_approval: !!tpl.requires_approval,
    recording_mode: tpl.recording_mode || 'cloud',
    ai_companion_policy: tpl.ai_companion_policy || 'ALLOWED',
    series_mode: tpl.series_mode || 'SINGLE_RESOURCE',
    is_active: !!tpl.is_active,
  };
  modalOpen = true;
};

const saveTemplate = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/templates', form.value);
    feedback.value = `Template "${form.value.name}" saved successfully.`;
    modalOpen = false;
    await fetchTemplates();
  } catch (err) {
    console.error('Failed to save template', err);
  } finally {
    saving.value = false;
  }
};

onMounted(fetchTemplates);
</script>
