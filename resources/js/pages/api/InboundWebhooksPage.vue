<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>Zoom Webhook Intake & Debugger</span>
          <span
            v-if="liveStreaming"
            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
          >
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />
            <span>LIVE</span>
          </span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Real-time intake stream of Zoom event notifications, HMAC signature validation, interactive debugging, and event replay.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Live Stream Toggle -->
        <button
          type="button"
          @click="toggleLiveStream"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all"
          :class="liveStreaming
            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700 shadow-sm'
            : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/60'"
        >
          <Activity class="w-3.5 h-3.5" :class="{ 'animate-pulse text-emerald-500': liveStreaming }" />
          <span>{{ liveStreaming ? 'Live Polling (3s)' : 'Enable Live Stream' }}</span>
        </button>

        <!-- Simulate / Debug Event Button -->
        <button
          type="button"
          @click="openSimulator"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-sm shadow-brand-600/20 transition-all"
        >
          <Play class="w-3.5 h-3.5" />
          <span>Simulate Event</span>
        </button>

        <!-- Test CRC Handshake Button -->
        <button
          type="button"
          @click="testCrcHandshake"
          :disabled="testingCrc"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all disabled:opacity-50"
          title="Verify Zoom URL Validation challenge-response handshake"
        >
          <ShieldCheck class="w-3.5 h-3.5 text-brand-500" :class="{ 'animate-spin': testingCrc }" />
          <span>Test CRC Handshake</span>
        </button>

        <!-- Refresh Button -->
        <button
          @click="fetchEvents"
          class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 transition"
          title="Refresh"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Webhook Endpoint Configuration Banner -->
    <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 text-white shadow-md space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="text-xs uppercase tracking-wider font-bold text-brand-400">Intake Endpoint URL</span>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold"
              :class="meta.has_secret_token ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
            >
              {{ meta.has_secret_token ? 'HMAC Secret Configured' : 'Secret Token Missing' }}
            </span>
          </div>
          <div class="flex items-center gap-2 font-mono text-sm font-semibold text-slate-100 break-all">
            <span>{{ meta.webhook_url || currentOriginWebhookUrl }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="copyToClipboard(meta.webhook_url || currentOriginWebhookUrl, 'url')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition"
          >
            <Check v-if="copiedUrl" class="w-3.5 h-3.5 text-emerald-400" />
            <Copy v-else class="w-3.5 h-3.5" />
            <span>{{ copiedUrl ? 'Copied!' : 'Copy URL' }}</span>
          </button>
          <router-link
            to="/app/settings/zoom"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold transition"
          >
            <Sliders class="w-3.5 h-3.5" />
            <span>Zoom Settings</span>
          </router-link>
        </div>
      </div>
      <p class="text-xs text-slate-400">
        In Zoom Marketplace App Settings, set the <strong>Event Notification Endpoint URL</strong> to this address. Zoom will issue an automated CRC check to validate this URL.
      </p>
    </div>

    <!-- Diagnostic Stats Overview -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total Received</div>
        <div class="text-xl font-black text-slate-900 dark:text-white">{{ stats.total }}</div>
      </div>
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Processed</div>
        <div class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.processed }}</div>
      </div>
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-amber-600 dark:text-amber-400">Pending</div>
        <div class="text-xl font-black text-amber-600 dark:text-amber-400">{{ stats.pending }}</div>
      </div>
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-rose-600 dark:text-rose-400">Failed</div>
        <div class="text-xl font-black text-rose-600 dark:text-rose-400">{{ stats.failed }}</div>
      </div>
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-teal-600 dark:text-teal-400">Valid Signature</div>
        <div class="text-xl font-black text-teal-600 dark:text-teal-400">{{ stats.signature_valid }}</div>
      </div>
      <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-1">
        <div class="text-[11px] font-medium text-rose-500">Invalid Sig</div>
        <div class="text-xl font-black text-rose-500">{{ stats.signature_invalid }}</div>
      </div>
    </div>

    <!-- Alert / Feedback -->
    <div
      v-if="feedback"
      class="p-4 rounded-xl text-xs font-semibold flex items-center justify-between transition-all"
      :class="feedbackType === 'error' ? 'bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400'"
    >
      <span>{{ feedback }}</span>
      <button @click="feedback = ''" class="hover:underline font-bold">Dismiss</button>
    </div>

    <!-- Events List Card -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60">
      <!-- Table Filter Bar -->
      <div class="p-4 border-b border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/50">
        <div class="flex items-center gap-2 flex-1">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search event type, ID, meeting ID, IP..."
              class="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all shadow-sm"
            />
          </div>

          <select
            v-model="filterStatus"
            @change="fetchEvents"
            class="text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processed">Processed</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="hasSimulatedEvents"
            type="button"
            @click="clearSimulatedEvents"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 transition"
            title="Remove simulated test events"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span>Clear Simulated</span>
          </button>
          <span class="text-xs text-slate-500 dark:text-slate-400">
            Showing {{ filteredEvents.length }} events
          </span>
        </div>
      </div>

      <div v-if="loading && !events.length" class="p-12 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm">Listening to intake stream...</p>
      </div>

      <div v-else-if="!filteredEvents.length" class="p-12 text-center text-slate-400">
        <Radio class="w-10 h-10 mx-auto mb-3 opacity-40 text-brand-500" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
          {{ searchQuery ? 'No Matching Inbound Events' : 'No Webhook Events Received Yet' }}
        </p>
        <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
          {{ searchQuery ? 'Try adjusting your search query.' : 'Configure your Zoom Marketplace App Event Subscriptions with the webhook URL above, or use the "Simulate Event" button above to verify intake.' }}
        </p>
        <button
          v-if="!searchQuery"
          type="button"
          @click="openSimulator"
          class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition shadow-sm"
        >
          <Play class="w-3.5 h-3.5" />
          <span>Send First Test Webhook</span>
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50">
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event Type</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Event ID</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Status</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Signature</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">IP Address</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider">Received At</th>
              <th class="py-3 px-4 font-semibold text-xs text-slate-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="ev in filteredEvents"
              :key="ev.public_id || ev.id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition cursor-pointer"
              @click="viewPayload(ev)"
            >
              <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-xs text-slate-900 dark:text-white">
                    {{ ev.event_type }}
                  </span>
                  <span
                    v-if="ev.event_id && ev.event_id.startsWith('sim-')"
                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-violet-500/10 text-violet-600 dark:text-violet-400"
                  >
                    Simulated
                  </span>
                </div>
              </td>
              <td class="py-3 px-4 font-mono text-[11px] text-slate-500 dark:text-slate-400 max-w-[140px] truncate" :title="ev.event_id">
                {{ ev.event_id }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="getStatusBadgeClass(ev.status)"
                >
                  {{ ev.status }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span
                  class="inline-flex items-center gap-1 text-[11px] font-semibold"
                  :class="ev.signature_valid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                >
                  <CheckCircle2 v-if="ev.signature_valid" class="w-3.5 h-3.5" />
                  <AlertCircle v-else class="w-3.5 h-3.5" />
                  {{ ev.signature_valid ? 'Valid HMAC' : 'Invalid' }}
                </span>
              </td>
              <td class="py-3 px-4 font-mono text-xs text-slate-500 dark:text-slate-400">
                {{ ev.ip_address || '-' }}
              </td>
              <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">
                {{ formatDate(ev.created_at) }}
              </td>
              <td class="py-3 px-4 text-right" @click.stop>
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="viewPayload(ev)"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    title="View Full JSON Payload & Diagnostic"
                  >
                    <Eye class="w-4 h-4" />
                  </button>
                  <button
                    @click="replayEvent(ev)"
                    :disabled="replayingId === ev.public_id"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                    title="Replay Event"
                  >
                    <RotateCcw class="w-3.5 h-3.5 text-brand-500" :class="{ 'animate-spin': replayingId === ev.public_id }" />
                    <span>Replay</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payload & Diagnostic Inspector Modal -->
    <div
      v-if="payloadModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3 shrink-0">
          <div class="space-y-0.5">
            <div class="text-sm font-bold text-slate-900 dark:text-white font-mono flex items-center gap-2">
              <span>{{ selectedEvent?.event_type }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                :class="getStatusBadgeClass(selectedEvent?.status)"
              >
                {{ selectedEvent?.status }}
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">
              Event ID: {{ selectedEvent?.event_id }} • Received: {{ formatDate(selectedEvent?.created_at) }}
            </div>
          </div>
          <button @click="payloadModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Diagnostic Information -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-slate-50 dark:bg-slate-950/50 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 shrink-0">
          <div>
            <span class="text-slate-400 block text-[10px] uppercase">HMAC Signature</span>
            <span class="font-semibold" :class="selectedEvent?.signature_valid ? 'text-emerald-600' : 'text-rose-500'">
              {{ selectedEvent?.signature_valid ? 'Valid SHA-256' : 'Invalid / Missing' }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase">Client IP</span>
            <span class="font-mono text-slate-700 dark:text-slate-300">{{ selectedEvent?.ip_address || 'Unknown' }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase">Attempts</span>
            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ selectedEvent?.attempts ?? 1 }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase">Processed At</span>
            <span class="text-slate-700 dark:text-slate-300">{{ selectedEvent?.processed_at ? formatDate(selectedEvent.processed_at) : 'Pending' }}</span>
          </div>
        </div>

        <!-- Error Message if failed -->
        <div v-if="selectedEvent?.error_message" class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-600 dark:text-rose-400 font-mono shrink-0">
          <strong class="font-bold">Execution Error:</strong> {{ selectedEvent.error_message }}
        </div>

        <!-- JSON Payload -->
        <div class="space-y-1.5 flex-1 min-h-0 flex flex-col">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span class="font-semibold">Raw JSON Payload</span>
            <button
              type="button"
              @click="copyToClipboard(JSON.stringify(selectedEvent?.payload, null, 2), 'payload')"
              class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200 font-medium"
            >
              <Check v-if="copiedPayload" class="w-3.5 h-3.5 text-emerald-500" />
              <Copy v-else class="w-3.5 h-3.5" />
              <span>{{ copiedPayload ? 'Copied' : 'Copy Payload' }}</span>
            </button>
          </div>
          <pre class="w-full flex-1 p-4 rounded-xl bg-slate-900 text-slate-200 text-xs font-mono overflow-auto leading-relaxed border border-slate-800">{{ JSON.stringify(selectedEvent?.payload, null, 2) }}</pre>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-200/60 dark:border-slate-800/60 shrink-0">
          <button
            type="button"
            @click="replayEvent(selectedEvent)"
            :disabled="replayingId === selectedEvent?.public_id"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition shadow-sm"
          >
            <RotateCcw class="w-3.5 h-3.5" :class="{ 'animate-spin': replayingId === selectedEvent?.public_id }" />
            <span>Replay Event Now</span>
          </button>

          <button
            @click="payloadModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Webhook Simulator Modal -->
    <div
      v-if="simulatorModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
    >
      <div class="w-full max-w-xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 pb-3">
          <div class="flex items-center gap-2">
            <Play class="w-4 h-4 text-brand-500" />
            <span class="text-sm font-bold text-slate-900 dark:text-white">Simulate Inbound Zoom Webhook</span>
          </div>
          <button @click="simulatorModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Event Type Preset</label>
            <select
              v-model="simEventType"
              @change="applyPresetTemplate"
              class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-brand-500"
            >
              <option value="meeting.started">meeting.started (Meeting Host Started Meeting)</option>
              <option value="meeting.ended">meeting.ended (Meeting Concluded)</option>
              <option value="meeting.participant_joined">meeting.participant_joined (Attendee Joined)</option>
              <option value="endpoint.url_validation">endpoint.url_validation (Zoom CRC Challenge Handshake)</option>
            </select>
          </div>

          <div v-if="simEventType !== 'endpoint.url_validation'">
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Meeting ID (Optional)</label>
            <input
              v-model="simMeetingId"
              type="text"
              placeholder="e.g. 91234567890"
              class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-900 dark:text-white font-mono"
            />
          </div>

          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Payload JSON Preview</label>
            <textarea
              v-model="simPayloadText"
              rows="6"
              class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-900 text-slate-100 font-mono text-[11px] p-3 leading-relaxed"
            />
          </div>

          <!-- CRC Result Preview if tested -->
          <div v-if="simCrcResult" class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl space-y-1">
            <div class="font-bold text-emerald-600 dark:text-emerald-400">CRC Handshake Successful:</div>
            <pre class="font-mono text-[10px] text-slate-700 dark:text-slate-300 overflow-x-auto">{{ JSON.stringify(simCrcResult, null, 2) }}</pre>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-slate-800/60">
          <button
            type="button"
            @click="simulatorModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="dispatchSimulatedEvent"
            :disabled="simulating"
            class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold transition shadow-sm flex items-center gap-1.5 disabled:opacity-50"
          >
            <Play class="w-3.5 h-3.5" :class="{ 'animate-spin': simulating }" />
            <span>{{ simulating ? 'Dispatching...' : 'Dispatch Simulated Event' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import {
  Radio,
  Eye,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  Search,
  X,
  Copy,
  Check,
  Play,
  Trash2,
  ShieldCheck,
  Activity,
  Sliders,
} from 'lucide-vue-next';

const toast = useToastStore();

const events = ref([]);
const loading = ref(false);
const filterStatus = ref('');
const feedback = ref('');
const feedbackType = ref('success');
const replayingId = ref(null);
const searchQuery = ref('');
const copiedUrl = ref(false);
const copiedPayload = ref(false);
const testingCrc = ref(false);

const stats = ref({
  total: 0,
  processed: 0,
  pending: 0,
  failed: 0,
  signature_valid: 0,
  signature_invalid: 0,
});

const meta = ref({
  webhook_url: '',
  has_secret_token: false,
});

const currentOriginWebhookUrl = computed(() => {
  return typeof window !== 'undefined' ? `${window.location.origin}/webhooks/zoom` : '/webhooks/zoom';
});

// Live Streaming / Auto-Refresh
const liveStreaming = ref(false);
let liveTimer = null;

const toggleLiveStream = () => {
  liveStreaming.value = !liveStreaming.value;
  if (liveStreaming.value) {
    toast.info('Live streaming webhook intake every 3 seconds');
    liveTimer = setInterval(fetchEvents, 3000);
  } else {
    if (liveTimer) {
      clearInterval(liveTimer);
      liveTimer = null;
    }
  }
};

const hasSimulatedEvents = computed(() => {
  return events.value.some((ev) => ev.event_id && ev.event_id.startsWith('sim-'));
});

const filteredEvents = computed(() => {
  if (!searchQuery.value.trim()) return events.value;
  const q = searchQuery.value.toLowerCase().trim();
  return events.value.filter((ev) => {
    return (
      (ev.event_type && ev.event_type.toLowerCase().includes(q)) ||
      (ev.event_id && ev.event_id.toLowerCase().includes(q)) ||
      (ev.ip_address && ev.ip_address.toLowerCase().includes(q)) ||
      (ev.status && ev.status.toLowerCase().includes(q)) ||
      (ev.payload && JSON.stringify(ev.payload).toLowerCase().includes(q))
    );
  });
});

const payloadModal = ref(false);
const selectedEvent = ref(null);

// Simulator
const simulatorModal = ref(false);
const simEventType = ref('meeting.started');
const simMeetingId = ref('98765432101');
const simPayloadText = ref('');
const simulating = ref(false);
const simCrcResult = ref(null);

const applyPresetTemplate = () => {
  simCrcResult.value = null;
  if (simEventType.value === 'endpoint.url_validation') {
    simPayloadText.value = JSON.stringify(
      {
        event: 'endpoint.url_validation',
        payload: {
          plainToken: 'test_token_' + Math.random().toString(36).substring(2, 10),
        },
      },
      null,
      2
    );
  } else {
    simPayloadText.value = JSON.stringify(
      {
        event: simEventType.value,
        event_ts: Date.now(),
        payload: {
          account_id: 'sample_account_123',
          object: {
            id: simMeetingId.value || '98765432101',
            uuid: btoa('uuid_' + (simMeetingId.value || '98765432101')),
            topic: 'Debug Simulated Meeting',
            type: 2,
            start_time: new Date().toISOString(),
            duration: 45,
            timezone: 'UTC',
            host_id: 'host_sample_123',
          },
        },
      },
      null,
      2
    );
  }
};

const openSimulator = () => {
  applyPresetTemplate();
  simulatorModal.value = true;
};

const dispatchSimulatedEvent = async () => {
  simulating.value = true;
  simCrcResult.value = null;
  try {
    let parsed = null;
    try {
      parsed = JSON.parse(simPayloadText.value);
    } catch {
      toast.error('Invalid JSON payload');
      simulating.value = false;
      return;
    }

    const res = await axios.post(
      '/admin/webhooks/simulate',
      {
        event_type: simEventType.value,
        payload: parsed,
        process_immediately: true,
      },
      { headers: { Accept: 'application/json' } }
    );

    if (res.data?.validation) {
      simCrcResult.value = res.data.validation;
      toast.success(res.data.message || 'CRC Challenge validated successfully!');
    } else {
      toast.success(res.data?.message || 'Simulated event dispatched successfully.');
      simulatorModal.value = false;
      await fetchEvents();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to dispatch simulated event');
  } finally {
    simulating.value = false;
  }
};

const testCrcHandshake = async () => {
  testingCrc.value = true;
  try {
    const plainToken = 'crc_test_' + Date.now();
    const res = await axios.post(
      '/admin/webhooks/simulate',
      {
        event_type: 'endpoint.url_validation',
        payload: { plainToken },
      },
      { headers: { Accept: 'application/json' } }
    );

    if (res.data?.validation?.encryptedToken) {
      toast.success(`CRC Validation verified! Token: ${res.data.validation.encryptedToken.substring(0, 16)}...`);
    } else {
      toast.info('CRC Challenge simulated.');
    }
  } catch (err) {
    toast.error('CRC Handshake test failed: ' + (err.response?.data?.message || err.message));
  } finally {
    testingCrc.value = false;
  }
};

const clearSimulatedEvents = async () => {
  try {
    const res = await axios.post('/admin/webhooks/clear-simulated', {}, {
      headers: { Accept: 'application/json' },
    });
    toast.success(res.data?.message || 'Simulated debug events cleared.');
    await fetchEvents();
  } catch (err) {
    toast.error('Failed to clear simulated events');
  }
};

const copyToClipboard = async (text, type) => {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    if (type === 'url') {
      copiedUrl.value = true;
      setTimeout(() => (copiedUrl.value = false), 2000);
    } else {
      copiedPayload.value = true;
      setTimeout(() => (copiedPayload.value = false), 2000);
    }
    toast.success('Copied to clipboard');
  } catch {
    toast.error('Could not copy to clipboard');
  }
};

const fetchEvents = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/admin/webhooks', {
      params: { status: filterStatus.value },
      headers: { Accept: 'application/json' },
    });
    events.value = res.data?.events?.data || res.data?.events || [];
    if (res.data?.stats) {
      stats.value = res.data.stats;
    }
    if (res.data?.webhook_url) {
      meta.value.webhook_url = res.data.webhook_url;
      meta.value.has_secret_token = !!res.data.has_secret_token;
    }
  } catch (err) {
    console.error('Failed to load webhook events', err);
  } finally {
    loading.value = false;
  }
};

const viewPayload = (ev) => {
  selectedEvent.value = ev;
  payloadModal.value = true;
};

const replayEvent = async (ev) => {
  replayingId.value = ev.public_id;
  try {
    const res = await axios.post(`/admin/webhooks/${ev.public_id}/replay`, {}, {
      headers: { Accept: 'application/json' },
    });
    toast.success(res.data?.message || 'Event queued for replay.');
    await fetchEvents();
    if (selectedEvent.value && selectedEvent.value.public_id === ev.public_id) {
      selectedEvent.value.status = 'pending';
    }
  } catch (err) {
    toast.error('Failed to replay webhook event: ' + (err.response?.data?.message || err.message));
  } finally {
    replayingId.value = null;
  }
};

const getStatusBadgeClass = (status) => {
  if (status === 'processed') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
  if (status === 'failed') return 'bg-rose-500/10 text-rose-600 dark:text-rose-400';
  return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
};

const formatDate = (iso) => {
  if (!iso) return 'N/A';
  return new Date(iso).toLocaleString();
};

onMounted(fetchEvents);

onUnmounted(() => {
  if (liveTimer) {
    clearInterval(liveTimer);
    liveTimer = null;
  }
});
</script>
