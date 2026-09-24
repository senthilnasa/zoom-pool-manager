<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <Video class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          All Scheduled Meetings
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Review reservations, join active sessions, manage host privileges, and export meeting logs.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Export Dropdown -->
        <div class="relative" ref="exportMenuRef">
          <button
            type="button"
            @click="showExportMenu = !showExportMenu"
            class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold shadow-xs transition cursor-pointer"
          >
            <Download class="w-4 h-4 text-brand-600 dark:text-brand-400" />
            <span>Export</span>
            <ChevronDown class="w-3.5 h-3.5 opacity-60 ml-0.5" />
          </button>

          <div
            v-if="showExportMenu"
            class="absolute right-0 mt-2 w-48 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-1.5 z-30 text-xs"
          >
            <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
              Download Format
            </div>
            <a
              :href="getExportUrl('xlsx')"
              @click="showExportMenu = false"
              class="flex items-center gap-2 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
            >
              <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
              <span>Export to Excel (.xlsx)</span>
            </a>
            <a
              :href="getExportUrl('pdf')"
              target="_blank"
              @click="showExportMenu = false"
              class="flex items-center gap-2 px-3 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
            >
              <FileText class="w-4 h-4 text-rose-500" />
              <span>Export to PDF (.pdf)</span>
            </a>
          </div>
        </div>

        <router-link
          to="/app/meetings/create"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-md shadow-brand-500/20 transition-all cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Book Meeting</span>
        </router-link>
      </div>
    </div>

    <!-- Filters Bar -->
    <GlassCard :padding="true">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <!-- Search Input -->
        <div class="relative w-full sm:w-96">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
          <input
            v-model="filters.search"
            @input="debouncedSearch"
            @keydown.enter="executeSearchImmediately"
            type="search"
            name="meetings_search_filter"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            placeholder="Search by title, public ID, or Zoom meeting ID... (↵ to search)"
            class="w-full glass-input pl-10 text-xs"
          />
        </div>

        <!-- Status Filter & Refresh -->
        <div class="flex items-center gap-3 w-full sm:w-auto">
          <select
            v-model="filters.status"
            @change="loadMeetings(1)"
            class="glass-input text-xs font-medium cursor-pointer"
          >
            <option value="">All Statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="started">Started / Active</option>
            <option value="allocating">Allocating</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="failed">Failed</option>
          </select>

          <button
            @click="loadMeetings(1)"
            class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:bg-slate-100 text-slate-600 dark:text-slate-300 cursor-pointer"
            title="Refresh"
          >
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          </button>
        </div>
      </div>
    </GlassCard>

    <!-- Meetings Table -->
    <GlassCard :padding="false">
      <div v-if="loading && meetings.length === 0" class="py-16 text-center text-sm text-slate-400">
        <RefreshCw class="w-6 h-6 animate-spin mx-auto mb-2 text-brand-500" />
        Loading meetings...
      </div>

      <div v-else-if="meetings.length === 0" class="py-16 text-center space-y-2">
        <Video class="w-10 h-10 text-slate-300 dark:text-slate-700 mx-auto mb-3" />
        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">No meetings found</div>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          Try adjusting your search criteria, clear status filters, or book a new meeting reservation.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 select-none bg-slate-50/50 dark:bg-slate-900/50">
              <!-- Title Column (Sortable) -->
              <th
                @click="toggleSort('title')"
                class="py-3.5 px-4 cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Title & Details</span>
                  <component
                    :is="getSortIcon('title')"
                    class="w-3.5 h-3.5"
                    :class="sortBy === 'title' ? 'text-brand-600 dark:text-brand-400' : 'opacity-40'"
                  />
                </div>
              </th>

              <!-- Time Window Column (Sortable) -->
              <th
                @click="toggleSort('starts_at')"
                class="py-3.5 px-4 cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Time Window</span>
                  <component
                    :is="getSortIcon('starts_at')"
                    class="w-3.5 h-3.5"
                    :class="sortBy === 'starts_at' ? 'text-brand-600 dark:text-brand-400' : 'opacity-40'"
                  />
                </div>
              </th>

              <th class="py-3.5 px-4">Resource / Host</th>

              <!-- Status Column (Sortable) -->
              <th
                @click="toggleSort('status')"
                class="py-3.5 px-4 cursor-pointer hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
              >
                <div class="flex items-center gap-1.5">
                  <span>Status</span>
                  <component
                    :is="getSortIcon('status')"
                    class="w-3.5 h-3.5"
                    :class="sortBy === 'status' ? 'text-brand-600 dark:text-brand-400' : 'opacity-40'"
                  />
                </div>
              </th>

              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr
              v-for="m in meetings"
              :key="m.public_id"
              class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors text-xs"
            >
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5 flex-wrap">
                  <span>{{ m.title }}</span>
                  <!-- Recurring Series Badge -->
                  <span
                    v-if="m.series_id"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20"
                    title="Recurring Meeting Series"
                  >
                    <Repeat class="w-3 h-3" />
                    <span>Series</span>
                  </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5 flex-wrap">
                  <span class="font-mono">ID: {{ m.public_id }}</span>
                  <span v-if="m.template">• {{ m.template.name }}</span>

                  <!-- Recording Badge -->
                  <span
                    v-if="m.recording_mode && m.recording_mode !== 'none'"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20"
                    :title="`Recording: ${m.recording_mode}`"
                  >
                    <Cloud class="w-3 h-3" />
                    <span>{{ m.recording_mode === 'cloud' ? 'Cloud Rec' : 'Local Rec' }}</span>
                  </span>

                  <!-- Waiting Room Badge -->
                  <span
                    v-if="m.waiting_room"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                    title="Waiting Room Enabled"
                  >
                    <ShieldAlert class="w-3 h-3" />
                    <span>Waiting Room</span>
                  </span>

                  <!-- Auto-Start / Join Before Host -->
                  <span
                    v-if="m.join_before_host"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20"
                    title="Auto-Start without host"
                  >
                    <PlayCircle class="w-3 h-3" />
                    <span>Auto-Start</span>
                  </span>

                  <!-- Attendance Tracking -->
                  <span
                    v-if="m.attendance_tracking"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                    title="Attendance Telemetry Tracking Enabled"
                  >
                    <Users2 class="w-3 h-3" />
                    <span>Attendance</span>
                  </span>

                  <!-- Host Key PIN 1-Click Copy Chip -->
                  <button
                    v-if="m.host_key || m.share_host_key"
                    type="button"
                    @click.stop="copyHostKey(m.host_key || m.zoom_resource?.zoom_user?.host_key)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition cursor-pointer"
                    title="Click to copy Host Key PIN (Use Participants > Claim Host in Zoom)"
                  >
                    <KeyRound class="w-3 h-3 text-indigo-500" />
                    <span>PIN: {{ m.host_key || m.zoom_resource?.zoom_user?.host_key || '6-digit' }}</span>
                  </button>
                </div>
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                <div>{{ formatDateTime(m.starts_at) }}</div>
                <div class="text-[11px] text-slate-400">to {{ formatTime(m.ends_at) }}</div>
              </td>
              <td class="py-3.5 px-4">
                <div class="text-slate-800 dark:text-slate-200 font-semibold">
                  {{ m.zoom_resource?.name || 'Pooled Auto-Assign' }}
                </div>
                <div class="text-slate-400 text-[11px]">
                  Owner: {{ m.owner?.name || 'System' }}
                </div>
              </td>
              <td class="py-3.5 px-4">
                <!-- Status Badges with clear state iconography -->
                <span
                  v-if="m.status === 'allocating'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                >
                  <Loader2 class="w-3 h-3 animate-spin" />
                  <span>Allocating...</span>
                </span>

                <span
                  v-else-if="m.status === 'started'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping" />
                  <span>Active Session</span>
                </span>

                <span
                  v-else-if="m.status === 'scheduled'"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20"
                >
                  Scheduled
                </span>

                <span
                  v-else-if="m.status === 'completed'"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20"
                >
                  Completed
                </span>

                <span
                  v-else-if="m.status === 'cancelled'"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20"
                >
                  Cancelled
                </span>

                <span
                  v-else-if="m.status === 'failed'"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20"
                  :title="m.cancelled_reason || 'Allocation timed out'"
                >
                  <AlertCircle class="w-3 h-3" />
                  <span>Failed</span>
                </span>

                <span
                  v-else
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20"
                >
                  {{ m.status }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    @click="openMeetingDetails(m)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    title="View Details & Host Key PIN"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <button
                    @click="copyFullInvitation(m)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    title="Copy Full Zoom Invitation"
                  >
                    <ClipboardCopy class="w-4 h-4" />
                  </button>

                  <button
                    v-if="['scheduled', 'allocating'].includes(m.status)"
                    @click="openEditModal(m)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    title="Edit & Reschedule Meeting"
                  >
                    <Pencil class="w-4 h-4" />
                  </button>

                  <button
                    v-if="m.status === 'started'"
                    @click="extendMeeting(m, 15)"
                    class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/60 transition-colors cursor-pointer"
                    title="Extend Meeting (+15m)"
                  >
                    <Clock class="w-4 h-4" />
                  </button>

                  <a
                    :href="`/spa/meetings/${m.public_id}/ics`"
                    download
                    class="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title="Download .ICS Calendar"
                  >
                    <CalendarIcon class="w-4 h-4" />
                  </a>

                  <button
                    v-if="m.status === 'started'"
                    @click="endMeetingEarly(m)"
                    class="p-1.5 rounded-lg text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/60 transition-colors cursor-pointer"
                    title="End Meeting Early & Free Pooled License"
                  >
                    <Square class="w-4 h-4" />
                  </button>

                  <a
                    v-if="m.join_url"
                    :href="m.join_url"
                    target="_blank"
                    class="p-1.5 rounded-lg text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-950/60 transition-colors"
                    title="Join Zoom Meeting"
                  >
                    <ExternalLink class="w-4 h-4" />
                  </a>

                  <button
                    v-if="['scheduled', 'allocating'].includes(m.status)"
                    @click="cancelMeeting(m)"
                    class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/60 transition-colors cursor-pointer"
                    title="Cancel Meeting"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Universal Server-Side Pagination Component -->
        <TablePagination
          :current-page="currentPage"
          :last-page="totalPages"
          :per-page="perPage"
          :total="totalRecords"
          @page-change="loadMeetings"
          @per-page-change="onPerPageChange"
        />
      </div>
    </GlassCard>

    <!-- Meeting Details / Inspector Modal -->
    <Modal
      :show="showDetailsModal"
      @close="showDetailsModal = false"
      max-width="xl"
      :title="selectedMeeting ? selectedMeeting.title : 'Meeting Details'"
    >
      <div v-if="selectedMeeting" class="space-y-4">
        <!-- Status & Badge Header -->
        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
          <div class="flex items-center gap-2">
            <span
              class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider"
              :class="getStatusBadgeClass(selectedMeeting.status)"
            >
              {{ selectedMeeting.status }}
            </span>
            <span v-if="selectedMeeting.meeting_type" class="text-xs text-slate-400 capitalize">
              • {{ selectedMeeting.meeting_type }}
            </span>
            <span v-if="selectedMeeting.series_id" class="text-xs px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold">
              Recurring Series
            </span>
          </div>

          <span class="text-xs font-mono text-slate-400">
            ID: {{ selectedMeeting.public_id }}
          </span>
        </div>

        <!-- Security & Automation Feature Badges -->
        <div class="flex items-center gap-2 flex-wrap text-xs">
          <span
            v-if="selectedMeeting.recording_mode && selectedMeeting.recording_mode !== 'none'"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20"
          >
            <Cloud class="w-3.5 h-3.5" />
            <span>{{ selectedMeeting.recording_mode === 'cloud' ? 'Cloud Rec' : 'Local Rec' }}</span>
          </span>

          <span
            v-if="selectedMeeting.waiting_room"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
          >
            <ShieldAlert class="w-3.5 h-3.5" />
            <span>Waiting Room</span>
          </span>

          <span
            v-if="selectedMeeting.join_before_host"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20"
          >
            <PlayCircle class="w-3.5 h-3.5" />
            <span>Auto-Start</span>
          </span>

          <span
            v-if="selectedMeeting.attendance_tracking"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
          >
            <Users2 class="w-3.5 h-3.5" />
            <span>Attendance Sync</span>
          </span>
        </div>

        <!-- Host Key PIN Banner with 1-Click Copy -->
        <div
          v-if="selectedMeeting.host_key || selectedMeeting.zoom_resource?.zoom_user?.host_key"
          class="p-3.5 rounded-xl border border-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-950/30 flex items-center justify-between gap-3"
        >
          <div class="space-y-0.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
              <KeyRound class="w-3.5 h-3.5" />
              <span>Zoom Host Key PIN</span>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-400">
              In Zoom: <span class="font-semibold text-slate-800 dark:text-slate-200">Participants &gt; Claim Host</span> &gt; enter PIN to start &amp; become host.
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 font-mono font-bold text-sm tracking-widest text-indigo-700 dark:text-indigo-300 select-all">
              {{ selectedMeeting.host_key || selectedMeeting.zoom_resource?.zoom_user?.host_key }}
            </span>
            <button
              type="button"
              @click="copyHostKey(selectedMeeting.host_key || selectedMeeting.zoom_resource?.zoom_user?.host_key)"
              class="p-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
              title="Copy 6-digit Host Key"
            >
              <Copy class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60 space-y-1">
            <div class="text-[10px] font-bold uppercase text-slate-400">Scheduled Time</div>
            <div class="font-bold text-slate-800 dark:text-slate-200">{{ formatDateTime(selectedMeeting.starts_at) }}</div>
            <div class="text-slate-400">to {{ formatTime(selectedMeeting.ends_at) }}</div>
          </div>

          <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60 space-y-1">
            <div class="text-[10px] font-bold uppercase text-slate-400">Host & Pool</div>
            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">
              {{ selectedMeeting.zoom_resource?.name || 'Pooled Host' }}
            </div>
            <div class="text-slate-400 truncate">Organizer: {{ selectedMeeting.owner?.name || 'N/A' }}</div>
          </div>

          <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60 space-y-1">
            <div class="text-[10px] font-bold uppercase text-slate-400">Zoom Meeting ID</div>
            <div class="font-mono font-bold text-slate-800 dark:text-slate-200">
              {{ selectedMeeting.zoom_meeting_id || 'Generating...' }}
            </div>
            <div v-if="selectedMeeting.passcode" class="text-slate-400">
              Passcode: <span class="font-mono text-slate-600 dark:text-slate-300">{{ selectedMeeting.passcode }}</span>
            </div>
          </div>

          <div class="p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60 space-y-1">
            <div class="text-[10px] font-bold uppercase text-slate-400">Department & Attendance</div>
            <div class="font-bold text-slate-800 dark:text-slate-200">
              {{ selectedMeeting.department?.name || 'General' }}
            </div>
            <div class="text-slate-400">Capacity: {{ selectedMeeting.participant_count }} seats</div>
          </div>
        </div>

        <!-- Description if present -->
        <div v-if="selectedMeeting.description" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-xs text-slate-600 dark:text-slate-300">
          <div class="font-bold text-[10px] uppercase text-slate-400 mb-1">Agenda / Description</div>
          <p>{{ selectedMeeting.description }}</p>
        </div>

        <!-- Attendees & Invitees -->
        <div class="p-3.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60 space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Attendees & Invitees ({{ selectedMeeting.invitees?.length || 0 }})
            </div>
          </div>
          <div v-if="selectedMeeting.invitees && selectedMeeting.invitees.length" class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
            <span
              v-for="inv in selectedMeeting.invitees"
              :key="inv.id"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-slate-50 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="inv.status === 'accepted' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
              <span>{{ inv.email }}</span>
            </span>
          </div>
          <div v-else class="text-xs text-slate-400 italic">No attendees specifically listed for this meeting.</div>

          <!-- Add Invitee Form -->
          <form @submit.prevent="addInviteeToSelected" class="flex items-center gap-2 pt-1.5 border-t border-slate-100 dark:border-slate-700/60">
            <input
              v-model="newInviteeEmail"
              type="email"
              placeholder="Invite attendee (e.g. colleague@institution.edu)..."
              class="flex-1 text-xs rounded-xl px-3 py-1.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
            <button
              type="submit"
              :disabled="!newInviteeEmail || addingInvitee"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white transition disabled:opacity-50 cursor-pointer shrink-0"
            >
              {{ addingInvitee ? 'Inviting...' : 'Invite' }}
            </button>
          </form>
        </div>

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2 flex-wrap">
            <a
              :href="`/spa/meetings/${selectedMeeting.public_id}/ics`"
              download
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
            >
              <Download class="w-3.5 h-3.5" />
              <span>Download .ICS</span>
            </a>

            <button
              @click="copyFullInvitation(selectedMeeting)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
              title="Copy formatted invitation to clipboard"
            >
              <ClipboardCopy class="w-3.5 h-3.5" />
              <span>Copy Full Invitation</span>
            </button>

            <button
              v-if="['scheduled', 'allocating'].includes(selectedMeeting.status)"
              @click="openEditModal(selectedMeeting)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
            >
              <Pencil class="w-3.5 h-3.5" />
              <span>Edit / Reschedule</span>
            </button>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <!-- Extend Meeting (+15m / +30m) -->
            <div v-if="selectedMeeting.status === 'started'" class="flex items-center gap-1">
              <button
                @click="extendMeeting(selectedMeeting, 15)"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 cursor-pointer"
                title="Extend meeting by 15 minutes"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>+15m</span>
              </button>
              <button
                @click="extendMeeting(selectedMeeting, 30)"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800 cursor-pointer"
                title="Extend meeting by 30 minutes"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>+30m</span>
              </button>
            </div>

            <button
              v-if="['started', 'scheduled'].includes(selectedMeeting.status)"
              @click="endMeetingEarly(selectedMeeting)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/20 transition cursor-pointer"
            >
              <Square class="w-3.5 h-3.5" />
              <span>End Early & Free Host</span>
            </button>

            <a
              v-if="selectedMeeting.join_url"
              :href="selectedMeeting.join_url"
              target="_blank"
              class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer"
            >
              <ExternalLink class="w-3.5 h-3.5" />
              <span>Join Zoom</span>
            </a>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Edit & Reschedule Modal -->
    <Modal
      :show="showEditModal"
      @close="showEditModal = false"
      max-width="lg"
      title="Edit & Reschedule Meeting"
    >
      <form v-if="editForm" @submit.prevent="saveMeetingEdit" class="space-y-4 text-xs">
        <div>
          <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Meeting Title *</label>
          <input v-model="editForm.title" type="text" required class="w-full glass-input" />
        </div>

        <div>
          <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Agenda / Description</label>
          <textarea v-model="editForm.description" rows="2" class="w-full glass-input resize-none"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Start Time *</label>
            <input v-model="editForm.starts_at" type="datetime-local" required class="w-full glass-input" />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">End Time *</label>
            <input v-model="editForm.ends_at" type="datetime-local" required class="w-full glass-input" />
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Meeting Passcode</label>
          <input v-model="editForm.passcode" type="text" maxlength="10" class="w-full glass-input font-mono" />
        </div>

        <!-- Flags Grid -->
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2">
          <div class="font-bold text-[10px] uppercase text-slate-400">Security & Automation Settings</div>
          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span>Waiting Room</span>
              <input type="checkbox" v-model="editForm.waiting_room" class="rounded text-brand-600">
            </label>
            <label class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span>Auto-Start (No Host)</span>
              <input type="checkbox" v-model="editForm.join_before_host" class="rounded text-brand-600">
            </label>
            <label class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span>Share Host Key PIN</span>
              <input type="checkbox" v-model="editForm.share_host_key" class="rounded text-brand-600">
            </label>
            <label class="flex items-center justify-between p-2 rounded bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span>Attendance Tracking</span>
              <input type="checkbox" v-model="editForm.attendance_tracking" class="rounded text-emerald-600">
            </label>
          </div>
          <div class="flex items-center justify-between pt-1">
            <span>Recording:</span>
            <select v-model="editForm.recording_mode" class="glass-input text-xs py-1">
              <option value="none">None</option>
              <option value="cloud">Cloud Recording</option>
              <option value="local">Local Recording</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="showEditModal = false" class="px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold cursor-pointer">
            Cancel
          </button>
          <button type="submit" :disabled="savingEdit" class="px-4 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition cursor-pointer disabled:opacity-50">
            {{ savingEdit ? 'Saving...' : 'Save & Reschedule' }}
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import TablePagination from '@/components/TablePagination.vue';
import Modal from '@/components/Modal.vue';
import { useToastStore } from '@/stores/toast';
import {
  Plus,
  Search,
  RefreshCw,
  Video,
  ExternalLink,
  Trash2,
  Download,
  ChevronDown,
  FileSpreadsheet,
  FileText,
  ArrowUp,
  ArrowDown,
  ArrowUpDown,
  Loader2,
  AlertCircle,
  Calendar as CalendarIcon,
  Square,
  KeyRound,
  ShieldAlert,
  PlayCircle,
  Cloud,
  Users2,
  Repeat,
  Copy,
  Eye,
  Pencil,
  Clock,
  ClipboardCopy,
} from 'lucide-vue-next';

const toast = useToastStore();

const meetings = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(25);
const totalRecords = ref(0);

const sortBy = ref('starts_at');
const sortDir = ref('desc');

const showExportMenu = ref(false);
const exportMenuRef = ref(null);

const showDetailsModal = ref(false);
const selectedMeeting = ref(null);

const showEditModal = ref(false);
const editForm = ref(null);
const savingEdit = ref(false);

const newInviteeEmail = ref('');
const addingInvitee = ref(false);

const openMeetingDetails = (m) => {
  selectedMeeting.value = m;
  showDetailsModal.value = true;
};

const copyFullInvitation = (meeting) => {
  if (!meeting) return;
  const startsStr = formatDateTime(meeting.starts_at);
  const tz = meeting.timezone || 'Asia/Kolkata';
  const ownerName = meeting.owner?.name || 'Organizer';
  const meetingId = meeting.zoom_meeting_id || 'N/A';
  const passcode = meeting.passcode ? `\nPasscode: ${meeting.passcode}` : '';
  const hostPin = meeting.host_key || meeting.zoom_resource?.zoom_user?.host_key;
  const hostKeyText = hostPin
    ? `\n\nHost Key PIN: ${hostPin}\n(To claim host: In Zoom client, open Participants > Claim Host, and enter this 6-digit PIN)`
    : '';

  const inviteText = `${ownerName} is inviting you to a scheduled Zoom meeting.\n\nTopic: ${meeting.title}\nTime: ${startsStr} (${tz})\n\nJoin Zoom Meeting:\n${meeting.join_url || 'https://zoom.us/j/' + meetingId}\n\nMeeting ID: ${meetingId}${passcode}${hostKeyText}\n\nPowered by Zoom Pool Manager`;

  navigator.clipboard.writeText(inviteText);
  toast.success('Full Zoom meeting invitation copied to clipboard!');
};

const extendMeeting = async (meeting, minutes = 15) => {
  try {
    const res = await axios.post(`/spa/meetings/${meeting.public_id}/extend`, { minutes });
    toast.success(res.data?.message || `Meeting extended by ${minutes} minutes!`);
    loadMeetings(currentPage.value);
    if (selectedMeeting.value && selectedMeeting.value.public_id === meeting.public_id) {
      selectedMeeting.value.ends_at = res.data.meeting?.ends_at || selectedMeeting.value.ends_at;
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to extend meeting.';
    toast.error(msg);
  }
};

const openEditModal = (meeting) => {
  const formatInput = (dStr) => {
    if (!dStr) return '';
    const d = new Date(dStr);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  editForm.value = {
    public_id: meeting.public_id,
    title: meeting.title,
    description: meeting.description || '',
    starts_at: formatInput(meeting.starts_at),
    ends_at: formatInput(meeting.ends_at),
    passcode: meeting.passcode || '',
    waiting_room: Boolean(meeting.waiting_room),
    join_before_host: Boolean(meeting.join_before_host),
    jbh_time: meeting.jbh_time || 0,
    share_host_key: Boolean(meeting.share_host_key),
    recording_mode: meeting.recording_mode || 'none',
    attendance_tracking: Boolean(meeting.attendance_tracking),
  };
  showEditModal.value = true;
};

const saveMeetingEdit = async () => {
  try {
    savingEdit.value = true;
    const res = await axios.put(`/spa/meetings/${editForm.value.public_id}`, editForm.value);
    toast.success(res.data?.message || 'Meeting updated and rescheduled successfully!');
    showEditModal.value = false;
    loadMeetings(currentPage.value);
    if (selectedMeeting.value && selectedMeeting.value.public_id === editForm.value.public_id) {
      selectedMeeting.value = res.data.meeting;
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to update meeting.';
    toast.error(msg);
  } finally {
    savingEdit.value = false;
  }
};

const addInviteeToSelected = async () => {
  if (!selectedMeeting.value || !newInviteeEmail.value) return;
  try {
    addingInvitee.value = true;
    const res = await axios.post(`/spa/meetings/${selectedMeeting.value.public_id}/invitees`, {
      email: newInviteeEmail.value,
    });
    toast.success(res.data?.message || 'Attendee added successfully!');
    if (!selectedMeeting.value.invitees) {
      selectedMeeting.value.invitees = [];
    }
    selectedMeeting.value.invitees.push(res.data.invitee);
    newInviteeEmail.value = '';
    loadMeetings(currentPage.value, true);
  } catch (e) {
    toast.error(e.response?.data?.message || 'Failed to invite attendee.');
  } finally {
    addingInvitee.value = false;
  }
};

const copyHostKey = async (key) => {
  if (!key) return;
  try {
    await navigator.clipboard.writeText(key);
    toast.success('Host Key PIN copied! In Zoom: Participants > Claim Host > enter PIN.');
  } catch {
    toast.error('Could not copy Host Key.');
  }
};

const copyJoinLink = async (url) => {
  if (!url) return;
  try {
    await navigator.clipboard.writeText(url);
    toast.success('Join link copied to clipboard!');
  } catch {
    toast.error('Could not copy link.');
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'started':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
    case 'pending_approval':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
    case 'completed':
      return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20';
    case 'failed':
      return 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20';
    default:
      return 'bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20';
  }
};

const filters = ref({
  search: '',
  status: '',
});

let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadMeetings(1);
  }, 750);
};

const executeSearchImmediately = () => {
  clearTimeout(searchTimeout);
  loadMeetings(1);
};

const toggleSort = (column) => {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = column;
    sortDir.value = column === 'starts_at' ? 'desc' : 'asc';
  }
  loadMeetings(1);
};

