<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Approval Delegations
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Temporarily delegate meeting approval authority during leaves or out-of-office periods
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="showModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Delegation</span>
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
          placeholder="Search delegations by user name or email..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Granted Delegations Section -->
    <div class="space-y-3">
      <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        My Active Delegations (Granted Authority)
      </h2>

      <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
        <div v-if="loading && !myDelegations.length" class="p-8 text-center text-slate-400">
          <RefreshCw class="w-6 h-6 mx-auto mb-2 animate-spin text-brand-500" />
          <p class="text-xs">Loading delegations...</p>
        </div>

        <div v-else-if="!filteredMyDelegations.length" class="p-8 text-center text-slate-400">
          <UserCheck class="w-8 h-8 mx-auto mb-2 opacity-40" />
          <p class="text-xs font-medium">
            {{ searchQuery ? 'No delegations matching search criteria.' : 'You have not delegated your approval authority to anyone.' }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Delegate User</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Time Window</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-24">Revoke</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr v-for="del in filteredMyDelegations" :key="del.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                  {{ del.delegate?.name || 'User #' + del.delegate_user_id }}
                </td>
                <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                  {{ formatDate(del.starts_at) }} – {{ formatDate(del.ends_at) }}
                </td>
                <td class="py-3.5 px-4">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                    :class="del.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-500'"
                  >
                    {{ del.is_active ? 'Active' : 'Expired' }}
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

    <!-- Delegations Granted to Me -->
    <div class="space-y-3">
      <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
        Delegations Assigned to Me (Acting Approver)
      </h2>

      <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
        <div v-if="!filteredDelegatedToMe.length" class="p-8 text-center text-slate-400">
          <p class="text-xs font-medium">
            {{ searchQuery ? 'No assigned delegations matching search criteria.' : 'No one has delegated approval authority to you.' }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm border-collapse">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Delegator</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Time Window</th>
                <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr v-for="del in filteredDelegatedToMe" :key="del.id">
                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                  {{ del.user?.name || 'User #' + del.user_id }}
                </td>
                <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                  {{ formatDate(del.starts_at) }} – {{ formatDate(del.ends_at) }}
                </td>
                <td class="py-3.5 px-4">
                  <router-link
                    to="/app/approvals"
                    class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-brand-500/10 text-brand-600 hover:bg-brand-500/20 transition"
                  >
                    Review Delegated Approvals
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Delegation Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
      @click.self="showModal = false"
    >
      <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="font-bold text-base text-slate-900 dark:text-white">Delegate Approval Authority</h3>
          <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitDelegation" class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Delegate (10,000+ Employees)</label>
            <SearchableSelect
              v-model="form.delegate_user_id"
              remote-url="/spa/users/search"
              :initial-options="eligibleDelegates"
              placeholder="Search eligible delegate by name or email..."
              search-placeholder="Type name, email, or designation..."
              label-key="name"
              value-key="id"
              sublabel-key="email"
              badge-key="department.name"
            />
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

          <div class="pt-3 flex justify-end gap-2">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition"
            >
              Grant Delegation
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
import SearchableSelect from '@/components/SearchableSelect.vue';
import {
  UserCheck,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  X,
} from 'lucide-vue-next';

const loading = ref(false);
const saving = ref(false);
const myDelegations = ref([]);
const delegatedToMe = ref([]);
const eligibleDelegates = ref([]);
const showModal = ref(false);
const searchQuery = ref('');

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

const fetchDelegations = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/settings/delegations');
    myDelegations.value = res.data.my_delegations || [];
    delegatedToMe.value = res.data.delegated_to_me || [];
    eligibleDelegates.value = res.data.eligible_delegates || [];
  } catch (err) {
    console.error('Failed to load delegations', err);
  } finally {
    loading.value = false;
  }
};

const submitDelegation = async () => {
  try {
    saving.value = true;
    await axios.post('/settings/delegations', form.value);
    showModal.value = false;
    await fetchDelegations();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to create delegation.');
  } finally {
    saving.value = false;
  }
};

const revokeDelegation = async (del) => {
  if (!confirm('Revoke this delegation?')) return;
  try {
    await axios.delete(`/settings/delegations/${del.public_id}`);
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
