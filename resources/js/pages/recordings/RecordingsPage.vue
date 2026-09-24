<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <Disc class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Cloud Recordings & Transcripts
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Access synced Zoom cloud recordings, transcript files, and securely view session playback.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="syncFromZoom"
          :disabled="syncing"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-500/20 transition cursor-pointer disabled:opacity-50"
        >
          <CloudDownload class="w-4 h-4" :class="{ 'animate-bounce': syncing }" />
          <span>{{ syncing ? 'Syncing...' : 'Sync from Zoom' }}</span>
        </button>

        <button
          type="button"
          @click="showCreateModal = true"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Add / Register Recording</span>
        </button>

        <button
          @click="loadRecordings(1)"
          class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:bg-slate-100 text-slate-600 dark:text-slate-300 cursor-pointer"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model="searchQuery"
          @input="debounceSearch"
          type="text"
          placeholder="Search recordings by topic, Zoom meeting ID, or public ID..."
          class="w-full text-xs rounded-xl pl-9 pr-4 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-brand-500"
        />
      </div>
    </div>

    <GlassCard :padding="false">
      <div v-if="loading && recordings.length === 0" class="py-16 text-center text-sm text-slate-400">
        <RefreshCw class="w-6 h-6 animate-spin mx-auto mb-2 text-brand-500" />
        Loading recordings...
      </div>

      <div v-else-if="recordings.length === 0" class="py-16 text-center space-y-3">
        <Disc class="w-10 h-10 text-slate-300 dark:text-slate-700 mx-auto" />
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">No recordings found</div>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          Recordings are automatically ingested via Zoom webhooks, or you can click "Sync from Zoom" to discover completed recordings.
        </p>
        <button
          @click="showCreateModal = true"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-800 cursor-pointer"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Register Recording Manually</span>
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4">Meeting Topic / Title</th>
              <th class="py-3.5 px-4">Recorded Date</th>
              <th class="py-3.5 px-4">Duration</th>
              <th class="py-3.5 px-4">Size</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr
              v-for="r in recordings"
              :key="r.public_id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors text-xs"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white">
                  {{ r.topic || r.meeting?.title || 'Recorded Meeting' }}
                </div>
                <div class="text-[11px] text-slate-400 font-mono">
                  Zoom ID: {{ r.zoom_meeting_id || 'N/A' }}
                </div>
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                {{ formatDateTime(r.recording_start || r.created_at) }}
              </td>
              <td class="py-3.5 px-4 text-slate-500 font-mono">
                {{ r.duration_minutes ? r.duration_minutes + ' min' : 'N/A' }}
              </td>
              <td class="py-3.5 px-4 text-slate-500 font-mono">
                {{ formatBytes(r.file_size_bytes) }}
              </td>
              <td class="py-3.5 px-4">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                  {{ r.status || 'Ready' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                <button
                  v-if="r.play_url || r.share_url"
                  @click="copyShareLink(r.share_url || r.play_url)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer inline-flex"
                  title="Copy Share Link"
                >
                  <Copy class="w-3.5 h-3.5" />
                </button>
                <a
                  v-if="r.play_url || r.share_url"
                  :href="r.play_url || r.share_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-900/40 text-xs font-semibold transition-colors"
                >
                  <Play class="w-3 h-3" />
                  <span>Watch</span>
                </a>
                <button
                  @click="deleteRecording(r)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer inline-flex"
                  title="Delete Recording"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Pagination -->
      <TablePagination
        :current-page="currentPage"
        :last-page="totalPages"
        :per-page="perPage"
        :total="totalRecords"
        @page-change="loadRecordings"
        @per-page-change="onPerPageChange"
      />
    </GlassCard>

    <!-- Register / Add Recording Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Register Cloud Recording</h2>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveRecording" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Recording Topic / Title *</label>
            <input
              v-model="createForm.topic"
              type="text"
              required
              placeholder="e.g. Advanced Physics Lecture - Session 4"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Playback URL *</label>
            <input
              v-model="createForm.play_url"
              type="url"
              required
              placeholder="https://zoom.us/rec/play/..."
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Zoom Meeting ID</label>
              <input
                v-model="createForm.zoom_meeting_id"
                type="text"
                placeholder="e.g. 84920491823"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
              />
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Duration (Minutes)</label>
              <input
                v-model.number="createForm.duration_minutes"
                type="number"
                min="1"
                placeholder="60"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 cursor-pointer"
            >
              {{ saving ? 'Saving...' : 'Register Recording' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import { useToastStore } from '@/stores/toast';
import {
  Disc,
  RefreshCw,
  Play,
  Plus,
  CloudDownload,
  Search,
  Copy,
  Trash2,
  X
} from 'lucide-vue-next';

const toast = useToastStore();

const recordings = ref([]);
const loading = ref(false);
const syncing = ref(false);
const saving = ref(false);
const searchQuery = ref('');
const showCreateModal = ref(false);

const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(25);
const totalRecords = ref(0);

const createForm = ref({
  topic: '',
  play_url: '',
  zoom_meeting_id: '',
  duration_minutes: 60,
});

let searchTimeout = null;

const formatDateTime = (str) => {
  if (!str) return 'N/A';
  return new Date(str).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatBytes = (bytes) => {
  if (!bytes) return 'N/A';
  const mb = bytes / (1024 * 1024);
  return mb > 1024 ? (mb / 1024).toFixed(1) + ' GB' : mb.toFixed(1) + ' MB';
};

const onPerPageChange = (newPerPage) => {
  perPage.value = newPerPage;
  loadRecordings(1);
};

const loadRecordings = async (page = 1) => {
  try {
    loading.value = true;
    const params = {
      page,
      per_page: perPage.value,
    };
    if (searchQuery.value) params.search = searchQuery.value;
    const res = await axios.get('/spa/recordings', { params });
    recordings.value = res.data.data || [];
    currentPage.value = res.data.current_page || 1;
    totalPages.value = res.data.last_page || 1;
    totalRecords.value = res.data.total || 0;
  } catch (e) {
    console.error('Failed to load recordings', e);
  } finally {
    loading.value = false;
  }
};

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadRecordings(1);
  }, 350);
};

const syncFromZoom = async () => {
  try {
    syncing.value = true;
    const res = await axios.post('/spa/recordings/sync-from-zoom');
    toast.success(res.data.message || 'Synced completed recordings from Zoom.');
    await loadRecordings(1);
  } catch (e) {
    toast.error('Failed to sync recordings from Zoom.');
  } finally {
    syncing.value = false;
  }
};

const saveRecording = async () => {
  try {
    saving.value = true;
    await axios.post('/spa/recordings', createForm.value);
    toast.success('Recording registered successfully.');
    showCreateModal.value = false;
    createForm.value = {
      topic: '',
      play_url: '',
      zoom_meeting_id: '',
      duration_minutes: 60,
    };
    await loadRecordings(1);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to save recording.');
  } finally {
    saving.value = false;
  }
};

const copyShareLink = async (url) => {
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    toast.success('Recording link copied to clipboard!');
  } catch {
    toast.error('Failed to copy recording link.');
  }
};

const deleteRecording = async (rec) => {
  if (!confirm(`Are you sure you want to remove recording for "${rec.topic || 'this meeting'}"?`)) return;
  try {
    const res = await axios.delete(`/spa/recordings/${rec.id}`);
    toast.success(res.data?.message || 'Recording deleted.');
    loadRecordings(currentPage.value);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to delete recording.');
  }
};

onMounted(() => {
  loadRecordings(1);
});
</script>
