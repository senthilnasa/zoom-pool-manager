<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
          <KeyRound class="w-6 h-6 text-brand-500 shrink-0" />
          <span>Single Sign-On (SSO) & SAML Configuration</span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Configure Google Workspace, Microsoft Entra ID (Azure AD), and SAML 2.0 Identity Providers with JIT provisioning.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchProviders"
          class="p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 rounded-xl transition shadow-sm"
          title="Refresh Providers"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white text-xs font-semibold rounded-xl shadow-sm transition"
        >
          <Plus class="w-4 h-4" />
          <span>Add Identity Provider</span>
        </button>
      </div>
    </div>

    <!-- Quick Telemetry Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
          <ShieldCheck class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ activeProvidersCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Active SSO Providers</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
          <Users class="w-6 h-6" />
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalIdentitiesCount }}</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Linked Federated Users</div>
        </div>
      </div>

      <div class="p-5 bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
          <FileCode2 class="w-6 h-6" />
        </div>
        <div>
          <div class="text-sm font-bold text-slate-900 dark:text-white">SAML 2.0 Compliant</div>
          <div class="text-xs font-medium text-slate-500 dark:text-slate-400">SP Metadata & ACS Active</div>
        </div>
      </div>
    </div>

    <!-- Search Bar & Filters -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search identity providers by name, driver, or domain..."
          class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
        />
      </div>
    </div>

    <!-- Providers List Grid -->
    <div v-if="loading && providers.length === 0" class="p-12 text-center bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
      <RefreshCw class="w-8 h-8 mx-auto text-brand-500 animate-spin mb-3" />
      <p class="text-sm text-slate-500">Loading SSO providers...</p>
    </div>

    <div v-else-if="providers.length === 0" class="p-12 text-center bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
      <ShieldAlert class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
      <h3 class="text-base font-bold text-slate-900 dark:text-white">No SSO Providers Configured</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1 mb-5">
        Connect Google Workspace, Microsoft Entra ID (Azure AD), or SAML 2.0 to enable single sign-on for your faculty and students.
      </p>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-xs font-semibold rounded-xl"
      >
        <Plus class="w-4 h-4" />
        <span>Add Identity Provider</span>
      </button>
    </div>

    <div v-else-if="!filteredProviders.length" class="p-12 text-center bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700">
      <ShieldAlert class="w-10 h-10 mx-auto text-slate-400 mb-3 opacity-40" />
      <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Matching Identity Providers</p>
      <p class="text-xs text-slate-400 mt-1">Try adjusting your search criteria.</p>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div
        v-for="p in filteredProviders"
        :key="p.public_id"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 p-5 shadow-sm space-y-4 hover:border-brand-300 dark:hover:border-brand-700 transition"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center shrink-0">
              <!-- Google Icon -->
              <svg v-if="p.driver === 'google'" class="w-6 h-6" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
              </svg>
              <!-- Microsoft Icon -->
              <svg v-else-if="p.driver === 'microsoft' || p.driver === 'azure'" class="w-6 h-6" viewBox="0 0 23 23">
                <path fill="#f35325" d="M1 1h10v10H1z"/>
                <path fill="#81bc06" d="M12 1h10v10H12z"/>
                <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                <path fill="#ffba08" d="M12 12h10v10H12z"/>
              </svg>
              <!-- SAML Icon -->
              <Lock v-else class="w-5 h-5 text-indigo-500" />
            </div>

            <div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white leading-snug">{{ p.name }}</h2>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider" :class="driverBadgeClass(p.driver)">
                  {{ formatDriverName(p.driver) }}
                </span>
                <span class="text-xs text-slate-400">ID: {{ p.public_id }}</span>
              </div>
            </div>
          </div>

          <!-- Status toggle switch -->
          <button
            @click="toggleProvider(p)"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
            :class="p.enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
            :title="p.enabled ? 'Enabled (Click to Disable)' : 'Disabled (Click to Enable)'"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="p.enabled ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>

        <!-- Details Info Grid -->
        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800/80">
          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Client ID / SSO URL</span>
            <span class="font-mono text-slate-700 dark:text-slate-300 truncate block" :title="p.client_id || p.metadata_url || 'None'">
              {{ p.client_id || p.metadata_url || 'None' }}
            </span>
          </div>

          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Secret / Certificate</span>
            <span class="text-slate-700 dark:text-slate-300">
              <span v-if="p.has_client_secret" class="text-emerald-600 dark:text-emerald-400 font-medium">✓ Client Secret</span>
              <span v-else-if="p.has_primary_cert" class="text-indigo-600 dark:text-indigo-400 font-medium">✓ Primary X.509</span>
              <span v-else class="text-amber-600 dark:text-amber-400 font-medium">⚠ None Set</span>
            </span>
          </div>

          <div class="col-span-2">
            <span class="text-slate-400 block text-[10px] uppercase font-bold mb-1">Allowed Domains</span>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="d in (p.allowed_domains || [])"
                :key="d"
                class="px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[11px] font-mono text-slate-600 dark:text-slate-300"
              >
                @{{ d }}
              </span>
              <span v-if="!p.allowed_domains || p.allowed_domains.length === 0" class="text-slate-400 text-[11px] italic">
                All domains allowed
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/80">
          <div class="flex items-center gap-2">
            <button
              v-if="p.driver === 'saml'"
              @click="openSpModal(p)"
              class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-lg text-xs font-semibold transition"
            >
              <FileCode2 class="w-3.5 h-3.5" />
              <span>SP Metadata</span>
            </button>

            <button
              @click="testProvider(p)"
              class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-medium transition"
            >
              <CheckCircle class="w-3.5 h-3.5" />
              <span>Test</span>
            </button>
          </div>

          <div class="flex items-center gap-1.5">
            <button
              @click="openEditModal(p)"
              class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
              title="Edit Provider"
            >
              <Edit3 class="w-4 h-4" />
            </button>
            <button
              @click="deleteProvider(p)"
              class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
              title="Delete Provider"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Provider Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 my-8 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-4">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <KeyRound class="w-5 h-5 text-brand-500" />
            <span>{{ isEditing ? 'Edit Identity Provider' : 'Add New Identity Provider' }}</span>
          </h2>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="saveProvider" class="space-y-4 text-xs">
          <!-- Name & Driver -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Provider Display Name *</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. University Google Workspace"
                required
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Identity Protocol / Driver *</label>
              <select
                v-model="form.driver"
                :disabled="isEditing"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              >
                <option value="google">Google Workspace (OAuth 2.0 / OIDC)</option>
                <option value="azure">Microsoft Entra ID / Azure AD (OAuth 2.0)</option>
                <option value="saml">SAML 2.0 (Okta, Shibboleth, Entra SAML)</option>
              </select>
            </div>
          </div>

          <!-- Google Specific -->
          <div v-if="form.driver === 'google'" class="space-y-3 p-4 bg-sky-50/50 dark:bg-sky-950/20 rounded-xl border border-sky-100 dark:border-sky-900/40">
            <div class="font-bold text-sky-800 dark:text-sky-300 text-[11px] uppercase tracking-wider">Google OAuth 2.0 Credentials</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Client ID</label>
                <input
                  v-model="form.client_id"
                  type="text"
                  placeholder="xxxxx.apps.googleusercontent.com"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Client Secret</label>
                <input
                  v-model="form.client_secret"
                  type="password"
                  :placeholder="isEditing ? '(Unchanged)' : 'Enter Client Secret'"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- Microsoft Specific -->
          <div v-if="form.driver === 'azure' || form.driver === 'microsoft'" class="space-y-3 p-4 bg-blue-50/50 dark:bg-blue-950/20 rounded-xl border border-blue-100 dark:border-blue-900/40">
            <div class="font-bold text-blue-800 dark:text-blue-300 text-[11px] uppercase tracking-wider">Microsoft Entra ID (Azure AD) Credentials</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Tenant ID</label>
                <input
                  v-model="form.tenant_id"
                  type="text"
                  placeholder="common or Directory GUID"
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
                  :placeholder="isEditing ? '(Unchanged)' : 'Enter Client Secret'"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
            </div>
          </div>

          <!-- SAML 2.0 Specific -->
          <div v-if="form.driver === 'saml'" class="space-y-3 p-4 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl border border-indigo-100 dark:border-indigo-900/40">
            <div class="font-bold text-indigo-800 dark:text-indigo-300 text-[11px] uppercase tracking-wider">SAML 2.0 IdP Settings</div>
            <div>
              <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">IdP Single Sign-On Service URL *</label>
              <input
                v-model="form.metadata_url"
                type="url"
                placeholder="https://login.microsoftonline.com/.../saml2 or https://idp.univ.edu/idp/profile/SAML2/Redirect/SSO"
                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Primary X.509 Certificate (PEM)</label>
                <textarea
                  v-model="form.certificate_primary"
                  rows="4"
                  placeholder="-----BEGIN CERTIFICATE-----&#10;MIID...&#10;-----END CERTIFICATE-----"
                  class="w-full font-mono text-[10px] px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                ></textarea>
              </div>

              <div>
                <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Secondary Certificate (Rotation)</label>
                <textarea
                  v-model="form.certificate_secondary"
                  rows="4"
                  placeholder="Optional rollover certificate"
                  class="w-full font-mono text-[10px] px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Common Settings -->
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Allowed Email Domains (Comma-separated)</label>
            <input
              v-model="domainsInput"
              type="text"
              placeholder="univ.edu, cs.univ.edu, student.univ.edu"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 focus:outline-none"
            />
            <span class="text-[10px] text-slate-400 mt-1 block">Leave empty to allow all domains.</span>
          </div>

          <div class="flex items-center gap-2 pt-2">
            <input
              v-model="form.enabled"
              id="enable-provider"
              type="checkbox"
              class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-700"
            />
            <label for="enable-provider" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
              Enable this provider for active sign-in on login page
            </label>
          </div>

          <!-- Footer Buttons -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-medium rounded-xl transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl shadow-sm transition disabled:opacity-60"
            >
              <RefreshCw v-if="saving" class="w-4 h-4 animate-spin" />
              <span>{{ isEditing ? 'Update Provider' : 'Create Provider' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- SP Metadata Drawer / Modal -->
    <div v-if="showSpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
          <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <FileCode2 class="w-5 h-5 text-indigo-500" />
            <span>SAML Service Provider (SP) Credentials</span>
          </h2>
          <button @click="showSpModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400">
          Copy these values into your Identity Provider (Azure AD Enterprise App, Okta SAML Integration, Google SAML App):
        </p>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">SP Entity ID (Audience URI)</label>
            <div class="flex gap-2">
              <input
                type="text"
                readonly
                :value="selectedProvider?.sp_entity_id"
                class="w-full font-mono text-[11px] px-3 py-1.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-xl"
              />
              <button
                @click="copyToClipboard(selectedProvider?.sp_entity_id)"
                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 rounded-xl font-medium shrink-0"
              >
                Copy
              </button>
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Assertion Consumer Service (ACS) URL (Reply URL)</label>
            <div class="flex gap-2">
              <input
                type="text"
                readonly
                :value="selectedProvider?.acs_url"
                class="w-full font-mono text-[11px] px-3 py-1.5 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-xl"
              />
              <button
                @click="copyToClipboard(selectedProvider?.acs_url)"
                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-200 rounded-xl font-medium shrink-0"
              >
                Copy
              </button>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
          <a
            :href="`/spa/settings/identity-providers/${selectedProvider?.public_id}/sp-metadata`"
            target="_blank"
            download
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition shadow-sm"
          >
            <Download class="w-4 h-4" />
            <span>Download SP Metadata XML</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
  KeyRound,
  ShieldCheck,
  ShieldAlert,
  Users,
  FileCode2,
  Plus,
  RefreshCw,
  Lock,
  Edit3,
  Trash2,
  CheckCircle,
  Search,
  X,
  Download,
} from 'lucide-vue-next';

const providers = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const showSpModal = ref(false);
const isEditing = ref(false);
const selectedProvider = ref(null);
const domainsInput = ref('');
const searchQuery = ref('');

const filteredProviders = computed(() => {
  if (!searchQuery.value.trim()) return providers.value;
  const q = searchQuery.value.toLowerCase().trim();
  return providers.value.filter((p) => {
    const domainsStr = (p.allowed_domains || []).join(' ');
    return (
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.driver && p.driver.toLowerCase().includes(q)) ||
      (p.public_id && p.public_id.toLowerCase().includes(q)) ||
      domainsStr.toLowerCase().includes(q)
    );
  });
});

