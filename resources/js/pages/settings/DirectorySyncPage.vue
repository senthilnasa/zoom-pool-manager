<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
          <FolderSync class="w-6 h-6 text-brand-500 shrink-0" />
          <span>Directory & Active Directory (AD) Synchronization</span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Automate user provisioning and department mapping from Microsoft Entra ID, Google Workspace, and LDAP.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchConfigs"
          class="p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 rounded-xl transition shadow-sm"
          title="Refresh Connectors"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition"
        >
          <Plus class="w-4 h-4" />
          <span>Add Directory Connector</span>
        </button>
      </div>
    </div>

    <!-- Quick Telemetry -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
          <FolderSync class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ configs.length }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Configured Connectors</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
          <Users class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalScannedCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Directory Accounts</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
          <UserPlus class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalCreatedCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Provisioned Accounts</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
          <Clock class="w-6 h-6" />
        </div>
        <div>
          <div class="text-sm font-bold text-slate-900 dark:text-white truncate">Auto-Sync Active</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Every 30 Minutes</div>
        </div>
      </div>
    </div>

    <!-- Directory Connectors Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 overflow-hidden shadow-sm">
      <div class="p-5 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-base font-bold text-slate-900 dark:text-white">Active Directory Connectors</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Enterprise directories polled for automated account sync.</p>
        </div>
        <div class="relative w-full sm:w-64">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search connectors..."
            class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
          />
        </div>
      </div>

      <div v-if="loading && configs.length === 0" class="p-12 text-center">
        <RefreshCw class="w-8 h-8 mx-auto text-brand-500 animate-spin mb-3" />
        <p class="text-sm text-slate-500">Loading directory connectors...</p>
      </div>

      <div v-else-if="configs.length === 0" class="p-12 text-center">
        <FolderSync class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
        <h3 class="text-base font-bold text-slate-900 dark:text-white">No Directory Connectors Configured</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1 mb-5">
          Connect Microsoft Entra ID (Azure AD Graph API), Google Workspace Directory API, or LDAP to automate staff and faculty onboarding.
        </p>
        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-xs font-semibold rounded-xl"
        >
          <Plus class="w-4 h-4" />
          <span>Add Directory Connector</span>
        </button>
      </div>

      <div v-else-if="!filteredConfigs.length" class="p-12 text-center text-slate-400">
        <FolderSync class="w-10 h-10 mx-auto mb-3 opacity-40" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Matching Directory Connectors</p>
        <p class="text-xs text-slate-400 mt-1">Try adjusting your search criteria.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/75 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700/80 text-[11px] uppercase tracking-wider text-slate-500 font-semibold">
              <th class="py-3 px-4">Connector</th>
              <th class="py-3 px-4">Provider Type</th>
              <th class="py-3 px-4">Filter Rules</th>
              <th class="py-3 px-4">Target Role & Dept</th>
              <th class="py-3 px-4">Last Sync Status</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
            <tr v-for="cfg in filteredConfigs" :key="cfg.public_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition">
              <!-- Connector Name -->
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                    <!-- Microsoft Icon -->
                    <svg v-if="cfg.provider_type === 'microsoft_entra'" class="w-4 h-4" viewBox="0 0 23 23">
                      <path fill="#f35325" d="M1 1h10v10H1z"/>
                      <path fill="#81bc06" d="M12 1h10v10H12z"/>
                      <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                      <path fill="#ffba08" d="M12 12h10v10H12z"/>
                    </svg>
                    <!-- Google Icon -->
                    <svg v-else-if="cfg.provider_type === 'google_workspace'" class="w-4 h-4" viewBox="0 0 24 24">
                      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <!-- LDAP Icon -->
                    <FolderSync v-else class="w-4 h-4 text-emerald-500" />
                  </div>
                  <div>
                    <div class="font-bold text-slate-900 dark:text-white">{{ cfg.name }}</div>
                    <div class="text-[10px] text-slate-400 font-mono">{{ cfg.public_id }}</div>
                  </div>
                </div>
              </td>

              <!-- Provider Type -->
              <td class="py-3.5 px-4">
                <span class="px-2 py-0.5 rounded-full font-semibold text-[11px]" :class="providerBadgeClass(cfg.provider_type)">
                  {{ formatProviderType(cfg.provider_type) }}
                </span>
              </td>

              <!-- Filter Rules -->
              <td class="py-3.5 px-4">
                <div class="space-y-1">
                  <div v-if="cfg.domain_filter" class="font-mono text-[11px] text-slate-700 dark:text-slate-300">
                    Domain: @{{ cfg.domain_filter }}
                  </div>
                  <div v-else class="text-slate-400 text-[11px] italic">
                    All domains
                  </div>
                  <div v-if="cfg.group_filter" class="text-[10px] text-slate-400 truncate max-w-xs">
                    Groups: {{ cfg.group_filter }}
                  </div>
                </div>
              </td>

              <!-- Target Role & Dept -->
              <td class="py-3.5 px-4">
                <div class="font-medium text-slate-800 dark:text-slate-200">{{ cfg.default_role }}</div>
                <div class="text-[11px] text-slate-400">
                  {{ cfg.auto_create_departments ? 'Auto-match Depts' : (cfg.default_department_name || 'No Default') }}
                </div>
              </td>

              <!-- Last Sync Status -->
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-1.5">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="{
                      'bg-emerald-500': cfg.last_sync_status === 'success',
                      'bg-amber-500 animate-pulse': cfg.last_sync_status === 'running',
                      'bg-rose-500': cfg.last_sync_status === 'failed',
                      'bg-slate-400': cfg.last_sync_status === 'idle'
                    }"
                  />
                  <span class="font-medium capitalize text-slate-700 dark:text-slate-300">
                    {{ cfg.last_sync_status }}
                  </span>
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-xs" :title="cfg.last_sync_message">
                  {{ cfg.last_sync_message || 'Never synced' }}
                </div>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 text-right space-x-1.5">
                <button
                  @click="syncNow(cfg)"
                  :disabled="syncingId === cfg.public_id"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-brand-50 dark:bg-brand-950/40 text-brand-600 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-900/60 rounded-lg font-semibold transition disabled:opacity-50"
                  title="Run Immediate Sync"
                >
                  <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': syncingId === cfg.public_id }" />
                  <span>{{ syncingId === cfg.public_id ? 'Syncing...' : 'Sync Now' }}</span>
                </button>

                <button
                  @click="testConnection(cfg)"
                  class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                  title="Test Connection"
                >
                  <CheckCircle class="w-4 h-4" />
                </button>

                <button
                  @click="openEditModal(cfg)"
                  class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                  title="Edit Connector"
                >
                  <Edit3 class="w-4 h-4" />
                </button>

                <button
                  @click="deleteConfig(cfg)"
                  class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
                  title="Delete Connector"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 my-8 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-4">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <FolderSync class="w-5 h-5 text-brand-500" />
            <span>{{ isEditing ? 'Edit Directory Connector' : 'Add Directory Connector' }}</span>
          </h2>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="saveConfig" class="space-y-4 text-xs">
          <!-- Name & Provider Type -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Connector Name *</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. Campus Microsoft Entra ID Sync"
                required
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Directory Provider *</label>
              <select
                v-model="form.provider_type"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              >
                <option value="microsoft_entra">Microsoft Entra ID (Azure AD Graph API)</option>
                <option value="google_workspace">Google Workspace Directory (Admin SDK)</option>
                <option value="ldap_active_directory">LDAP / On-Premise Active Directory</option>
              </select>
            </div>
          </div>

          <!-- Microsoft Entra ID Settings -->
          <div v-if="form.provider_type === 'microsoft_entra'" class="space-y-3 p-4 bg-blue-50/50 dark:bg-blue-950/20 rounded-xl border border-blue-100 dark:border-blue-900/40">
            <div class="font-bold text-blue-800 dark:text-blue-300 text-[11px] uppercase tracking-wider">Microsoft Graph API Application Credentials</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Tenant ID</label>
                <input
                  v-model="form.tenant_id"
                  type="text"
                  placeholder="Directory (tenant) GUID"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Application (Client) ID</label>
                <input
                  v-model="form.client_id"
                  type="text"
                  placeholder="Application GUID"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Client Secret</label>
                <input
                  v-model="form.client_secret"
                  type="password"
                  :placeholder="isEditing ? '(Unchanged)' : 'Client Secret Value'"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
            </div>
            <p class="text-[10px] text-slate-500">Requires Microsoft Graph Application permission: <code class="font-mono">User.Read.All</code>.</p>
          </div>

          <!-- Google Workspace Settings -->
          <div v-if="form.provider_type === 'google_workspace'" class="space-y-3 p-4 bg-sky-50/50 dark:bg-sky-950/20 rounded-xl border border-sky-100 dark:border-sky-900/40">
            <div class="font-bold text-sky-800 dark:text-sky-300 text-[11px] uppercase tracking-wider">Google Admin SDK Directory Configuration</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Delegated Admin Email</label>
                <input
                  v-model="form.admin_email"
                  type="email"
                  placeholder="admin@univ.edu"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Service Account JSON / Key</label>
                <input
                  v-model="form.service_account_json"
                  type="password"
                  :placeholder="isEditing ? '(Unchanged)' : 'Service Account Private Key JSON'"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- LDAP Settings -->
          <div v-if="form.provider_type === 'ldap_active_directory'" class="space-y-3 p-4 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-xl border border-emerald-100 dark:border-emerald-900/40">
            <div class="font-bold text-emerald-800 dark:text-emerald-300 text-[11px] uppercase tracking-wider">LDAP Active Directory Server</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">LDAP Host</label>
                <input
                  v-model="form.ldap_host"
                  type="text"
                  placeholder="ldap.univ.edu or DC IP"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Port</label>
                <input
                  v-model="form.ldap_port"
                  type="number"
                  placeholder="389 or 636"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Base DN</label>
                <input
                  v-model="form.ldap_base_dn"
                  type="text"
                  placeholder="dc=univ,dc=edu"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Bind DN / Username</label>
                <input
                  v-model="form.ldap_bind_dn"
                  type="text"
                  placeholder="cn=admin,dc=univ,dc=edu"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"
                />
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Bind Password</label>
                <input
                  v-model="form.ldap_bind_password"
                  type="password"
                  :placeholder="isEditing ? '(Unchanged)' : 'Password'"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"
                />
              </div>
            </div>
          </div>

          <!-- Rules & Policy -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Domain Filter</label>
              <input
                v-model="form.domain_filter"
                type="text"
                placeholder="univ.edu"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              />
              <span class="text-[10px] text-slate-400 mt-0.5 block">Only sync accounts with this email domain.</span>
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Provisioned Role</label>
              <select
                v-model="form.default_role"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              >
                <option v-for="r in (availableRoles.length ? availableRoles : ['Standard User', 'staff', 'faculty', 'super_admin'])" :key="r" :value="r">
                  {{ r }}
                </option>
              </select>
            </div>
          </div>

          <!-- Options Toggles -->
          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-2">
              <input
                v-model="form.auto_create_departments"
                id="auto-create-depts"
                type="checkbox"
                class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-700"
              />
              <label for="auto-create-depts" class="text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                Automatically create and map departments based on directory attribute
              </label>
            </div>

            <div class="flex items-center gap-2">
              <input
                v-model="form.deactivate_missing_users"
                id="deactivate-missing"
                type="checkbox"
                class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300 dark:border-slate-700"
              />
              <label for="deactivate-missing" class="text-xs font-medium text-rose-600 dark:text-rose-400 cursor-pointer">
                Deactivate users in ZPM who are no longer present in external directory (Safe: never deactivates Super Admins)
              </label>
            </div>
          </div>

          <!-- Footer Buttons -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-medium rounded-xl transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl shadow-sm transition disabled:opacity-60"
            >
              <RefreshCw v-if="saving" class="w-4 h-4 animate-spin" />
              <span>{{ isEditing ? 'Update Connector' : 'Create Connector' }}</span>
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
import {
  FolderSync,
  Users,
  UserPlus,
  Clock,
  Plus,
  RefreshCw,
  Edit3,
  Trash2,
  CheckCircle,
  Search,
  X,
} from 'lucide-vue-next';

