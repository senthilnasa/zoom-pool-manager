<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
          <Layers class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Zoom Resource Pools & Licenses
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Dedicated and shared Zoom account pools, host license synchronization, and allocation strategies
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <router-link
          to="/app/reports/zoom-usage"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-brand-700 dark:text-brand-300 bg-brand-50 hover:bg-brand-100 dark:bg-brand-950/50 dark:hover:bg-brand-900/60 border border-brand-200/80 dark:border-brand-800/80 transition cursor-pointer"
        >
          <BarChart2 class="w-4 h-4 text-brand-600 dark:text-brand-400" />
          <span>Usage & Concurrency Report</span>
        </router-link>

        <button
          type="button"
          @click="showHowItWorks = !showHowItWorks"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-200/80 dark:border-slate-700/80 transition cursor-pointer"
        >
          <HelpCircle class="w-4 h-4 text-brand-500" />
          <span>{{ showHowItWorks ? 'Hide Guide' : 'How to Configure' }}</span>
        </button>

        <button
          v-if="authStore.can('resource.manage') || authStore.isAdmin"
          type="button"
          @click="syncFromZoom"
          :disabled="syncing"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-500/20 transition cursor-pointer disabled:opacity-50"
        >
          <CloudDownload class="w-4 h-4" :class="{ 'animate-bounce': syncing }" />
          <span>{{ syncing ? 'Syncing with Zoom...' : 'Sync Users from Zoom' }}</span>
        </button>

        <button
          @click="fetchData"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition cursor-pointer"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.can('pool.manage') || authStore.isAdmin"
          @click="openCreatePoolModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>New Pool</span>
        </button>
      </div>
    </div>

    <!-- Alert / Feedback Notification -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold flex items-center justify-between"
    >
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="text-emerald-500 hover:underline cursor-pointer">Dismiss</button>
    </div>

    <!-- How It Works Instructional Guide -->
    <div
      v-if="showHowItWorks"
      class="p-5 rounded-2xl bg-gradient-to-br from-brand-50/70 via-sky-50/50 to-indigo-50/40 dark:from-slate-900/90 dark:via-brand-950/40 dark:to-slate-900/90 border border-brand-200 dark:border-brand-800/60 shadow-sm space-y-3"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-xs">
            Guide
          </div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">
            How to Configure Zoom Accounts into Resource Pools
          </h3>
        </div>
        <button @click="showHowItWorks = false" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
          Dismiss
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">1</span>
            Sync Zoom Host Accounts
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Click <strong>"Sync Users from Zoom"</strong> above. Zoom Pool Manager calls the Zoom Users API to import all licensed hosts, capacities, and capabilities into your catalog.
          </p>
        </div>

        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">2</span>
            Create or Edit Pools
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Create pools (e.g. <em>General Classrooms</em>, <em>Large Webinars</em>, <em>Exam Proctoring</em>) and choose an allocation strategy like <strong>Least Hours Today</strong> for balanced license wear.
          </p>
        </div>

        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">3</span>
            Assign Accounts to Pools
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            In the table below, click <strong>"Assign Pools"</strong> on any host account to check which pools it serves. A host account can belong to multiple pools or be excluded anytime.
          </p>
        </div>
      </div>
    </div>

    <!-- Resource Pools Section -->
    <div class="space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Configured Resource Pools ({{ pools.length }})
          </h2>
          <span class="text-xs text-slate-400">Allocation engines select available hosts from these pools</span>
        </div>
        <div class="relative w-full sm:w-64">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="poolSearchQuery"
            type="text"
            placeholder="Search pools..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>
      </div>

      <div v-if="loading && !pools.length" class="glass-card rounded-2xl p-12 text-center text-slate-400 border border-slate-200/60 dark:border-slate-800/60">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Loading resource pools...</p>
      </div>

      <div v-else-if="!filteredPools.length" class="glass-card rounded-2xl p-8 text-center border border-slate-200/60 dark:border-slate-800/60 space-y-3">
        <Layers class="w-10 h-10 mx-auto text-slate-400" />
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">
          {{ poolSearchQuery ? 'No Pools Match Search' : 'No Resource Pools Created Yet' }}
        </h3>
        <p class="text-xs text-slate-500 max-w-md mx-auto">
          {{ poolSearchQuery ? 'Try adjusting your search criteria.' : 'Resource pools allow faculty to reserve pooled Zoom licenses without conflicting. Create your first pool to begin allocating host licenses.' }}
        </p>
        <button
          v-if="!poolSearchQuery"
          @click="openCreatePoolModal"
          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Create First Resource Pool</span>
        </button>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="pool in filteredPools"
          :key="pool.id"
          class="glass-card rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 space-y-4 hover:border-brand-500/40 transition flex flex-col justify-between"
        >
          <div class="space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ pool.name }}</h3>
                <code class="text-[10px] font-mono text-slate-400">{{ pool.code }}</code>
              </div>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="pool.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
              >
                {{ pool.is_active ? 'Active' : 'Disabled' }}
              </span>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
              {{ pool.description || 'No description provided.' }}
            </p>

            <div class="pt-2 flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 border-t border-slate-100 dark:border-slate-800">
              <span class="text-slate-400">Strategy:</span>
              <span class="font-semibold capitalize">{{ pool.pool_strategy?.replace(/_/g, ' ') }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
              <span class="text-slate-400">Assigned Resources:</span>
              <span class="font-bold text-brand-600 dark:text-brand-400">{{ pool.resources_count || pool.resources?.length || 0 }} accounts</span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button
              v-if="authStore.can('pool.manage') || authStore.isAdmin"
              @click="editPool(pool)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              Edit & Accounts
            </button>
            <button
              v-if="authStore.can('pool.manage') || authStore.isAdmin"
              @click="togglePool(pool)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer"
              :class="pool.is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'"
            >
              {{ pool.is_active ? 'Disable' : 'Enable' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Zoom Resources (Accounts) Table -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 space-y-4">
      <div class="px-6 py-4 border-b border-slate-200/60 dark:border-slate-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Zoom Host Accounts (Licenses)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-800 font-mono text-slate-600 dark:text-slate-300">
              {{ resources.length }} accounts
            </span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Underlying Zoom host licenses discovered from your Zoom organization
          </p>
        </div>

        <div class="flex items-center gap-3">
          <div class="relative w-full sm:w-64">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="resourceSearchQuery"
              type="text"
              placeholder="Search host accounts..."
              class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
            />
          </div>

          <button
            v-if="authStore.can('resource.manage') || authStore.isAdmin"
            @click="syncFromZoom"
            :disabled="syncing"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition cursor-pointer disabled:opacity-50 shrink-0"
          >
            <CloudDownload class="w-3.5 h-3.5" :class="{ 'animate-bounce': syncing }" />
            <span>{{ syncing ? 'Syncing...' : 'Sync from Zoom' }}</span>
          </button>
        </div>
      </div>

      <!-- Empty State if No Accounts Synced Yet -->
      <div v-if="!loading && !resources.length" class="p-12 text-center space-y-3">
        <Users class="w-10 h-10 mx-auto text-slate-400" />
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">No Zoom Accounts Discovered Yet</h3>
        <p class="text-xs text-slate-500 max-w-md mx-auto">
          Your Zoom Server-to-Server connection is configured. Click "Sync Users from Zoom" to discover and import your organization's Zoom host licenses.
        </p>
        <button
          @click="syncFromZoom"
          :disabled="syncing"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-500/20 transition cursor-pointer"
        >
          <CloudDownload class="w-4 h-4" />
          <span>{{ syncing ? 'Syncing...' : 'Sync Users from Zoom Now' }}</span>
        </button>
      </div>

      <div v-else-if="!filteredResources.length" class="p-12 text-center text-slate-400">
        <Users class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Host Accounts Match Search</p>
        <p class="text-xs text-slate-400 mt-1">Try adjusting your search query.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Account / Host</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Capacity</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Capabilities</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Assigned Pools</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
            <tr
              v-for="res in filteredResources"
              :key="res.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition"
            >
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900 dark:text-white">{{ res.name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">{{ res.zoom_user?.email || res.zoom_user_id }}</div>
              </td>
              <td class="py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">
                {{ res.participant_capacity || 100 }} seats
              </td>
              <td class="py-3 px-4">
                <div class="flex flex-wrap gap-1">
                  <span v-if="res.cloud_recording" class="px-2 py-0.5 rounded text-[10px] bg-blue-500/10 text-blue-600 dark:text-blue-400 font-medium">Recording</span>
                  <span v-if="res.ai_companion" class="px-2 py-0.5 rounded text-[10px] bg-purple-500/10 text-purple-600 dark:text-purple-400 font-medium">AI Companion</span>
                  <span v-if="res.webinar_capacity" class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium">Webinar</span>
                </div>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                <div v-if="res.pools && res.pools.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="p in res.pools"
                    :key="p.id"
                    class="px-2 py-0.5 rounded bg-brand-50 dark:bg-brand-950/60 border border-brand-200/50 dark:border-brand-800/50 text-[10px] text-brand-700 dark:text-brand-300 font-medium"
                  >
                    {{ p.name }}
                  </span>
                </div>
                <span v-else class="text-[11px] text-slate-400 italic">Not in any pool</span>
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="res.managed ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/10 text-slate-500'"
                >
                  {{ res.managed ? 'Managed' : 'Excluded' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right space-x-2">
                <button
                  v-if="authStore.can('pool.manage') || authStore.isAdmin"
                  @click="openAssignModal(res)"
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-950/40 transition cursor-pointer"
                  title="Assign to Pools"
                >
                  Assign Pools
                </button>
                <button
                  v-if="authStore.can('resource.manage') || authStore.isAdmin"
                  @click="toggleResource(res)"
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer"
                  :class="res.managed ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'"
                >
                  {{ res.managed ? 'Exclude' : 'Include' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Pool Modal -->
    <div
      v-if="poolModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ editingPool ? 'Edit Resource Pool' : 'Create Resource Pool' }}</h2>
          <button @click="poolModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="savePool" class="space-y-3.5">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pool Name *</label>
              <input
                v-model="poolForm.name"
                type="text"
                required
                placeholder="e.g. Standard Classroom Pool"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pool Code *</label>
              <input
                v-model="poolForm.code"
                type="text"
                required
                placeholder="e.g. POOL_STANDARD"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 font-mono"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Allocation Strategy *</label>
            <select
              v-model="poolForm.pool_strategy"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option value="least_hours_today">Least Hours Today (Balanced Wear - Recommended)</option>
              <option value="round_robin">Round Robin (Cyclic Order)</option>
              <option value="priority">Priority Order</option>
              <option value="random">Random</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <textarea
              v-model="poolForm.description"
              rows="2"
              placeholder="Primary pool for daily 300-seat academic lectures"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>

          <!-- Select Host Accounts for this Pool -->
          <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Assigned Zoom Host Accounts ({{ poolForm.resource_ids.length }})
              </label>
              <span class="text-[11px] text-slate-400">Select which accounts belong to this pool</span>
            </div>

            <div v-if="!resources.length" class="p-3 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/40 rounded-xl">
              No Zoom host accounts available yet. Please sync from Zoom first.
            </div>

            <div v-else class="max-h-44 overflow-y-auto space-y-1.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 custom-scrollbar">
              <label
                v-for="res in resources"
                :key="res.id"
                class="flex items-center gap-2 p-2 rounded-lg hover:bg-white dark:hover:bg-slate-700/60 transition cursor-pointer text-xs"
              >
                <input
                  type="checkbox"
                  :value="res.id"
                  v-model="poolForm.resource_ids"
                  class="rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-600"
                />
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-slate-900 dark:text-white truncate">{{ res.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono truncate">{{ res.zoom_user?.email || res.zoom_user_id }}</div>
                </div>
                <span class="text-[11px] text-slate-500 font-medium shrink-0">{{ res.participant_capacity }} seats</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
            <button
              type="button"
              @click="poolModal = false"
              class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 cursor-pointer"
            >
              {{ saving ? 'Saving...' : 'Save Pool' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Assign Resource to Pools Modal -->
    <div
      v-if="assignModal && selectedResource"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Assign Host to Resource Pools</h2>
            <p class="text-xs text-slate-400">{{ selectedResource.name }} ({{ selectedResource.zoom_user?.email }})</p>
          </div>
          <button @click="assignModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-2">
          <p class="text-xs text-slate-600 dark:text-slate-300">
            Select which pools can allocate this Zoom account:
          </p>

          <div v-if="!pools.length" class="p-3 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/40 rounded-xl">
            No pools created yet. Please create a pool first.
          </div>

          <div v-else class="space-y-1.5 max-h-56 overflow-y-auto p-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 custom-scrollbar">
            <label
              v-for="p in pools"
              :key="p.id"
              class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-white dark:hover:bg-slate-700/60 transition cursor-pointer text-xs"
            >
              <input
                type="checkbox"
                :value="p.id"
                v-model="assignedPoolIds"
                class="rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-600"
              />
              <div class="min-w-0 flex-1">
                <div class="font-bold text-slate-900 dark:text-white">{{ p.name }}</div>
                <div class="text-[11px] text-slate-400 font-mono">{{ p.code }} • {{ p.pool_strategy }}</div>
              </div>
            </label>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            type="button"
            @click="assignModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="saveResourcePools"
            :disabled="saving"
            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 cursor-pointer"
          >
            {{ saving ? 'Saving...' : 'Save Assignments' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import {
  Layers,
  BarChart2,
  Plus,
  RefreshCw,
  Search,
  X,
  Users,
  CloudDownload,
  HelpCircle,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const toast = useToastStore();

const pools = ref([]);
const resources = ref([]);
const loading = ref(false);
const saving = ref(false);
const syncing = ref(false);
const feedback = ref('');
const showHowItWorks = ref(false);
const poolSearchQuery = ref('');
const resourceSearchQuery = ref('');

const filteredPools = computed(() => {
  if (!poolSearchQuery.value.trim()) return pools.value;
  const q = poolSearchQuery.value.toLowerCase().trim();
  return pools.value.filter((p) => {
    return (
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.code && p.code.toLowerCase().includes(q)) ||
      (p.description && p.description.toLowerCase().includes(q)) ||
      (p.pool_strategy && p.pool_strategy.toLowerCase().includes(q))
    );
  });
});

const filteredResources = computed(() => {
  if (!resourceSearchQuery.value.trim()) return resources.value;
  const q = resourceSearchQuery.value.toLowerCase().trim();
  return resources.value.filter((r) => {
    const poolNames = (r.pools || []).map((p) => p.name).join(' ');
    return (
      (r.name && r.name.toLowerCase().includes(q)) ||
      (r.zoom_user_id && r.zoom_user_id.toLowerCase().includes(q)) ||
      (r.zoom_user?.email && r.zoom_user.email.toLowerCase().includes(q)) ||
      poolNames.toLowerCase().includes(q)
    );
  });
});

const poolModal = ref(false);
const editingPool = ref(null);
const poolForm = ref({
  id: null,
  name: '',
  code: '',
  description: '',
  pool_strategy: 'least_hours_today',
  is_active: true,
  resource_ids: [],
});

const assignModal = ref(false);
const selectedResource = ref(null);
const assignedPoolIds = ref([]);

const fetchData = async () => {
  loading.value = true;
  try {
    const [pRes, rRes] = await Promise.all([
      axios.get('/spa/pools'),
      axios.get('/spa/resources'),
    ]);
    pools.value = pRes.data || [];
    resources.value = rRes.data || [];

    // Auto open guide if no resources yet
    if (!resources.value.length) {
      showHowItWorks.value = true;
    }
  } catch (err) {
    console.error('Failed to load pool data', err);
  } finally {
    loading.value = false;
  }
};

const syncFromZoom = async () => {
  syncing.value = true;
  feedback.value = '';
  try {
    const res = await axios.post('/spa/resources/sync-from-zoom');
    toast.success(res.data.message || 'Zoom host accounts synced successfully.');
    feedback.value = res.data.message;
    await fetchData();
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to sync users from Zoom.';
    toast.error(msg);
    feedback.value = msg;
  } finally {
    syncing.value = false;
  }
};

const openCreatePoolModal = () => {
  editingPool.value = null;
  poolForm.value = {
    id: null,
    name: '',
    code: '',
    description: '',
    pool_strategy: 'least_hours_today',
    is_active: true,
    resource_ids: [],
  };
  poolModal.value = true;
};

const editPool = (pool) => {
  editingPool.value = pool;
  // Extract currently assigned resource IDs
  const currentResourceIds = pool.resources ? pool.resources.map(r => r.id) : [];
  poolForm.value = {
    id: pool.id,
    name: pool.name,
    code: pool.code,
    description: pool.description || '',
    pool_strategy: pool.pool_strategy || 'least_hours_today',
    is_active: !!pool.is_active,
    resource_ids: currentResourceIds,
  };
  poolModal.value = true;
};

const savePool = async () => {
  saving.value = true;
  try {
    await axios.post('/spa/pools', poolForm.value);
    toast.success(`Pool "${poolForm.value.name}" saved successfully.`);
    feedback.value = `Pool "${poolForm.value.name}" saved successfully.`;
    poolModal.value = false;
    await fetchData();
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to save pool.');
    console.error('Failed to save pool', err);
  } finally {
    saving.value = false;
  }
};

const togglePool = async (pool) => {
  try {
    await axios.post(`/spa/pools/${pool.id}/toggle`);
    await fetchData();
  } catch (err) {
    console.error('Failed to toggle pool', err);
  }
};

const toggleResource = async (res) => {
  try {
    await axios.post(`/spa/resources/${res.id}/toggle`);
    await fetchData();
  } catch (err) {
    console.error('Failed to toggle resource', err);
  }
};

const openAssignModal = (res) => {
  selectedResource.value = res;
  assignedPoolIds.value = res.pools ? res.pools.map(p => p.id) : [];
  assignModal.value = true;
};

const saveResourcePools = async () => {
  if (!selectedResource.value) return;
  saving.value = true;
  try {
    await axios.post(`/spa/resources/${selectedResource.value.id}/pools`, {
      pool_ids: assignedPoolIds.value,
    });
    toast.success(`Pool assignments updated for "${selectedResource.value.name}".`);
    assignModal.value = false;
    await fetchData();
  } catch (err) {
    toast.error('Failed to assign pools.');
  } finally {
    saving.value = false;
  }
};

onMounted(fetchData);
</script>
