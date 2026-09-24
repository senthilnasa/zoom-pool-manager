<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
          Platform & Institutional Settings
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Configure institutional identity, logo branding, legal compliance pages, global scheduling constraints, and AI governance
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchSettings"
          :disabled="loading"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Reload Settings"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="saveSettings"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Settings' }}</span>
        </button>
      </div>
    </div>

    <!-- Feedback Banner -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl flex items-center justify-between text-xs font-semibold transition-all"
      :class="feedbackError ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <div class="flex items-center gap-2">
        <CheckCircle2 v-if="!feedbackError" class="w-4 h-4 shrink-0 text-emerald-500" />
        <AlertCircle v-else class="w-4 h-4 shrink-0 text-rose-500" />
        <span>{{ feedback }}</span>
      </div>
      <button @click="feedback = ''" class="hover:underline font-bold">Dismiss</button>
    </div>

    <div v-if="loading && !form.org_name" class="p-12 text-center text-slate-400">
      <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
      <p class="text-sm font-semibold">Loading institutional configuration...</p>
    </div>

    <form v-else @submit.prevent="saveSettings" class="space-y-6">
      <!-- Section 1: Institutional Identity & Logo Branding -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-6">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Building2 class="w-4 h-4 text-brand-500" />
            <span>Institutional Identity & Logo Branding</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Branding logo, authoritative organization name, support contact points, and primary system timezone.
          </p>
        </div>

        <!-- Logo Management Card -->
        <div class="bg-slate-50/70 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
            Institution Logo
          </label>
          <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <!-- Logo Preview Container -->
            <div class="w-24 h-24 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-2 flex items-center justify-center shrink-0 shadow-xs relative overflow-hidden group">
              <img
                v-if="form.org_logo_url"
                :src="form.org_logo_url"
                alt="Logo Preview"
                class="w-full h-full object-contain"
              />
              <div v-else class="w-full h-full rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 flex items-center justify-center text-white font-black text-2xl shadow-sm">
                {{ form.org_name ? form.org_name.charAt(0).toUpperCase() : 'Z' }}
              </div>
            </div>

            <!-- Upload Controls -->
            <div class="space-y-2 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <input
                  ref="logoInputRef"
                  type="file"
                  accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
                  @change="onLogoSelected"
                  class="hidden"
                />

                <button
                  type="button"
                  @click="triggerLogoUpload"
                  :disabled="uploadingLogo"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 shadow-sm transition disabled:opacity-50"
                >
                  <UploadCloud class="w-3.5 h-3.5" :class="{ 'animate-bounce': uploadingLogo }" />
                  <span>{{ uploadingLogo ? 'Uploading Logo...' : 'Upload New Logo' }}</span>
                </button>

                <button
                  v-if="form.org_logo_url"
                  type="button"
                  @click="removeLogo"
                  :disabled="uploadingLogo"
                  class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 transition disabled:opacity-50"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                  <span>Remove Custom Logo</span>
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-[11px] text-slate-400">Or enter an image URL directly:</span>
                <input
                  v-model="form.org_logo_url"
                  type="text"
                  placeholder="https://example.com/logo.png"
                  class="text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-slate-800 dark:text-slate-200 flex-1 max-w-sm focus:ring-1 focus:ring-brand-500"
                />
              </div>

              <p class="text-[11px] text-slate-400">
                Supports PNG, SVG, JPG, WebP (Max 4MB). Shown in navigation sidebar, login screen, and institutional policies.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Institution / Organization Name *
            </label>
            <input
              v-model="form.org_name"
              type="text"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              placeholder="e.g. Krea University"
            />
            <p class="text-[10px] text-slate-400 mt-1">Updates globally across the navigation sidebar, header, browser tabs, and emails.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Official Support Email Address *
            </label>
            <input
              v-model="form.org_support_email"
              type="email"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              placeholder="support@institution.edu"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Institutional Website URL
            </label>
            <input
              v-model="form.org_website"
              type="url"
              placeholder="https://univ.edu"
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Primary System Timezone *
            </label>
            <select
              v-model="form.org_timezone"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section 2: Legal Policies & Compliance (Privacy Policy & Terms of Service) -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-6">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <FileText class="w-4 h-4 text-sky-500" />
              <span>Legal Policies & Compliance Documents</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Configure your institutional Privacy Policy and Terms of Service as custom HTML pages or external redirect URLs.
            </p>
          </div>

          <!-- Document Selector Tabs -->
          <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-0.5 rounded-xl text-xs font-semibold">
            <button
              type="button"
              @click="activeLegalSection = 'privacy'"
              class="px-3 py-1.5 rounded-lg transition"
              :class="activeLegalSection === 'privacy' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400'"
            >
              Privacy Policy
            </button>
            <button
              type="button"
              @click="activeLegalSection = 'terms'"
              class="px-3 py-1.5 rounded-lg transition"
              :class="activeLegalSection === 'terms' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400'"
            >
              Terms of Service
            </button>
          </div>
        </div>

        <!-- 2A. Privacy Policy Configuration -->
        <div v-show="activeLegalSection === 'privacy'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50/70 dark:bg-slate-800/30 p-3.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                Privacy Policy Mode
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Choose how users and guests access your institutional privacy policy.
              </p>
            </div>

            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.privacy_policy_type"
                  value="none"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>Default Notice</span>
              </label>

              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.privacy_policy_type"
                  value="url"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>External URL</span>
              </label>

              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.privacy_policy_type"
                  value="custom"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>Custom HTML Page</span>
              </label>
            </div>
          </div>

          <!-- URL Input for Privacy -->
          <div v-if="form.privacy_policy_type === 'url'" class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              External Privacy Policy URL *
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.privacy_policy_url"
                type="url"
                placeholder="https://institution.edu/privacy-policy"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
              <a
                v-if="form.privacy_policy_url"
                :href="form.privacy_policy_url"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3 py-2 rounded-xl text-xs font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/50 hover:bg-brand-100 border border-brand-200 dark:border-brand-800 shrink-0 flex items-center gap-1"
              >
                <span>Test Link</span>
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </div>
            <p class="text-[10px] text-slate-400">Users clicking Privacy Policy links will be seamlessly redirected to this URL.</p>
          </div>

          <!-- Rich HTML Editor for Privacy -->
          <div v-if="form.privacy_policy_type === 'custom'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Custom Privacy Policy Document (Visual Rich Text & HTML Editor)
              </label>
              <router-link
                to="/app/privacy-policy"
                target="_blank"
                class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1"
              >
                <span>Preview Published Page</span>
                <ExternalLink class="w-3 h-3" />
              </router-link>
            </div>

            <RichHtmlEditor
              v-model="form.privacy_policy_content"
              template-type="privacy"
              :org-name="form.org_name"
              placeholder="Draft your institutional privacy policy here..."
            />
          </div>
        </div>

        <!-- 2B. Terms of Service Configuration -->
        <div v-show="activeLegalSection === 'terms'" class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50/70 dark:bg-slate-800/30 p-3.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                Terms of Service Mode
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Choose how users and guests access your institutional terms of service.
              </p>
            </div>

            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.terms_type"
                  value="none"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>Default Notice</span>
              </label>

              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.terms_type"
                  value="url"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>External URL</span>
              </label>

              <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input
                  type="radio"
                  v-model="form.terms_type"
                  value="custom"
                  class="text-brand-600 focus:ring-brand-500"
                />
                <span>Custom HTML Page</span>
              </label>
            </div>
          </div>

          <!-- URL Input for Terms -->
          <div v-if="form.terms_type === 'url'" class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              External Terms of Service URL *
            </label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.terms_url"
                type="url"
                placeholder="https://institution.edu/terms"
                required
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
              />
              <a
                v-if="form.terms_url"
                :href="form.terms_url"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3 py-2 rounded-xl text-xs font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/50 hover:bg-brand-100 border border-brand-200 dark:border-brand-800 shrink-0 flex items-center gap-1"
              >
                <span>Test Link</span>
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </div>
            <p class="text-[10px] text-slate-400">Users clicking Terms of Service links will be seamlessly redirected to this URL.</p>
          </div>

          <!-- Rich HTML Editor for Terms -->
          <div v-if="form.terms_type === 'custom'" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Custom Terms of Service Document (Visual Rich Text & HTML Editor)
              </label>
              <router-link
                to="/app/terms-of-service"
                target="_blank"
                class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1"
              >
                <span>Preview Published Page</span>
                <ExternalLink class="w-3 h-3" />
              </router-link>
            </div>

            <RichHtmlEditor
              v-model="form.terms_content"
              template-type="terms"
              :org-name="form.org_name"
              placeholder="Draft your institutional terms of service here..."
            />
          </div>
        </div>
      </div>

      <!-- Section 3: Host Pools & Scheduling Constraints -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Clock class="w-4 h-4 text-indigo-500" />
            <span>Host Pools & Scheduling Boundaries</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Buffers prevent consecutive Zoom host collisions and control booking lead times across all resource pools.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Buffer Time (Minutes) *
            </label>
            <input
              v-model.number="form.org_default_buffer_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Recommended: 10 minutes between sessions.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Minimum Buffer Time (Minutes) *
            </label>
            <input
              v-model.number="form.org_min_buffer_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Absolute minimum allowed buffer enforced by allocator.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Minimum Advance Notice (Hours) *
            </label>
            <input
              v-model.number="form.org_min_notice_hours"
              type="number"
              min="0"
              max="168"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Hours before start time required to book a pool license.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Max Advance Horizon (Days) *
            </label>
            <input
              v-model.number="form.org_max_advance_days"
              type="number"
              min="1"
              max="365"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">How far into the future users can schedule reservations.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Max Meeting Duration (Minutes) *
            </label>
            <input
              v-model.number="form.org_max_duration_minutes"
              type="number"
              min="15"
              max="1440"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Default 480 mins (8 hours) cap per single session.</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Host Key Lead Time (Minutes) *
            </label>
            <input
              v-model.number="form.host_lead_minutes"
              type="number"
              min="0"
              max="120"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            />
            <p class="text-[10px] text-slate-400 mt-1">Minutes before meeting when host key becomes visible/claimable.</p>
          </div>
        </div>
      </div>

      <!-- Section 4: Recording & AI Governance -->
      <div class="glass-card p-6 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-4">
        <div class="border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-emerald-500" />
            <span>Recording & AI Companion Governance Policies</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Enforce institutional privacy, compliance recording rules, and generative AI transcription boundaries.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Default Recording Policy *
            </label>
            <select
              v-model="form.org_default_recording_mode"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="mode in recordingModes" :key="mode.value" :value="mode.value">
                {{ mode.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              AI Companion & Smart Summary Policy *
            </label>
            <select
              v-model="form.org_ai_companion_policy"
              required
              class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="policy in aiPolicies" :key="policy.value" :value="policy.value">
                {{ policy.label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="authStore.isAdmin" class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          @click="fetchSettings"
          class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        >
          Reset to Saved
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-500/20 transition disabled:opacity-50 flex items-center gap-2"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Institutional Settings' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useBrandingStore } from '@/stores/branding';
import RichHtmlEditor from '@/components/RichHtmlEditor.vue';
import {
  Building2,
  Clock,
  ShieldCheck,
  RefreshCw,
  Save,
  CheckCircle2,
  AlertCircle,
  UploadCloud,
  Trash2,
  FileText,
  ExternalLink,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const loading = ref(false);
const saving = ref(false);
const uploadingLogo = ref(false);
const feedback = ref('');
const feedbackError = ref(false);
const activeLegalSection = ref('privacy');
const logoInputRef = ref(null);

const timezones = ref([]);
const recordingModes = ref([]);
const aiPolicies = ref([]);

const form = ref({
  org_name: '',
  org_logo_url: '',
  org_support_email: '',
  org_website: '',
  org_timezone: 'Asia/Kolkata',

  privacy_policy_type: 'none',
  privacy_policy_url: '',
  privacy_policy_content: '',
  terms_type: 'none',
  terms_url: '',
  terms_content: '',

  org_min_buffer_minutes: 10,
  org_default_buffer_minutes: 10,
  org_min_notice_hours: 2,
  org_max_advance_days: 90,
  org_max_duration_minutes: 480,
  host_lead_minutes: 15,

  org_ai_companion_policy: 'ALLOWED',
  org_default_recording_mode: 'none',
});

const triggerLogoUpload = () => {
  if (logoInputRef.value) {
    logoInputRef.value.click();
  }
};

const onLogoSelected = async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  uploadingLogo.value = true;
  feedback.value = '';
  feedbackError.value = false;

  const data = new FormData();
  data.append('logo', file);

  try {
    const res = await axios.post('/spa/settings/general/logo', data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    form.value.org_logo_url = res.data.logo_url;
    brandingStore.updateBranding({ org_logo_url: res.data.logo_url });
    feedback.value = res.data.message || 'Logo uploaded successfully.';
  } catch (err) {
    console.error('Failed to upload logo', err);
    feedback.value = err.response?.data?.message || 'Failed to upload institutional logo.';
    feedbackError.value = true;
  } finally {
    uploadingLogo.value = false;
    if (logoInputRef.value) logoInputRef.value.value = '';
  }
};

const removeLogo = async () => {
  if (!confirm('Are you sure you want to remove the custom logo and revert to the default brand mark?')) {
    return;
  }

  uploadingLogo.value = true;
  feedback.value = '';
  feedbackError.value = false;

  try {
    const res = await axios.delete('/spa/settings/general/logo');
    form.value.org_logo_url = '';
    brandingStore.updateBranding({ org_logo_url: '' });
    feedback.value = res.data.message || 'Custom logo removed successfully.';
  } catch (err) {
    console.error('Failed to remove logo', err);
    feedback.value = err.response?.data?.message || 'Failed to remove institutional logo.';
    feedbackError.value = true;
  } finally {
    uploadingLogo.value = false;
  }
};

const fetchSettings = async () => {
  loading.value = true;
  feedback.value = '';
  try {
    const res = await axios.get('/spa/settings/general');
    form.value = { ...res.data.settings };
    timezones.value = res.data.timezones || [];
    recordingModes.value = res.data.recording_modes || [];
    aiPolicies.value = res.data.ai_companion_policies || [];
  } catch (err) {
    console.error('Failed to load settings', err);
    feedback.value = 'Failed to load general platform settings.';
    feedbackError.value = true;
  } finally {
    loading.value = false;
  }
};

const saveSettings = async () => {
  saving.value = true;
  feedback.value = '';
  feedbackError.value = false;
  try {
    const res = await axios.put('/spa/settings/general', form.value);
    feedback.value = res.data.message || 'General settings saved successfully.';
    feedbackError.value = false;

    // Immediately synchronize the branding store so the Sidebar, Header, Title, and Footer update reactively across all pages!
    brandingStore.updateBranding({
      org_name: form.value.org_name,
      org_logo_url: form.value.org_logo_url,
      org_support_email: form.value.org_support_email,
      org_website: form.value.org_website,
      privacy_policy_type: form.value.privacy_policy_type,
      privacy_policy_url: form.value.privacy_policy_url,
      terms_type: form.value.terms_type,
      terms_url: form.value.terms_url,
    });
  } catch (err) {
    console.error('Failed to save settings', err);
    feedback.value = err.response?.data?.message || 'Failed to save general settings.';
    feedbackError.value = true;
  } finally {
    saving.value = false;
  }
};

onMounted(fetchSettings);
</script>
