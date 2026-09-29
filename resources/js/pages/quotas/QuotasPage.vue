<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-gradient-to-br from-indigo-500/10 to-sky-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
            <PieChart class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              Quotas & Limits Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Enforce monthly meeting booking counts and pooled hours limits across Users and Departments
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="authStore.can('quota.manage') || authStore.isAdmin"
          @click="openModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>Configure Quota</span>
        </button>
      </div>
    </div>

    <!-- Overview Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
          <CalendarDays class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Current Period</div>
          <div class="text-sm font-black text-slate-900 dark:text-white">
            {{ currentPeriod || 'Current Month' }}
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold">
          <Layers class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Quotas</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ quotas.length }}
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
          <CheckCircle2 class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Active Policies</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ quotas.filter(q => getIsActive(q)).length }}
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
          <Building2 class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Department Limits</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ quotas.filter(q => getScopeType(q) === 'department').length }}
          </div>
        </div>
      </div>
    </div>

    <!-- Active Period & Search Toolbar -->
    <div class="glass-card p-3 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
      <div class="relative w-full sm:max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by user name, email, department, or scope..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>

      <div class="flex items-center gap-2 self-end sm:self-auto">
        <button
          @click="scopeFilter = 'all'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="scopeFilter === 'all' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          All
        </button>
        <button
          @click="scopeFilter = 'department'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="scopeFilter === 'department' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          Departments
        </button>
        <button
          @click="scopeFilter = 'user'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="scopeFilter === 'user' ? 'bg-sky-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          Users
        </button>
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

    <!-- Quotas Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
      <div v-if="loading && !quotas.length" class="p-16 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm font-medium">Loading quota policies and consumption meters...</p>
      </div>

      <div v-else-if="!filteredQuotas.length" class="p-16 text-center text-slate-400">
        <PieChart class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-500" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No quotas match your search query.' : 'No quotas configured yet.' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'Users and departments currently have unlimited pooled resource access.' }}
        </p>
        <button
          v-if="!searchQuery && (authStore.can('quota.manage') || authStore.isAdmin)"
          @click="openModal"
          class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition shadow-sm"
        >
          <Plus class="w-4 h-4" />
          <span>Configure First Quota</span>
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Scope Target</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider min-w-[200px]">Meetings / Month</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider min-w-[200px]">Pooled Hours / Month</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-28">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-20">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="q in filteredQuotas"
              :key="getId(q)"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition group"
            >
              <!-- Target Name & Scope Badge -->
              <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                <div class="flex items-center gap-2.5">
                  <div
                    class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                    :class="getScopeType(q) === 'department' ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'bg-sky-500/10 text-sky-600 dark:text-sky-400'"
                  >
                    <Building2 v-if="getScopeType(q) === 'department'" class="w-4 h-4" />
                    <UserIcon v-else class="w-4 h-4" />
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="text-slate-900 dark:text-white font-semibold">{{ getTargetName(q) }}</span>
                      <span
                        class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider"
                        :class="getScopeType(q) === 'department' ? 'bg-indigo-500/10 text-indigo-500' : 'bg-sky-500/10 text-sky-500'"
                      >
                        {{ getScopeType(q) }}
                      </span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Meetings Limit / Used Progress -->
              <td class="py-4 px-4 text-xs">
                <div v-if="getMaxMeetings(q)" class="space-y-1.5 max-w-xs">
                  <div class="flex justify-between text-[11px] font-medium">
                    <span class="text-slate-700 dark:text-slate-300">
                      <strong>{{ getMeetingsUsed(q) }}</strong> / {{ getMaxMeetings(q) }} meetings
                    </span>
                    <span
                      class="font-bold font-mono"
                      :class="getMeetingsPct(q) >= 90 ? 'text-rose-500' : getMeetingsPct(q) >= 75 ? 'text-amber-500' : 'text-emerald-500'"
                    >
                      {{ getMeetingsPct(q) }}%
                    </span>
                  </div>
                  <div class="w-full bg-slate-100 dark:bg-slate-700/60 h-2 rounded-full overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-500"
                      :class="getMeetingsPct(q) >= 90 ? 'bg-rose-500' : getMeetingsPct(q) >= 75 ? 'bg-amber-500' : 'bg-brand-500'"
                      :style="{ width: `${getMeetingsPct(q)}%` }"
                    ></div>
                  </div>
                </div>
                <span v-else class="text-slate-400 italic text-xs">Unlimited Meetings</span>
              </td>

              <!-- Hours Limit / Used Progress -->
              <td class="py-4 px-4 text-xs">
                <div v-if="getMaxHours(q)" class="space-y-1.5 max-w-xs">
                  <div class="flex justify-between text-[11px] font-medium">
                    <span class="text-slate-700 dark:text-slate-300">
                      <strong>{{ getHoursUsed(q) }}h</strong> / {{ getMaxHours(q) }}h pooled
                    </span>
                    <span
                      class="font-bold font-mono"
                      :class="getHoursPct(q) >= 90 ? 'text-rose-500' : getHoursPct(q) >= 75 ? 'text-amber-500' : 'text-indigo-500'"
                    >
                      {{ getHoursPct(q) }}%
                    </span>
                  </div>
                  <div class="w-full bg-slate-100 dark:bg-slate-700/60 h-2 rounded-full overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-500"
                      :class="getHoursPct(q) >= 90 ? 'bg-rose-500' : getHoursPct(q) >= 75 ? 'bg-amber-500' : 'bg-indigo-500'"
                      :style="{ width: `${getHoursPct(q)}%` }"
                    ></div>
                  </div>
                </div>
                <span v-else class="text-slate-400 italic text-xs">Unlimited Hours</span>
              </td>

              <!-- Status Toggle -->
              <td class="py-4 px-4">
                <button
                  @click="toggleQuota(q)"
                  :disabled="!authStore.can('quota.manage') && !authStore.isAdmin"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer"
                  :class="getIsActive(q) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="getIsActive(q) ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                  <span>{{ getIsActive(q) ? 'Active' : 'Disabled' }}</span>
                </button>
              </td>

              <!-- Actions -->
              <td class="py-4 px-4 text-right">
                <button
                  v-if="authStore.can('quota.manage') || authStore.isAdmin"
                  @click="deleteQuota(q)"
                  class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                  title="Remove Quota Limit"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Configure Quota Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
        @click.self="showModal = false"
      >
        <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <PieChart class="w-5 h-5 text-brand-600" />
              <h3 class="font-bold text-base text-slate-900 dark:text-white">Configure Quota Limit</h3>
            </div>
            <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="submitQuota" class="space-y-4 text-xs">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Scope</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="changeScopeType('department')"
                  class="py-2 px-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition"
                  :class="form.scope_type === 'department' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
                >
                  <Building2 class="w-3.5 h-3.5" />
                  <span>Department</span>
                </button>
                <button
                  type="button"
                  @click="changeScopeType('user')"
                  class="py-2 px-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition"
                  :class="form.scope_type === 'user' ? 'bg-sky-600 text-white border-sky-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
                >
                  <UserIcon class="w-3.5 h-3.5" />
                  <span>Single User</span>
                </button>
              </div>
            </div>

            <div v-if="form.scope_type === 'department'">
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Department</label>
              <SearchableSelect
                v-model="form.scope_id"
                :options="departments"
                placeholder="Search department..."
                search-placeholder="Type department name..."
                label-key="name"
                value-key="id"
              />
            </div>

            <div v-else>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select User (10,000+ Employees)</label>
              <SearchableSelect
                v-model="form.scope_id"
                remote-url="/spa/users/search"
                :initial-options="users"
                placeholder="Search employee by name or email..."
                search-placeholder="Type name, email, or designation..."
                label-key="name"
                value-key="id"
                sublabel-key="email"
                badge-key="department.name"
              />
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Meetings / Month</label>
              <input
                v-model.number="form.max_meetings_per_month"
                type="number"
                min="1"
                placeholder="e.g., 20 (leave blank for unlimited)"
                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
              />
              <span class="text-[10px] text-slate-400 mt-0.5 block">Limit number of scheduled meetings in the calendar month</span>
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Pooled Hours / Month</label>
              <input
                v-model.number="form.max_hours_per_month"
                type="number"
                min="1"
                placeholder="e.g., 40 (leave blank for unlimited)"
                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
              />
              <span class="text-[10px] text-slate-400 mt-0.5 block">Limit total meeting duration in hours across the calendar month</span>
            </div>

            <div class="pt-3 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="form.is_active"
                  class="rounded text-brand-600 focus:ring-brand-500"
                />
                <span class="font-semibold text-slate-700 dark:text-slate-300">Active</span>
              </label>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="showModal = false"
                  class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="saving || !form.scope_id"
                  class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition flex items-center gap-1.5"
                >
                  <RefreshCw v-if="saving" class="w-3.5 h-3.5 animate-spin" />
                  <span>Save Quota</span>
                </button>
              </div>
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
import { useAuthStore } from '@/stores/auth';
import {
  Building2,
  CalendarDays,
  CheckCircle2,
  Layers,
  PieChart,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  User as UserIcon,
  X,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const quotas = ref([]);
const departments = ref([]);
const users = ref([]);
const currentPeriod = ref('');
const showModal = ref(false);
const searchQuery = ref('');
const scopeFilter = ref('all');
const statusMessage = ref('');

// Safe data extractors to guarantee the UI never breaks
const getQuotaModel = (item) => item?.model || item || {};
const getQuotaStats = (item) => item?.stats || item || {};
const getScopeType = (item) => getQuotaModel(item).scope_type || 'user';
const getTargetName = (item) => item?.target_name || (getScopeType(item) === 'department' ? `Department #${getQuotaModel(item).scope_id}` : `User #${getQuotaModel(item).scope_id}`);
const getMaxMeetings = (item) => getQuotaModel(item).max_meetings_per_month ?? null;
const getMaxHours = (item) => getQuotaModel(item).max_hours_per_month ?? null;
const getMeetingsUsed = (item) => item?.meetings_used ?? getQuotaStats(item)?.meetings_used ?? 0;
const getMinutesUsed = (item) => item?.minutes_used ?? getQuotaStats(item)?.minutes_used ?? 0;
const getHoursUsed = (item) => item?.hours_used ?? (Math.round((getMinutesUsed(item) / 60) * 10) / 10);

const getMeetingsPct = (item) => {
  const max = getMaxMeetings(item);
  if (!max || max <= 0) return 0;
  const used = getMeetingsUsed(item);
  return Math.min(100, Math.max(0, Math.round((used / max) * 100)));
};

const getHoursPct = (item) => {
  const max = getMaxHours(item);
  if (!max || max <= 0) return 0;
  const used = getMinutesUsed(item);
  return Math.min(100, Math.max(0, Math.round((used / (max * 60)) * 100)));
};

const getIsActive = (item) => {
  const m = getQuotaModel(item);
  return m.is_active !== undefined ? !!m.is_active : true;
};

const getPublicId = (item) => getQuotaModel(item).public_id || '';
const getId = (item) => getQuotaModel(item).id || getPublicId(item) || Math.random();

const filteredQuotas = computed(() => {
  let list = quotas.value;

  if (scopeFilter.value !== 'all') {
    list = list.filter((q) => getScopeType(q) === scopeFilter.value);
  }

  if (!searchQuery.value.trim()) return list;
  const q = searchQuery.value.toLowerCase().trim();

  return list.filter((item) => {
    const target = (getTargetName(item) || '').toLowerCase();
    const scope = (getScopeType(item) || '').toLowerCase();
    return target.includes(q) || scope.includes(q);
  });
});

const form = ref({
  scope_type: 'department',
  scope_id: null,
  max_meetings_per_month: null,
  max_hours_per_month: null,
  is_active: true,
});

const changeScopeType = (type) => {
  form.value.scope_type = type;
  form.value.scope_id = null;
};

const openModal = () => {
  form.value = {
    scope_type: 'department',
    scope_id: null,
    max_meetings_per_month: null,
    max_hours_per_month: null,
    is_active: true,
  };
  showModal.value = true;
};

const fetchQuotas = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/quotas');
    quotas.value = res.data.quotas?.data || res.data.quotas || [];
    departments.value = res.data.departments || [];
    users.value = res.data.users || [];
    currentPeriod.value = res.data.current_period || '';
  } catch (err) {
    console.error('Failed to load quotas via /spa/quotas, falling back', err);
    try {
      const fallback = await axios.get('/admin/quotas');
      quotas.value = fallback.data.quotas?.data || fallback.data.quotas || [];
      departments.value = fallback.data.departments || [];
      users.value = fallback.data.users || [];
      currentPeriod.value = fallback.data.current_period || '';
    } catch (e) {
      console.error('Failed fallback quotas load', e);
    }
  } finally {
    loading.value = false;
  }
};

