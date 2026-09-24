<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            Emergency IT Override
          </h1>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
            High Privilege
          </span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Break-glass administrative controls to reallocate resources or cancel active/stuck meetings
        </p>
      </div>

      <button
        @click="fetchData"
        class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition self-start sm:self-auto"
        title="Refresh"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <!-- Warning Banner -->
    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-start gap-3.5">
      <AlertTriangle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
      <div class="text-xs text-amber-800 dark:text-amber-200 space-y-1">
        <p class="font-bold">Caution: Direct Infrastructure Intervention</p>
        <p class="text-amber-700 dark:text-amber-300">
          Actions taken here bypass standard workflow constraints and approval pipelines. Every override is permanently recorded in the immutable security audit log with your administrative user credentials and justification.
        </p>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Active Meetings Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Active & Scheduled Sessions</h2>
        <span class="text-xs text-slate-400 font-medium">{{ activeMeetings.length }} session(s) detected</span>
      </div>

      <div v-if="loading && !activeMeetings.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Scanning active sessions...</p>
      </div>

      <div v-else-if="!activeMeetings.length" class="p-12 text-center text-slate-400">
        <CheckCircle2 class="w-10 h-10 mx-auto mb-3 text-emerald-500/60" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Active or Scheduled Sessions</p>
        <p class="text-xs text-slate-400 mt-1">There are currently no active meetings requiring emergency intervention.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Session Title</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Host / Owner</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Assigned Resource</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Timing</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Intervention</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="meeting in activeMeetings"
              :key="meeting.public_id || meeting.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ meeting.title }}</div>
                <div class="text-[11px] text-slate-400 font-mono">ID: {{ meeting.zoom_meeting_id || meeting.public_id }}</div>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                {{ meeting.owner?.name || 'Unknown' }}
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  {{ meeting.zoom_resource?.name || 'Unassigned' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                <div>{{ formatDate(meeting.starts_at) }}</div>
                <div class="text-[11px] text-slate-400">Duration: {{ meeting.duration_minutes || 60 }}m</div>
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="meeting.status === 'started' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 animate-pulse' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'"
                >
                  {{ meeting.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="openReallocateModal(meeting)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 hover:bg-amber-100 transition shadow-sm"
                  >
                    <ArrowRightLeft class="w-3.5 h-3.5" />
                    <span>Reallocate</span>
                  </button>
                  <button
                    @click="openCancelModal(meeting)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 transition shadow-sm"
                  >
                    <Ban class="w-3.5 h-3.5" />
                    <span>Force Cancel</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Intervention Modal -->
    <div
      v-if="activeActionModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
            <AlertTriangle class="w-4 h-4 text-rose-500" />
            <span>Confirm Emergency {{ actionType === 'reallocate' ? 'Reallocation' : 'Cancellation' }}</span>
          </div>
          <button @click="activeActionModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="text-xs text-slate-600 dark:text-slate-300">
          Target Session: <strong class="text-slate-900 dark:text-white">{{ selectedMeeting?.title }}</strong>
        </div>

        <div v-if="actionType === 'reallocate'" class="space-y-1.5">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Select Target Zoom Resource *</label>
          <select
            v-model="actionForm.resource_id"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          >
            <option value="">-- Choose New Resource --</option>
            <option
              v-for="res in resources"
              :key="res.id"
              :value="res.id"
              :disabled="res.id === selectedMeeting?.zoom_resource_id"
            >
              {{ res.name }} ({{ res.email }}) {{ res.id === selectedMeeting?.zoom_resource_id ? '[Current]' : '' }}
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Administrative Justification / Reason *</label>
          <textarea
            v-model="actionForm.reason"
            rows="3"
            placeholder="Explain why this emergency intervention is necessary (audited)..."
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            @click="activeActionModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Cancel
          </button>
          <button
            @click="submitOverride"
            :disabled="submitting || !actionForm.reason || (actionType === 'reallocate' && !actionForm.resource_id)"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-md transition disabled:opacity-50"
            :class="actionType === 'reallocate' ? 'bg-amber-600 hover:bg-amber-700 shadow-amber-500/20' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20'"
          >
            {{ submitting ? 'Executing...' : `Execute ${actionType === 'reallocate' ? 'Reallocation' : 'Cancel'}` }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
  AlertTriangle,
  RefreshCw,
  CheckCircle2,
  ArrowRightLeft,
  Ban,
  X,
} from 'lucide-vue-next';

const activeMeetings = ref([]);
const resources = ref([]);
const loading = ref(false);
const feedback = ref('');

const activeActionModal = ref(false);
const actionType = ref('reallocate');
const selectedMeeting = ref(null);
const submitting = ref(false);

const actionForm = ref({
  resource_id: '',
  reason: '',
});

const fetchData = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/emergency', {
      headers: { Accept: 'application/json' },
    });
    activeMeetings.value = res.data?.active_meetings?.data || res.data?.active_meetings || [];
    resources.value = res.data?.resources || [];
  } catch (err) {
    console.error('Failed to load emergency data', err);
  } finally {
    loading.value = false;
  }
};

const openReallocateModal = (meeting) => {
  selectedMeeting.value = meeting;
  actionType.value = 'reallocate';
  actionForm.value = { resource_id: '', reason: '' };
  activeActionModal.value = true;
};

const openCancelModal = (meeting) => {
  selectedMeeting.value = meeting;
  actionType.value = 'cancel';
  actionForm.value = { resource_id: '', reason: '' };
  activeActionModal.value = true;
};

const submitOverride = async () => {
  submitting.value = true;
  feedback.value = '';
  try {
    const payload = {
      meeting_public_id: selectedMeeting.value.public_id,
      action: actionType.value,
      reason: actionForm.value.reason,
    };
    if (actionType.value === 'reallocate') {
      payload.resource_id = actionForm.value.resource_id;
    }
    const res = await axios.post('/admin/emergency/override', payload, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Emergency action executed successfully.';
    activeActionModal.value = false;
    await fetchData();
  } catch (err) {
    console.error('Failed to execute emergency override', err);
  } finally {
    submitting.value = false;
  }
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchData);
</script>
