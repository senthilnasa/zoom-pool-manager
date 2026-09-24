<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
          <BarChart2 class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Zoom Account Usage & Concurrency Report
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Real-time license concurrency monitoring, peak utilization high-water marks, and per-host airtime breakdown
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <router-link
          to="/app/pools"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200/80 dark:border-slate-700/80 transition cursor-pointer"
        >
          <Layers class="w-4 h-4 text-brand-500" />
          <span>Manage Pools</span>
        </router-link>

        <button
          @click="fetchReport"
          :disabled="loading"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition cursor-pointer disabled:opacity-50"
          title="Refresh Data"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="downloadCsv"
          :disabled="exporting || loading"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer disabled:opacity-50"
        >
          <Download class="w-4 h-4" />
          <span>{{ exporting ? 'Exporting...' : 'Export CSV' }}</span>
        </button>
      </div>
    </div>

    <!-- Filter & Date Preset Toolbar -->
    <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <!-- Date Presets -->
        <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl text-xs font-semibold">
          <button
            type="button"
            v-for="preset in presets"
            :key="preset.id"
            @click="selectPreset(preset.id)"
            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
            :class="selectedPreset === preset.id ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-brand-300 shadow-sm font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            {{ preset.label }}
          </button>
        </div>

        <!-- Custom Date Range Inputs -->
        <div v-if="selectedPreset === 'custom'" class="flex items-center gap-2 text-xs">
          <div class="flex items-center gap-1.5">
            <span class="text-slate-400">From:</span>
            <input
              type="date"
              v-model="customStartDate"
              @change="applyCustomDates"
              class="px-2 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500"
            />
          </div>
          <div class="flex items-center gap-1.5">
            <span class="text-slate-400">To:</span>
            <input
              type="date"
              v-model="customEndDate"
              @change="applyCustomDates"
              class="px-2 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500"
            />
          </div>
        </div>

        <!-- Pool Selector & Account Search -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
          <select
            v-model="selectedPoolId"
            @change="fetchReport"
            class="text-xs px-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          >
            <option value="">All Resource Pools</option>
            <option v-for="pool in availablePools" :key="pool.id" :value="pool.id">
              {{ pool.name }}
            </option>
          </select>

          <div class="relative w-48 sm:w-56">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchQuery"
              @input="debounceSearch"
              type="text"
              placeholder="Search account / host..."
              class="w-full pl-8 pr-2.5 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all shadow-sm"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
      <!-- Card 1: Total Licenses -->
      <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-[11px] font-bold uppercase tracking-wider">Total Accounts</span>
          <Users class="w-4 h-4 text-brand-500" />
        </div>
        <div class="text-2xl font-black text-slate-900 dark:text-white">
          {{ summary.total_accounts || 0 }}
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1">
          <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ summary.managed_accounts || 0 }}</span> managed in pools
        </div>
      </div>

      <!-- Card 2: Live In-Use Concurrency -->
      <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-[11px] font-bold uppercase tracking-wider">Live In-Use Now</span>
          <div class="relative flex items-center justify-center">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping absolute"></span>
            <span class="w-2 h-2 rounded-full bg-emerald-500 relative"></span>
          </div>
        </div>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
          {{ summary.currently_active_accounts || 0 }}
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400">
          {{ summary.current_concurrency_rate || 0 }}% current concurrency
        </div>
      </div>

      <!-- Card 3: Peak Concurrency (High-Water Mark) -->
      <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-[11px] font-bold uppercase tracking-wider">Peak Concurrency</span>
          <TrendingUp class="w-4 h-4 text-indigo-500" />
        </div>
        <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
          {{ summary.peak_concurrent_accounts || 0 }}
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400">
          {{ summary.peak_concurrency_rate || 0 }}% max capacity reached
        </div>
      </div>

      <!-- Card 4: Spare Headroom / Buffer -->
      <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-[11px] font-bold uppercase tracking-wider">Spare Headroom</span>
          <ShieldCheck class="w-4 h-4 text-sky-500" />
        </div>
        <div class="text-2xl font-black text-slate-900 dark:text-white">
          {{ summary.concurrency_headroom || 0 }} <span class="text-xs font-normal text-slate-400">accounts</span>
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400">
          <span class="font-bold text-sky-600 dark:text-sky-400">{{ summary.buffer_percentage || 0 }}%</span> safety buffer at peak
        </div>
      </div>

      <!-- Card 5: Meeting Volume & Airtime -->
      <div class="glass-card rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/60 space-y-1">
        <div class="flex items-center justify-between text-slate-400">
          <span class="text-[11px] font-bold uppercase tracking-wider">Total Hosted</span>
          <Clock class="w-4 h-4 text-amber-500" />
        </div>
        <div class="text-2xl font-black text-slate-900 dark:text-white">
          {{ summary.total_hours || 0 }} <span class="text-xs font-normal text-slate-400">hrs</span>
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400">
          Across {{ summary.total_meetings || 0 }} completed meetings
        </div>
      </div>
    </div>

    <!-- Capacity Right-Sizing Insights Banner -->
    <div
      v-if="insights"
      class="p-4 rounded-2xl border transition-all"
      :class="{
        'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/50 text-emerald-900 dark:text-emerald-100': insights.status_level === 'optimal',
        'bg-amber-50/70 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/50 text-amber-900 dark:text-amber-100': insights.status_level === 'warning',
        'bg-blue-50/70 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800/50 text-blue-900 dark:text-blue-100': insights.status_level === 'healthy',
        'bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300': insights.status_level === 'neutral',
      }"
    >
      <div class="flex items-start gap-3">
        <div class="p-2 rounded-xl bg-white dark:bg-slate-800 shadow-sm shrink-0 mt-0.5">
          <Lightbulb class="w-5 h-5 text-amber-500" />
        </div>
        <div class="space-y-1 flex-1">
          <div class="font-bold text-sm">{{ insights.headline }}</div>
          <p class="text-xs opacity-90 leading-relaxed">{{ insights.recommendation }}</p>
        </div>
        <div v-if="insights.recommended_licenses > 0" class="shrink-0 text-right hidden sm:block">
          <div class="text-[10px] uppercase font-bold tracking-wider opacity-70">Recommended Pool</div>
          <div class="text-lg font-black">{{ insights.recommended_licenses }} licenses</div>
        </div>
      </div>
    </div>

    <!-- Timeline / Concurrency Trend Visualizer -->
    <div class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Activity class="w-4 h-4 text-brand-500" />
            <span>Concurrency & Demand Timeline</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Simultaneous accounts active and meeting volume over the selected timeframe
          </p>
        </div>

        <div class="flex items-center gap-4 text-xs">
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-brand-500"></span>
            <span class="text-slate-600 dark:text-slate-300">Peak Concurrency</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-slate-300 dark:bg-slate-700"></span>
            <span class="text-slate-600 dark:text-slate-300">Meeting Count</span>
          </div>
        </div>
      </div>

      <!-- Visual Bar Chart -->
      <div v-if="loading && !timeline.length" class="py-12 text-center text-slate-400">
        <RefreshCw class="w-6 h-6 mx-auto mb-2 animate-spin text-brand-500" />
        <p class="text-xs">Analyzing timeline metrics...</p>
      </div>

      <div v-else-if="!timeline.length" class="py-8 text-center text-slate-400 text-xs">
        No meeting activity recorded in this date range.
      </div>

      <div v-else class="space-y-2">
        <div class="h-44 flex items-end gap-1 sm:gap-2 pt-6 pb-2 px-2 overflow-x-auto custom-scrollbar">
          <div
            v-for="(point, idx) in timeline"
            :key="idx"
            class="flex-1 min-w-[28px] max-w-[48px] h-full flex flex-col justify-end items-center group relative cursor-pointer"
          >
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:flex flex-col p-2 bg-slate-900 text-white rounded-lg shadow-xl text-[10px] whitespace-nowrap z-20 pointer-events-none">
              <span class="font-bold">{{ point.date }}</span>
              <span>Peak Concurrency: <strong>{{ point.peak_concurrency }} accounts</strong></span>
              <span>Meetings: <strong>{{ point.meetings_count }}</strong></span>
              <span>Hours: <strong>{{ point.total_hours }} hrs</strong></span>
            </div>

            <!-- Peak Concurrency Bar -->
            <div class="w-full flex items-end justify-center gap-0.5 h-full">
              <!-- Concurrency Bar -->
              <div
                class="w-full bg-brand-500 group-hover:bg-brand-600 rounded-t transition-all duration-300"
                :style="{
                  height: maxTimelineVal > 0 ? `${Math.max(4, (point.peak_concurrency / maxTimelineVal) * 100)}%` : '4%'
                }"
              ></div>
            </div>

            <!-- Label -->
            <span class="text-[9px] text-slate-400 mt-1 truncate w-full text-center">
              {{ point.label }}
            </span>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
          <span>Scale: 0 to {{ maxTimelineVal }} peak concurrent accounts</span>
          <span>Timeline points: {{ timeline.length }} intervals</span>
        </div>
      </div>
    </div>

    <!-- Zoom Accounts Usage Breakdown Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 space-y-4">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Per-Account Usage & Airtime</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 font-mono text-slate-600 dark:text-slate-300">
              {{ accounts.length }} accounts
            </span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Individual license consumption, meeting allocation frequency, and current occupancy
          </p>
        </div>

        <div class="flex items-center gap-2 text-xs">
          <span class="text-slate-400">Sort by:</span>
          <select
            v-model="sortBy"
            class="px-2.5 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200 font-semibold focus:ring-2 focus:ring-brand-500"
          >
            <option value="hours_desc">Hours (Highest First)</option>
            <option value="meetings_desc">Meetings (Most First)</option>
            <option value="utilization_desc">Utilization % (Highest First)</option>
            <option value="name_asc">Account Name (A-Z)</option>
          </select>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !accounts.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Calculating Zoom account usage metrics...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!sortedAccounts.length" class="p-12 text-center text-slate-400">
        <Users class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Host Accounts Found</p>
        <p class="text-xs text-slate-400 mt-1">Try adjusting your search criteria or pool selection.</p>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Host Account</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Live Status</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Pools</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Meetings</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Total Hours</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Est. Utilization</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Last Active</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="acc in sortedAccounts"
              :key="acc.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <!-- Account Name / Email -->
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                  <span>{{ acc.name }}</span>
                  <span
                    v-if="acc.is_underutilized"
                    class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    title="Account had zero or very low meetings in this period"
                  >
                    Idle / Low
                  </span>
                </div>
                <div class="text-[11px] text-slate-400 font-mono">{{ acc.email }}</div>
                <div class="flex items-center gap-1.5 mt-1">
                  <span class="text-[10px] text-slate-500 font-medium">{{ acc.capacity }} seats</span>
                  <span v-if="acc.capabilities.cloud_recording" class="text-[9px] px-1 py-0.2 bg-blue-500/10 text-blue-500 rounded font-medium">Rec</span>
                  <span v-if="acc.capabilities.ai_companion" class="text-[9px] px-1 py-0.2 bg-purple-500/10 text-purple-500 rounded font-medium">AI</span>
                  <span v-if="acc.capabilities.webinar" class="text-[9px] px-1 py-0.2 bg-emerald-500/10 text-emerald-500 rounded font-medium">Webinar</span>
                </div>
              </td>

              <!-- Live Status -->
              <td class="py-3 px-4">
                <span
                  v-if="acc.current_status === 'in_meeting'"
                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  In Meeting
                </span>
                <span
                  v-else-if="acc.current_status === 'idle'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500"
                >
                  Idle (Available)
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600"
                >
                  Excluded
                </span>

                <div v-if="acc.current_meeting" class="text-[10px] text-slate-400 mt-1 truncate max-w-[140px]" :title="acc.current_meeting.title">
                  {{ acc.current_meeting.title }}
                </div>
              </td>

              <!-- Pools -->
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                <div v-if="acc.pools && acc.pools.length" class="flex flex-wrap gap-1 max-w-xs">
                  <span
                    v-for="p in acc.pools"
                    :key="p.id"
                    class="px-2 py-0.5 rounded bg-brand-50 dark:bg-brand-950/60 border border-brand-200/50 dark:border-brand-800/50 text-[10px] text-brand-700 dark:text-brand-300 font-medium"
                  >
                    {{ p.name }}
                  </span>
                </div>
                <span v-else class="text-[11px] text-slate-400 italic">No pool</span>
              </td>

              <!-- Meetings Count -->
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                {{ acc.meetings_count }}
              </td>

              <!-- Total Hours -->
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900 dark:text-white">{{ acc.total_hours }} hrs</div>
                <div class="text-[10px] text-slate-400">{{ acc.total_minutes }} mins</div>
              </td>

              <!-- Utilization Progress Bar -->
              <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                  <div class="w-20 bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden shrink-0">
                    <div
                      class="h-full rounded-full transition-all duration-500"
                      :class="{
                        'bg-emerald-500': acc.utilization_rate < 50,
                        'bg-blue-500': acc.utilization_rate >= 50 && acc.utilization_rate < 80,
                        'bg-amber-500': acc.utilization_rate >= 80,
                      }"
                      :style="{ width: `${Math.min(100, Math.max(3, acc.utilization_rate))}%` }"
                    ></div>
                  </div>
                  <span class="font-bold text-[11px] text-slate-700 dark:text-slate-300">
                    {{ acc.utilization_rate }}%
                  </span>
                </div>
              </td>

              <!-- Last Active Date -->
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                <span v-if="acc.last_used_at">{{ formatDate(acc.last_used_at) }}</span>
                <span v-else class="text-slate-400 italic">Never</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import {
  BarChart2,
  Users,
  Activity,
  TrendingUp,
  ShieldCheck,
  Clock,
  Lightbulb,
  Search,
  RefreshCw,
  Download,
  Layers,
} from 'lucide-vue-next';

