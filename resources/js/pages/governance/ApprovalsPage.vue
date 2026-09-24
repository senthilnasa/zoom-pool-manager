<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
          Governance & Workflow Approvals
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Review VIP booking requests, duration policy exceptions, and department allocations.
        </p>
      </div>

      <button
        @click="loadApprovals(1)"
        class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:bg-slate-100 text-slate-600 dark:text-slate-300"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search approvals by title, requester, rule, or status..."
          class="w-full pl-10 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
        />
      </div>
    </div>

    <GlassCard :padding="false">
      <div v-if="loading && approvals.length === 0" class="py-16 text-center text-sm text-slate-400">
        Loading approval requests...
      </div>

      <div v-else-if="filteredApprovals.length === 0" class="py-16 text-center">
        <ShieldCheck class="w-10 h-10 text-emerald-500 mx-auto mb-3 opacity-70" />
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Approvals Found' : 'No pending approvals' }}
        </div>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search query.' : 'All meeting reservation requests comply with automated governance policies.' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <th class="py-3.5 px-4">Meeting Title</th>
              <th class="py-3.5 px-4">Requester</th>
              <th class="py-3.5 px-4">Rule Triggered</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr
              v-for="a in filteredApprovals"
              :key="a.public_id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white">
                  {{ a.meeting?.title || 'Reservation ' + a.meeting_id }}
                </div>
                <div class="text-xs text-slate-400">
                  ID: {{ a.public_id }}
                </div>
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                {{ a.meeting?.owner?.name || 'User' }}
              </td>
              <td class="py-3.5 px-4 text-xs text-slate-500">
                {{ a.rule?.name || 'Manual Approval Rule' }}
              </td>
              <td class="py-3.5 px-4">
                <span
                  class="glass-badge"
                  :class="{
                    'bg-amber-500/10 text-amber-600 border-amber-500/20': a.status === 'pending',
                    'bg-emerald-500/10 text-emerald-600 border-emerald-500/20': a.status === 'approved',
                    'bg-rose-500/10 text-rose-600 border-rose-500/20': a.status === 'rejected',
                  }"
                >
                  {{ a.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div v-if="a.status === 'pending'" class="flex items-center justify-end gap-2">
                  <button
                    @click="decideApproval(a, 'approved')"
                    class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-400 text-xs font-semibold"
                  >
                    Approve
                  </button>
                  <button
                    @click="decideApproval(a, 'rejected')"
                    class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-400 text-xs font-semibold"
                  >
                    Reject
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </GlassCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import { useToastStore } from '@/stores/toast';
import { ShieldCheck, RefreshCw, Search } from 'lucide-vue-next';

const toast = useToastStore();
const approvals = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const filteredApprovals = computed(() => {
  if (!searchQuery.value.trim()) return approvals.value;
  const q = searchQuery.value.toLowerCase().trim();
  return approvals.value.filter((a) => {
    const title = (a.meeting?.title || '').toLowerCase();
    const pid = (a.public_id || '').toLowerCase();
    const owner = (a.meeting?.owner?.name || '').toLowerCase();
    const rule = (a.rule?.name || '').toLowerCase();
    const status = (a.status || '').toLowerCase();
    return title.includes(q) || pid.includes(q) || owner.includes(q) || rule.includes(q) || status.includes(q);
  });
});

const loadApprovals = async (page = 1) => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/approvals', { params: { page } });
    approvals.value = res.data.data;
  } catch (e) {
    console.error('Failed to load approvals', e);
  } finally {
    loading.value = false;
  }
};

const decideApproval = async (approval, decision) => {
  const reason = prompt(`Optional reason for ${decision}:`) || '';
  try {
    await axios.post(`/approvals/${approval.public_id}/decide`, {
      decision,
      reason,
    });
    toast.success(`Request ${decision} successfully.`);
    loadApprovals(1);
  } catch (e) {
    toast.error('Failed to process approval decision.');
  }
};

onMounted(() => {
  loadApprovals(1);
});
</script>
