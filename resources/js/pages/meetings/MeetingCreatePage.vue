<template>
  <div class="max-w-4xl mx-auto space-y-6 pb-12">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
          <CalendarIcon class="w-6 h-6 text-brand-600 dark:text-brand-400" />
          Schedule Pooled Meeting
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Reserve an intelligent pooled Zoom host license with automated security, recording, attendance, and host key delegation.
        </p>
      </div>

      <router-link
        to="/app/meetings"
        class="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200"
      >
        &larr; Back to Meetings
      </router-link>
    </div>

    <!-- Booking Form -->
    <form @submit.prevent="submitBooking" class="space-y-6">
      <!-- 1. Meeting Basic Details -->
      <GlassCard title="Meeting Information">
        <div class="space-y-4">
          <!-- Title -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Meeting Title *
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              class="w-full glass-input"
              placeholder="e.g. CS101 Lecture, Faculty Board Sync, or Lab Practical"
            />
          </div>

          <!-- Agenda / Description -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Agenda / Description (Optional)
            </label>
            <textarea
              v-model="form.agenda"
              rows="2"
              class="w-full glass-input resize-none"
              placeholder="Summary of meeting topics, session goals, or preparation instructions..."
            ></textarea>
          </div>

          <!-- Date & Times -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                Start Time *
              </label>
              <input
                v-model="form.starts_at"
                type="datetime-local"
                required
                @change="checkConflicts"
                class="w-full glass-input"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                End Time *
              </label>
              <input
                v-model="form.ends_at"
                type="datetime-local"
                required
                @change="checkConflicts"
                class="w-full glass-input"
              />
            </div>
          </div>

          <!-- Template, Expected Participants & Resource Pool -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                Expected Participants
              </label>
              <input
                v-model.number="form.participant_count"
                type="number"
                min="1"
                max="1000"
                class="w-full glass-input"
                placeholder="10"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                Meeting Template
              </label>
              <SearchableSelect
                v-model="form.template_id"
                :options="[{ id: null, name: 'Default Template' }, ...(options.templates || [])]"
                @change="onTemplateSelected"
                placeholder="Default Template"
                search-placeholder="Search templates..."
                label-key="name"
                value-key="id"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                Resource Pool
              </label>
              <SearchableSelect
                v-model="form.pool_id"
                :options="[{ id: null, name: 'Auto-Select Best Available Pool' }, ...(options.pools || [])]"
                @change="checkConflicts"
                placeholder="Auto-Select Best Available Pool"
                search-placeholder="Search pools..."
                label-key="name"
                value-key="id"
              />
            </div>
          </div>

          <!-- Book on Behalf of User (Admin / Delegated Scheduling) -->
          <div v-if="options.can_book_on_behalf" class="p-4 rounded-xl border border-brand-500/20 bg-brand-50/50 dark:bg-brand-950/20 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <UserCheck class="w-4 h-4 text-brand-600 dark:text-brand-400" />
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                  Book on behalf of another user (e.g. delegated faculty scheduling)
                </span>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="bookOnBehalf" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
              </label>
            </div>

            <div v-if="bookOnBehalf" class="space-y-1.5 pt-1">
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">
                Select Host / Meeting Owner (10,000+ Employees) *
              </label>
              <SearchableSelect
                v-model="form.owner_user_id"
                remote-url="/spa/users/search"
                :initial-options="options.users || []"
                placeholder="Search faculty / staff member by name or email..."
                search-placeholder="Type name, email, or designation..."
                label-key="name"
                value-key="id"
                sublabel-key="email"
                badge-key="department.name"
              />
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                The selected user will be designated as the meeting host/owner, will receive calendar invites with host instructions, and the meeting will be attributed to their department.
              </p>
            </div>
          </div>
        </div>
      </GlassCard>

      <!-- 2. Security & Room Controls (Waiting Room, Auto-Start / Join Before Host, Share Host Key) -->
      <GlassCard title="Access & Security Controls">
        <div class="space-y-4">
          <!-- Waiting Room -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40">
            <div class="flex items-start gap-3">
              <ShieldAlert class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Waiting Room</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  Attendees will be placed in the Zoom Waiting Room until admitted by the host or a user with the Host Key.
                </p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
              <input type="checkbox" v-model="form.waiting_room" class="sr-only peer">
              <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
            </label>
          </div>

          <!-- Auto-Start Without Host (Join Before Host) -->
          <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40 space-y-3">
            <div class="flex items-start justify-between">
              <div class="flex items-start gap-3">
                <PlayCircle class="w-5 h-5 text-sky-500 shrink-0 mt-0.5" />
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white">Auto-Start Without Host (Join Before Host)</div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Allow participants to join and start the meeting session before the pooled host account logs in.
                  </p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
                <input type="checkbox" v-model="form.join_before_host" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
              </label>
            </div>

            <!-- Lead Time Dropdown when Join Before Host is enabled -->
            <div v-if="form.join_before_host" class="pt-2 border-t border-slate-200/60 dark:border-slate-800 flex items-center justify-between gap-4">
              <label class="text-xs font-medium text-slate-700 dark:text-slate-300">
                Allowed Join Lead Time
              </label>
              <select
                v-model.number="form.jbh_time"
                class="glass-input text-xs w-48 cursor-pointer"
              >
                <option :value="0">Anytime before start</option>
                <option :value="5">5 minutes before start</option>
                <option :value="10">10 minutes before start</option>
                <option :value="15">15 minutes before start</option>
              </select>
            </div>
          </div>

          <!-- Share Host Key PIN with User / Organizer -->
          <div class="p-3.5 rounded-xl border border-indigo-500/20 bg-indigo-50/30 dark:bg-indigo-950/20 space-y-3">
            <div class="flex items-start justify-between">
              <div class="flex items-start gap-3">
                <KeyRound class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span>Share Zoom Host Key PIN</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">Claim Host</span>
                  </div>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Share the 6-digit Host Key PIN with meeting organizers and participants so they can become the host and start the meeting independently.
                  </p>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
                <input type="checkbox" v-model="form.share_host_key" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
              </label>
            </div>

            <div v-if="form.share_host_key" class="text-[11px] p-2.5 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-indigo-800 dark:text-indigo-200 flex items-center gap-2">
              <CheckCircle2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span>
                <strong>How it works:</strong> Anyone with the meeting link can open <em>Participants &gt; Claim Host</em> in the Zoom client, enter the 6-digit PIN, and instantly become host without needing the host account's password.
              </span>
            </div>
          </div>

          <!-- Custom Passcode -->
          <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-slate-900 dark:text-white">
                Meeting Passcode (Optional)
              </label>
              <button
                type="button"
                @click="generatePasscode"
                class="text-[11px] font-semibold text-brand-600 dark:text-brand-400 hover:underline cursor-pointer"
              >
                Auto-Generate PIN
              </button>
            </div>
            <input
              v-model="form.passcode"
              type="text"
              maxlength="10"
              class="w-full glass-input font-mono text-xs"
              placeholder="Leave blank to auto-generate a secure 6-digit passcode"
            />
          </div>
        </div>
      </GlassCard>

      <!-- 3. Media & Attendance Automation (Recording Mode, Attendance Tracking) -->
      <GlassCard title="Recording & Attendance Automation">
        <div class="space-y-4">
          <!-- Recording Mode Selector -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
              Recording Mode
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <!-- None -->
              <div
                @click="form.recording_mode = 'none'"
                class="p-3 rounded-xl border text-xs cursor-pointer transition-all flex flex-col justify-between"
                :class="form.recording_mode === 'none' ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-950/30 ring-1 ring-brand-500' : 'border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white/40 dark:bg-slate-900/40'"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <span class="font-bold text-slate-900 dark:text-white">None (Off)</span>
                  <div class="w-3.5 h-3.5 rounded-full border flex items-center justify-center" :class="form.recording_mode === 'none' ? 'border-brand-600 bg-brand-600' : 'border-slate-400'">
                    <span v-if="form.recording_mode === 'none'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                  Do not record session. Recommended for sensitive or routine meetings.
                </p>
              </div>

              <!-- Cloud Recording -->
              <div
                @click="form.recording_mode = 'cloud'"
                class="p-3 rounded-xl border text-xs cursor-pointer transition-all flex flex-col justify-between"
                :class="form.recording_mode === 'cloud' ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-950/30 ring-1 ring-brand-500' : 'border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white/40 dark:bg-slate-900/40'"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <Cloud class="w-3.5 h-3.5 text-brand-600 dark:text-brand-400" />
                    Cloud Recording
                  </span>
                  <div class="w-3.5 h-3.5 rounded-full border flex items-center justify-center" :class="form.recording_mode === 'cloud' ? 'border-brand-600 bg-brand-600' : 'border-slate-400'">
                    <span v-if="form.recording_mode === 'cloud'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                  Auto-record to Zoom Cloud with audio transcripts and automatic institutional archiving.
                </p>
              </div>

              <!-- Local Recording -->
              <div
                @click="form.recording_mode = 'local'"
                class="p-3 rounded-xl border text-xs cursor-pointer transition-all flex flex-col justify-between"
                :class="form.recording_mode === 'local' ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-950/30 ring-1 ring-brand-500' : 'border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white/40 dark:bg-slate-900/40'"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <HardDrive class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" />
                    Local Recording
                  </span>
                  <div class="w-3.5 h-3.5 rounded-full border flex items-center justify-center" :class="form.recording_mode === 'local' ? 'border-brand-600 bg-brand-600' : 'border-slate-400'">
                    <span v-if="form.recording_mode === 'local'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                  Allows host to save MP4 recording files directly to their local desktop device.
                </p>
              </div>
            </div>
          </div>

          <!-- Attendance Tracking -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40">
            <div class="flex items-start gap-3">
              <Users2 class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Automated Attendance Tracking</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  Automatically sync participant join/leave timestamps, compute active minutes, and generate CSV attendance reports.
                </p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
              <input type="checkbox" v-model="form.attendance_tracking" class="sr-only peer">
              <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
            </label>
          </div>
        </div>
      </GlassCard>

      <!-- 4. Recurring Meeting Series (Optional) -->
      <GlassCard title="Recurring Schedule (Optional)">
        <div class="space-y-4">
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40">
            <div class="flex items-start gap-3">
              <Repeat class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" />
              <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Recurring Meeting Series</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  Schedule this session as a recurring series (e.g. weekly lectures, recurring lab sessions, or daily standups).
                </p>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
              <input type="checkbox" v-model="form.is_recurring" class="sr-only peer">
              <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
            </label>
          </div>

          <!-- Recurrence Options when enabled -->
          <div v-if="form.is_recurring" class="p-4 rounded-xl border border-brand-500/20 bg-brand-50/30 dark:bg-brand-950/20 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Frequency -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                  Recurrence Frequency
                </label>
                <select v-model="form.frequency" class="w-full glass-input cursor-pointer">
                  <option value="WEEKLY">Weekly</option>
                  <option value="DAILY">Daily</option>
                  <option value="MONTHLY">Monthly</option>
                </select>
              </div>

              <!-- Interval -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                  Repeat Every
                </label>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="form.interval"
                    type="number"
                    min="1"
                    max="12"
                    class="w-24 glass-input"
                  />
                  <span class="text-xs text-slate-500">
                    {{ form.frequency === 'DAILY' ? 'day(s)' : form.frequency === 'WEEKLY' ? 'week(s)' : 'month(s)' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Weekly Days Selector -->
            <div v-if="form.frequency === 'WEEKLY'">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                Repeat On Days
              </label>
              <div class="flex items-center gap-2 flex-wrap">
                <button
                  type="button"
                  v-for="d in daysOfWeek"
                  :key="d.code"
                  @click="toggleDay(d.code)"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
                  :class="form.byday.includes(d.code) ? 'bg-brand-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'"
                >
                  {{ d.label }}
                </button>
              </div>
            </div>

            <!-- Series End Criteria & Allocation Strategy -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-200/60 dark:border-slate-800">
              <!-- Occurrences Count -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                  End After (Occurrences)
                </label>
                <input
                  v-model.number="form.occurrence_count"
                  type="number"
                  min="2"
                  max="50"
                  class="w-full glass-input"
                  placeholder="5"
                />
              </div>

              <!-- Series Mode -->
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                  Host License Allocation Mode
                </label>
                <select v-model="form.series_mode" class="w-full glass-input cursor-pointer">
                  <option value="SINGLE_RESOURCE">Single Host License (Same Zoom Link)</option>
                  <option value="SPLIT_WHEN_NEEDED">Smart Re-allocation (Fallback if conflict)</option>
                  <option value="PER_OCCURRENCE">Dynamic Per-Occurrence</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </GlassCard>
      <!-- 5. Attendees & Invitees -->
      <GlassCard title="Attendees & Invitees">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
              Invitee Email Addresses (Comma-Separated)
            </label>
            <input
              v-model="form.invitees"
              type="text"
              class="w-full glass-input"
              placeholder="e.g. alice@institution.edu, prof.sharma@dept.org, external.guest@partner.com"
            />
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              Add attendees to automatically distribute calendar invites, Zoom join URLs, and track attendance telemetry.
            </p>
          </div>
        </div>
      </GlassCard>

      <!-- Live Conflict Preview -->
      <div v-if="conflictStatus" class="p-4 rounded-xl text-xs flex items-center gap-2.5" :class="conflictStatus.has_conflict ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20'">
        <CheckCircle2 v-if="!conflictStatus.has_conflict" class="w-4 h-4 shrink-0" />
        <AlertCircle v-else class="w-4 h-4 shrink-0" />
        <span>{{ conflictStatus.message }}</span>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <router-link
          to="/app/meetings"
          class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        >
          Cancel
        </router-link>

        <button
          type="submit"
          :disabled="submitting"
          class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-md shadow-brand-500/20 transition-all disabled:opacity-50 cursor-pointer"
        >
          <RefreshCw v-if="submitting" class="w-4 h-4 animate-spin" />
          <span>{{ submitting ? 'Reserving...' : (form.is_recurring ? 'Schedule Recurring Series' : 'Confirm Reservation') }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import GlassCard from '@/components/GlassCard.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { useToastStore } from '@/stores/toast';
import {
  Calendar as CalendarIcon,
  CheckCircle2,
  AlertCircle,
  UserCheck,
  ShieldAlert,
  PlayCircle,
  KeyRound,
  Cloud,
  HardDrive,
  Users2,
  Repeat,
  RefreshCw,
} from 'lucide-vue-next';

const router = useRouter();
const toast = useToastStore();

const submitting = ref(false);
const bookOnBehalf = ref(false);
const conflictStatus = ref(null);

const daysOfWeek = [
  { code: 'MO', label: 'Mon' },
  { code: 'TU', label: 'Tue' },
  { code: 'WE', label: 'Wed' },
  { code: 'TH', label: 'Thu' },
  { code: 'FR', label: 'Fri' },
  { code: 'SA', label: 'Sat' },
  { code: 'SU', label: 'Sun' },
];

const options = ref({
  templates: [],
  profiles: [],
  pools: [],
  can_book_on_behalf: false,
  users: [],
});

const form = ref({
  title: '',
  agenda: '',
  starts_at: '',
  ends_at: '',
  participant_count: 10,
  template_id: null,
  pool_id: null,
  owner_user_id: null,
  // Extended Flags
  waiting_room: true,
  join_before_host: false,
  jbh_time: 0,
  share_host_key: true,
  recording_mode: 'none',
  attendance_tracking: true,
  passcode: '',
  invitees: '',
  // Recurring
  is_recurring: false,
  frequency: 'WEEKLY',
  interval: 1,
  byday: ['MO', 'WE'],
  occurrence_count: 5,
  series_mode: 'SINGLE_RESOURCE',
});

const generatePasscode = () => {
  form.value.passcode = String(Math.floor(100000 + Math.random() * 900000));
};

const onTemplateSelected = () => {
  if (!form.value.template_id) return;
  const t = options.value.templates?.find((x) => x.id === form.value.template_id);
  if (!t) return;

  if (t.default_duration_minutes && form.value.starts_at) {
    const start = new Date(form.value.starts_at);
    const end = new Date(start.getTime() + t.default_duration_minutes * 60000);
    const pad = (n) => String(n).padStart(2, '0');
    form.value.ends_at = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}`;
  }
  if (t.default_pool_id) {
    form.value.pool_id = t.default_pool_id;
  }
  if (t.recording_mode) {
    form.value.recording_mode = t.recording_mode;
  }
  if (t.settings) {
    if (typeof t.settings.waiting_room !== 'undefined') {
      form.value.waiting_room = Boolean(t.settings.waiting_room);
    }
    if (typeof t.settings.join_before_host !== 'undefined') {
      form.value.join_before_host = Boolean(t.settings.join_before_host);
    }
  }
  checkConflicts();
};

const toggleDay = (code) => {
  const idx = form.value.byday.indexOf(code);
  if (idx > -1) {
    if (form.value.byday.length > 1) {
      form.value.byday.splice(idx, 1);
    }
  } else {
    form.value.byday.push(code);
  }
};

const setDefaultTimes = () => {
  const start = new Date();
  start.setHours(start.getHours() + 1, 0, 0, 0);
  const end = new Date(start);
  end.setMinutes(end.getMinutes() + 60);

  const formatInput = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  form.value.starts_at = formatInput(start);
  form.value.ends_at = formatInput(end);

  // Default day to current day
  const dayNames = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
  form.value.byday = [dayNames[start.getDay()]];
};

const loadOptions = async () => {
  try {
    const res = await axios.get('/spa/meetings/options');
    options.value = res.data;
  } catch (e) {
    console.error('Failed to load meeting options', e);
  }
};

const checkConflicts = async () => {
  if (!form.value.starts_at || !form.value.ends_at) return;
  try {
    const res = await axios.post('/meetings/preview-conflicts', {
      starts_at: form.value.starts_at,
      ends_at: form.value.ends_at,
      pool_id: form.value.pool_id,
      participant_count: form.value.participant_count,
    });
    conflictStatus.value = {
      has_conflict: !res.data.available,
      message: res.data.message || (res.data.available ? 'Pool resources available for this timeframe.' : 'Resource allocation conflict detected.'),
    };
  } catch (e) {
    // ignore
  }
};

const submitBooking = async () => {
  if (bookOnBehalf.value && !form.value.owner_user_id) {
    toast.error('Please select the user on whose behalf you are booking.');
    return;
  }

  try {
    submitting.value = true;
    const payload = {
      title: form.value.title,
      agenda: form.value.agenda,
      description: form.value.agenda,
      starts_at: form.value.starts_at,
      ends_at: form.value.ends_at,
      participant_count: form.value.participant_count,
      template_id: form.value.template_id,
      pool_id: form.value.pool_id,
      owner_user_id: bookOnBehalf.value ? form.value.owner_user_id : null,
      // Extended Flags
      waiting_room: form.value.waiting_room,
      join_before_host: form.value.join_before_host,
      jbh_time: form.value.jbh_time,
      share_host_key: form.value.share_host_key,
      recording_mode: form.value.recording_mode,
      attendance_tracking: form.value.attendance_tracking,
      passcode: form.value.passcode || null,
      invitees: form.value.invitees || null,
      // Recurring series settings
      is_recurring: form.value.is_recurring,
      frequency: form.value.frequency,
      interval: form.value.interval,
      byday: form.value.byday,
      occurrence_count: form.value.occurrence_count,
      series_mode: form.value.series_mode,
    };

    const res = await axios.post('/meetings', payload);
    toast.success(res.data?.message || 'Meeting scheduled successfully!');
    router.push('/app/meetings');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to schedule meeting.';
    toast.error(msg);
  } finally {
    submitting.value = false;
  }
};

onMounted(() => {
  setDefaultTimes();
  loadOptions();
});
</script>
