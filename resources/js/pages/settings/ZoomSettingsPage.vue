<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <Layers class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Zoom API & Marketplace Configuration
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Manage Server-to-Server OAuth credentials, webhook verification, and inspect permission scopes.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          @click="showGuide = !showGuide"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all border border-slate-200/80 dark:border-slate-700/80 cursor-pointer"
        >
          <HelpCircle class="w-4 h-4 text-brand-500" />
          <span>{{ showGuide ? 'Hide Setup Guide' : 'How to Create App' }}</span>
        </button>

        <a
          href="https://marketplace.zoom.us/develop/"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 text-xs font-semibold transition-all border border-blue-200/80 dark:border-blue-800/80"
        >
          <span>Zoom Marketplace</span>
          <ExternalLink class="w-3.5 h-3.5" />
        </a>

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

        <div class="flex-1 min-w-0">
          <div class="font-bold">
            {{ testResult.message }}
          </div>
          <div v-if="testResult.scopes && testResult.scopes.length" class="mt-2 text-xs">
            <span class="font-semibold">Granted Scopes ({{ testResult.scopes.length }}):</span>
            <div class="flex flex-wrap gap-1 mt-1">
              <span
                v-for="sc in testResult.scopes"
                :key="sc"
                class="px-2 py-0.5 rounded-md font-mono text-[10px] bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
              >
                {{ sc }}
              </span>
            </div>
          </div>
          <div v-if="testResult.details" class="mt-2 text-xs space-y-1 opacity-90">
            <div v-if="testResult.details.account_id">Account ID: <span class="font-mono">{{ testResult.details.account_id }}</span></div>
            <div v-if="testResult.details.latency_ms">Roundtrip Latency: <span class="font-mono font-semibold">{{ testResult.details.latency_ms }}ms</span></div>
            <div v-if="testResult.details.error" class="text-rose-600 dark:text-rose-300 font-mono mt-1">Error: {{ testResult.details.error }}</div>
          </div>
        </div>

        <button @click="testResult = null" class="p-1 opacity-60 hover:opacity-100 cursor-pointer">
          <X class="w-4 h-4" />
        </button>
      </div>
    </transition>

    <!-- Interactive Step-by-Step App Creation Guide -->
    <div
      v-if="showGuide"
      class="p-5 rounded-2xl bg-gradient-to-br from-brand-50/70 via-sky-50/50 to-indigo-50/40 dark:from-slate-900/90 dark:via-brand-950/40 dark:to-slate-900/90 border border-brand-200 dark:border-brand-800/60 shadow-sm space-y-4"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold text-xs">
            Guide
          </div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white">
            How to Create a Server-to-Server OAuth App in Zoom Marketplace
          </h3>
        </div>
        <button
          @click="showGuide = false"
          class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
        >
          Dismiss
        </button>
      </div>

      <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
        Server-to-Server OAuth apps allow Zoom Pool Manager to manage meetings, users, and recordings securely without requiring individual user passwords. Follow these 6 steps to configure your Zoom App:
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
        <!-- Step 1 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">1</span>
            Access Zoom Marketplace Developer Portal
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Visit <a href="https://marketplace.zoom.us/develop/" target="_blank" rel="noopener noreferrer" class="text-brand-600 dark:text-brand-400 underline font-medium">marketplace.zoom.us/develop/</a> and sign in with your organization’s Zoom Account Owner or Admin account.
          </p>
        </div>

        <!-- Step 2 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">2</span>
            Click Develop → Build App
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            In the top-right menu, select <strong>Develop</strong> → <strong>Build App</strong>. On the app type selection page, choose <strong>Server-to-Server OAuth</strong> and click <strong>Create</strong>.
          </p>
        </div>

        <!-- Step 3 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">3</span>
            Name App & Copy Credentials
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Enter an app name like <code class="px-1 py-0.5 bg-slate-100 dark:bg-slate-700 rounded font-mono text-[11px]">Zoom Pool Manager</code>. On the <strong>App Credentials</strong> tab, copy your <strong>Account ID</strong>, <strong>Client ID</strong>, and <strong>Client Secret</strong> into the form below.
          </p>
        </div>

        <!-- Step 4 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">4</span>
            Add Required Permission Scopes
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Under the <strong>Scopes</strong> tab, click <strong>+ Add Scopes</strong>. Select Meeting (<code class="font-mono text-[10px]">meeting:write:admin</code>, <code class="font-mono text-[10px]">meeting:read:admin</code>), User (<code class="font-mono text-[10px]">user:read:admin</code>, <code class="font-mono text-[10px]">user:write:admin</code>), and Recording (<code class="font-mono text-[10px]">recording:read:admin</code>) scopes.
          </p>
        </div>

        <!-- Step 5 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">5</span>
            Configure Webhook Subscriptions
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Under <strong>Feature</strong> → toggle <strong>Event Subscriptions</strong>. Paste your ZPM endpoint URL, copy the <strong>Secret Token</strong> into ZPM, and add <code class="font-mono text-[10px]">meeting.started</code>, <code class="font-mono text-[10px]">meeting.ended</code>, and <code class="font-mono text-[10px]">recording.completed</code> events.
          </p>
        </div>

        <!-- Step 6 -->
        <div class="p-3 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 space-y-1">
          <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
            <span class="w-4 h-4 rounded-full bg-brand-600 text-white text-[10px] flex items-center justify-center">6</span>
            Activate App & Verify
          </div>
          <p class="text-slate-600 dark:text-slate-400 leading-normal">
            Click <strong>Continue</strong> through to the <strong>Activation</strong> tab and click <strong>Activate your app</strong>. Return here and click <strong>Test Connection</strong> to confirm live handshake.
          </p>
        </div>
      </div>
    </div>

    <!-- Main Config Form -->
    <GlassCard title="Server-to-Server OAuth Credentials">
      <form @submit.prevent="saveConfiguration" class="space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Connection Name -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Connection Name
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full glass-input"
              placeholder="Primary Zoom Account"
            />
          </div>

          <!-- Account ID -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Zoom Account ID
            </label>
            <input
              v-model="form.account_id"
              type="text"
              required
              class="w-full glass-input font-mono text-xs"
              placeholder="e.g. 10_characters_or_more"
            />
            <p class="text-[11px] text-slate-400 mt-1">
              Found on Zoom Marketplace under App Credentials → Account ID.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Client ID -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Client ID
            </label>
            <input
              v-model="form.client_id"
              type="text"
              required
              class="w-full glass-input font-mono text-xs"
              placeholder="OAuth Client ID"
            />
            <p class="text-[11px] text-slate-400 mt-1">
              Found on Zoom Marketplace under App Credentials → Client ID.
            </p>
          </div>

          <!-- Client Secret -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Client Secret
            </label>
            <input
              v-model="form.client_secret"
              type="password"
              class="w-full glass-input font-mono text-xs"
              :placeholder="config?.has_client_secret ? '•••••••••••••••• (Leave blank to keep unchanged)' : 'Enter Zoom Client Secret'"
            />
            <p v-if="config?.has_client_secret" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1">
              <Check class="w-3 h-3" /> Encrypted Client Secret is saved
            </p>
            <p v-else class="text-[11px] text-slate-400 mt-1">
              Found on Zoom Marketplace under App Credentials → Client Secret.
            </p>
          </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
          <!-- Webhook Secret Token -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1.5">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
              Webhook Secret Token
            </label>
            <button
              type="button"
              @click="copyWebhookUrl"
              class="text-xs text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1 cursor-pointer"
            >
              <Copy class="w-3 h-3" />
              <span>{{ copiedWebhookUrl ? 'Webhook URL Copied!' : 'Copy ZPM Webhook Endpoint URL' }}</span>
            </button>
          </div>

          <input
            v-model="form.webhook_secret_token"
            type="password"
            class="w-full glass-input font-mono text-xs max-w-lg"
            :placeholder="config?.has_webhook_secret ? '•••••••••••••••• (Leave blank to keep unchanged)' : 'Zoom Webhook Secret Token'"
          />
          <p class="text-[11px] text-slate-400 mt-1">
            Found on Zoom Marketplace under Feature → Event Subscriptions → Secret Token. Used to cryptographically verify inbound webhooks (meeting started, ended, recordings).
          </p>
        </div>

        <!-- Enabled Toggle -->
        <div class="flex items-center gap-3 pt-2">
          <input
            id="enabled-toggle"
            v-model="form.enabled"
            type="checkbox"
            class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 border-slate-300 dark:border-slate-700 cursor-pointer"
          />
          <label for="enabled-toggle" class="text-sm font-medium text-slate-700 dark:text-slate-200 cursor-pointer">
            Enable Zoom Integration for Active Allocations
          </label>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
          <button
            type="submit"
            :disabled="saving"
            class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-md shadow-brand-500/20 transition-all disabled:opacity-50 cursor-pointer"
          >
            {{ saving ? 'Saving...' : 'Save Configuration' }}
          </button>
        </div>
      </form>
    </GlassCard>

    <!-- Scopes & Capabilities Directory -->
    <GlassCard title="Zoom OAuth Permission Scopes & Capabilities">
      <div class="space-y-4">
        <!-- Explanation Banner with Zoom Docs Link -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-300 leading-relaxed flex items-start gap-2.5">
          <Shield class="w-4 h-4 text-brand-600 dark:text-brand-400 shrink-0 mt-0.5" />
          <div class="space-y-1">
            <p>
              <strong>What are Scopes?</strong> Scopes define the API methods this app is allowed to call, and thus which information and capabilities are available on Zoom. Scopes are restricted to specific resources like meetings, users, and recordings.
            </p>
            <div class="flex items-center gap-3 pt-1 flex-wrap">
              <a
                href="https://developers.zoom.us/docs/integrations/oauth-scopes-overview/"
                target="_blank"
                rel="noopener noreferrer"
                class="text-brand-600 dark:text-brand-400 hover:underline font-semibold flex items-center gap-1"
              >
                <span>Learn more about Zoom’s scopes</span>
                <ExternalLink class="w-3 h-3" />
              </a>
              <span class="text-slate-400">•</span>
              <a
                href="https://marketplace.zoom.us/develop/"
                target="_blank"
                rel="noopener noreferrer"
                class="text-brand-600 dark:text-brand-400 hover:underline font-semibold flex items-center gap-1"
              >
                <span>Add Scopes in Zoom Marketplace</span>
                <ExternalLink class="w-3 h-3" />
              </a>
            </div>
          </div>
        </div>

        <!-- Scope Action Controls & Live Search Bar -->
        <div class="space-y-3 pt-1">
          <!-- Search Input & Quick Shortcuts -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <div class="relative flex-1">
              <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search scopes by name, keyword, or endpoint (e.g. recording, meeting:read, user)..."
                class="w-full text-xs rounded-xl pl-9 pr-8 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-brand-500"
              />
              <button
                v-if="searchQuery"
                @click="searchQuery = ''"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5"
                title="Clear search"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Quick Filter Presets -->
            <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
              <span class="text-[11px] text-slate-400 font-medium">Quick find:</span>
              <button
                type="button"
                @click="searchQuery = 'recording'"
                class="px-2 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer"
                :class="searchQuery.toLowerCase().includes('recording') ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
              >
                📹 Cloud Recordings
              </button>
              <button
                type="button"
                @click="searchQuery = 'meeting'"
                class="px-2 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer"
                :class="searchQuery.toLowerCase().includes('meeting') ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
              >
                🗓️ Meetings
              </button>
              <button
                type="button"
                @click="searchQuery = 'report'"
                class="px-2 py-1 rounded-lg text-[11px] font-semibold transition-all cursor-pointer"
                :class="searchQuery.toLowerCase().includes('report') ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
              >
                📊 Attendance
              </button>
            </div>
          </div>

          <!-- Tabs & Copy All -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Category:</span>
              <div class="flex rounded-lg bg-slate-100 dark:bg-slate-800 p-0.5 text-xs">
                <button
                  type="button"
                  @click="scopeTab = 'all'"
                  :class="scopeTab === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                  class="px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer"
                >
                  All ({{ allScopesList.length }})
                </button>
                <button
                  type="button"
                  @click="scopeTab = 'required'"
                  :class="scopeTab === 'required' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                  class="px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer"
                >
                  Required Core ({{ requiredScopes.length }})
                </button>
                <button
                  type="button"
                  @click="scopeTab = 'recommended'"
                  :class="scopeTab === 'recommended' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                  class="px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer"
                >
                  Recommended ({{ recommendedScopes.length }})
                </button>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="copyAllScopes"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all border border-slate-200/80 dark:border-slate-700/80 cursor-pointer"
              >
                <Check v-if="copiedAllScopes" class="w-3.5 h-3.5 text-emerald-500" />
                <Copy v-else class="w-3.5 h-3.5 text-slate-500" />
                <span>{{ copiedAllScopes ? 'Scopes Copied!' : 'Copy All Scopes List' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Scopes Grid -->
        <div class="space-y-3">
          <div
            v-if="displayedScopes.length === 0"
            class="p-6 text-center text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-dashed border-slate-300 dark:border-slate-700"
          >
            No scopes match your search criteria "{{ searchQuery }}".
            <button @click="searchQuery = ''; scopeTab = 'all'" class="text-brand-600 dark:text-brand-400 font-semibold underline ml-1 cursor-pointer">
              Reset search
            </button>
          </div>

          <div
            v-for="sc in displayedScopes"
            :key="sc.scope"
            class="p-4 rounded-xl border transition-all space-y-2.5"
            :class="isScopeGranted(sc.scope, sc.granular)
              ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-200/70 dark:border-emerald-800/50'
              : sc.required
                ? 'bg-amber-50/40 dark:bg-amber-950/20 border-amber-200/70 dark:border-amber-800/50'
                : 'bg-white dark:bg-slate-800/60 border-slate-200/60 dark:border-slate-700/60'"
          >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <!-- Scope Name -->
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-700">
                  {{ sc.scope }}
                </span>

                <!-- Granular Scope alternative -->
                <span
                  v-if="sc.granular"
                  class="font-mono text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/60 px-1.5 py-0.5 rounded border border-slate-200/50 dark:border-slate-800"
                  title="Granular alternative accepted by Zoom Marketplace"
                >
                  alt: {{ sc.granular }}
                </span>

                <!-- Category -->
                <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded bg-brand-50 dark:bg-brand-950/60 text-brand-600 dark:text-brand-400 border border-brand-200/50 dark:border-brand-800/50">
                  {{ sc.category }}
                </span>

                <!-- Priority Badge -->
                <span
                  v-if="sc.required"
                  class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                >
                  Required
                </span>
                <span
                  v-else
                  class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800"
                >
                  Recommended
                </span>
              </div>

              <!-- Live Verification Status Badge -->
              <div class="shrink-0 flex items-center gap-2">
                <span
                  v-if="isScopeGranted(sc.scope, sc.granular)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700"
                >
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                  <span>Granted & Active</span>
                </span>
                <span
                  v-else-if="sc.required"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700"
                >
                  <AlertTriangle class="w-3.5 h-3.5 text-amber-600" />
                  <span>Missing in Zoom</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700"
                >
                  <span>Optional</span>
                </span>

                <button
                  type="button"
                  @click="copySingleScope(sc.scope)"
                  class="p-1 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                  title="Copy this scope"
                >
                  <Copy class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Description -->
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              <strong>{{ sc.label }}:</strong> {{ sc.description }}
            </p>

            <!-- EXACT HOW TO FIND IN ZOOM MARKETPLACE HELPER BOX -->
            <div class="p-2.5 rounded-lg bg-slate-100/90 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-xs space-y-1.5">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200 text-[11px] uppercase tracking-wider">
                  <MapPin class="w-3.5 h-3.5 text-brand-600 dark:text-brand-400" />
                  <span>How to Find & Check in Zoom Marketplace:</span>
                </div>
                <a
                  href="https://marketplace.zoom.us/develop/"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-[11px] text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1 font-semibold"
                >
                  <span>Open Zoom</span>
                  <ExternalLink class="w-3 h-3" />
                </a>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-1.5">
                  <span class="text-slate-400 font-medium">1. Left Category:</span>
                  <span class="font-bold px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 border border-slate-200 dark:border-slate-700">
                    {{ sc.zoom_category || sc.category }}
                  </span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-slate-400 font-medium">2. Checkbox Label:</span>
                  <span class="font-semibold text-slate-900 dark:text-white font-mono text-[11px]">
                    "{{ sc.zoom_option_label || sc.label }}"
                  </span>
                </div>
              </div>

              <p v-if="sc.how_to_find" class="text-[11px] text-slate-500 dark:text-slate-400 italic pt-0.5">
                💡 {{ sc.how_to_find }}
              </p>
            </div>

            <!-- API Endpoints -->
            <div v-if="sc.endpoints && sc.endpoints.length" class="flex items-center gap-1.5 flex-wrap pt-0.5">
              <span class="text-[10px] text-slate-400 font-semibold uppercase">API Endpoints:</span>
              <span
                v-for="ep in sc.endpoints"
                :key="ep"
                class="px-1.5 py-0.5 rounded font-mono text-[10px] bg-slate-100 dark:bg-slate-900/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800"
              >
                {{ ep }}
              </span>
            </div>
          </div>
        </div>

        <!-- Webhook Event Subscriptions Table -->
        <div class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700 space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
              <Activity class="w-4 h-4 text-brand-600 dark:text-brand-400" />
              Required Webhook Event Subscriptions
            </h4>
            <span class="text-[11px] text-slate-400">Configure under Zoom Marketplace → Feature → Event Subscriptions</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <div
              v-for="ev in webhookEvents"
              :key="ev.event"
              class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60 space-y-1"
            >
              <div class="flex items-center justify-between">
                <span class="font-mono text-xs font-bold text-slate-900 dark:text-white">{{ ev.event }}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-700 font-semibold text-slate-600 dark:text-slate-300">{{ ev.category }}</span>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-normal">{{ ev.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </GlassCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import { useToastStore } from '@/stores/toast';
import {
  Activity,
  CheckCircle2,
  AlertCircle,
  Check,
  X,
  ExternalLink,
  Copy,
  HelpCircle,
  Shield,
  Layers,
  AlertTriangle,
  Search,
  MapPin
} from 'lucide-vue-next';

const toast = useToastStore();

const config = ref(null);
const saving = ref(false);
const testing = ref(false);
const testResult = ref(null);
const showGuide = ref(false);
const scopeTab = ref('all');
const searchQuery = ref('');
const copiedAllScopes = ref(false);
const copiedWebhookUrl = ref(false);

const form = ref({
  name: 'Primary Zoom Account',
  account_id: '',
  client_id: '',
  client_secret: '',
  webhook_secret_token: '',
  enabled: true,
});

// Fallback scopes if API is unconfigured
const defaultRequiredScopes = [
  {
    scope: 'meeting:write:admin',
    granular: 'meeting:write:meeting:admin',
    category: 'Meetings',
    label: 'Create & Manage Meetings',
    description: 'Allows ZPM to schedule pooled sessions, update meeting topics/times, apply security profiles, and delete/release cancelled bookings.',
    endpoints: ['POST /users/{userId}/meetings', 'PATCH /meetings/{meetingId}', 'DELETE /meetings/{meetingId}'],
    zoom_category: 'Meeting',
    zoom_option_label: 'View and manage all user meetings',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "Meeting" in the left sidebar, then check "View and manage all user meetings" (or "Create a meeting for a user" in granular view).',
    required: true,
  },
  {
    scope: 'meeting:read:admin',
    granular: 'meeting:read:meeting:admin',
    category: 'Meetings',
    label: 'Read Meeting Details & Start URLs',
    description: 'Allows ZPM to query meeting details, retrieve dynamic JIT host start URLs, and verify session status.',
    endpoints: ['GET /meetings/{meetingId}', 'GET /users/{userId}/meetings'],
    zoom_category: 'Meeting',
    zoom_option_label: 'View all user meetings',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "Meeting" in the left sidebar, then check "View all user meetings" (or "View a meeting").',
    required: true,
  },
  {
    scope: 'user:read:admin',
    granular: 'user:read:user:admin',
    category: 'Users',
    label: 'Inspect Pooled Host Accounts',
    description: 'Discovers host accounts in your Zoom organization, queries license types (Basic vs Licensed), and verifies meeting seat capacity (e.g. 100, 300, 500, or 1000 seats).',
    endpoints: ['GET /users', 'GET /users/{userId}'],
    zoom_category: 'User',
    zoom_option_label: 'View all user information',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "User" in the left sidebar, then check "View all user information" (or "View users").',
    required: true,
  },
  {
    scope: 'user:write:admin',
    granular: 'user:update:user:admin',
    category: 'Users',
    label: 'Rotate Host Keys',
    description: 'Enables automated rotation of the 6-digit host key on pooled accounts after each meeting ends, preventing unauthorized host takeover.',
    endpoints: ['PATCH /users/{userId}'],
    zoom_category: 'User',
    zoom_option_label: 'View and manage all user information',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "User" in the left sidebar, then check "View and manage all user information" (or "Update a user").',
    required: true,
  },
];

const defaultRecommendedScopes = [
  {
    scope: 'recording:read:admin',
    granular: 'recording:read:recording:admin',
    category: 'Cloud Recordings',
    label: 'Cloud Recordings & Transcripts',
    description: 'Allows ZPM to index completed cloud recordings, generate secure playback redirects, and download AI audio transcripts.',
    endpoints: ['GET /meetings/{meetingId}/recordings', 'GET /users/{userId}/recordings'],
    zoom_category: 'Recording',
    zoom_option_label: 'View all user recordings',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "Recording" (or "Cloud Recording") on the left sidebar, then check "View all user recordings" (or granular "View a meeting\'s recordings" / "View recording").',
    required: false,
  },
  {
    scope: 'report:read:admin',
    granular: 'report:read:list_meeting_participants:admin',
    category: 'Reports & Attendance',
    label: 'Meeting Attendance & Participant Reports',
    description: 'Allows ZPM to pull participant attendance records, join times, leave times, and total session duration for post-meeting auditing.',
    endpoints: ['GET /report/meetings/{meetingId}/participants', 'GET /past_meetings/{meetingId}/participants'],
    zoom_category: 'Report',
    zoom_option_label: 'View all user and meeting reports',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "Report" on the left sidebar, then check "View all user and meeting reports" (or "View meeting report").',
    required: false,
  },
  {
    scope: 'dashboard:read:admin',
    granular: 'dashboard:read:list_meeting_participants:admin',
    category: 'Telemetry',
    label: 'Live Telemetry & Diagnostics',
    description: 'Provides live meeting metrics, latency, and real-time active session diagnostics in your institutional Zoom account.',
    endpoints: ['GET /metrics/meetings', 'GET /metrics/meetings/{meetingId}/participants'],
    zoom_category: 'Dashboard',
    zoom_option_label: 'View all user dashboard data',
    how_to_find: 'In Zoom Marketplace "+ Add Scopes" modal: Click "Dashboard" on the left sidebar, then check "View all user dashboard data".',
    required: false,
  },
];

const defaultWebhookEvents = [
  {
    event: 'meeting.started',
    category: 'Meeting',
    description: 'Notifies ZPM the moment a host starts a pooled session; updates live status and begins the active buffer window.',
  },
  {
    event: 'meeting.ended',
    category: 'Meeting',
    description: 'Notifies ZPM that the meeting concluded; immediately frees the host resource and triggers automatic host key rotation.',
  },
  {
    event: 'meeting.updated',
    category: 'Meeting',
    description: 'Detects when meeting settings, topics, or times are modified in the native Zoom client.',
  },
  {
    event: 'meeting.deleted',
    category: 'Meeting',
    description: 'Syncs cancellation if a meeting is directly removed from the Zoom portal.',
  },
  {
    event: 'recording.completed',
    category: 'Recording',
    description: 'Notifies ZPM that cloud recording files are processed and ready for distribution.',
  },
  {
    event: 'recording.transcript_completed',
    category: 'Recording',
    description: 'Notifies ZPM that audio/video transcript files are ready to sync.',
  },
];

const requiredScopes = computed(() => {
  return config.value?.required_scopes?.length ? config.value.required_scopes : defaultRequiredScopes;
});

const recommendedScopes = computed(() => {
  return config.value?.recommended_scopes?.length ? config.value.recommended_scopes : defaultRecommendedScopes;
});

const webhookEvents = computed(() => {
  return config.value?.webhook_events?.length ? config.value.webhook_events : defaultWebhookEvents;
});

const allScopesList = computed(() => {
  return [...requiredScopes.value, ...recommendedScopes.value];
});

const displayedScopes = computed(() => {
  let list = allScopesList.value;
  if (scopeTab.value === 'required') list = requiredScopes.value;
  else if (scopeTab.value === 'recommended') list = recommendedScopes.value;

  if (searchQuery.value && searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    list = list.filter(s =>
      s.scope.toLowerCase().includes(q) ||
      (s.granular && s.granular.toLowerCase().includes(q)) ||
      s.category.toLowerCase().includes(q) ||
      s.label.toLowerCase().includes(q) ||
      s.description.toLowerCase().includes(q) ||
      (s.zoom_category && s.zoom_category.toLowerCase().includes(q)) ||
      (s.zoom_option_label && s.zoom_option_label.toLowerCase().includes(q))
    );
  }
  return list;
});

const isScopeGranted = (scopeName, granularName) => {
  const granted = config.value?.granted_scopes || [];
  if (!granted.length) return false;
  return granted.includes(scopeName) || (granularName && granted.includes(granularName));
};

const copyAllScopes = () => {
  const scopes = allScopesList.value.map(s => s.scope).join(' ');
  navigator.clipboard.writeText(scopes);
  copiedAllScopes.value = true;
  toast.success('All Zoom OAuth scopes copied to clipboard! Paste or search them in Zoom Marketplace.');
  setTimeout(() => {
    copiedAllScopes.value = false;
  }, 3000);
};

const copySingleScope = (scope) => {
  navigator.clipboard.writeText(scope);
  toast.info(`Copied "${scope}" to clipboard`);
};

const copyWebhookUrl = () => {
  const url = config.value?.canonical_webhook_url || `${window.location.origin}/webhooks/zoom`;
  navigator.clipboard.writeText(url);
  copiedWebhookUrl.value = true;
  toast.success('Webhook endpoint URL copied to clipboard!');
  setTimeout(() => {
    copiedWebhookUrl.value = false;
  }, 3000);
};

const loadConfig = async () => {
  try {
    const res = await axios.get('/spa/settings/zoom');
    config.value = res.data;
    if (res.data) {
      form.value.name = res.data.name || 'Primary Zoom Account';
      form.value.account_id = res.data.account_id || '';
      form.value.client_id = res.data.client_id || '';
      form.value.enabled = !!res.data.enabled;

      // Auto-open guide if unconfigured
      if (!res.data.account_id || !res.data.has_client_secret) {
        showGuide.value = true;
      }
    }
  } catch (e) {
    console.error('Failed to load Zoom configuration', e);
  }
};

const saveConfiguration = async () => {
  try {
    saving.value = true;
    const payload = { ...form.value };
    if (!payload.client_secret) delete payload.client_secret;
    if (!payload.webhook_secret_token) delete payload.webhook_secret_token;

    const res = await axios.post('/spa/settings/zoom', payload);
    config.value = res.data.config;
    form.value.client_secret = '';
    form.value.webhook_secret_token = '';
    toast.success('Zoom configuration saved successfully.');
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to save Zoom configuration.');
  } finally {
    saving.value = false;
  }
};

const testConnection = async () => {
  try {
    testing.value = true;
    testResult.value = null;
    const res = await axios.post('/spa/settings/zoom/test');
    testResult.value = res.data;
    if (res.data.scopes) {
      config.value.granted_scopes = res.data.scopes;
    }
    if (res.data.success) {
      toast.success('Zoom connection test successful!');
    } else {
      toast.warning('Zoom connection test returned warning/error.');
    }
  } catch (e) {
    testResult.value = e.response?.data || {
      success: false,
      message: 'Failed to connect to Zoom OAuth endpoint.',
    };
    toast.error('Zoom connection test failed.');
  } finally {
    testing.value = false;
  }
};

onMounted(() => {
  loadConfig();
});
</script>
