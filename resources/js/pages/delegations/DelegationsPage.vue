<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-gradient-to-br from-indigo-500/10 to-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
            <UserCheck class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              Approval Delegations
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Temporarily transfer your meeting approval authority during leaves or out-of-office periods
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="openModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Delegation</span>
        </button>
      </div>
    </div>

    <!-- Summary Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold">
          <Send class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Granted by Me</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ activeGrantedCount }} Active
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
          <ShieldCheck class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Assigned to Me</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ delegatedToMe.length }} Active
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
          <Clock class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Audit Status</div>
          <div class="text-sm font-bold text-slate-900 dark:text-white">Time-Bounded & Logged</div>
        </div>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search delegations by user name or email..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Status Message Alert -->
    <div
      v-if="statusMessage"
      class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 class="w-4 h-4" />
        <span>{{ statusMessage }}</span>
      </div>
      <button @click="statusMessage = ''" class="text-emerald-500 hover:underline">Dismiss</button>
    </div>

    <!-- Granted Delegations Section -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
          <Send class="w-3.5 h-3.5" />
          <span>My Active Delegations (Granted Authority)</span>
        </h2>
        <span class="text-xs text-slate-400">{{ filteredMyDelegations.length }} records</span>
      </div>

      <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <div v-if="loading && !myDelegations.length" class="p-12 text-center text-slate-400">
          <RefreshCw class="w-7 h-7 mx-auto mb-2 animate-spin text-brand-500" />
          <p class="text-xs font-medium">Loading your delegations...</p>
        </div>

        <div v-else-if="!filteredMyDelegations.length" class="p-12 text-center text-slate-400">
          <UserCheck class="w-10 h-10 mx-auto mb-2 opacity-30 text-slate-500" />
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
            {{ searchQuery ? 'No delegations matching search criteria.' : 'You have not delegated your approval authority to anyone.' }}
          </p>
          <p class="text-xs text-slate-400 mt-1">
            When going on leave, delegate your authority so pending booking requests aren't stalled.
          </p>
          <button
            v-if="!searchQuery"
            @click="openModal"
            class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition shadow-sm"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Delegate Now</span>
          </button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Delegate User</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Time Window</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-24">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr
                v-for="del in filteredMyDelegations"
                :key="del.id"
                class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition group"
              >
                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold flex items-center justify-center text-xs">
                      {{ (del.delegate?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 dark:text-white">
                        {{ del.delegate?.name || 'User #' + del.delegate_user_id }}
                      </div>
                      <div class="text-[11px] font-normal text-slate-400">
                        {{ del.delegate?.email || '' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                  <div class="flex items-center gap-1.5 font-medium">
                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ formatDate(del.starts_at) }}</span>
                    <span class="text-slate-400">→</span>
                    <span>{{ formatDate(del.ends_at) }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="isDelegationValid(del) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 border border-slate-500/20'"
                  >
                    {{ isDelegationValid(del) ? 'Active' : 'Expired / Inactive' }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <button
                    v-if="del.is_active"
                    @click="revokeDelegation(del)"
                    class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                    title="Revoke Delegation"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delegations Assigned to Me -->
    <div class="space-y-3 pt-2">
      <div class="flex items-center justify-between">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
          <ShieldCheck class="w-3.5 h-3.5" />
          <span>Delegations Assigned to Me (Acting Approver)</span>
        </h2>
        <span class="text-xs text-slate-400">{{ filteredDelegatedToMe.length }} records</span>
      </div>

      <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <div v-if="!filteredDelegatedToMe.length" class="p-12 text-center text-slate-400">
          <ShieldCheck class="w-10 h-10 mx-auto mb-2 opacity-30 text-slate-500" />
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
            {{ searchQuery ? 'No assigned delegations matching search criteria.' : 'No active delegations assigned to you.' }}
          </p>
          <p class="text-xs text-slate-400 mt-1">
            When colleagues delegate their approval authority to you, their pending requests will appear here.
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Delegator</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Time Window</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr
                v-for="del in filteredDelegatedToMe"
                :key="del.id"
                class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition group"
              >
                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold flex items-center justify-center text-xs">
                      {{ (del.user?.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 dark:text-white">
                        {{ del.user?.name || 'User #' + del.user_id }}
                      </div>
                      <div class="text-[11px] font-normal text-slate-400">
                        {{ del.user?.email || '' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                  <div class="flex items-center gap-1.5 font-medium">
                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ formatDate(del.starts_at) }}</span>
                    <span class="text-slate-400">→</span>
                    <span>{{ formatDate(del.ends_at) }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <router-link
                    to="/app/approvals"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-500/10 text-brand-600 dark:text-brand-400 hover:bg-brand-500/20 transition"
                  >
                    <span>Review Approvals</span>
                    <ArrowUpRight class="w-3.5 h-3.5" />
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Delegation Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        v-scroll-lock
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm overflow-y-auto"
        @click.self="showModal = false"
      >
        <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4 my-8">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <UserCheck class="w-5 h-5 text-brand-600" />
              <h3 class="font-bold text-base text-slate-900 dark:text-white">Delegate Approval Authority</h3>
            </div>
            <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="submitDelegation" class="space-y-4 text-xs">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Select Delegate (Acting Approver)
              </label>
              <SearchableSelect
                v-model="form.delegate_user_id"
                remote-url="/spa/users/search"
                :initial-options="eligibleDelegates"
                placeholder="Search colleague by name or email..."
                search-placeholder="Type name, email, or designation..."
                label-key="name"
                value-key="id"
                sublabel-key="email"
                badge-key="department.name"
              />
              <span class="text-[10px] text-slate-400 mt-1 block">
                The chosen delegate will be able to approve or reject requests on your behalf during the window.
              </span>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At</label>
                <input
                  v-model="form.starts_at"
                  type="date"
                  required
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
              </div>
              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At</label>
                <input
                  v-model="form.ends_at"
                  type="date"
                  required
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
              </div>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving || !form.delegate_user_id"
                class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition flex items-center gap-1.5"
              >
                <RefreshCw v-if="saving" class="w-3.5 h-3.5 animate-spin" />
                <span>Grant Delegation</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import SearchableSelect from '@/components/SearchableSelect.vue';
import {
  ArrowUpRight,
  Calendar,
  CheckCircle2,
  Clock,
  Plus,
  RefreshCw,
  Search,
  Send,
  ShieldCheck,
  Trash2,
  UserCheck,
  X,
} from 'lucide-vue-next';

const loading = ref(false);
const saving = ref(false);
const myDelegations = ref([]);
const delegatedToMe = ref([]);
const eligibleDelegates = ref([]);
const showModal = ref(false);
const searchQuery = ref('');
const statusMessage = ref('');

const isDelegationValid = (del) => {
  if (!del.is_active) return false;
  if (!del.ends_at) return true;
  return new Date(del.ends_at) >= new Date();
};

const activeGrantedCount = computed(() => {
  return myDelegations.value.filter((d) => isDelegationValid(d)).length;
});

const filteredMyDelegations = computed(() => {
  if (!searchQuery.value.trim()) return myDelegations.value;
  const q = searchQuery.value.toLowerCase().trim();
  return myDelegations.value.filter((d) => {
    return (
      (d.delegate?.name && d.delegate.name.toLowerCase().includes(q)) ||
      (d.delegate?.email && d.delegate.email.toLowerCase().includes(q))
    );
  });
});

const filteredDelegatedToMe = computed(() => {
  if (!searchQuery.value.trim()) return delegatedToMe.value;
  const q = searchQuery.value.toLowerCase().trim();
  return delegatedToMe.value.filter((d) => {
    return (
      (d.user?.name && d.user.name.toLowerCase().includes(q)) ||
      (d.user?.email && d.user.email.toLowerCase().includes(q))
    );
  });
});

const form = ref({
  delegate_user_id: null,
  starts_at: new Date().toISOString().split('T')[0],
  ends_at: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
});

const openModal = () => {
  form.value = {
    delegate_user_id: null,
    starts_at: new Date().toISOString().split('T')[0],
    ends_at: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
  };
  showModal.value = true;
};

const fetchDelegations = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/delegations');
    myDelegations.value = res.data.my_delegations || [];
    delegatedToMe.value = res.data.delegated_to_me || [];
    eligibleDelegates.value = res.data.eligible_delegates || [];
  } catch (err) {
    console.error('Failed to load delegations via /spa/delegations, falling back', err);
    try {
      const fallback = await axios.get('/settings/delegations');
      myDelegations.value = fallback.data.my_delegations || [];
      delegatedToMe.value = fallback.data.delegated_to_me || [];
      eligibleDelegates.value = fallback.data.eligible_delegates || [];
    } catch (e) {
      console.error('Failed fallback delegations load', e);
    }
  } finally {
    loading.value = false;
  }
};

const submitDelegation = async () => {
  if (!form.value.delegate_user_id) {
    alert('Please select a delegate user.');
    return;
  }

  try {
    saving.value = true;
    await axios.post('/spa/delegations', form.value);
    showModal.value = false;
    statusMessage.value = 'Approval authority successfully delegated.';
    await fetchDelegations();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to create delegation.');
  } finally {
    saving.value = false;
  }
};

const revokeDelegation = async (del) => {
  const delegateName = del.delegate?.name || 'this delegate';
  if (!confirm(`Revoke approval delegation for "${delegateName}"?`)) return;

  try {
    await axios.delete(`/spa/delegations/${del.public_id}`);
    statusMessage.value = `Delegation for "${delegateName}" revoked.`;
    await fetchDelegations();
  } catch (err) {
    alert('Failed to revoke delegation.');
  }
};

const formatDate = (val) => {
  if (!val) return '';
  return new Date(val).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

onMounted(() => {
  fetchDelegations();
});
</script>
