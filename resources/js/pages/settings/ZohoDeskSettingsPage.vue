<template>
  <div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <Headphones class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Zoho Desk Integration & Widget
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Schedule Zoom meetings on behalf of ticket requesters directly from Zoho Desk and auto-reply with meeting details and credentials.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="showGuide = !showGuide"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all border border-slate-200/80 dark:border-slate-700/80 cursor-pointer"
        >
          <HelpCircle class="w-4 h-4 text-brand-500" />
          <span>{{ showGuide ? 'Hide Guide' : 'Setup Guide' }}</span>
        </button>

        <button
          type="button"
          @click="downloadExtension"
          :disabled="downloading"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 text-xs font-semibold transition-all border border-emerald-200/80 dark:border-emerald-800/80 cursor-pointer"
          title="Download ready-to-install Zoho Desk extension ZIP package"
        >
          <Download class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
          <span>{{ downloading ? 'Generating ZIP...' : 'Download Extension (.zip)' }}</span>
        </button>

        <button
          type="button"
          @click="testConnection"
          :disabled="testing"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-md shadow-brand-500/20 transition-all cursor-pointer disabled:opacity-50"
        >
          <Activity class="w-4 h-4" :class="{ 'animate-spin': testing }" />
          <span>{{ testing ? 'Testing...' : 'Test Connection' }}</span>
        </button>
      </div>
    </div>

    <!-- Test Connection Results Alert Box -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
    >
      <div
        v-if="testResult"
        class="p-4 rounded-2xl border backdrop-blur-xl flex items-start gap-3 text-sm"
        :class="testResult.success ? 'bg-emerald-50/80 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200' : 'bg-rose-50/80 dark:bg-rose-950/60 border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-200'"
      >
        <CheckCircle2 v-if="testResult.success" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
        <AlertCircle v-else class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />

        <div class="flex-1 space-y-1">
          <div class="font-bold flex items-center justify-between">
            <span>{{ testResult.success ? 'Zoho Desk Connection Verified' : 'Connection Handshake Failed' }}</span>
            <button
              type="button"
              @click="testResult = null"
              class="text-xs opacity-60 hover:opacity-100 cursor-pointer"
            >
              ✕
            </button>
          </div>
          <p class="text-xs leading-relaxed opacity-90">{{ testResult.message }}</p>
          <div v-if="testResult.org_name" class="text-xs font-medium pt-1">
            <strong>Connected Org:</strong> {{ testResult.org_name }}
          </div>
        </div>
      </div>
    </transition>

    <!-- Setup Guide Box -->
    <div
      v-if="showGuide"
      class="p-5 rounded-2xl bg-gradient-to-br from-brand-50/80 to-blue-50/50 dark:from-brand-950/30 dark:to-slate-900 border border-brand-200/80 dark:border-brand-800/60 text-xs space-y-4"
    >
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-sm text-brand-900 dark:text-brand-200 flex items-center gap-2">
          <BookOpen class="w-4 h-4 text-brand-600" />
          <span>Zoho Desk Extension Installation & Architecture Guide</span>
        </h3>
        <button
          type="button"
          @click="showGuide = false"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
        >
          ✕
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">1</span>
            Download Extension Package
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Click <strong>Download Extension (.zip)</strong> above. The ZIP package comes pre-configured with your ZPM Server URL and dedicated API Token.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">2</span>
            Install in Zoho Desk Setup
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            In Zoho Desk, navigate to <strong>Setup (Gear)</strong> → <strong>Developer Space</strong> → <strong>Extensions</strong> → <strong>Upload Custom Extension</strong> and upload the ZIP.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">3</span>
            Book On Behalf of Requester
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            When an IT agent opens any ticket, the <strong>Zoom Meeting</strong> widget appears in the right sidebar, reading the requester's name, email, and ticket subject automatically.
          </p>
        </div>

        <div class="p-3.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1.5">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-bold flex items-center justify-center">4</span>
            Automatic Reply & Close
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Clicking <em>Book Meeting & Update Ticket</em> creates the session, posts meeting link, passcode, and host key into ticket conversation, and marks the ticket resolved/closed!
          </p>
        </div>
      </div>
    </div>

    <!-- Main Configuration Form -->
    <form @submit.prevent="saveConfiguration" class="space-y-6">
      <!-- Section 1: Extension Connection & Token -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <Key class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">ZPM Server & Widget API Token</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Credentials used by the Zoho Desk right-panel widget to communicate securely with this ZPM server.
              </p>
            </div>
          </div>

          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.enabled" class="sr-only peer">
            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
            <span class="ml-2.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
              {{ form.enabled ? 'Enabled' : 'Disabled' }}
            </span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              ZPM Server URL
            </label>
            <input
              type="url"
              v-model="form.server_url"
              placeholder="https://zoom.yourdomain.edu"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
              required
            />
            <p class="text-[11px] text-slate-400">
              Public HTTPS URL of this Zoom Pool Manager deployment reachable by support agents' browsers.
            </p>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Zoho Desk Widget API Token
            </label>
            <div class="relative flex items-center">
              <input
                :type="showApiToken ? 'text' : 'password'"
                v-model="form.api_token"
                placeholder="zpm_zd_..."
                class="w-full pl-3.5 pr-20 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono focus:ring-2 focus:ring-brand-500 outline-none"
                required
              />
              <div class="absolute right-1.5 flex items-center gap-1">
                <button
                  type="button"
                  @click="showApiToken = !showApiToken"
                  class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                  title="Toggle visibility"
                >
                  <Eye v-if="!showApiToken" class="w-3.5 h-3.5" />
                  <EyeOff v-else class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="copyText(form.api_token, 'API Token copied!')"
                  class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                  title="Copy token"
                >
                  <Copy class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="regenerateApiToken"
                  :disabled="regeneratingToken"
                  class="p-1.5 text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 cursor-pointer"
                  title="Regenerate token (Revokes current token)"
                >
                  <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': regeneratingToken }" />
                </button>
              </div>
            </div>
            <p class="text-[11px] text-slate-400">
              Authenticated token bundled into the extension package. Click refresh to rotate.
            </p>
          </div>
        </div>

        <!-- Security & Domain Whitelist Safeguard -->
        <div class="p-4 rounded-xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/50 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <ShieldAlert class="w-4 h-4 text-amber-600 dark:text-amber-400" />
              <span class="text-xs font-bold text-amber-900 dark:text-amber-200">
                Security Safeguard: Allowed Recipient Domains
              </span>
            </div>
            <span class="text-[10px] text-amber-700 dark:text-amber-300 font-medium bg-amber-100 dark:bg-amber-900/50 px-2 py-0.5 rounded">
              Leaked Password & Token Protection
            </span>
          </div>

          <p class="text-xs text-amber-800/90 dark:text-amber-300/80 leading-relaxed">
            Specify comma-separated domains (e.g. <code>krea.edu.in, student.krea.edu.in</code>). Even if an IT agent account or widget token is ever compromised, meeting requests for unauthorized recipient domains will be instantly rejected by the server.
          </p>

          <div>
            <input
              type="text"
              v-model="form.allowed_domains"
              placeholder="e.g. krea.edu.in, student.krea.edu.in (leave empty to allow all domains)"
              class="w-full px-3.5 py-2.5 rounded-xl border border-amber-300/80 dark:border-amber-700/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono focus:ring-2 focus:ring-amber-500 outline-none"
            />
          </div>
        </div>
      </GlassCard>

      <!-- Section 2: Zoho Desk Server-to-Server API & OAuth Credentials -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <ShieldCheck class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">Zoho Desk API & OAuth Credentials</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Optional server-side credentials allowing ZPM to post ticket comments and close tickets via official Zoho Desk REST API v2.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Zoho Data Center (DC)
            </label>
            <select
              v-model="form.dc"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            >
              <option v-for="(info, key) in dataCenters" :key="key" :value="key">
                {{ info.label }} ({{ key }})
              </option>
            </select>
            <p class="text-[11px] text-slate-400">Matches your Zoho Desk account login domain.</p>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Portal / Organization ID
            </label>
            <input
              type="text"
              v-model="form.org_id"
              placeholder="e.g. 60001234567"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            />
            <p class="text-[11px] text-slate-400">Found in Zoho Desk Setup → Portal / Organization Profile.</p>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              OAuth Client ID
            </label>
            <input
              type="text"
              v-model="form.client_id"
              placeholder="1000.XXXX..."
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            />
            <p class="text-[11px] text-slate-400">Created in Zoho Developer Console (Self Client / Server-based App).</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between">
              <span>OAuth Client Secret</span>
              <span v-if="config?.has_client_secret" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                <Check class="w-3 h-3" /> Configured
              </span>
            </label>
            <input
              type="password"
              v-model="form.client_secret"
              :placeholder="config?.has_client_secret ? '•••••••••••••••• (Leave blank to keep unchanged)' : 'Enter Client Secret'"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between">
              <span>OAuth Refresh Token</span>
              <span v-if="config?.has_refresh_token" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                <Check class="w-3 h-3" /> Configured
              </span>
            </label>
            <input
              type="password"
              v-model="form.refresh_token"
              :placeholder="config?.has_refresh_token ? '•••••••••••••••• (Leave blank to keep unchanged)' : 'Enter Refresh Token with Desk scopes'"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            />
          </div>
        </div>

        <div class="space-y-1.5 pt-1">
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between">
            <span>Direct Desk Agent / Personal Token (Alternative to OAuth)</span>
            <span v-if="config?.has_agent_token" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
              <Check class="w-3 h-3" /> Configured
            </span>
          </label>
          <input
            type="password"
            v-model="form.agent_token"
            :placeholder="config?.has_agent_token ? '•••••••••••••••• (Leave blank to keep unchanged)' : 'Optional: Direct Desk Agent API Token'"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
          />
          <p class="text-[11px] text-slate-400">
            If you provide a personal agent token, ZPM can use it directly without OAuth token exchange.
          </p>
        </div>
      </GlassCard>

      <!-- Section 3: Booking Defaults & Auto-Actions -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <Sliders class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">Booking Defaults & Ticket Actions</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Default behavior for meeting duration, pool selection, ticket replies, and ticket status updates.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Default Resource Pool
            </label>
            <select
              v-model="form.default_pool_id"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            >
              <option :value="null">Any Available Pool (Automatic)</option>
              <option v-for="pool in pools" :key="pool.id" :value="pool.id">
                {{ pool.name }} (Cap: {{ pool.capacity }})
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Default Meeting Template
            </label>
            <select
              v-model="form.default_template_id"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            >
              <option :value="null">None (Use Default Profile)</option>
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
                {{ tpl.name }} ({{ tpl.duration_minutes }}m)
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Default Duration (Minutes)
            </label>
            <select
              v-model="form.default_duration_minutes"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-brand-500 outline-none"
            >
              <option :value="30">30 minutes</option>
              <option :value="45">45 minutes</option>
              <option :value="60">60 minutes (1 hour)</option>
              <option :value="90">90 minutes (1.5 hours)</option>
              <option :value="120">120 minutes (2 hours)</option>
              <option :value="180">180 minutes (3 hours)</option>
              <option :value="240">240 minutes (4 hours)</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
          <!-- Reply Visibility Toggle -->
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-900 dark:text-white">Default Ticket Reply Type</span>
              <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md" :class="form.default_is_public ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'">
                {{ form.default_is_public ? 'Public Reply' : 'Internal Note' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Choose whether the scheduled meeting details are sent directly to the ticket requester as a public reply or saved as an internal private comment for the IT team.
            </p>
            <div class="flex items-center gap-4 pt-1">
              <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                <input type="radio" v-model="form.default_is_public" :value="true" class="text-brand-600 focus:ring-brand-500" />
                <span class="text-slate-800 dark:text-slate-200 font-medium">Public Reply (Visible to User)</span>
              </label>
              <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                <input type="radio" v-model="form.default_is_public" :value="false" class="text-brand-600 focus:ring-brand-500" />
                <span class="text-slate-800 dark:text-slate-200 font-medium">Internal Note (Private)</span>
              </label>
            </div>
          </div>

          <!-- Auto-Close Ticket Toggle -->
          <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-900 dark:text-white">Auto-Close / Resolve Ticket</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.auto_close_ticket" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
              </label>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              When enabled, scheduling the meeting will automatically update the Zoho Desk ticket status.
            </p>
            <div class="flex items-center gap-2 pt-1" v-if="form.auto_close_ticket">
              <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Target Status:</span>
              <input
                type="text"
                v-model="form.ticket_close_status"
                placeholder="Closed"
                class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-medium focus:ring-2 focus:ring-brand-500 outline-none w-32"
              />
            </div>
          </div>
        </div>
      </GlassCard>

      <!-- Section 4: Comment & Message Template -->
      <GlassCard class="p-6 space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2.5">
            <MessageSquareShare class="w-5 h-5 text-brand-600 dark:text-brand-400" />
            <div>
              <h3 class="font-bold text-slate-900 dark:text-white text-base">Ticket Comment & Reply Template</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Customize the message template filled into the Zoho Desk ticket upon meeting creation.
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="resetCommentTemplate"
            class="text-xs text-slate-500 hover:text-brand-600 dark:hover:text-brand-400 font-semibold cursor-pointer"
          >
            Reset to Default
          </button>
        </div>

        <!-- Placeholder Chips -->
        <div class="space-y-1.5">
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Available Placeholders (Click to insert):</span>
          <div class="flex flex-wrap gap-1.5 pt-1">
            <button
              v-for="ph in placeholders"
              :key="ph.tag"
              type="button"
              @click="insertPlaceholder(ph.tag)"
              class="px-2 py-1 rounded-md text-[11px] font-mono bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
              :title="ph.description"
            >
              {{ ph.tag }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              Message Template
            </label>
            <textarea
              ref="templateTextarea"
              v-model="form.comment_template"
              rows="12"
              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono focus:ring-2 focus:ring-brand-500 outline-none resize-y"
              required
            ></textarea>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between">
              <span>Live Render Preview</span>
              <span class="text-[10px] text-slate-400">Sample Ticket Context</span>
            </label>
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/60 text-xs font-sans whitespace-pre-wrap text-slate-800 dark:text-slate-200 overflow-y-auto max-h-72">
              {{ renderedPreview }}
            </div>
          </div>
        </div>
      </GlassCard>

      <!-- Bottom Actions -->
      <div class="flex items-center justify-end gap-3 pt-2">
        <button
          type="button"
          @click="loadConfig"
          class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
        >
          Cancel / Reload
        </button>

        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-lg shadow-brand-500/25 transition-all cursor-pointer disabled:opacity-50"
        >
          <Check class="w-4 h-4" v-if="!saving" />
          <Activity class="w-4 h-4 animate-spin" v-else />
          <span>{{ saving ? 'Saving Changes...' : 'Save Configuration' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import { useToastStore } from '@/stores/toast';
import {
  Headphones,
  Key,
  ShieldCheck,
  Sliders,
  MessageSquareShare,
  HelpCircle,
  BookOpen,
  Download,
  Activity,
  CheckCircle2,
  AlertCircle,
  Check,
  Copy,
  Eye,
  EyeOff,
  RefreshCw,
  ShieldAlert,
} from 'lucide-vue-next';

const toast = useToastStore();

const config = ref(null);
const pools = ref([]);
const templates = ref([]);
const dataCenters = ref({});

const saving = ref(false);
const testing = ref(false);
const downloading = ref(false);
const testResult = ref(null);
const showGuide = ref(false);
const showApiToken = ref(false);
const templateTextarea = ref(null);

const form = ref({
  enabled: true,
  server_url: '',
  api_token: '',
  dc: 'in',
  org_id: '',
  client_id: '',
  client_secret: '',
  refresh_token: '',
  agent_token: '',
  default_pool_id: null,
  default_template_id: null,
  default_duration_minutes: 60,
  default_is_public: true,
  auto_close_ticket: true,
  ticket_close_status: 'Closed',
  comment_template: '',
  allowed_domains: '',
});

const regeneratingToken = ref(false);

const placeholders = [
  { tag: '{requester_name}', description: 'Contact / Requester name' },
  { tag: '{meeting_title}', description: 'Meeting Topic' },
  { tag: '{starts_at}', description: 'Formatted Start date & time' },
  { tag: '{ends_at}', description: 'Formatted End date & time' },
  { tag: '{start_time}', description: 'Time (e.g. 10:30 AM)' },
  { tag: '{end_time}', description: 'Time (e.g. 11:30 AM)' },
  { tag: '{timezone}', description: 'Session Timezone' },
  { tag: '{duration_minutes}', description: 'Duration in minutes' },
  { tag: '{join_url}', description: 'Zoom Join URL' },
  { tag: '{meeting_id}', description: 'Zoom Meeting ID' },
  { tag: '{passcode}', description: 'Meeting Passcode' },
  { tag: '{host_key}', description: 'Host Key PIN' },
  { tag: '{host_key_section}', description: 'Claim Host Instructions' },
  { tag: '{ticket_number}', description: 'Zoho Desk Ticket Number' },
  { tag: '{app_url}', description: 'ZPM Portal Base URL' },
];

const renderedPreview = computed(() => {
  const tpl = form.value.comment_template || '';
  const replacements = {
    '{requester_name}': 'Dr. Rajesh Sharma',
    '{meeting_title}': 'Urgent Academic Advisory Session',
    '{starts_at}': '2026-10-06 14:00',
    '{ends_at}': '2026-10-06 15:00',
    '{start_time}': '02:00 PM',
    '{end_time}': '03:00 PM',
    '{timezone}': 'Asia/Kolkata',
    '{duration_minutes}': String(form.value.default_duration_minutes || 60),
    '{join_url}': 'https://zoom.us/j/84920485918?pwd=SECURE_PASSCODE',
    '{meeting_id}': '849 2048 5918',
    '{passcode}': '842918',
    '{host_key}': '592019',
    '{host_key_section}': '🛡️ **Host Key PIN:** 592019 (Claim Host: Zoom client > Participants > Claim Host)',
    '{ticket_number}': 'TKT-10492',
    '{app_url}': form.value.server_url || window.location.origin,
  };

  let res = tpl;
  for (const [k, v] of Object.entries(replacements)) {
    res = res.replaceAll(k, v);
  }
  return res;
});

const loadConfig = async () => {
  try {
    const res = await axios.get('/spa/settings/zoho-desk');
    if (res.data) {
      config.value = res.data.config;
      pools.value = res.data.pools || [];
      templates.value = res.data.templates || [];
      dataCenters.value = res.data.config.data_centers || {};

      form.value.enabled = !!res.data.config.enabled;
      form.value.server_url = res.data.config.server_url || window.location.origin;
      form.value.api_token = res.data.config.api_token || '';
      form.value.dc = res.data.config.dc || 'in';
      form.value.org_id = res.data.config.org_id || '';
      form.value.client_id = res.data.config.client_id || '';
      form.value.client_secret = '';
      form.value.refresh_token = '';
      form.value.agent_token = '';
      form.value.default_pool_id = res.data.config.default_pool_id;
      form.value.default_template_id = res.data.config.default_template_id;
      form.value.default_duration_minutes = res.data.config.default_duration_minutes || 60;
      form.value.default_is_public = res.data.config.default_is_public !== false;
      form.value.auto_close_ticket = res.data.config.auto_close_ticket !== false;
      form.value.ticket_close_status = res.data.config.ticket_close_status || 'Closed';
      form.value.comment_template = res.data.config.comment_template || '';
      form.value.allowed_domains = res.data.config.allowed_domains || '';
    }
  } catch (e) {
    console.error('Failed to load Zoho Desk configuration', e);
    toast.error('Could not load Zoho Desk configuration.');
  }
};

const regenerateApiToken = async () => {
  if (!confirm('Are you sure you want to regenerate the Zoho Desk API Token? Existing widget installations will immediately lose access until updated with this new token.')) {
    return;
  }

  try {
    regeneratingToken.value = true;
    const res = await axios.post('/spa/settings/zoho-desk/regenerate-token');
    if (res.data.success) {
      form.value.api_token = res.data.api_token;
      if (config.value) {
        config.value.api_token = res.data.api_token;
      }
      toast.success(res.data.message || 'Zoho Desk API Token regenerated successfully!');
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to regenerate API Token.');
  } finally {
    regeneratingToken.value = false;
  }
};

const saveConfiguration = async () => {
  try {
    saving.value = true;
    const payload = { ...form.value };
    if (!payload.client_secret) delete payload.client_secret;
    if (!payload.refresh_token) delete payload.refresh_token;
    if (!payload.agent_token) delete payload.agent_token;

    const res = await axios.put('/spa/settings/zoho-desk', payload);
    if (res.data.success) {
      config.value = res.data.config;
      form.value.client_secret = '';
      form.value.refresh_token = '';
      form.value.agent_token = '';
      toast.success('Zoho Desk configuration saved successfully.');
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to save Zoho Desk settings.');
  } finally {
    saving.value = false;
  }
};

const testConnection = async () => {
  try {
    testing.value = true;
    testResult.value = null;

    const payload = {
      dc: form.value.dc,
      org_id: form.value.org_id,
      client_id: form.value.client_id,
      client_secret: form.value.client_secret,
      refresh_token: form.value.refresh_token,
      agent_token: form.value.agent_token,
    };

    const res = await axios.post('/spa/settings/zoho-desk/test', payload);
    testResult.value = res.data;

    if (res.data.success) {
      toast.success('Zoho Desk connection test successful!');
      if (res.data.org_id && !form.value.org_id) {
        form.value.org_id = res.data.org_id;
      }
    } else {
      toast.warning('Zoho Desk connection test returned warning/error.');
    }
  } catch (e) {
    testResult.value = e.response?.data || {
      success: false,
      message: 'Failed to connect to Zoho Desk API.',
    };
    toast.error('Zoho Desk connection test failed.');
  } finally {
    testing.value = false;
  }
};

const downloadExtension = async () => {
  try {
    downloading.value = true;
    const response = await axios.get('/spa/settings/zoho-desk/extension/download', {
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'zoom-pool-manager-zoho-desk.zip');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success('Extension ZIP downloaded! Upload it to Zoho Desk Developer Space.');
  } catch (e) {
    toast.error('Failed to generate extension ZIP package.');
  } finally {
    downloading.value = false;
  }
};

const copyText = (val, successMsg) => {
  if (!val) return;
  navigator.clipboard.writeText(val);
  toast.success(successMsg || 'Copied to clipboard!');
};

const insertPlaceholder = (tag) => {
  if (!templateTextarea.value) {
    form.value.comment_template += ' ' + tag;
    return;
  }
  const el = templateTextarea.value;
  const start = el.selectionStart;
  const end = el.selectionEnd;
  const text = form.value.comment_template;
  form.value.comment_template = text.substring(0, start) + tag + text.substring(end);
  setTimeout(() => {
    el.focus();
    el.setSelectionRange(start + tag.length, start + tag.length);
  }, 0);
};

const resetCommentTemplate = () => {
  form.value.comment_template = `Hello {requester_name},

Your Zoom meeting has been scheduled via Zoom Pool Manager.

📅 **Topic:** {meeting_title}
🕒 **Date & Time:** {starts_at} - {ends_at} ({timezone})
⏱️ **Duration:** {duration_minutes} minutes

🔗 **Join Zoom Meeting:**
{join_url}

🆔 **Meeting ID:** {meeting_id}
🔑 **Passcode:** {passcode}
{host_key_section}

Please let us know if you need any additional assistance.`;
  toast.info('Comment template reset to default.');
};

onMounted(() => {
  loadConfig();
});
</script>