const form = ref({
  name: '',
  driver: 'google',
  client_id: '',
  client_secret: '',
  tenant_id: '',
  metadata_url: '',
  certificate_primary: '',
  certificate_secondary: '',
  allowed_domains: [],
  enabled: true,
});

const activeProvidersCount = computed(() => {
  return providers.value.filter(p => p.enabled).length;
});

const totalIdentitiesCount = computed(() => {
  return providers.value.reduce((acc, p) => acc + (p.users_count || 0), 0);
});

function formatDriverName(driver) {
  if (driver === 'google') return 'Google Workspace';
  if (driver === 'azure' || driver === 'microsoft') return 'Microsoft Entra ID';
  if (driver === 'saml') return 'SAML 2.0';
  return driver;
}

function driverBadgeClass(driver) {
  if (driver === 'google') return 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400';
  if (driver === 'azure' || driver === 'microsoft') return 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400';
  return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400';
}

async function fetchProviders() {
  loading.value = true;
  try {
    const res = await axios.get('/spa/settings/identity-providers');
    providers.value = res.data.providers || [];
  } catch (err) {
    console.error('Failed to load identity providers', err);
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  isEditing.value = false;
  selectedProvider.value = null;
  domainsInput.value = '';
  form.value = {
    name: '',
    driver: 'google',
    client_id: '',
    client_secret: '',
    tenant_id: '',
    metadata_url: '',
    certificate_primary: '',
    certificate_secondary: '',
    allowed_domains: [],
    enabled: true,
  };
  showModal.value = true;
}

function openEditModal(p) {
  isEditing.value = true;
  selectedProvider.value = p;
  domainsInput.value = (p.allowed_domains || []).join(', ');
  form.value = {
    name: p.name,
    driver: p.driver,
    client_id: p.client_id || '',
    client_secret: '',
    tenant_id: p.tenant_id || '',
    metadata_url: p.metadata_url || '',
    certificate_primary: p.certificate_primary || '',
    certificate_secondary: p.certificate_secondary || '',
    allowed_domains: p.allowed_domains || [],
    enabled: p.enabled,
  };
  showModal.value = true;
}

function openSpModal(p) {
  selectedProvider.value = p;
  showSpModal.value = true;
}

async function saveProvider() {
  saving.value = true;
  try {
    const payload = {
      ...form.value,
      allowed_domains: domainsInput.value
        .split(',')
        .map(d => d.trim().replace(/^@/, ''))
        .filter(Boolean),
    };

    if (isEditing.value && selectedProvider.value) {
      await axios.put(`/spa/settings/identity-providers/${selectedProvider.value.public_id}`, payload);
    } else {
      await axios.post('/spa/settings/identity-providers', payload);
    }

    showModal.value = false;
    await fetchProviders();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save identity provider');
  } finally {
    saving.value = false;
  }
}

async function toggleProvider(p) {
  try {
    await axios.put(`/spa/settings/identity-providers/${p.public_id}`, {
      enabled: !p.enabled,
    });
    p.enabled = !p.enabled;
  } catch (err) {
    alert('Failed to toggle provider state');
  }
}

async function deleteProvider(p) {
  if (!confirm(`Are you sure you want to delete identity provider '${p.name}'?`)) {
    return;
  }

  try {
    await axios.delete(`/spa/settings/identity-providers/${p.public_id}`);
    await fetchProviders();
  } catch (err) {
    alert('Failed to delete provider');
  }
}

async function testProvider(p) {
  try {
    const res = await axios.post(`/spa/settings/identity-providers/${p.public_id}/test`);
    const diag = res.data.diagnostics?.join('\n') || res.data.message;
    alert(`Diagnostics for ${p.name}:\n\n${diag}`);
  } catch (err) {
    alert('Failed to test identity provider configuration');
  }
}

function copyToClipboard(text) {
  if (!text) return;
  navigator.clipboard.writeText(text).then(() => {
    alert('Copied to clipboard!');
  });
}

onMounted(() => {
  fetchProviders();
});
</script>