const toast = useToastStore();

const loading = ref(false);
const exporting = ref(false);
const selectedPreset = ref('30d');
const customStartDate = ref('');
const customEndDate = ref('');
const selectedPoolId = ref('');
const searchQuery = ref('');
const sortBy = ref('hours_desc');

const summary = ref({});
const insights = ref(null);
const timeline = ref([]);
const accounts = ref([]);
const availablePools = ref([]);

const presets = [
  { id: 'today', label: 'Today' },
  { id: '7d', label: 'Last 7 Days' },
  { id: '30d', label: 'Last 30 Days' },
  { id: 'this_month', label: 'This Month' },
  { id: '90d', label: 'Last 90 Days' },
  { id: 'custom', label: 'Custom' },
];

let debounceTimeout = null;
function debounceSearch() {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    fetchReport();
  }, 300);
}

function calculatePresetDates(presetId) {
  const end = new Date();
  const start = new Date();

  if (presetId === 'today') {
    // start is today
  } else if (presetId === '7d') {
    start.setDate(end.getDate() - 7);
  } else if (presetId === '30d') {
    start.setDate(end.getDate() - 30);
  } else if (presetId === 'this_month') {
    start.setDate(1);
  } else if (presetId === '90d') {
    start.setDate(end.getDate() - 90);
  }

  const format = (d) => d.toISOString().split('T')[0];
  return { start: format(start), end: format(end) };
}

