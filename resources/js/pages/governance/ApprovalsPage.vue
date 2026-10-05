<template>
  <div v-scroll-lock class="overflow-y-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <ShieldCheck class="w-7 h-7 text-brand-500" />
          <span>Governance & Workflow Approvals</span>
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Review VIP meeting requests, policy exception bookings, and department allocation limits.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="loadApprovals(1)"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold shadow-xs transition cursor-pointer"
          title="Refresh List"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- Filters & Stats Bar -->
    <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
      <!-- Search Input -->
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by meeting title, requester, or ID..."
          class="w-full pl-10 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 shadow-xs"
        />
      </div>

      <!-- Decision Status Filter Tabs -->
      <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl border border-slate-200/60 dark:border-slate-700/60 shrink-0 overflow-x-auto">
        <button
          v-for="tab in filterTabs"
          :key="tab.value"
          @click="statusFilter = tab.value"
          class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 shrink-0 cursor-pointer"
          :class="statusFilter === tab.value ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
        >
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.count !== null"
            class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
            :class="statusFilter === tab.value ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>
    </div>

    <!-- Approvals Table Card -->
    <GlassCard :padding="false">
      <!-- Loading State -->
      <div v-if="loading && approvals.length === 0" class="py-20 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Loading approval requests...</div>
        <p class="text-xs text-slate-400 mt-1">Fetching pending meeting reviews and authorization workflows.</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredApprovals.length === 0" class="py-20 text-center">
        <ShieldCheck class="w-12 h-12 text-emerald-500 mx-auto mb-3 opacity-80" />
        <div class="text-sm font-bold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Approvals Found' : (statusFilter === 'pending' ? 'All Clear — No Pending Approvals' : 'No records found') }}
        </div>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'Every scheduled meeting reservation meets automated compliance rules.' }}
        </p>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <th class="py-3.5 px-4">Meeting Details</th>
              <th class="py-3.5 px-4">Requester</th>
              <th class="py-3.5 px-4">Schedule & Resource</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
            <tr
              v-for="a in filteredApprovals"
              :key="a.public_id || a.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
            >
              <!-- Meeting Details -->
              <td class="py-4 px-4">
                <div class="font-bold text-slate-900 dark:text-white text-sm">
                  {{ a.meeting?.title || 'Reservation #' + a.meeting_id }}
                </div>
                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400">
                  <span class="font-mono">ID: {{ a.meeting?.public_id || a.public_id }}</span>
                  <span v-if="a.step" class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold">
                    Step {{ a.step }}
                  </span>
                </div>
              </td>

              <!-- Requester -->
              <td class="py-4 px-4">
                <div class="font-semibold text-slate-900 dark:text-white">
                  {{ a.meeting?.requester?.name || a.meeting?.owner?.name || 'User' }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  {{ a.meeting?.requester?.email || a.meeting?.owner?.email || '-' }}
                </div>
                <div v-if="a.meeting?.department" class="text-[10px] text-brand-600 dark:text-brand-400 font-medium mt-0.5">
                  {{ a.meeting.department.name }}
                </div>
              </td>

              <!-- Schedule & Resource -->
              <td class="py-4 px-4 text-slate-700 dark:text-slate-300">
                <div class="flex items-center gap-1.5 font-medium">
                  <Clock class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ formatDateTime(a.meeting?.starts_at) }}</span>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  {{ a.meeting?.duration_minutes ? `${a.meeting.duration_minutes} mins` : '-' }} &bull; {{ a.meeting?.participant_count || 1 }} attendees
                </div>
              </td>

              <!-- Status Badge -->
              <td class="py-4 px-4">
                <div class="space-y-1">
                  <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider inline-flex items-center gap-1"
                    :class="getDecisionBadgeClass(getDecision(a))"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="getDecisionDotClass(getDecision(a))"></span>
                    <span>{{ getDecision(a) }}</span>
                  </span>

                  <div v-if="a.decided_at" class="text-[10px] text-slate-400">
                    Decided {{ formatDateTime(a.decided_at) }}
                  </div>
                  <div v-if="a.decision_notes" class="text-[10px] text-slate-500 italic max-w-xs truncate" :title="a.decision_notes">
                    "{{ a.decision_notes }}"
                  </div>
                </div>
              </td>

              <!-- Actions Column -->
              <td class="py-4 px-4 text-right">
                <!-- If Pending: Show Approve / Reject Buttons -->
                <div v-if="getDecision(a) === 'pending'" class="flex items-center justify-end gap-2">
                  <button
                    @click="openDecideModal(a, 'approved')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs hover:shadow-sm transition cursor-pointer"
                  >
                    <Check class="w-3.5 h-3.5" />
                    <span>Approve</span>
                  </button>

                  <button
                    @click="openDecideModal(a, 'rejected')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs hover:shadow-sm transition cursor-pointer"
                  >
                    <X class="w-3.5 h-3.5" />
                    <span>Reject</span>
                  </button>
                </div>

                <!-- If already decided -->
                <div v-else class="text-xs text-slate-400 flex items-center justify-end gap-1">
                  <CheckCircle2 v-if="getDecision(a) === 'approved'" class="w-4 h-4 text-emerald-500" />
                  <AlertCircle v-else class="w-4 h-4 text-rose-500" />
                  <span class="capitalize">{{ getDecision(a) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-500">Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex items-center gap-2">
          <button
            :disabled="currentPage <= 1"
            @click="loadApprovals(currentPage - 1)"
            class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            Previous
          </button>
          <button
            :disabled="currentPage >= totalPages"
            @click="loadApprovals(currentPage + 1)"
            class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </GlassCard>

    <!-- Modal for Approval / Rejection Decision -->
    <Teleport to="body">
      <div
        v-if="showModal && activeApproval"
        v-scroll-lock
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm overflow-y-auto"
      >
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden animate-in fade-in zoom-in-95 duration-150 my-8">
        <!-- Modal Header -->
        <div
          class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between"
          :class="targetDecision === 'approved' ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : 'bg-rose-50/50 dark:bg-rose-950/20'"
        >
          <div class="flex items-center gap-2.5">
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center"
              :class="targetDecision === 'approved' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
            >
              <Check v-if="targetDecision === 'approved'" class="w-5 h-5" />
              <X v-else class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">
                {{ targetDecision === 'approved' ? 'Approve Meeting Request' : 'Decline Meeting Request' }}
              </h3>
              <p class="text-xs text-slate-500">
                Confirm your decision for this booking reservation.
              </p>
            </div>
          </div>

          <button
            @click="closeModal"
            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 space-y-4">
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 text-xs space-y-1.5">
            <div class="flex justify-between">
              <span class="text-slate-400">Meeting:</span>
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeApproval.meeting?.title }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Requester:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ activeApproval.meeting?.requester?.name || activeApproval.meeting?.owner?.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-400">Scheduled:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ formatDateTime(activeApproval.meeting?.starts_at) }}</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Decision Notes / Reason <span class="text-slate-400 font-normal">(Optional)</span>
            </label>
            <textarea
              v-model="decisionNotes"
              rows="3"
              class="w-full p-3 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              :placeholder="targetDecision === 'approved' ? 'Add any guidance or special conditions for the requester...' : 'Reason for rejection (e.g. priority institutional event, duration limit exceeded)...'"
            ></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
          <button
            @click="closeModal"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition"
          >
            Cancel
          </button>
          <button
            :disabled="processingDecision"
            @click="submitDecision"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white transition flex items-center gap-1.5 shadow-sm"
            :class="targetDecision === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-400' : 'bg-rose-600 hover:bg-rose-700 disabled:bg-rose-400'"
          >
            <RefreshCw v-if="processingDecision" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ targetDecision === 'approved' ? 'Confirm Approval' : 'Confirm Rejection' }}</span>
          </button>
        </div>
      </div>
    </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import { useToastStore } from '@/stores/toast';
