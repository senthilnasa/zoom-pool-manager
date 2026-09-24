<template>
  <div class="space-y-6">
    <!-- Top Breadcrumb & Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-1">
      <div class="flex items-center gap-3.5">
        <router-link
          to="/app/users"
          class="p-2.5 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/80 dark:border-slate-800 transition shadow-xs"
          title="Back to User Directory & Access Control"
        >
          <ArrowLeft class="w-4 h-4" />
        </router-link>
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-400">
            <router-link to="/app/users" class="hover:text-brand-600 transition">User Directory & Access Control</router-link>
            <span>/</span>
            <span class="text-slate-600 dark:text-slate-300">User Profile & Level Summary</span>
          </div>
          <div class="flex items-center gap-3 mt-1 flex-wrap">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
              {{ user?.name || 'User Profile' }}
            </h1>
            <span
              v-if="user"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
              :class="user.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
              <span>{{ user.is_active ? 'Active' : 'Disabled' }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Header Action Controls -->
      <div class="flex items-center gap-2 flex-wrap">
        <button
          @click="fetchProfile"
          class="p-2.5 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/70 dark:border-slate-800 transition shadow-xs"
          title="Refresh Profile"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="user"
          @click="bookOnBehalf"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer"
        >
          <CalendarPlus class="w-4 h-4" />
          <span>Book on Behalf</span>
        </button>

        <button
          v-if="user && (authStore.can('user.manage') || authStore.isAdmin)"
          @click="openEditModal"
          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-800 transition shadow-xs"
        >
          <Edit3 class="w-4 h-4" />
          <span>Edit Account</span>
        </button>

        <button
          v-if="user && (authStore.can('user.manage') || authStore.isAdmin)"
          @click="toggleUserActive"
          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold border transition shadow-xs"
          :class="user.is_active ? 'border-amber-500/30 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30' : 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
        >
          <Power class="w-4 h-4" />
          <span>{{ user.is_active ? 'Deactivate' : 'Activate' }}</span>
        </button>

        <button
          v-if="user"
          @click="permissionsModal = true"
          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200/80 dark:hover:bg-slate-700 transition"
        >
          <ShieldCheck class="w-4 h-4 text-brand-500" />
          <span>Permissions</span>
        </button>
      </div>
    </div>

    <!-- Feedback Notice Banner -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl flex items-center justify-between text-xs font-semibold transition-all shadow-xs"
      :class="feedbackError ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="!feedbackError" class="w-4 h-4 shrink-0 text-emerald-500" />
        <AlertCircle v-else class="w-4 h-4 shrink-0 text-rose-500" />
        <span>{{ feedback }}</span>
      </div>
      <button @click="feedback = ''" class="hover:underline font-bold">Dismiss</button>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !user" class="p-20 text-center text-slate-400 glass-card rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
      <RefreshCw class="w-10 h-10 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Loading User Profile & Level Summary...</p>
      <p class="text-xs text-slate-400 mt-1">Aggregating meeting requests, approval history, and resource utilization</p>
    </div>

    <!-- User Profile & Summary Dashboard -->
    <div v-else-if="user" class="space-y-6">
      <!-- 1. Hero Identity Card -->
      <div class="glass-card p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <!-- Left: User Identity Details -->
          <div class="flex items-start sm:items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xl shadow-md shadow-brand-500/20 shrink-0">
              {{ (user.name || 'U').charAt(0).toUpperCase() }}
            </div>

            <div class="space-y-1 min-w-0">
              <div class="flex items-center gap-2.5 flex-wrap">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white truncate">{{ user.name }}</h2>
                <span
                  v-if="user.designation"
                  class="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60"
                >
                  {{ user.designation }}
                </span>
              </div>

              <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
                <a :href="'mailto:' + user.email" class="flex items-center gap-1.5 hover:text-brand-600 dark:hover:text-brand-400 transition">
                  <Mail class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ user.email }}</span>
                </a>

                <span class="flex items-center gap-1.5">
                  <Building2 class="w-3.5 h-3.5 text-slate-400" />
                  <span class="font-medium text-slate-700 dark:text-slate-300">{{ user.department?.name || 'No Department' }}</span>
                </span>

                <span class="flex items-center gap-1.5">
                  <Clock class="w-3.5 h-3.5 text-slate-400" />
                  <span>TZ: {{ user.timezone || 'Asia/Kolkata' }}</span>
                </span>
              </div>

              <!-- Metadata Badges -->
              <div class="flex items-center gap-2 pt-1 flex-wrap">
                <span
                  v-for="r in user.roles"
                  :key="r"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20"
                >
                  {{ r }}
                </span>
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold"
                  :class="user.mfa_enabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 border border-slate-500/20'"
                >
                  {{ user.mfa_enabled ? '2FA Active' : 'No 2FA' }}
                </span>
                <span class="text-[11px] text-slate-400 font-mono">
                  ID: {{ user.public_id || user.id }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Membership & Governance Info -->
          <div class="flex items-center gap-8 border-t lg:border-t-0 lg:border-l border-slate-200/80 dark:border-slate-800 pt-4 lg:pt-0 lg:pl-8 shrink-0">
            <div>
              <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Member Since</div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ formatDate(user.created_at) }}</div>
              <div class="text-[11px] text-slate-400 mt-1">Last login: {{ user.last_login_at ? formatDate(user.last_login_at) : 'Never' }}</div>
            </div>

            <div>
              <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Effective Rights</div>
              <div class="text-xs font-bold text-brand-600 dark:text-brand-400 mt-0.5">
                {{ isSuperAdmin ? 'Full Super Admin' : `${permissions.length} actions allowed` }}
              </div>
              <button
                @click="permissionsModal = true"
                class="text-[11px] text-brand-600 dark:text-brand-400 hover:underline font-semibold mt-1 flex items-center gap-1 cursor-pointer"
              >
                <span>Inspect matrix</span>
                <span>&rarr;</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. USER LEVEL SUMMARY & ACTIVITY METRICS -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <BarChart2 class="w-4 h-4 text-brand-500" />
            <h2 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
              User Level Summary & Activity Metrics
            </h2>
          </div>
          <span class="text-xs text-slate-400">Lifetime usage & request totals</span>
        </div>

        <!-- 4 Top KPI Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Total Requests -->
          <div class="glass-card p-5 border border-slate-200/70 dark:border-slate-800/80 flex flex-col justify-between hover:border-brand-500/40 transition-all">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Requests</span>
              <div class="w-8 h-8 rounded-xl bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 border border-brand-200/50 dark:border-brand-800/50 flex items-center justify-center shrink-0">
                <Video class="w-4 h-4" />
              </div>
            </div>
            <div class="my-2.5">
              <div class="text-3xl font-extrabold text-slate-900 dark:text-white leading-none">
                {{ summary.total_requests.toLocaleString() }}
              </div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              All pooled Zoom booking requests
            </div>
          </div>

          <!-- Scheduled / Active -->
          <div class="glass-card p-5 border border-slate-200/70 dark:border-slate-800/80 flex flex-col justify-between hover:border-emerald-500/40 transition-all">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Scheduled / Active</span>
              <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50 flex items-center justify-center shrink-0">
                <Calendar class="w-4 h-4" />
              </div>
            </div>
            <div class="my-2.5">
              <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 leading-none">
                {{ (summary.scheduled_requests + summary.in_progress_requests).toLocaleString() }}
              </div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              {{ summary.scheduled_requests }} upcoming &bull; {{ summary.in_progress_requests }} live
            </div>
          </div>

          <!-- Completed Requests -->
          <div class="glass-card p-5 border border-slate-200/70 dark:border-slate-800/80 flex flex-col justify-between hover:border-sky-500/40 transition-all">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Completed Sessions</span>
              <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-200/50 dark:border-sky-800/50 flex items-center justify-center shrink-0">
                <CheckCircle2 class="w-4 h-4" />
              </div>
            </div>
            <div class="my-2.5">
              <div class="text-3xl font-extrabold text-sky-600 dark:text-sky-400 leading-none">
                {{ summary.completed_requests.toLocaleString() }}
              </div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              Successfully hosted meetings
            </div>
          </div>

          <!-- Total Pool Time -->
          <div class="glass-card p-5 border border-slate-200/70 dark:border-slate-800/80 flex flex-col justify-between hover:border-purple-500/40 transition-all">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Pool Time</span>
              <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border border-purple-200/50 dark:border-purple-800/50 flex items-center justify-center shrink-0">
                <Clock class="w-4 h-4" />
              </div>
            </div>
            <div class="my-2.5">
              <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 leading-none">
                {{ summary.total_hours }}<span class="text-base font-bold text-slate-400"> hrs</span>
              </div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              {{ summary.cancelled_requests }} cancelled requests
            </div>
          </div>
        </div>

        <!-- 3 Secondary Cards (Approvals, Cloud Recordings, Attendance) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Card 1: Workflow Approvals -->
          <div class="glass-card p-4.5 border border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center justify-center shrink-0">
                <FileCheck class="w-5 h-5" />
              </div>
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Workflow Approvals</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  {{ summary.approvals_submitted }} submitted ({{ summary.approvals_approved }} approved)
                </div>
              </div>
            </div>
            <div class="shrink-0 pl-2">
              <span
                v-if="summary.approvals_pending > 0"
                class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
              >
                {{ summary.approvals_pending }} Pending
              </span>
              <span v-else class="text-xl font-extrabold text-slate-900 dark:text-white">
                {{ summary.approvals_submitted }}
              </span>
            </div>
          </div>

          <!-- Card 2: Cloud Recordings -->
          <div class="glass-card p-4.5 border border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 flex items-center justify-center shrink-0">
                <Film class="w-5 h-5" />
              </div>
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Cloud Recordings</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  {{ summary.recordings_mb }} MB total storage
                </div>
              </div>
            </div>
            <div class="shrink-0 pl-2 text-xl font-extrabold text-slate-900 dark:text-white">
              {{ summary.recordings_count }}
            </div>
          </div>

          <!-- Card 3: Attendance Telemetry -->
          <div class="glass-card p-4.5 border border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20 flex items-center justify-center shrink-0">
                <Users class="w-5 h-5" />
              </div>
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Attendance Sessions</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  Participant telemetry logs
                </div>
              </div>
            </div>
            <div class="shrink-0 pl-2 text-xl font-extrabold text-slate-900 dark:text-white">
              {{ summary.attendance_sessions_count }}
            </div>
          </div>
        </div>
      </div>

      <!-- Department / Personal Quota Card (if applicable) -->
      <div
        v-if="quota"
        class="glass-card p-5 border border-slate-200/80 dark:border-slate-800 space-y-3"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <Gauge class="w-4 h-4 text-brand-500" />
            <span class="text-xs font-bold text-slate-900 dark:text-white">
              {{ quota.scope_type === 'user' ? 'User Allocated Quota' : `${user.department?.name || 'Department'} Quota Allowance` }}
            </span>
          </div>
          <span class="text-xs text-slate-400 font-medium">Monthly Allocation</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <!-- Hours Quota Bar -->
          <div v-if="quota.max_hours_per_month" class="space-y-1.5">
            <div class="flex justify-between text-slate-600 dark:text-slate-300">
              <span>Pool Hours Used This Month</span>
              <span class="font-bold">{{ quota.current_month_hours }} / {{ quota.max_hours_per_month }} hrs</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="quotaPercentage(quota.current_month_hours, quota.max_hours_per_month) > 85 ? 'bg-rose-500' : 'bg-brand-500'"
                :style="{ width: Math.min(100, quotaPercentage(quota.current_month_hours, quota.max_hours_per_month)) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Meetings Quota Bar -->
          <div v-if="quota.max_meetings_per_month" class="space-y-1.5">
            <div class="flex justify-between text-slate-600 dark:text-slate-300">
              <span>Meetings Count This Month</span>
              <span class="font-bold">{{ quota.current_month_meetings }} / {{ quota.max_meetings_per_month }} sessions</span>
            </div>
            <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="quotaPercentage(quota.current_month_meetings, quota.max_meetings_per_month) > 85 ? 'bg-rose-500' : 'bg-sky-500'"
                :style="{ width: Math.min(100, quotaPercentage(quota.current_month_meetings, quota.max_meetings_per_month)) + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. TABBED SECTIONS -->
      <div class="space-y-4">
        <!-- Tab Bar -->
        <div class="flex border-b border-slate-200/80 dark:border-slate-800 gap-6 overflow-x-auto">
          <button
            @click="activeTab = 'meetings'"
            class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2 shrink-0 cursor-pointer"
            :class="activeTab === 'meetings' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <Video class="w-4 h-4" />
            <span>Meeting Requests</span>
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              {{ meetings.length }}
            </span>
          </button>

          <button
            @click="activeTab = 'approvals'"
            class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2 shrink-0 cursor-pointer"
            :class="activeTab === 'approvals' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <FileCheck class="w-4 h-4" />
            <span>Approval History</span>
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              {{ approvals.length }}
            </span>
          </button>

          <button
            @click="activeTab = 'recordings'"
            class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2 shrink-0 cursor-pointer"
            :class="activeTab === 'recordings' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <Film class="w-4 h-4" />
            <span>Cloud Recordings</span>
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              {{ recordings.length }}
            </span>
          </button>

          <button
            @click="activeTab = 'permissions'"
            class="pb-3 text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 border-b-2 shrink-0 cursor-pointer"
            :class="activeTab === 'permissions' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <ShieldCheck class="w-4 h-4" />
            <span>Roles & Permissions</span>
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              {{ isSuperAdmin ? 'ALL' : permissions.length }}
            </span>
          </button>
        </div>

        <!-- TAB 1: MEETINGS & REQUESTS -->
        <div v-if="activeTab === 'meetings'" class="space-y-4">
          <!-- Filters & Actions Toolbar -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input
                v-model="meetingFilterSearch"
                type="text"
                placeholder="Filter meetings by title or ID..."
                class="glass-input pl-10 text-xs w-full"
              />
            </div>

            <div class="flex items-center gap-2.5 w-full sm:w-auto">
              <select
                v-model="meetingFilterStatus"
                class="glass-input text-xs font-medium cursor-pointer"
              >
                <option value="">All Statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="started">Started / Live</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>

              <button
                @click="bookOnBehalf"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-xs transition shrink-0 cursor-pointer"
              >
                <Plus class="w-3.5 h-3.5" />
                <span>New Request</span>
              </button>
            </div>
          </div>

          <!-- Meetings Table -->
          <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800">
            <div v-if="!filteredMeetings.length" class="p-14 text-center text-slate-400">
              <Video class="w-10 h-10 mx-auto mb-2.5 opacity-40" />
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300">No Meetings Found</p>
              <p class="text-xs text-slate-400 mt-1">This user has not requested or hosted any meetings matching the filter.</p>
              <button
                @click="bookOnBehalf"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-xs transition mt-4 cursor-pointer"
              >
                <Plus class="w-3.5 h-3.5" />
                <span>Book Meeting on Behalf</span>
              </button>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Meeting Title</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Scheduled Time</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Duration</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Resource Pool</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr
                    v-for="m in filteredMeetings"
                    :key="m.id"
                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                  >
                    <td class="py-3.5 px-4">
                      <div class="font-bold text-slate-900 dark:text-white text-sm">{{ m.title }}</div>
                      <div class="text-[11px] font-mono text-slate-400 mt-0.5">ID: {{ m.public_id }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                      <div class="font-medium">{{ formatDateTime(m.starts_at) }}</div>
                      <div class="text-[11px] text-slate-400 mt-0.5">to {{ formatTime(m.ends_at) }}</div>
                    </td>
                    <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-300">
                      {{ formatDuration(m.duration_minutes) }}
                    </td>
                    <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700 text-xs font-medium">
                        <Layers class="w-3.5 h-3.5 text-slate-400" />
                        <span>{{ m.resource_name }}</span>
                      </span>
                    </td>
                    <td class="py-3.5 px-4">
                      <span
                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider inline-block"
                        :class="getStatusBadgeClass(m.status)"
                      >
                        {{ m.status }}
                      </span>
                    </td>
                    <td class="py-3.5 px-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <router-link
                          :to="'/app/meetings?search=' + encodeURIComponent(m.title)"
                          class="px-2.5 py-1 rounded-lg text-xs font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-950/40 transition"
                        >
                          View in Meetings
                        </router-link>
                        <a
                          v-if="m.join_url"
                          :href="m.join_url"
                          target="_blank"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 border border-emerald-200/60 dark:border-emerald-800/60 transition"
                        >
                          <span>Join</span>
                          <ExternalLink class="w-3 h-3" />
                        </a>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 2: APPROVAL HISTORY -->
        <div v-if="activeTab === 'approvals'" class="space-y-4">
          <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800">
            <div v-if="!approvals.length" class="p-14 text-center text-slate-400">
              <FileCheck class="w-10 h-10 mx-auto mb-2.5 opacity-40" />
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300">No Approvals on Record</p>
              <p class="text-xs text-slate-400 mt-1">This user's bookings have not triggered institutional approval workflows.</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Associated Meeting</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Workflow Step</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Decision</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Approver</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Decided At</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Notes</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr
                    v-for="appr in approvals"
                    :key="appr.id"
                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                  >
                    <td class="py-3.5 px-4">
                      <div class="font-bold text-slate-900 dark:text-white text-sm">{{ appr.meeting?.title || 'Meeting #' + appr.meeting_id }}</div>
                      <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ appr.meeting?.public_id }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-medium">
                      Step {{ appr.step || 1 }}
                    </td>
                    <td class="py-3.5 px-4">
                      <span
                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider inline-block"
                        :class="getDecisionBadgeClass(appr.decision)"
                      >
                        {{ appr.decision }}
                      </span>
                    </td>
                    <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300 font-medium">
                      {{ appr.approver?.name || 'Assigned Approver' }}
                    </td>
                    <td class="py-3.5 px-4 text-slate-500">
                      {{ appr.decided_at ? formatDateTime(appr.decided_at) : 'Pending Review' }}
                    </td>
                    <td class="py-3.5 px-4 text-slate-500 italic max-w-xs truncate">
                      {{ appr.decision_notes || '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 3: CLOUD RECORDINGS -->
        <div v-if="activeTab === 'recordings'" class="space-y-4">
          <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800">
            <div v-if="!recordings.length" class="p-14 text-center text-slate-400">
              <Film class="w-10 h-10 mx-auto mb-2.5 opacity-40" />
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300">No Cloud Recordings</p>
              <p class="text-xs text-slate-400 mt-1">This user currently owns no synchronized Zoom cloud recordings.</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Recording Topic</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Recorded Date</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Duration</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">File Size</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="py-3.5 px-4 font-semibold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr
                    v-for="rec in recordings"
                    :key="rec.id"
                    class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                  >
                    <td class="py-3.5 px-4">
                      <div class="font-bold text-slate-900 dark:text-white text-sm">{{ rec.topic || rec.meeting?.title || 'Recording' }}</div>
                      <div class="text-[11px] text-slate-400 font-mono mt-0.5">{{ rec.public_id }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                      {{ formatDateTime(rec.recording_start || rec.created_at) }}
                    </td>
                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-medium">
                      {{ rec.duration_minutes ? rec.duration_minutes + ' mins' : '—' }}
                    </td>
                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 font-medium">
                      {{ rec.file_size_bytes ? formatBytes(rec.file_size_bytes) : '—' }}
                    </td>
                    <td class="py-3.5 px-4">
                      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                        {{ rec.status || 'Active' }}
                      </span>
                    </td>
                    <td class="py-3.5 px-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <a
                          v-if="rec.play_url || rec.share_url"
                          :href="rec.play_url || rec.share_url"
                          target="_blank"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-950/40 transition"
                        >
                          <Play class="w-3 h-3" />
                          <span>Playback</span>
                        </a>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 4: ROLES & PERMISSIONS -->
        <div v-if="activeTab === 'permissions'" class="space-y-4">
          <div class="glass-card p-6 border border-slate-200/70 dark:border-slate-800 space-y-6">
            <!-- Assigned Roles -->
            <div>
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Assigned Institutional Roles</h3>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="r in user.roles"
                  :key="r"
                  class="px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20 flex items-center gap-1.5"
                >
                  <ShieldCheck class="w-3.5 h-3.5" />
                  <span>{{ r }}</span>
                </span>
              </div>
            </div>

            <!-- Super Admin Notice -->
            <div
              v-if="isSuperAdmin"
              class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs"
            >
              <div class="font-bold flex items-center gap-1.5">
                <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                <span>Super Administrator Privilege Active</span>
              </div>
              <p class="text-[11px] mt-1 opacity-90 leading-relaxed">
                This account holds the Super Admin core role, granting full institutional authority across all scheduling pools, meetings, users, and system infrastructure.
              </p>
            </div>

            <!-- Granular Effective Permissions Grid -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                  Effective Action Permissions ({{ permissions.length }})
                </h3>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                <div
                  v-for="perm in permissions"
                  :key="perm"
                  class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs"
                >
                  <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ perm }}</span>
                  <Check class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: EDIT USER MODAL -->
    <div
      v-if="editModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">
            Edit User Profile: {{ user.name }}
          </h2>
          <button @click="editModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="saveUserChanges" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
            <input
              v-model="editForm.name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
            <input
              v-model="editForm.email"
              type="email"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Designation / Title</label>
            <input
              v-model="editForm.designation"
              type="text"
              placeholder="e.g. Professor of Computing"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Role *</label>
            <select
              v-model="editForm.role"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="r in availableRoles" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password (leave blank to retain)</label>
            <input
              v-model="editForm.password"
              type="password"
              placeholder="••••••••"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="editModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingEdit"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50"
            >
              {{ savingEdit ? 'Saving...' : 'Update Account' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: INSPECT PERMISSIONS MODAL -->
    <div
      v-if="permissionsModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
              Effective Rights & Permissions: {{ user?.name }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Evaluated based on assigned system and custom roles
            </p>
          </div>
          <button @click="permissionsModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4">
          <div v-if="isSuperAdmin" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs">
            <p class="font-bold flex items-center gap-1.5">
              <CheckCircle2 class="w-4 h-4 text-emerald-500" />
              <span>Full System Super Administrator</span>
            </p>
            <p class="text-[11px] mt-1 opacity-90">
              This account has the Super Admin role and can perform any action across the platform.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div
              v-for="p in permissions"
              :key="p"
              class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between text-xs"
            >
              <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ p }}</span>
              <Check class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex justify-end">
          <button
            @click="permissionsModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  ArrowLeft,
  RefreshCw,
  Edit3,
  CalendarPlus,
  Power,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Building2,
  Mail,
  Clock,
  Video,
  Calendar,
  Layers,
  ExternalLink,
  Plus,
  Search,
  FileCheck,
  Film,
  Users,
  BarChart2,
  Gauge,
  Check,
  X,
  Play,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const userId = computed(() => route.params.id);

// Tab: 'meetings' | 'approvals' | 'recordings' | 'permissions'
const activeTab = ref('meetings');

// Data State
const loading = ref(true);
const user = ref(null);
const summary = ref({
  total_requests: 0,
  scheduled_requests: 0,
  in_progress_requests: 0,
  completed_requests: 0,
  cancelled_requests: 0,
  total_hours: 0,
  total_minutes: 0,
  approvals_submitted: 0,
  approvals_pending: 0,
  approvals_approved: 0,
  approvals_rejected: 0,
  assigned_approvals_count: 0,
  assigned_approvals_pending: 0,
  recordings_count: 0,
  recordings_mb: 0,
  attendance_sessions_count: 0,
});
const quota = ref(null);
const meetings = ref([]);
const approvals = ref([]);
const recordings = ref([]);
const permissions = ref([]);
const isSuperAdmin = ref(false);
const availableRoles = ref(['Super Admin', 'Approval', 'User']);

// Filters inside meetings tab
const meetingFilterSearch = ref('');
const meetingFilterStatus = ref('');

// Modals
const editModal = ref(false);
const savingEdit = ref(false);
const editForm = ref({
  id: null,
  name: '',
  email: '',
  designation: '',
  role: 'User',
  password: '',
});

const permissionsModal = ref(false);
const feedback = ref('');
const feedbackError = ref(false);

const filteredMeetings = computed(() => {
  return meetings.value.filter((m) => {
    if (meetingFilterStatus.value && m.status !== meetingFilterStatus.value) {
      return false;
    }
    if (meetingFilterSearch.value.trim()) {
      const q = meetingFilterSearch.value.trim().toLowerCase();
      const titleMatch = (m.title || '').toLowerCase().includes(q);
      const idMatch = (m.public_id || '').toLowerCase().includes(q);
      return titleMatch || idMatch;
    }
    return true;
  });
});

const fetchProfile = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/spa/users/${userId.value}/profile`);
    user.value = res.data.user;
    summary.value = res.data.summary || summary.value;
    quota.value = res.data.quota || null;
    meetings.value = res.data.meetings || [];
    approvals.value = res.data.approvals || [];
    recordings.value = res.data.recordings || [];
    permissions.value = res.data.permissions || [];
    isSuperAdmin.value = !!res.data.is_super_admin;
  } catch (err) {
    console.error('Failed to load user profile', err);
    feedback.value = err.response?.data?.message || 'Could not load user profile. The user may not exist.';
    feedbackError.value = true;
  } finally {
    loading.value = false;
  }
};

const bookOnBehalf = () => {
  if (!user.value) return;
  router.push({
    path: '/app/meetings/create',
    query: { user_id: user.value.id },
  });
};

const openEditModal = () => {
  if (!user.value) return;
  editForm.value = {
    id: user.value.id,
    name: user.value.name,
    email: user.value.email,
    designation: user.value.designation || '',
    department_id: user.value.department_id,
    role: user.value.roles?.[0] || 'User',
    password: '',
  };
  editModal.value = true;
};

const saveUserChanges = async () => {
  savingEdit.value = true;
  try {
    await axios.post('/spa/users', editForm.value);
    feedback.value = `User "${editForm.value.name}" updated successfully.`;
    feedbackError.value = false;
    editModal.value = false;
    await fetchProfile();
  } catch (err) {
    console.error('Failed to update user', err);
    feedback.value = err.response?.data?.message || 'Failed to update user.';
    feedbackError.value = true;
  } finally {
    savingEdit.value = false;
  }
};

const toggleUserActive = async () => {
  if (!user.value) return;
  try {
    await axios.post(`/spa/users/${user.value.id}/toggle`);
    feedback.value = `User account status updated.`;
    feedbackError.value = false;
    await fetchProfile();
  } catch (err) {
    console.error('Failed to toggle status', err);
    feedback.value = 'Failed to toggle account status.';
    feedbackError.value = true;
  }
};

// Utilities
const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatTime = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
};

const formatDuration = (mins) => {
  const m = Math.max(0, Math.abs(parseInt(mins, 10) || 0));
  if (m === 0) return '0 mins';
  if (m < 60) return `${m} mins`;
  const hrs = Math.floor(m / 60);
  const remainingMins = m % 60;
  return remainingMins > 0 ? `${hrs}h ${remainingMins}m` : `${hrs} hrs`;
};

const formatBytes = (bytes) => {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const quotaPercentage = (used, max) => {
  if (!max || max === 0) return 0;
  return Math.round((used / max) * 100);
};

const getStatusBadgeClass = (status) => {
  const s = String(status || '').toLowerCase();
  if (s === 'scheduled' || s === 'allocating') {
    return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
  }
  if (s === 'started' || s === 'in_progress') {
    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
  }
  if (s === 'completed') {
    return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
  }
  if (s === 'cancelled') {
    return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20';
  }
  return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
};

const getDecisionBadgeClass = (decision) => {
  const d = String(decision || '').toLowerCase();
  if (d === 'approved') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
  if (d === 'rejected') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20';
  return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
};

onMounted(() => {
  fetchProfile();
});
</script>