function selectPreset(presetId) {
  selectedPreset.value = presetId;
  if (presetId === 'custom') {
    const dates = calculatePresetDates('30d');
    customStartDate.value = dates.start;
    customEndDate.value = dates.end;
  } else {
    fetchReport();
  }
}

function applyCustomDates() {
  if (customStartDate.value && customEndDate.value) {
    fetchReport();
  }
}

const maxTimelineVal = computed(() => {
  if (!timeline.value.length) return summary.value.total_accounts || 10;
  const maxRecorded = Math.max(...timeline.value.map((t) => t.peak_concurrency || 0));
  return Math.max(maxRecorded, summary.value.total_accounts || 10);
});

const sortedAccounts = computed(() => {
  const list = [...accounts.value];
  if (sortBy.value === 'hours_desc') {
    return list.sort((a, b) => b.total_minutes - a.total_minutes);
  }
  if (sortBy.value === 'meetings_desc') {
    return list.sort((a, b) => b.meetings_count - a.meetings_count);
  }
  if (sortBy.value === 'utilization_desc') {
    return list.sort((a, b) => b.utilization_rate - a.utilization_rate);
  }
  if (sortBy.value === 'name_asc') {
    return list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  }
  return list;
});

async function fetchReport() {
  loading.value = true;
  try {
    let startDate = null;
    let endDate = null;

    if (selectedPreset.value === 'custom') {
      startDate = customStartDate.value;
      endDate = customEndDate.value;
    } else {
      const dates = calculatePresetDates(selectedPreset.value);
      startDate = dates.start;
      endDate = dates.end;
    }

    const params = {
      start_date: startDate,
      end_date: endDate,
    };

    if (selectedPoolId.value) {
      params.pool_id = selectedPoolId.value;
    }

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const response = await axios.get('/spa/reports/zoom-usage', { params });
    summary.value = response.data.summary || {};
    insights.value = response.data.insights || null;
    timeline.value = response.data.timeline || [];
    accounts.value = response.data.accounts || [];
    availablePools.value = response.data.available_pools || [];
  } catch (error) {
    toast.error('Failed to load Zoom account usage report');
  } finally {
    loading.value = false;
  }
}

function downloadCsv() {
  exporting.value = true;
  let startDate = null;
  let endDate = null;

  if (selectedPreset.value === 'custom') {
    startDate = customStartDate.value;
    endDate = customEndDate.value;
  } else {
    const dates = calculatePresetDates(selectedPreset.value);
    startDate = dates.start;
    endDate = dates.end;
  }

  const query = new URLSearchParams();
  if (startDate) query.append('start_date', startDate);
  if (endDate) query.append('end_date', endDate);
  if (selectedPoolId.value) query.append('pool_id', selectedPoolId.value);
  if (searchQuery.value.trim()) query.append('search', searchQuery.value.trim());

  window.location.href = `/spa/reports/zoom-usage/export?${query.toString()}`;
  setTimeout(() => {
    exporting.value = false;
  }, 1500);
}

function formatDate(isoString) {
  if (!isoString) return '';
  const d = new Date(isoString);
  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

onMounted(() => {
  fetchReport();
});
</script>
