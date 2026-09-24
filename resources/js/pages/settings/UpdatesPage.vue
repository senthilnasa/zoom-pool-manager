<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
          System Updates & GitHub Releases
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Verify and apply authenticated release packages directly from GitHub with automated pre-update backups.
        </p>
      </div>

      <button
        @click="checkUpdates"
        :disabled="checking || applying"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-md shadow-brand-500/20 transition-all disabled:opacity-50"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': checking }" />
        <span>{{ checking ? 'Checking...' : 'Check for Updates' }}</span>
      </button>
    </div>

    <!-- Current Version Status Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <GlassCard title="Installed Version">
        <div class="flex items-center gap-4 mt-2">
          <div class="w-12 h-12 rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold text-xl">
            v
          </div>
          <div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">
              {{ updateInfo?.current_version || authStore.appVersion }}
            </div>
            <div class="text-xs text-slate-500">
              Target Repository: <span class="font-mono font-medium text-slate-700 dark:text-slate-300">senthilnasa/zoom-pool-manager</span>
            </div>
          </div>
        </div>
      </GlassCard>

      <GlassCard title="Latest Release Available">
        <div class="flex items-center gap-4 mt-2">
          <div
            class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-xl"
            :class="updateInfo?.update_available ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
          >
            <CheckCircle2 v-if="!updateInfo?.update_available" class="w-6 h-6" />
            <ArrowUpCircle v-else class="w-6 h-6" />
          </div>
          <div>
            <div class="text-2xl font-black text-slate-900 dark:text-white">
              {{ updateInfo?.latest_version || 'v' + authStore.appVersion }}
            </div>
            <div class="text-xs" :class="updateInfo?.update_available ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-500'">
              {{ updateInfo?.update_available ? 'New Version Available!' : 'You are running the latest version' }}
            </div>
          </div>
        </div>
      </GlassCard>
    </div>

    <!-- Update Action Banner if Update Available -->
    <div
      v-if="updateInfo?.update_available"
      class="p-6 rounded-2xl border border-brand-200 dark:border-brand-800/80 bg-brand-50/50 dark:bg-brand-950/40 backdrop-blur-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
    >
      <div>
        <h4 class="font-bold text-slate-900 dark:text-white">
          Release {{ updateInfo.latest_version }} is ready to apply
        </h4>
        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 max-w-xl">
          An automated pre-update backup of your database and environment will be taken. Release checksums and zip directory integrity are validated before file replacement.
        </p>
      </div>

      <button
        @click="confirmModal = true"
        :disabled="applying"
        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-md shadow-emerald-500/20 transition-all shrink-0"
      >
        <span>Apply Update Now</span>
      </button>
    </div>

    <!-- Changelog & Release Notes -->
    <GlassCard v-if="updateInfo?.release_notes" title="Release Notes">
      <div class="prose prose-sm dark:prose-invert max-w-none text-xs whitespace-pre-line text-slate-700 dark:text-slate-300 bg-slate-50/50 dark:bg-slate-900/50 p-4 rounded-xl font-mono">
        {{ updateInfo.release_notes }}
      </div>
    </GlassCard>

    <!-- Confirmation Modal -->
    <Modal
      :show="confirmModal"
      title="Confirm System Update"
      @close="confirmModal = false"
    >
      <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
        <p>
          You are about to update Zoom Pool Manager to version
          <span class="font-bold text-slate-900 dark:text-white">{{ updateInfo?.latest_version }}</span>.
        </p>
        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs">
          <strong>Safety Protections:</strong> An exclusive update lock is acquired to prevent concurrent writes, a full database snapshot is archived to <code class="font-mono">storage/app/backups/</code>, and migrations are run automatically.
        </div>
      </div>

      <template #footer>
        <button
          type="button"
          @click="confirmModal = false"
          class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-semibold"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="applyUpdate"
          :disabled="applying"
          class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition-all disabled:opacity-50"
        >
          {{ applying ? 'Applying Update...' : 'Proceed with Update' }}
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import GlassCard from '@/components/GlassCard.vue';
import Modal from '@/components/Modal.vue';
import { RefreshCw, CheckCircle2, ArrowUpCircle } from 'lucide-vue-next';

const authStore = useAuthStore();
const toast = useToastStore();

const checking = ref(false);
const applying = ref(false);
const confirmModal = ref(false);

const updateInfo = ref({
  current_version: '1.0.0',
  latest_version: '1.0.0',
  update_available: false,
  release_notes: '',
});

const loadCurrentStatus = async () => {
  try {
    const res = await axios.get('/spa/settings/updates');
    updateInfo.value = res.data;
  } catch (e) {
    // Defaults
    updateInfo.value = {
      current_version: authStore.appVersion,
      latest_version: authStore.appVersion,
      update_available: false,
    };
  }
};

const checkUpdates = async () => {
  try {
    checking.value = true;
    const res = await axios.post('/spa/settings/updates/check');
    updateInfo.value = res.data;
    if (res.data.update_available) {
      toast.info(`A newer release (${res.data.latest_version}) is available!`);
    } else {
      toast.success('Your system is up to date.');
    }
  } catch (e) {
    toast.error('Unable to fetch updates from GitHub repository.');
  } finally {
    checking.value = false;
  }
};

const applyUpdate = async () => {
  try {
    applying.value = true;
    confirmModal.value = false;
    const res = await axios.post('/spa/settings/updates/apply');
    if (res.data.success) {
      toast.success('Update applied successfully! Reloading...');
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      toast.error(res.data.message || 'Update failed.');
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error occurred while applying update.');
  } finally {
    applying.value = false;
  }
};

onMounted(() => {
  loadCurrentStatus();
});
</script>
