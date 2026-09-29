<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-gradient-to-br from-brand-500/10 to-indigo-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20">
            <GitMerge class="w-5 h-5" />
          </div>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              Workflow Rules Builder
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Automated governance policies for auto-approval, rejection, and resource pool enforcement
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="openSimulateModal"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition shadow-sm"
        >
          <Play class="w-3.5 h-3.5 text-amber-500" />
          <span>Simulate Engine</span>
        </button>

        <button
          v-if="authStore.can('workflow.manage') || authStore.isAdmin"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Rule</span>
        </button>
      </div>
    </div>

    <!-- Stats & Filters Toolbar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400 flex items-center justify-center font-bold">
          <Layers class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Rules</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">{{ rules.length }}</div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
          <CheckCircle2 class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Active Rules</div>
          <div class="text-lg font-black text-slate-900 dark:text-white">
            {{ rules.filter(r => r.is_enabled).length }}
          </div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
          <ShieldAlert class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Enforcement Mode</div>
          <div class="text-sm font-bold text-slate-900 dark:text-white">Priority Order</div>
        </div>
      </div>

      <div class="glass-card p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
          <Sparkles class="w-5 h-5" />
        </div>
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Evaluation</div>
          <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Zero-Latency Engine</div>
        </div>
      </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="relative w-full sm:max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search rules by name, priority, or conditions..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>

      <div class="flex items-center gap-2 self-end sm:self-auto">
        <button
          @click="statusFilter = 'all'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="statusFilter === 'all' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          All
        </button>
        <button
          @click="statusFilter = 'enabled'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="statusFilter === 'enabled' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          Enabled
        </button>
        <button
          @click="statusFilter = 'disabled'"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold transition"
          :class="statusFilter === 'disabled' ? 'bg-rose-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
        >
          Disabled
        </button>
      </div>
    </div>

    <!-- Alert Toast Message -->
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

    <!-- Rules Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
      <div v-if="loading && !rules.length" class="p-16 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm font-medium">Loading workflow rules...</p>
      </div>

      <div v-else-if="!filteredRules.length" class="p-16 text-center text-slate-400">
        <GitMerge class="w-12 h-12 mx-auto mb-3 opacity-30 text-slate-500" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No workflow rules match your search.' : 'No workflow rules configured yet.' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ searchQuery ? 'Try adjusting your search criteria.' : 'Create your first automated rule to enforce booking governance.' }}
        </p>
        <button
          v-if="!searchQuery && (authStore.can('workflow.manage') || authStore.isAdmin)"
          @click="openCreateModal"
          class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition shadow-sm"
        >
          <Plus class="w-4 h-4" />
          <span>Add First Rule</span>
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-24">Priority</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Rule Name</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider min-w-[200px]">Conditions</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider min-w-[200px]">Enforcement Actions</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider w-28">Status</th>
              <th class="py-3.5 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right w-24">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="rule in filteredRules"
              :key="rule.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition group"
            >
              <td class="py-4 px-4 font-mono font-black text-xs text-brand-600 dark:text-brand-400">
                <span class="px-2 py-1 rounded-lg bg-brand-500/10 border border-brand-500/20">
                  #{{ rule.priority }}
                </span>
              </td>
              <td class="py-4 px-4 font-bold text-slate-900 dark:text-white">
                <div>{{ rule.name }}</div>
                <div class="text-[11px] font-normal text-slate-400 mt-0.5">
                  ID: {{ rule.public_id }}
                </div>
              </td>
              <td class="py-4 px-4">
                <div class="flex flex-wrap gap-1.5 max-w-sm">
                  <span
                    v-if="rule.conditions?.meeting_type"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20"
                  >
                    Type: {{ Array.isArray(rule.conditions.meeting_type) ? rule.conditions.meeting_type.join(', ') : rule.conditions.meeting_type }}
                  </span>
                  <span
                    v-if="rule.conditions?.duration_min"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                  >
                    ≥ {{ rule.conditions.duration_min }}m
                  </span>
                  <span
                    v-if="rule.conditions?.duration_max"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                  >
                    ≤ {{ rule.conditions.duration_max }}m
                  </span>
                  <span
                    v-if="rule.conditions?.participant_count_min"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20"
                  >
                    ≥ {{ rule.conditions.participant_count_min }} attendees
                  </span>
                  <span
                    v-if="rule.conditions?.participant_count_max"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20"
                  >
                    ≤ {{ rule.conditions.participant_count_max }} attendees
                  </span>
                  <span
                    v-if="rule.conditions?.role"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20"
                  >
                    Role: {{ rule.conditions.role }}
                  </span>
                  <span
                    v-if="rule.conditions?.department_id"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-500/10 text-slate-600 dark:text-slate-300 border border-slate-500/20"
                  >
                    Dept: #{{ rule.conditions.department_id }}
                  </span>
                  <span
                    v-if="rule.conditions?.external_participants"
                    class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20"
                  >
                    External Guests
                  </span>
                  <span
                    v-if="!hasConditions(rule.conditions)"
                    class="text-xs text-slate-400 italic"
                  >
                    Matches all meetings (Catch-all)
                  </span>
                </div>
              </td>
              <td class="py-4 px-4">
                <div class="flex flex-wrap gap-1.5 max-w-sm">
                  <span
                    v-if="rule.actions?.auto_approve"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-1"
                  >
                    <CheckCircle2 class="w-3 h-3" /> Auto Approve
                  </span>
                  <span
                    v-if="rule.actions?.reject"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 flex items-center gap-1"
                    :title="rule.actions.reject"
                  >
                    <XCircle class="w-3 h-3" /> Reject: {{ truncate(rule.actions.reject, 24) }}
                  </span>
                  <span
                    v-if="rule.actions?.require_approval"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-1"
                  >
                    <ShieldAlert class="w-3 h-3" /> Approval Required
                  </span>
                  <span
                    v-if="rule.actions?.assign_pool"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20"
                  >
                    Pool Override #{{ rule.actions.assign_pool }}
                  </span>
                  <span
                    v-if="rule.actions?.enable_recording"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20"
                  >
                    Rec: {{ rule.actions.enable_recording }}
                  </span>
                </div>
              </td>
              <td class="py-4 px-4">
                <button
                  @click="toggleRule(rule)"
                  :disabled="!authStore.can('workflow.manage') && !authStore.isAdmin"
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold transition flex items-center gap-1.5 cursor-pointer"
                  :class="rule.is_enabled ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="rule.is_enabled ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                  <span>{{ rule.is_enabled ? 'Active' : 'Disabled' }}</span>
                </button>
              </td>
              <td class="py-4 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="authStore.can('workflow.manage') || authStore.isAdmin"
                    @click="openEditModal(rule)"
                    class="p-1.5 text-slate-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-950/40 rounded-lg transition"
                    title="Edit Rule"
                  >
                    <Edit3 class="w-4 h-4" />
                  </button>
                  <button
                    v-if="authStore.can('workflow.manage') || authStore.isAdmin"
                    @click="deleteRule(rule)"
                    class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                    title="Delete Rule"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create / Edit Rule Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
        @click.self="showModal = false"
      >
        <div class="glass-card max-w-xl w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <GitMerge class="w-5 h-5 text-brand-600" />
              <h3 class="font-bold text-base text-slate-900 dark:text-white">
                {{ editingRule ? 'Edit Workflow Rule' : 'Create Workflow Rule' }}
              </h3>
            </div>
            <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="submitRule" class="space-y-4 text-xs">
            <div class="grid grid-cols-3 gap-3">
              <div class="col-span-2">
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
                <input
                  v-model="form.name"
                  required
                  placeholder="e.g., Auto-Approve Short Faculty Lectures"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Priority (1 = Highest)</label>
                <input
                  v-model.number="form.priority"
                  type="number"
                  min="1"
                  max="1000"
                  required
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                />
              </div>
            </div>

            <!-- Match Conditions Fieldset -->
            <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 space-y-3">
              <div class="flex items-center justify-between">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                  <Filter class="w-3.5 h-3.5 text-brand-500" />
                  <span>Match Conditions (Triggers)</span>
                </h4>
                <span class="text-[11px] text-slate-400">Leave fields empty for any match</span>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Meeting Type</label>
                  <select
                    v-model="form.conditions.meeting_type"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  >
                    <option value="">Any Meeting Type</option>
                    <option value="meeting">General Meeting</option>
                    <option value="webinar">Webinar / Broadcast</option>
                    <option value="lecture">Classroom Lecture</option>
                    <option value="exam">Proctored Exam</option>
                    <option value="interview">Hiring Interview</option>
                  </select>
                </div>

                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Department</label>
                  <select
                    v-model="form.conditions.department_id"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  >
                    <option :value="null">Any Department</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">
                      {{ d.name }} ({{ d.code }})
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Min Duration (min)</label>
                  <input
                    v-model.number="form.conditions.duration_min"
                    type="number"
                    min="1"
                    placeholder="e.g., 60"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Max Duration (min)</label>
                  <input
                    v-model.number="form.conditions.duration_max"
                    type="number"
                    min="1"
                    placeholder="e.g., 180"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Min Participants</label>
                  <input
                    v-model.number="form.conditions.participant_count_min"
                    type="number"
                    min="1"
                    placeholder="e.g., 50"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  />
                </div>

                <div>
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">User Role</label>
                  <input
                    v-model="form.conditions.role"
                    placeholder="e.g., faculty, student, staff"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  />
                </div>
              </div>
            </div>

            <!-- Enforcement Actions Fieldset -->
            <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 space-y-3">
              <h4 class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                <Sliders class="w-3.5 h-3.5 text-brand-500" />
                <span>Enforcement Actions</span>
              </h4>

              <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="form.actions.auto_approve"
                    @change="onAutoApproveChanged"
                    class="rounded text-brand-600 focus:ring-brand-500"
                  />
                  <span class="font-semibold text-slate-800 dark:text-slate-200">Auto-Approve Instantly</span>
                  <span class="text-[11px] text-slate-400">(Skips manual approval queues)</span>
                </label>

                <div v-if="!form.actions.auto_approve">
                  <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Reject Request with Reason</label>
                  <input
                    v-model="form.actions.reject"
                    placeholder="e.g., High-capacity exams require Dean authorization."
                    class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                  />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                  <div>
                    <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Assign Resource Pool</label>
                    <select
                      v-model="form.actions.assign_pool"
                      class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                    >
                      <option :value="null">Default Pool Routing</option>
                      <option v-for="p in pools" :key="p.id" :value="p.id">
                        {{ p.name }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-[10px] uppercase font-bold tracking-wider text-slate-400 mb-1">Force Recording Mode</label>
                    <select
                      v-model="form.actions.enable_recording"
                      class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                    >
                      <option value="">No Override</option>
                      <option value="cloud">Force Cloud Recording</option>
                      <option value="local">Force Local Recording</option>
                      <option value="none">Disable Recording</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div class="pt-3 flex items-center justify-between border-t border-slate-100 dark:border-slate-800">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="form.is_enabled"
                  class="rounded text-brand-600 focus:ring-brand-500"
                />
                <span class="font-semibold text-slate-700 dark:text-slate-300">Enable this rule immediately</span>
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
                  :disabled="saving"
                  class="px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition flex items-center gap-1.5"
                >
                  <RefreshCw v-if="saving" class="w-3.5 h-3.5 animate-spin" />
                  <span>{{ editingRule ? 'Update Rule' : 'Save Rule' }}</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Rule Simulator Modal -->
    <Teleport to="body">
      <div
        v-if="showSimulateModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
        @click.self="showSimulateModal = false"
      >
        <div class="glass-card max-w-lg w-full p-6 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-2xl bg-white dark:bg-slate-900 space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <Play class="w-4 h-4 text-amber-500" />
              <h3 class="font-bold text-base text-slate-900 dark:text-white">Workflow Engine Simulator</h3>
            </div>
            <button @click="showSimulateModal = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="w-5 h-5" />
            </button>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400">
            Simulate how your current priority chain will evaluate a drafted booking without creating any real meetings.
          </p>

          <form @submit.prevent="runSimulation" class="space-y-3 text-xs">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-600 dark:text-slate-300 mb-1">Meeting Type</label>
                <select
                  v-model="simData.meeting_type"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                >
                  <option value="meeting">General Meeting</option>
                  <option value="webinar">Webinar</option>
                  <option value="exam">Proctored Exam</option>
                  <option value="lecture">Class Lecture</option>
                </select>
              </div>

              <div>
                <label class="block font-medium text-slate-600 dark:text-slate-300 mb-1">Duration (minutes)</label>
                <input
                  v-model.number="simData.duration_minutes"
                  type="number"
                  min="15"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-xs"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-600 dark:text-slate-300 mb-1">Participant Count</label>
                <input
                  v-model.number="simData.participant_count"
                  type="number"
                  min="1"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-xs"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-600 dark:text-slate-300 mb-1">Department</label>
                <select
                  v-model="simData.department_id"
                  class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"
                >
                  <option :value="null">Default Department</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
            </div>

            <button
              type="submit"
              :disabled="simulating"
              class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold transition flex items-center justify-center gap-2 shadow-sm"
            >
              <RefreshCw v-if="simulating" class="w-3.5 h-3.5 animate-spin" />
              <Play v-else class="w-3.5 h-3.5" />
              <span>{{ simulating ? 'Evaluating Rules...' : 'Run Simulation' }}</span>
            </button>
          </form>

          <div v-if="simResult" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 space-y-3 text-xs animate-fade-in">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 dark:text-slate-200">Simulation Outcome:</span>
              <span
                v-if="simResult.is_rejected"
                class="px-2.5 py-1 rounded-full font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20"
              >
                REJECTED
              </span>
              <span
                v-else-if="simResult.is_auto_approved"
                class="px-2.5 py-1 rounded-full font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20"
              >
                AUTO-APPROVED
              </span>
              <span
                v-else
                class="px-2.5 py-1 rounded-full font-bold bg-brand-500/10 text-brand-500 border border-brand-500/20"
              >
                MANUAL APPROVAL REQUIRED
              </span>
            </div>

            <div v-if="simResult.reject_reason" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 text-rose-600 dark:text-rose-400 font-semibold">
              Reason: {{ simResult.reject_reason }}
            </div>

            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center justify-between border-t border-slate-200/60 dark:border-slate-700/60 pt-2">
              <span>Matched Rules: <strong>{{ simResult.matched_count }}</strong></span>
              <span>{{ (simResult.matched_rules || []).map(r => r.name).join(', ') || 'No rules matched' }}</span>
            </div>

            <div v-if="simResult.logs && simResult.logs.length" class="space-y-1">
              <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Execution Log</div>
              <div class="p-2 rounded-xl bg-slate-900 text-slate-300 font-mono text-[10px] space-y-0.5 max-h-32 overflow-y-auto">
                <div v-for="(log, idx) in simResult.logs" :key="idx">> {{ log }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import {
  CheckCircle2,
  Edit3,
  Filter,
  GitMerge,
  Layers,
  Play,
  Plus,
  RefreshCw,
  Search,
  ShieldAlert,
  Sliders,
  Sparkles,
  Trash2,
  X,
  XCircle,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(false);
const saving = ref(false);
const simulating = ref(false);
const rules = ref([]);
const departments = ref([]);
const pools = ref([]);
const profiles = ref([]);
const templates = ref([]);
const statusMessage = ref('');
const searchQuery = ref('');
const statusFilter = ref('all');

const showModal = ref(false);
const showSimulateModal = ref(false);
const editingRule = ref(null);

const form = ref({
  name: '',
  priority: 50,
  conditions: {},
  actions: {},
  is_enabled: true,
});

const simData = ref({
  meeting_type: 'exam',
  duration_minutes: 180,
  participant_count: 50,
  department_id: null,
});
const simResult = ref(null);

const filteredRules = computed(() => {
  let list = rules.value;

  if (statusFilter.value === 'enabled') {
    list = list.filter((r) => r.is_enabled);
  } else if (statusFilter.value === 'disabled') {
    list = list.filter((r) => !r.is_enabled);
  }

  if (!searchQuery.value.trim()) return list;
  const q = searchQuery.value.toLowerCase().trim();

  return list.filter((r) => {
    return (
      (r.name && r.name.toLowerCase().includes(q)) ||
      String(r.priority).includes(q) ||
      JSON.stringify(r.conditions || {}).toLowerCase().includes(q) ||
      JSON.stringify(r.actions || {}).toLowerCase().includes(q)
    );
  });
});

const hasConditions = (cond) => {
  if (!cond) return false;
  return Object.values(cond).some((v) => v !== null && v !== '' && v !== false);
};

const truncate = (str, len = 30) => {
  if (!str) return '';
  return str.length > len ? str.substring(0, len) + '...' : str;
};

const onAutoApproveChanged = () => {
  if (form.value.actions.auto_approve) {
    form.value.actions.reject = '';
    form.value.actions.require_approval = false;
  }
};

const fetchRules = async () => {
  try {
    loading.value = true;
    const res = await axios.get('/spa/workflows');
    rules.value = res.data.rules || [];
    departments.value = res.data.departments || [];
    pools.value = res.data.pools || [];
    profiles.value = res.data.profiles || [];
    templates.value = res.data.templates || [];
  } catch (err) {
    console.error('Failed to load rules', err);
    // Fallback to /admin/workflows if /spa/workflows not ready
    try {
      const fallback = await axios.get('/admin/workflows');
      rules.value = fallback.data.rules || [];
      departments.value = fallback.data.departments || [];
      pools.value = fallback.data.pools || [];
    } catch (e) {
      console.error('Failed fallback rules load', e);
    }
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingRule.value = null;
  form.value = {
    name: '',
    priority: 50,
    conditions: {
      meeting_type: '',
      department_id: null,
      duration_min: null,
      duration_max: null,
      participant_count_min: null,
      role: '',
    },
    actions: {
      auto_approve: false,
      reject: '',
      assign_pool: null,
      enable_recording: '',
    },
    is_enabled: true,
  };
  showModal.value = true;
};

const openEditModal = (rule) => {
  editingRule.value = rule;
  form.value = {
    name: rule.name,
    priority: rule.priority,
    conditions: { ...(rule.conditions || {}) },
    actions: { ...(rule.actions || {}) },
    is_enabled: !!rule.is_enabled,
  };
  showModal.value = true;
};

const submitRule = async () => {
  try {
    saving.value = true;

    // Clean empty condition keys
    const cleanConditions = {};
    for (const [k, v] of Object.entries(form.value.conditions)) {
      if (v !== null && v !== '' && v !== undefined) {
        cleanConditions[k] = v;
      }
    }

    const cleanActions = {};
    for (const [k, v] of Object.entries(form.value.actions)) {
      if (v !== null && v !== '' && v !== undefined && v !== false) {
        cleanActions[k] = v;
      }
    }

    const payload = {
      name: form.value.name,
      priority: form.value.priority,
      conditions: cleanConditions,
      actions: cleanActions,
      is_enabled: form.value.is_enabled,
    };

    if (editingRule.value) {
      await axios.put(`/spa/workflows/${editingRule.value.public_id}`, payload);
      statusMessage.value = `Workflow rule "${form.value.name}" updated successfully.`;
    } else {
      await axios.post('/spa/workflows', payload);
      statusMessage.value = `Workflow rule "${form.value.name}" created successfully.`;
    }

    showModal.value = false;
    await fetchRules();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save workflow rule.');
  } finally {
    saving.value = false;
  }
};

const toggleRule = async (rule) => {
  try {
    const res = await axios.post(`/spa/workflows/${rule.public_id}/toggle`);
    rule.is_enabled = !rule.is_enabled;
    statusMessage.value = `Rule "${rule.name}" is now ${rule.is_enabled ? 'enabled' : 'disabled'}.`;
  } catch (err) {
    console.error('Failed to toggle rule', err);
    alert('Failed to toggle rule status.');
  }
};

const deleteRule = async (rule) => {
  if (!confirm(`Are you sure you want to delete workflow rule "${rule.name}"?`)) return;
  try {
    await axios.delete(`/spa/workflows/${rule.public_id}`);
    rules.value = rules.value.filter((r) => r.id !== rule.id);
    statusMessage.value = `Workflow rule "${rule.name}" deleted.`;
  } catch (err) {
    alert('Failed to delete workflow rule.');
  }
};

const openSimulateModal = () => {
  simResult.value = null;
  showSimulateModal.value = true;
};

const runSimulation = async () => {
  try {
    simulating.value = true;
    const res = await axios.post('/spa/workflows/simulate', simData.value);
    simResult.value = res.data;
  } catch (err) {
    alert(err.response?.data?.message || 'Simulation error. Please check your parameters.');
  } finally {
    simulating.value = false;
  }
};

onMounted(() => {
  fetchRules();
});
</script>
