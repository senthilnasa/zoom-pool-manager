<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <Calendar class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Google Workspace Add-on (Calendar & Gmail)
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Enable native Google Calendar video conferencing and 1-click Zoom meeting link insertion in Gmail Compose backed by your pooled licenses.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="showGuide = !showGuide"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all border border-slate-200/80 dark:border-slate-700/80 cursor-pointer"
        >
          <HelpCircle class="w-4 h-4 text-brand-500" />
          <span>{{ showGuide ? 'Hide Guide' : 'Setup Guide' }}</span>
        </button>

        <button
          type="button"
          @click="downloadAddonPackage"
          :disabled="downloading"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 text-xs font-semibold transition-all border border-emerald-200/80 dark:border-emerald-800/80 cursor-pointer"
          title="Download ready-to-deploy Google Apps Script / Add-on package (.zip)"
        >
          <Download class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
          <span>{{ downloading ? 'Generating ZIP...' : 'Download Add-on Package (.zip)' }}</span>
        </button>

        <button
          type="button"
          @click="testConnection"
          :disabled="testing"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-md shadow-brand-500/20 transition-all cursor-pointer disabled:opacity-50"
        >
          <Activity class="w-4 h-4" :class="{ 'animate-spin': testing }" />
          <span>{{ testing ? 'Testing...' : 'Test Connection' }}</span>
        </button>
      </div>
    </div>

    <!-- Test Connection Results Alert Box -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div
        v-if="testResult"
        class="p-4 rounded-2xl border backdrop-blur-xl flex items-start gap-3 text-sm"
        :class="testResult.success ? 'bg-emerald-50/80 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200' : 'bg-rose-50/80 dark:bg-rose-950/60 border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200'"
      >
        <CheckCircle2 v-if="testResult.success" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
        <AlertCircle v-else class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />

        <div class="flex-1 space-y-1">
          <div class="font-bold flex items-center justify-between">
            <span>{{ testResult.success ? 'Google Workspace Integration Verified' : 'Handshake Failed' }}</span>
            <button
              type="button"
              @click="testResult = null"
              class="text-xs opacity-60 hover:opacity-100 cursor-pointer"
            >
              ✕
            </button>
          </div>
          <p class="text-xs leading-relaxed opacity-90">{{ testResult.message }}</p>
        </div>
      </div>
    </transition>

    <!-- Setup Guide Box -->
    <div
      v-if="showGuide"
      class="p-5 rounded-2xl bg-gradient-to-br from-brand-50/80 to-blue-50/50 dark:from-brand-950/30 dark:to-slate-900 border border-brand-200/80 dark:border-brand-800/60 text-xs space-y-4"
    >
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-sm text-brand-900 dark:text-brand-200 flex items-center gap-2">
          <BookOpen class="w-4 h-4 text-brand-600" />
          <span>Google Workspace Add-on Deployment & Architecture Guide</span>
        </h3>
        <button
          type="button"
          @click="showGuide = false"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
        >
          ✕
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">1</span>
            Download Add-on Package
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Click <strong>Download Add-on Package (.zip)</strong> above. The ZIP contains <code>appsscript.json</code> and <code>Code.gs</code> pre-filled with your server endpoint and API credentials.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">2</span>
            Create Apps Script Project
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Open <a href="https://script.google.com" target="_blank" class="text-brand-600 underline font-semibold">script.google.com</a>, create a project, enable <em>"Show appsscript.json manifest"</em> in Project Settings, and paste the code.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">3</span>
            Google Calendar "Add conferencing"
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            When users create a calendar event and click <strong>Add video conferencing</strong>, they choose <strong>Zoom Meeting (Zoom Pool Manager)</strong>. ZPM assigns a licensed host and syncs the join link & passcode.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">4</span>
            Gmail Compose 1-Click Insert
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            In Gmail compose toolbar, clicking the Zoom icon presents a 1-click generator to inject full meeting join details, passcode, and host key into the email draft.
          </p>
        </div>
      </div>
    </div>

    <!-- Main Configuration Form -->
    <form @submit.prevent="saveConfiguration" class="space-y-6">
      <!-- Section 1: Server URL & API Token -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <Key class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">ZPM Endpoint & Add-on API Token</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Authentication credentials used by Google Apps Script to communicate with Zoom Pool Manager.
              </p>
            </div>
          </div>

          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.enabled" class="sr-only peer">
            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
            <span class="ml-3 text-xs font-semibold text-slate-700 dark:text-slate-300">
              {{ form.enabled ? 'Enabled' : 'Disabled' }}
            </span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Public ZPM Server URL <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input
                type="url"
                v-model="form.server_url"
                required
                placeholder="https://zpm.yourdomain.com"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 font-mono"
              />
              <button
                type="button"
                @click="copyText(form.server_url, 'Server URL copied!')"
                class="absolute right-2 top-2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                title="Copy URL"
              >
                <Copy class="w-3.5 h-3.5" />
              </button>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
              Must be reachable via HTTPS by Google Apps Script cloud servers.
            </p>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Google Workspace API Token <span class="text-rose-500">*</span>
              </label>
              <button
                type="button"
                @click="confirmRegenerateToken"
                :disabled="regeneratingToken"
                class="text-[11px] text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 cursor-pointer font-medium"
              >
                <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': regeneratingToken }" />
                <span>{{ regeneratingToken ? 'Regenerating...' : 'Regenerate Token' }}</span>
              </button>
            </div>
            <div class="relative">
              <input
                type="text"
                v-model="form.api_token"
                readonly
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 px-3 py-2.5 text-slate-700 dark:text-slate-300 focus:outline-none font-mono select-all"
              />
              <button
                type="button"
                @click="copyText(form.api_token, 'API Token copied!')"
                class="absolute right-2 top-2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                title="Copy Token"
              >
                <Copy class="w-3.5 h-3.5" />
              </button>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
              Bearer authentication token embedded in the Google Apps Script package.
            </p>
          </div>
        </div>

        <!-- Allowed Domains Whitelist -->
        <div class="pt-2">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center gap-1.5">
            <ShieldCheck class="w-4 h-4 text-emerald-500" />
            Allowed Google Account Domains (Whitelist Protection)
          </label>
          <input
            type="text"
            v-model="form.allowed_domains"
            placeholder="e.g. yourcompany.com, university.edu (comma-separated, leave blank for all)"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 font-mono"
          />
          <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
            Restricts Add-on scheduling strictly to your authorized institutional Google accounts. Unauthorized domains are blocked.
          </p>
        </div>
      </GlassCard>

      <!-- Section 2: Defaults & Policy -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center gap-2.5 border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <Sliders class="w-5 h-5 text-brand-600 dark:text-brand-400" />
          <div>
            <h3 class="font-bold text-slate-900 dark:text-white text-base">Booking & Resource Allocation Defaults</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Configure default resource pools, durations, and host security rules when scheduling from Calendar or Gmail.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Resource Pool
            </label>
            <select
              v-model="form.default_pool_id"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              <option :value="null">Automatic (Cluster Smart Allocation)</option>
              <option v-for="p in pools" :key="p.id" :value="p.id">
                {{ p.name }} (Cap: {{ p.capacity }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Meeting Template
            </label>
            <select
              v-model="form.default_template_id"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              <option :value="null">Default Institutional Profile</option>
              <option v-for="t in templates" :key="t.id" :value="t.id">
                {{ t.name }} ({{ t.duration_minutes }}m)
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Duration (Minutes)
            </label>
            <input
              type="number"
              v-model.number="form.default_duration_minutes"
              min="15"
              max="480"
              step="15"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            />
          </div>
        </div>

        <div class="pt-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              v-model="form.share_host_key"
              class="rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-700"
            />
            <span class="text-xs font-medium text-slate-800 dark:text-slate-200">
              Include Host Key PIN in calendar notes and Gmail insert (Allows calendar owner to claim host in Zoom)
            </span>
          </label>
        </div>
      </GlassCard>

      <!-- Section 3: Gmail Insert Template -->
      <GlassCard class="p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <Mail class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">Gmail Draft HTML Insertion Template</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                HTML card markup inserted into the compose window when user clicks "Generate & Insert Link" in Gmail.
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="resetEmailTemplate"
            class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 underline cursor-pointer"
          >
            Reset to Default
          </button>
        </div>

        <div class="space-y-2">
          <div class="flex flex-wrap gap-1.5 text-[11px]">
            <span class="text-slate-400 py-1">Insert Tags:</span>
            <button
              v-for="tag in availableTags"
              :key="tag"
              type="button"
              @click="insertTag(tag)"
              class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-mono cursor-pointer border border-slate-200/60 dark:border-slate-700/60"
            >
              {{ tag }}
            </button>
          </div>

          <textarea
            ref="emailTemplateTextarea"
            v-model="form.email_template"
            rows="7"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 font-mono leading-relaxed"
          ></textarea>
        </div>
      </GlassCard>

      <!-- Save Actions -->
      <div class="flex items-center justify-end gap-3 pt-2">
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-lg shadow-brand-500/20 transition-all cursor-pointer disabled:opacity-50"
        >
          <Save class="w-4 h-4" :class="{ 'animate-spin': saving }" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Configuration' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import GlassCard from '@/components/GlassCard.vue';
import {
  Calendar,
  Key,
  Sliders,
  Mail,
  Copy,
  Download,
  Activity,
  HelpCircle,
  BookOpen,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  ShieldCheck,
  Save,
} from 'lucide-vue-next';

const toast = useToastStore();

const showGuide = ref(false);
const saving = ref(false);
const testing = ref(false);
const downloading = ref(false);
const regeneratingToken = ref(false);
const testResult = ref(null);
const emailTemplateTextarea = ref(null);

const pools = ref([]);
const templates = ref([]);

const form = ref({
  enabled: true,
  server_url: '',
  api_token: '',
  allowed_domains: '',
  default_pool_id: null,
  default_template_id: null,
  default_duration_minutes: 60,
  share_host_key: true,
  email_template: '',
});

const availableTags = [
  '{meeting_title}',
  '{starts_at}',
  '{ends_at}',
  '{start_time}',
  '{end_time}',
  '{timezone}',
  '{duration_minutes}',
  '{join_url}',
  '{meeting_id}',
  '{passcode}',
  '{host_key}',
  '{host_key_section}',
];

const loadConfig = async () => {
  try {
    const res = await axios.get('/spa/settings/google-workspace');
    if (res.data.config) {
      form.value = { ...form.value, ...res.data.config };
    }
    pools.value = res.data.pools || [];
    templates.value = res.data.templates || [];
  } catch (e) {
    toast.error('Failed to load Google Workspace settings.');
  }
};

const saveConfiguration = async () => {
  try {
    saving.value = true;
    const res = await axios.put('/spa/settings/google-workspace', form.value);
    if (res.data.config) {
      form.value = { ...form.value, ...res.data.config };
    }
    toast.success(res.data.message || 'Settings saved successfully!');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to save settings.');
  } finally {
    saving.value = false;
  }
};

const testConnection = async () => {
  try {
    testing.value = true;
    testResult.value = null;
    const res = await axios.post('/spa/settings/google-workspace/test');
    testResult.value = res.data;
    if (res.data.success) {
      toast.success('Google Workspace integration verified!');
    } else {
      toast.warning('Test returned warning/error.');
    }
  } catch (e) {
    testResult.value = e.response?.data || {
      success: false,
      message: 'Failed to communicate with integration endpoint.',
    };
    toast.error('Test connection failed.');
  } finally {
    testing.value = false;
  }
};

const confirmRegenerateToken = async () => {
  if (!confirm('Are you sure you want to regenerate the Google Workspace API Token? You will need to update Code.gs in Google Apps Script with the new token.')) {
    return;
  }
  try {
    regeneratingToken.value = true;
    const res = await axios.post('/spa/settings/google-workspace/regenerate-token');
    form.value.api_token = res.data.api_token;
    toast.success('Token regenerated successfully!');
  } catch (e) {
    toast.error('Failed to regenerate token.');
  } finally {
    regeneratingToken.value = false;
  }
};

const downloadAddonPackage = async () => {
  try {
    downloading.value = true;
    const response = await axios.get('/spa/settings/google-workspace/addon/download', {
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'zoom-pool-manager-google-workspace-addon.zip');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    toast.success('Add-on ZIP package downloaded! Open README.md for setup steps.');
  } catch (e) {
    toast.error('Failed to generate Add-on package.');
  } finally {
    downloading.value = false;
  }
};

const copyText = (val, msg) => {
  if (!val) return;
  navigator.clipboard.writeText(val);
  toast.success(msg || 'Copied to clipboard!');
};

const insertTag = (tag) => {
  if (!emailTemplateTextarea.value) {
    form.value.email_template += ' ' + tag;
    return;
  }
  const el = emailTemplateTextarea.value;
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const text = form.value.email_template;
  form.value.email_template = text.substring(0, start) + tag + text.substring(end);
  setTimeout(() => {
    el.focus();
    el.setSelectionRange(start + tag.length, start + tag.length);
  }, 0);
};

const resetEmailTemplate = () => {
  form.value.email_template = `<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #1e293b; border-left: 4px solid #0284c7; padding: 12px 16px; background-color: #f8fafc; border-radius: 4px; margin: 12px 0;">
  <p style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600; color: #0369a1;">
    📹 Zoom Meeting: {meeting_title}
  </p>
  <p style="margin: 0 0 8px 0;">
    <strong>Date & Time:</strong> {starts_at} - {ends_at} ({timezone})<br>
    <strong>Duration:</strong> {duration_minutes} minutes
  </p>
  <p style="margin: 0 0 10px 0;">
    <a href="{join_url}" target="_blank" style="display: inline-block; background-color: #0284c7; color: #ffffff; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-weight: 500; font-size: 13px;">
      Join Zoom Meeting
    </a>
  </p>
  <p style="margin: 0; font-size: 12px; color: #64748b;">
    <strong>Meeting ID:</strong> {meeting_id} &nbsp;|&nbsp; <strong>Passcode:</strong> {passcode}
    {host_key_section}
  </p>
</div>`;
  toast.info('Template reset to default.');
};

onMounted(() => {
  loadConfig();
});
</script>