const configs = ref([]);
const loading = ref(false);
const saving = ref(false);
const syncingId = ref(null);
const showModal = ref(false);
const isEditing = ref(false);
const selectedConfig = ref(null);
const availableRoles = ref([]);
const searchQuery = ref('');

const filteredConfigs = computed(() => {
  if (!searchQuery.value.trim()) return configs.value;
  const q = searchQuery.value.toLowerCase().trim();
  return configs.value.filter((c) => {
    return (
      (c.name && c.name.toLowerCase().includes(q)) ||
      (c.provider_type && c.provider_type.toLowerCase().includes(q)) ||
      (c.default_role && c.default_role.toLowerCase().includes(q)) ||
      (c.domain_filter && c.domain_filter.toLowerCase().includes(q))
    );
  });
});

const form = ref({
  name: '',
  provider_type: 'microsoft_entra',
  is_active: true,
  sync_interval_minutes: 60,
  tenant_id: '',
  client_id: '',
  client_secret: '',
  service_account_json: '',
  admin_email: '',
  ldap_host: '',
  ldap_port: 389,
  ldap_base_dn: '',
  ldap_bind_dn: '',
  ldap_bind_password: '',
  domain_filter: '',
  group_filter: '',
  default_role: 'Standard User',
  auto_create_departments: true,
  deactivate_missing_users: false,
});

