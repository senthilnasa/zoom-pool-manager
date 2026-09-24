<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Platform & Institutional Settings
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Configure institutional identity, global scheduling constraints, host pool buffer rules, and AI governance
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchSettings"
          :disabled="loading"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Reload Settings"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="saveSettings"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Settings' }}</span>
        </button>
      </div>
    </div>

    <!-- Feedback Banner -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl flex items-center justify-between text-xs font-semibold transition-all"
      :class="feedbackError ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="!feedbackError" class="w-4 h-4 shrink-0 text-emerald-500" />
        <AlertCircle v-else class="w-4 h-4 shrink-0 text-rose-500" />
        <span>{{ feedback }}</span>
      </div>
      <button @click="feedback = ''" class="hover:underline font-bold">Dismiss</button>
    </div>

    <div v-if="loading && !form.org_name" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm font-semibold">Loading institutional configuration...</p>
    </div>

    <form v-else @submit.prevent="saveSettings" class="space-y-6">
      <!-- Section 1: Institutional Identity & Localization -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Building2 class="w-4 h-4 text-brand-500" />
            <span>Institutional Identity & Localization</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Branding, contact points, and authoritative primary timezone applied across calendar views and ICS invitations.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Institution / Organization Name *
            </label>
            <input
              v-model="form.org_name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Official Support Email Address *
            </label>
            <input
              v-model="form.org_support_email"
              type="email"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Institutional Website URL
            </label>
            <input
              v-model="form.org_website"
              type="url"
              placeholder="https://univ.edu"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Primary System Timezone *
            </label>
            <select
              v-model="form.org_timezone"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section 2: Host Pools & Scheduling Constraints -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Clock class="w-4 h-4 text-indigo-500" />
            <span>Host Pools & Scheduling Boundaries</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Buffers prevent consecutive Zoom host collisions and control booking lead times across all resource pools.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Buffer Time (Minutes) *
            </label>
            <input
              v-model.number="form.org_default_buffer_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Recommended: 10 minutes between sessions.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Minimum Buffer Time (Minutes) *
            </label>
            <input
              v-model.number="form.org_min_buffer_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Absolute minimum allowed buffer enforced by allocator.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Minimum Advance Notice (Hours) *
            </label>
            <input
              v-model.number="form.org_min_notice_hours"
              type="number"
              min="0"
              max="168"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Hours before start time required to book a pool license.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Max Advance Horizon (Days) *
            </label>
            <input
              v-model.number="form.org_max_advance_days"
              type="number"
              min="1"
              max="365"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">How far into the future users can schedule reservations.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Max Meeting Duration (Minutes) *
            </label>
            <input
              v-model.number="form.org_max_duration_minutes"
              type="number"
              min="15"
              max="1440"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Default 480 mins (8 hours) cap per single session.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Host Key Lead Time (Minutes) *
            </label>
            <input
              v-model.number="form.host_lead_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Minutes before meeting when host key becomes visible/claimable.</p>
          </div>
        </div>
      </div>

      <!-- Section 3: Recording & AI Companion Governance -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-emerald-500" />
            <span>Recording & AI Companion Governance Policies</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Enforce institutional privacy, compliance recording rules, and generative AI transcription boundaries.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Recording Policy *
            </label>
            <select
              v-model="form.org_default_recording_mode"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="mode in recordingModes" :key="mode.value" :value="mode.value">
                {{ mode.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              AI Companion & Smart Summary Policy *
            </label>
            <select
              v-model="form.org_ai_companion_policy"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="policy in aiPolicies" :key="policy.value" :value="policy.value">
                {{ policy.label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="authStore.isAdmin" class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          @click="fetchSettings"
          class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        >
          Reset to Saved
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 flex items-center gap-2"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Institutional Settings' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  Building2,
  Clock,
  ShieldCheck,
  RefreshCw,
  Save,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const feedback = ref('');
const feedbackError = ref(false);

const timezones = ref([]);
const recordingModes = ref([]);
const aiPolicies = ref([]);

const form = ref({
  org_name: '',
  org_support_email: '',
  org_website: '',
  org_timezone: 'Asia/Kolkata',

  org_min_buffer_minutes: 10,
  org_default_buffer_minutes: 10,
  org_min_notice_hours: 2,
  org_max_advance_days: 90,
  org_max_duration_minutes: 480,
  host_lead_minutes: 15,

  org_ai_companion_policy: 'ALLOWED',
  org_default_recording_mode: 'none',
});

const fetchSettings = async () => {
  loading.value = true;
  feedback.value = '';
  try {
    const res = await axios.get('/spa/settings/general');
    form.value = { ...res.data.settings };
    timezones.value = res.data.timezones || [];
    recordingModes.value = res.data.recording_modes || [];
    aiPolicies.value = res.data.ai_companion_policies || [];
  } catch (err) {
    console.error('Failed to load settings', err);
    feedback.value = 'Failed to load general platform settings.';
    feedbackError.value = true;
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  feedback.value = '';
  feedbackError.value = false;
  try {
    const res = await axios.put('/spa/settings/general', form.value);
    feedback.value = res.data.message || 'General settings saved successfully.';
    feedbackError.value = false;
  } catch (err) {
    console.error('Failed to save settings', err);
    feedback.value = err.response?.data?.message || 'Failed to save general settings.';
    feedbackError.value = true;
  } finally {
    saving.value = false;
  }
};

onMounted(fetchSettings);
</script>
