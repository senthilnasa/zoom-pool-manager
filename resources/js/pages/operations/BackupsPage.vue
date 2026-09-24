<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          System Backups
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Automated snapshots, database archives, and disaster recovery dumps
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchBackups"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="createBackup"
          :disabled="creating"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <Database class="w-4 h-4" />
          <span>{{ creating ? 'Creating Backup...' : 'Create Backup Now' }}</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div v-if="feedback" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between">
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Backup List Card -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <div v-if="loading && !backups.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading backup archives...</p>
      </div>

      <div v-else-if="!backups.length" class="p-12 text-center text-slate-400">
        <Archive class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Backups Found</p>
        <p class="text-xs text-slate-400 mt-1">Click "Create Backup Now" to create your first database snapshot.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Archive Name</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">File Size</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Created By</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Date</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="backup in backups"
              :key="backup.public_id || backup.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-2.5">
                  <FileArchive class="w-4 h-4 text-brand-500 shrink-0" />
                  <span class="font-mono text-xs text-slate-900 dark:text-white font-medium">{{ backup.filename }}</span>
                </div>
              </td>
              <td class="py-3.5 px-4 text-xs font-semibold text-slate-700 dark:text-slate-300">
                {{ formatBytes(backup.file_size_bytes) }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="backup.status === 'completed' || backup.status === 'success' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'"
                >
                  {{ backup.status || 'completed' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400">
                {{ backup.creator?.name || 'Automated Cron' }}
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                {{ formatDate(backup.created_at) }}
              </td>
              <td class="py-3.5 px-4 text-right">
                <a
                  :href="`/admin/backups/${backup.public_id}/download`"
                  target="_blank"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                >
                  <Download class="w-3.5 h-3.5 text-brand-500" />
                  <span>Download</span>
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
  Database,
  Archive,
  FileArchive,
  Download,
  RefreshCw,
} from 'lucide-vue-next';

const backups = ref([]);
const loading = ref(false);
const creating = ref(false);
const feedback = ref('');

const fetchBackups = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/backups', {
      headers: { Accept: 'application/json' },
    });
    backups.value = res.data?.backups || [];
  } catch (err) {
    console.error('Failed to load backups', err);
  } finally {
    loading.value = false;
  }
};

const createBackup = async () => {
  creating.value = true;
  feedback.value = '';
  try {
    const res = await axios.post('/admin/backups', {}, {
      headers: { Accept: 'application/json' },
    });
    feedback.value = res.data?.message || 'Database backup created successfully.';
    await fetchBackups();
  } catch (err) {
    console.error('Failed to create backup', err);
  } finally {
    creating.value = false;
  }
};

const formatBytes = (bytes) => {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchBackups);
</script>