const totalScannedCount = computed(() => {
  return configs.value.reduce((acc, c) => acc + (c.last_sync_stats?.total_scanned || 0), 0);
});

const totalCreatedCount = computed(() => {
  return configs.value.reduce((acc, c) => acc + (c.last_sync_stats?.created || 0), 0);
});

function formatProviderType(type) {
  if (type === 'microsoft_entra') return 'Microsoft Entra ID';
  if (type === 'google_workspace') return 'Google Workspace';
  if (type === 'ldap_active_directory') return 'LDAP Active Directory';
  return type;
}

function providerBadgeClass(type) {
  if (type === 'microsoft_entra') return 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400';
  if (type === 'google_workspace') return 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400';
  return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400';
}

async function fetchConfigs() {
  loading.value = true;
  try {
    const res = await axios.get('/spa/settings/directory-sync');
    configs.value = res.data.configs || [];
    if (res.data.roles && Array.isArray(res.data.roles)) {
      availableRoles.value = res.data.roles;
    }
  } catch (err) {
    console.error('Failed to load directory sync configs', err);
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  isEditing.value = false;
  selectedConfig.value = null;
  form.value = {
    name: '',
    provider_type: 'microsoft_entra',
    is_active: true,
    sync_interval_minutes: 60,
    tenant_id: '',
    client_id: '',
    client_secret: '',
    service_account_json: '',
    admin_email: '',
    ldap_host: '',
    ldap_port: 389,
    ldap_base_dn: '',
    ldap_bind_dn: '',
    ldap_bind_password: '',
    domain_filter: '',
    group_filter: '',
    default_role: 'Standard User',
    auto_create_departments: true,
    deactivate_missing_users: false,
  };
  showModal.value = true;
}

function openEditModal(cfg) {
  isEditing.value = true;
  selectedConfig.value = cfg;
  form.value = {
    name: cfg.name,
    provider_type: cfg.provider_type,
    is_active: cfg.is_active,
    sync_interval_minutes: cfg.sync_interval_minutes,
    tenant_id: cfg.tenant_id || '',
    client_id: cfg.client_id || '',
    client_secret: '',
    service_account_json: '',
    admin_email: cfg.admin_email || '',
    ldap_host: cfg.ldap_host || '',
    ldap_port: cfg.ldap_port || 389,
    ldap_base_dn: cfg.ldap_base_dn || '',
    ldap_bind_dn: cfg.ldap_bind_dn || '',
    ldap_bind_password: '',
    domain_filter: cfg.domain_filter || '',
    group_filter: cfg.group_filter || '',
    default_role: cfg.default_role || 'Standard User',
    auto_create_departments: cfg.auto_create_departments,
    deactivate_missing_users: cfg.deactivate_missing_users,
  };
  showModal.value = true;
}

async function saveConfig() {
  saving.value = true;
  try {
    if (isEditing.value && selectedConfig.value) {
      await axios.put(`/spa/settings/directory-sync/${selectedConfig.value.public_id}`, form.value);
    } else {
      await axios.post('/spa/settings/directory-sync', form.value);
    }

    showModal.value = false;
    await fetchConfigs();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save directory connector');
  } finally {
    saving.value = false;
  }
}

async function deleteConfig(cfg) {
  if (!confirm(`Are you sure you want to delete directory connector '${cfg.name}'?`)) {
    return;
  }

  try {
    await axios.delete(`/spa/settings/directory-sync/${cfg.public_id}`);
    await fetchConfigs();
  } catch (err) {
    alert('Failed to delete directory connector');
  }
}

async function syncNow(cfg) {
  syncingId.value = cfg.public_id;
  try {
    const res = await axios.post(`/spa/settings/directory-sync/${cfg.public_id}/sync-now`);
    alert(`Sync Result for ${cfg.name}:\n\n${res.data.message}`);
    await fetchConfigs();
  } catch (err) {
    alert('Directory synchronization failed');
  } finally {
    syncingId.value = null;
  }
}

async function testConnection(cfg) {
  try {
    const res = await axios.post(`/spa/settings/directory-sync/${cfg.public_id}/test`);
    alert(res.data.message);
  } catch (err) {
    alert('Connection test failed');
  }
}

onMounted(() => {
  fetchConfigs();
});
</script>