const submitQuota = async () => {
  if (!form.value.scope_id) {
    alert('Please select a target user or department.');
    return;
  }

  try {
    saving.value = true;
    await axios.post('/spa/quotas', form.value);
    showModal.value = false;
    statusMessage.value = 'Quota policy saved successfully.';
    await fetchQuotas();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to configure quota.');
  } finally {
    saving.value = false;
  }
};

const toggleQuota = async (item) => {
  const publicId = getPublicId(item);
  if (!publicId) return;

  try {
    const res = await axios.post(`/spa/quotas/${publicId}/toggle`);
    const model = getQuotaModel(item);
    model.is_active = res.data.is_active;
    statusMessage.value = `Quota is now ${model.is_active ? 'active' : 'disabled'}.`;
  } catch (err) {
    console.error('Failed to toggle quota', err);
    alert('Failed to toggle quota status.');
  }
};

const deleteQuota = async (item) => {
  const publicId = getPublicId(item);
  const target = getTargetName(item);
  if (!confirm(`Remove quota limit for "${target}"?`)) return;

  try {
    await axios.delete(`/spa/quotas/${publicId}`);
    quotas.value = quotas.value.filter((q) => getPublicId(q) !== publicId);
    statusMessage.value = `Quota limit for "${target}" removed.`;
  } catch (err) {
    alert('Failed to remove quota.');
  }
};

onMounted(() => {
  fetchQuotas();
});
</script>