import {
  ShieldCheck,
  RefreshCw,
  Search,
  Check,
  X,
  Clock,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next';

const toast = useToastStore();

const approvals = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('pending');

const currentPage = ref(1);
const totalPages = ref(1);

// Modal state
const showModal = ref(false);
const activeApproval = ref(null);
const targetDecision = ref('approved');
const decisionNotes = ref('');
const processingDecision = ref(false);

const getDecision = (a) => {
  return String(a?.decision || a?.status || 'pending').toLowerCase();
};

const filterTabs = computed(() => {
  const pendingCount = approvals.value.filter((a) => getDecision(a) === 'pending').length;
  return [
    { label: 'Pending Review', value: 'pending', count: pendingCount },
    { label: 'All Requests', value: 'all', count: null },
    { label: 'Approved', value: 'approved', count: null },
    { label: 'Rejected', value: 'rejected', count: null },
  ];
});

const filteredApprovals = computed(() => {
  return approvals.value.filter((a) => {
    const decision = getDecision(a);
    if (statusFilter.value !== 'all' && decision !== statusFilter.value) {
      return false;
    }

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const title = (a.meeting?.title || '').toLowerCase();
      const pid = (a.public_id || a.meeting?.public_id || '').toLowerCase();
      const reqName = (a.meeting?.requester?.name || a.meeting?.owner?.name || '').toLowerCase();
      const reqEmail = (a.meeting?.requester?.email || a.meeting?.owner?.email || '').toLowerCase();
      const notes = (a.decision_notes || '').toLowerCase();
      return title.includes(q) || pid.includes(q) || reqName.includes(q) || reqEmail.includes(q) || notes.includes(q);
    }

    return true;
  });
});

const loadApprovals = async (page = 1) => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/approvals', { params: { page } });
    approvals.value = res.data.data || res.data || [];
    currentPage.value = res.data.current_page || 1;
    totalPages.value = res.data.last_page || 1;
  } catch (e) {
    console.error('Failed to load approvals', e);
    toast.error('Failed to load workflow approvals.');
  } finally {
    loading.value = false;
  }
};

const openDecideModal = (approval, decision) => {
  activeApproval.value = approval;
  targetDecision.value = decision;
  decisionNotes.value = '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  activeApproval.value = null;
  decisionNotes.value = '';
};

const submitDecision = async () => {
  if (!activeApproval.value) return;
  processingDecision.value = true;
  try {
    const pubId = activeApproval.value.public_id;
    await axios.post(`/spa/approvals/${pubId}/decide`, {
      decision: targetDecision.value,
      decision_notes: decisionNotes.value,
    });
    toast.success(`Meeting request ${targetDecision.value === 'approved' ? 'approved' : 'rejected'} successfully.`);
    closeModal();
    await loadApprovals(currentPage.value);
  } catch (e) {
    console.error('Approval decision error:', e);
    const msg = e.response?.data?.message || 'Failed to process decision.';
    toast.error(msg);
  } finally {
    processingDecision.value = false;
  }
};

const getDecisionBadgeClass = (decision) => {
  const d = String(decision || '').toLowerCase();
  if (d === 'approved') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
  if (d === 'rejected') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20';
  return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
};

const getDecisionDotClass = (decision) => {
  const d = String(decision || '').toLowerCase();
  if (d === 'approved') return 'bg-emerald-500';
  if (d === 'rejected') return 'bg-rose-500';
  return 'bg-amber-500';
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  loadApprovals(1);
});
</script>