const getSortIcon = (column) => {
  if (sortBy.value !== column) return ArrowUpDown;
  return sortDir.value === 'asc' ? ArrowUp : ArrowDown;
};

const onPerPageChange = (newPerPage) => {
  perPage.value = newPerPage;
  loadMeetings(1);
};

const getExportUrl = (format) => {
  const params = new URLSearchParams({
    format,
    search: filters.value.search || '',
    status: filters.value.status || '',
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  });
  return `/spa/meetings/export?${params.toString()}`;
};

const formatDateTime = (str) => {
  if (!str) return 'N/A';
  return new Date(str).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatTime = (str) => {
  if (!str) return '';
  return new Date(str).toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
};

let pollTimer = null;
const checkAndSchedulePolling = () => {
  clearTimeout(pollTimer);
  const hasActiveOrAllocating = meetings.value.some((m) =>
    ['allocating', 'started'].includes(m.status)
  );

  if (hasActiveOrAllocating) {
    pollTimer = setTimeout(() => {
      loadMeetings(currentPage.value, true);
    }, 5000);
  }
};

const loadMeetings = async (page = 1, isSilentPoll = false) => {
  try {
    if (!isSilentPoll) {
      loading.value = true;
    }
    const params = {
      page,
      per_page: perPage.value,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      search: filters.value.search || undefined,
      status: filters.value.status || undefined,
    };
    const res = await axios.get('/spa/meetings', { params });
    meetings.value = res.data.data || [];
    currentPage.value = res.data.current_page || 1;
    totalPages.value = res.data.last_page || 1;
    totalRecords.value = res.data.total || 0;

    checkAndSchedulePolling();
  } catch (e) {
    if (!isSilentPoll) {
      toast.error('Failed to load scheduled meetings.');
    }
  } finally {
    loading.value = false;
  }
};

const cancelMeeting = async (meeting) => {
  if (!confirm(`Are you sure you want to cancel "${meeting.title}"?`)) return;
  try {
    await axios.post(`/meetings/${meeting.public_id}/cancel`, {
      reason: 'Cancelled via modern SPA interface',
    });
    toast.success('Meeting reservation cancelled.');
    loadMeetings(currentPage.value);
  } catch (e) {
    toast.error('Failed to cancel meeting.');
  }
};

const endMeetingEarly = async (meeting) => {
  if (!confirm(`Are you sure you want to end "${meeting.title}" early? This will immediately release the pooled Zoom host license back to the pool.`)) {
    return;
  }
  try {
    const res = await axios.post(`/spa/meetings/${meeting.public_id}/end-early`);
    if (res.data.success) {
      toast.success(res.data.message);
      loadMeetings(currentPage.value);
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to end meeting early.');
  }
};

onMounted(() => {
  loadMeetings(1);
});

onUnmounted(() => {
  clearTimeout(pollTimer);
  clearTimeout(searchTimeout);
});
</script>
