<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <div class="p-2 rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
            <CalendarIcon class="w-6 h-6" />
          </div>
          <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
            Resource Timeline Calendar
          </h1>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Interactive visual scheduling grid for Zoom host accounts, live sessions, safety buffers, and instant slot booking.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Range Hours Toggle -->
        <button
          @click="toggleFullDay"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition shadow-sm cursor-pointer"
          :title="fullDayHours ? 'Switch to Work Hours (08:00 - 20:00)' : 'Switch to Full 24 Hours'"
        >
          <Clock class="w-3.5 h-3.5 text-slate-400" />
          <span>{{ fullDayHours ? '24-Hour View' : 'Work Hours (08-20h)' }}</span>
        </button>

        <!-- View Mode: Day vs 3-Day vs Week -->
        <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-0.5 border border-slate-200/60 dark:border-slate-700/60 text-xs font-semibold">
          <button
            v-for="mode in ['day', '3day', 'week']"
            :key="mode"
            @click="viewMode = mode"
            class="px-3 py-1.5 rounded-lg capitalize transition cursor-pointer"
            :class="viewMode === mode ? 'bg-white dark:bg-slate-700 text-brand-600 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'"
          >
            {{ mode === '3day' ? '3 Days' : mode }}
          </button>
        </div>

        <button
          @click="fetchCalendar"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition shadow-sm cursor-pointer"
          title="Refresh Calendar"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>

        <button
          @click="openQuickBookModal()"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Quick Book Meeting</span>
        </button>
      </div>
    </div>

    <!-- Date Navigation & Filter Bar -->
    <div class="glass-card p-4 rounded-2xl flex flex-wrap items-center justify-between gap-4 border border-slate-200/60 dark:border-slate-800/60">
      <!-- Date Controls -->
      <div class="flex items-center gap-2">
        <button
          @click="shiftDate(-stepDays)"
          class="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
          title="Previous"
        >
          <ChevronLeft class="w-4 h-4" />
        </button>
        <button
          @click="goToToday"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
        >
          Today
        </button>
        <button
          @click="shiftDate(stepDays)"
          class="p-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
          title="Next"
        >
          <ChevronRight class="w-4 h-4" />
        </button>

        <!-- Current Date Heading & Native Picker -->
        <div class="relative flex items-center ml-2">
          <div class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
            <span>{{ dateRangeLabel }}</span>
          </div>
          <input
            type="date"
            :value="selectedDateString"
            @change="onDateInputChange"
            class="ml-2 text-xs py-1 px-2 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 focus:outline-none cursor-pointer"
          />
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Search Filter -->
        <div class="relative">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search meetings..."
            class="text-xs rounded-xl pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-brand-500 w-44"
          />
        </div>

        <!-- Pool Filter -->
        <select
          v-model="selectedPoolId"
          class="text-xs rounded-xl px-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-brand-500 cursor-pointer"
        >
          <option :value="null">All Resource Pools</option>
          <option v-for="p in pools" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>

        <!-- Status Filter -->
        <select
          v-model="selectedStatus"
          class="text-xs rounded-xl px-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-brand-500 cursor-pointer"
        >
          <option value="">All Statuses</option>
          <option value="started">Live (Started)</option>
          <option value="scheduled">Scheduled</option>
          <option value="pending_approval">Pending Approval</option>
          <option value="completed">Completed</option>
        </select>
      </div>
    </div>

    <!-- Visual Legend Bar -->
    <div class="flex items-center justify-between text-xs px-2 text-slate-500 dark:text-slate-400 flex-wrap gap-3">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded bg-emerald-500"></span>
          <span>Live Session (Started)</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded bg-brand-500"></span>
          <span>Scheduled</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded bg-amber-500"></span>
          <span>Pending Approval</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded bg-slate-400"></span>
          <span>Completed</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded border border-dashed border-slate-400 dark:border-slate-600 bg-slate-200/60 dark:bg-slate-800/80"></span>
          <span>Safety Buffer ({{ bufferMinutes }}m Cooldown)</span>
        </div>
      </div>
      <div class="text-[11px] text-slate-400 italic">
        Tip: Click any empty slot on a resource row to instantly schedule a meeting.
      </div>
    </div>

    <!-- Interactive Timeline Grid -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
      <div v-if="loading && !resources.length" class="p-16 text-center text-slate-400">
        <RefreshCw class="w-8 h-8 mx-auto mb-3 animate-spin text-brand-500" />
        <p class="text-sm font-medium">Loading resource timeline schedule...</p>
      </div>

      <div v-else-if="!filteredResources.length" class="p-16 text-center text-slate-400 space-y-2">
        <CalendarIcon class="w-10 h-10 mx-auto opacity-40 text-slate-400" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No matching Zoom host accounts found.</p>
        <p class="text-xs text-slate-400">Try adjusting your pool or status filter above.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <div class="min-w-[960px]">
          <!-- Grid Header (Hours) -->
          <div class="flex border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 text-[11px] font-bold text-slate-500 select-none">
            <!-- Resource Column Header -->
            <div class="w-64 p-3 shrink-0 border-r border-slate-200 dark:border-slate-800">
              Host Account & Pool
            </div>

            <!-- Hour Columns -->
            <div class="flex-1 flex relative">
              <div
                v-for="hour in visibleHours"
                :key="hour"
                class="flex-1 p-2 text-center border-r border-slate-200/60 dark:border-slate-800/60 truncate"
              >
                {{ formatHour(hour) }}
              </div>
            </div>
          </div>

          <!-- Resource Rows -->
          <div class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <div
              v-for="res in filteredResources"
              :key="res.id"
              class="flex hover:bg-slate-50/40 dark:hover:bg-slate-800/20 transition group"
            >
              <!-- Resource Info Card -->
              <div class="w-64 p-3 shrink-0 border-r border-slate-200 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40 flex flex-col justify-center">
                <div class="font-bold text-xs text-slate-900 dark:text-white flex items-center gap-1.5 truncate">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="res.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"
                  ></span>
                  <span class="truncate" :title="res.name || res.zoom_user?.email">
                    {{ res.name || res.zoom_user?.email || 'Host Account #' + res.id }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5 truncate">
                  <span>{{ res.participant_capacity || 300 }} seats</span>
                  <span>•</span>
                  <span class="truncate font-medium text-slate-500 dark:text-slate-400">
                    {{ getPoolName(res) }}
                  </span>
                </div>
              </div>

              <!-- Resource Timeline Track -->
              <div
                class="flex-1 relative h-16 bg-white/20 dark:bg-slate-900/20 cursor-pointer overflow-hidden"
                @click="onTrackClick($event, res)"
                title="Click anywhere to book this host at selected time"
              >
                <!-- Vertical Hour Guides -->
                <div class="absolute inset-0 flex pointer-events-none">
                  <div
                    v-for="hour in visibleHours"
                    :key="'guide-' + hour"
                    class="flex-1 border-r border-slate-200/40 dark:border-slate-800/40 h-full"
                  ></div>
                </div>

                <!-- Current Time Indicator (if viewing today) -->
                <div
                  v-if="currentTimePosition !== null"
                  class="absolute top-0 bottom-0 w-0.5 bg-rose-500 z-20 pointer-events-none shadow-sm"
                  :style="{ left: currentTimePosition + '%' }"
                >
                  <div class="w-2 h-2 rounded-full bg-rose-500 -ml-[3px] -mt-1 shadow"></div>
                </div>

                <!-- Meeting Blocks for this Resource -->
                <div
                  v-for="m in getResourceMeetingsForDate(res.id)"
                  :key="m.public_id || m.id"
                  class="absolute top-1.5 bottom-1.5 rounded-xl px-2.5 py-1 z-10 text-white shadow-md transition transform hover:scale-[1.01] hover:z-30 cursor-pointer flex flex-col justify-center overflow-hidden"
                  :style="getMeetingStyle(m)"
                  :class="getMeetingClass(m)"
                  @click.stop="openMeetingDetails(m)"
                  :title="`${m.title} (${formatTime(m.starts_at)} - ${formatTime(m.ends_at)})`"
                >
                  <div class="text-[11px] font-bold truncate flex items-center gap-1">
                    <span v-if="m.status === 'started'" class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    <span class="truncate">{{ m.title }}</span>
                  </div>
                  <div class="text-[9px] opacity-90 truncate flex items-center gap-1 font-mono">
                    <span>{{ formatTime(m.starts_at) }}–{{ formatTime(m.ends_at) }}</span>
                    <span>•</span>
                    <span class="truncate">{{ m.owner?.name || 'Assigned' }}</span>
                  </div>
                </div>

                <!-- Buffer Blocks -->
                <div
                  v-for="m in getResourceMeetingsForDate(res.id)"
                  :key="'buf-' + (m.public_id || m.id)"
                  class="absolute top-2 bottom-2 rounded-lg z-0 border border-dashed border-slate-300 dark:border-slate-700 bg-slate-200/50 dark:bg-slate-800/60 pointer-events-none"
                  :style="getBufferStyle(m)"
                  :title="`Safety Buffer (${bufferMinutes} mins cooldown)`"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Book Modal -->
    <Modal
      :show="showQuickModal"
      @close="showQuickModal = false"
      max-width="2xl"
      title="Quick Book Meeting Slot"
    >
      <form @submit.prevent="submitQuickBook" class="space-y-4">
        <!-- Title -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            Meeting Title <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="bookingForm.title"
            type="text"
            required
            placeholder="e.g. Advanced Algorithms Lecture / Department Sync"
            class="w-full text-xs rounded-xl px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-1 focus:ring-brand-500 focus:outline-none"
          />
        </div>

        <!-- Date & Times -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Date</label>
            <input
              v-model="bookingForm.date"
              type="date"
              required
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Start Time</label>
            <input
              v-model="bookingForm.startTime"
              type="time"
              required
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">End Time</label>
            <input
              v-model="bookingForm.endTime"
              type="time"
              required
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>
        </div>

        <!-- Pool & Department -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Resource Pool</label>
            <select
              v-model="bookingForm.pool_id"
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            >
              <option :value="null">Automatic Pool Optimization</option>
              <option v-for="p in pools" :key="p.id" :value="p.id">
                {{ p.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Department</label>
            <select
              v-model="bookingForm.department_id"
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            >
              <option :value="null">Default User Department</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">
                {{ d.name }} ({{ d.code }})
              </option>
            </select>
          </div>
        </div>

        <!-- Participant Count & Meeting Type -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Expected Participants</label>
            <input
              v-model.number="bookingForm.participant_count"
              type="number"
              min="1"
              max="1000"
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Meeting Classification</label>
            <select
              v-model="bookingForm.meeting_type"
              class="w-full text-xs rounded-xl px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
            >
              <option value="class">Lecture / Class</option>
              <option value="meeting">General Meeting</option>
              <option value="webinar">Webinar / Broadcast</option>
              <option value="exam">Proctored Assessment</option>
              <option value="office_hours">Office Hours</option>
            </select>
          </div>
        </div>

        <!-- Description / Agenda -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Agenda / Description (Optional)</label>
          <textarea
            v-model="bookingForm.description"
            rows="2"
            placeholder="Key discussion topics or session notes..."
            class="w-full text-xs rounded-xl p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500"
          ></textarea>
        </div>

        <!-- Room Security & Recording Flags -->
        <div class="space-y-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
          <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Security & Automation Flags</div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <!-- Waiting Room -->
            <label class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span class="text-slate-700 dark:text-slate-300 font-medium">Waiting Room</span>
              <input type="checkbox" v-model="bookingForm.waiting_room" class="rounded text-brand-600 focus:ring-brand-500">
            </label>

            <!-- Auto-Start / Join Before Host -->
            <label class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span class="text-slate-700 dark:text-slate-300 font-medium">Auto-Start (No Host)</span>
              <input type="checkbox" v-model="bookingForm.join_before_host" class="rounded text-brand-600 focus:ring-brand-500">
            </label>

            <!-- Share Host Key -->
            <label class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span class="text-slate-700 dark:text-slate-300 font-medium">Share Host Key PIN</span>
              <input type="checkbox" v-model="bookingForm.share_host_key" class="rounded text-brand-600 focus:ring-brand-500">
            </label>

            <!-- Attendance Tracking -->
            <label class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 cursor-pointer">
              <span class="text-slate-700 dark:text-slate-300 font-medium">Attendance Tracking</span>
              <input type="checkbox" v-model="bookingForm.attendance_tracking" class="rounded text-emerald-600 focus:ring-emerald-500">
            </label>
          </div>

          <!-- Recording Mode Selector -->
          <div class="pt-1 flex items-center justify-between gap-3 text-xs">
            <span class="text-slate-700 dark:text-slate-300 font-medium">Recording:</span>
            <select
              v-model="bookingForm.recording_mode"
              class="text-xs rounded-lg px-2.5 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
            >
              <option value="none">None (Off)</option>
              <option value="cloud">Cloud Recording</option>
              <option value="local">Local Recording</option>
            </select>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="showQuickModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="bookingSubmitting"
            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-md shadow-brand-500/20 transition cursor-pointer disabled:opacity-50"
          >
            <RefreshCw v-if="bookingSubmitting" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ bookingSubmitting ? 'Reserving...' : 'Confirm & Reserve Slot' }}</span>
          </button>
        </div>
      </form>
    </Modal>

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
          v-if="selectedMeeting.host_key"
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
              {{ selectedMeeting.host_key }}
            </span>
            <button
              type="button"
              @click="copyHostKey(selectedMeeting.host_key)"
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

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <!-- 1-Click .ICS Download -->
            <a
              :href="`/spa/meetings/${selectedMeeting.public_id}/ics`"
              download
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
              title="Add to Google, Outlook, or Apple Calendar (.ics)"
            >
              <Download class="w-3.5 h-3.5" />
              <span>Download .ICS</span>
            </a>

            <!-- Copy Full Invitation -->
            <button
              @click="copyFullInvitation(selectedMeeting)"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
              title="Copy formatted invitation to clipboard"
            >
              <ClipboardCopy class="w-3.5 h-3.5" />
              <span>Copy Invitation</span>
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

            <!-- End Meeting Early (Release License) -->
            <button
              v-if="['started', 'scheduled'].includes(selectedMeeting.status)"
              @click="endMeetingEarly(selectedMeeting)"
              :disabled="endingEarly"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/20 transition cursor-pointer"
              title="Release Zoom host license back to pool early"
            >
              <Square class="w-3.5 h-3.5" />
              <span>{{ endingEarly ? 'Releasing...' : 'End Early & Free Host' }}</span>
            </button>

            <!-- Direct Join -->
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import Modal from '@/components/Modal.vue';
import {
  Calendar as CalendarIcon,
  RefreshCw,
  Plus,
  Clock,
  ChevronLeft,
  ChevronRight,
  Search,
  Download,
  Copy,
  ClipboardCopy,
  ExternalLink,
  Square,
  KeyRound,
  ShieldAlert,
  PlayCircle,
  Cloud,
  Users2,
} from 'lucide-vue-next';

const toast = useToastStore();

const loading = ref(false);
const resources = ref([]);
const meetings = ref([]);
const pools = ref([]);
const departments = ref([]);
const bufferMinutes = ref(15);

// Calendar Navigation
const selectedDate = ref(new Date());
const viewMode = ref('day'); // 'day', '3day', 'week'
const fullDayHours = ref(false); // false: 08-20h, true: 00-24h
const searchQuery = ref('');
const selectedPoolId = ref(null);
const selectedStatus = ref('');

// Modals
const showQuickModal = ref(false);
const showDetailsModal = ref(false);
const selectedMeeting = ref(null);
const bookingSubmitting = ref(false);
const endingEarly = ref(false);
const copied = ref(false);

const bookingForm = reactive({
  title: '',
  date: '',
  startTime: '10:00',
  endTime: '11:00',
  pool_id: null,
  department_id: null,
  participant_count: 10,
  meeting_type: 'class',
  description: '',
  waiting_room: true,
  join_before_host: false,
  jbh_time: 0,
  share_host_key: true,
  recording_mode: 'none',
  attendance_tracking: true,
});

// Visible Hours Array
const visibleHours = computed(() => {
  if (fullDayHours.value) {
    return Array.from({ length: 24 }, (_, i) => i);
  }
  // 08:00 to 20:00
  return Array.from({ length: 13 }, (_, i) => i + 8);
});

const startHour = computed(() => visibleHours.value[0]);
const endHour = computed(() => visibleHours.value[visibleHours.value.length - 1] + 1);
const totalHours = computed(() => endHour.value - startHour.value);

const stepDays = computed(() => {
  if (viewMode.value === 'week') return 7;
  if (viewMode.value === '3day') return 3;
  return 1;
});

const selectedDateString = computed(() => {
  const d = selectedDate.value;
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
});

const dateRangeLabel = computed(() => {
  const d = selectedDate.value;
  if (viewMode.value === 'day') {
    return d.toLocaleDateString(undefined, {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }
  const end = new Date(d);
  end.setDate(d.getDate() + (stepDays.value - 1));
  return `${d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

const toggleFullDay = () => {
  fullDayHours.value = !fullDayHours.value;
};

const goToToday = () => {
  selectedDate.value = new Date();
  fetchCalendar();
};

const shiftDate = (days) => {
  const next = new Date(selectedDate.value);
  next.setDate(next.getDate() + days);
  selectedDate.value = next;
  fetchCalendar();
};

const onDateInputChange = (e) => {
  const val = e.target.value;
  if (val) {
    selectedDate.value = new Date(val + 'T00:00:00');
    fetchCalendar();
  }
};

const fetchCalendar = async () => {
  try {
    loading.value = true;
    const start = new Date(selectedDate.value);
    start.setHours(0, 0, 0, 0);

    const end = new Date(start);
    end.setDate(start.getDate() + stepDays.value + 1);

    const params = {
      start: start.toISOString(),
      end: end.toISOString(),
    };
    if (selectedPoolId.value) params.pool_id = selectedPoolId.value;

    const res = await axios.get('/spa/calendar', { params });
    resources.value = res.data.resources || [];
    meetings.value = res.data.meetings || [];
    pools.value = res.data.pools || [];
    departments.value = res.data.departments || [];
    if (res.data.buffer_minutes) {
      bufferMinutes.value = res.data.buffer_minutes;
    }
  } catch (err) {
    console.error('Failed to load calendar', err);
    toast.error('Failed to load resource schedule.');
  } finally {
    loading.value = false;
  }
};

const filteredResources = computed(() => {
  let list = resources.value;
  if (selectedPoolId.value) {
    list = list.filter((r) => r.pools && r.pools.some((p) => p.id === selectedPoolId.value));
  }
  return list;
});

const getPoolName = (res) => {
  if (res.pools && res.pools.length) {
    return res.pools.map((p) => p.name).join(', ');
  }
  return 'Default Pool';
};

const getResourceMeetingsForDate = (resourceId) => {
  const curDateStr = selectedDateString.value;

  return meetings.value.filter((m) => {
    if (m.zoom_resource_id !== resourceId) return false;

    // Check date match
    const mDate = new Date(m.starts_at);
    const year = mDate.getFullYear();
    const month = String(mDate.getMonth() + 1).padStart(2, '0');
    const day = String(mDate.getDate()).padStart(2, '0');
    const mDateStr = `${year}-${month}-${day}`;

    if (mDateStr !== curDateStr) return false;

    // Filter by status
    if (selectedStatus.value && m.status !== selectedStatus.value) {
      return false;
    }

    // Filter by search query
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const matchTitle = m.title?.toLowerCase().includes(q);
      const matchOwner = m.owner?.name?.toLowerCase().includes(q);
      const matchId = m.public_id?.toLowerCase().includes(q);
      if (!matchTitle && !matchOwner && !matchId) return false;
    }

    return true;
  });
};

// Compute meeting block coordinates
const getMeetingStyle = (m) => {
  const start = new Date(m.starts_at);
  const end = new Date(m.ends_at);

  const startMinutes = start.getHours() * 60 + start.getMinutes();
  const endMinutes = end.getHours() * 60 + end.getMinutes();

  const rangeStartMinutes = startHour.value * 60;
  const totalRangeMinutes = totalHours.value * 60;

  const left = Math.max(0, ((startMinutes - rangeStartMinutes) / totalRangeMinutes) * 100);
  const width = Math.min(100 - left, Math.max(2, ((endMinutes - startMinutes) / totalRangeMinutes) * 100));

  return {
    left: `${left}%`,
    width: `${width}%`,
  };
};

const getMeetingClass = (m) => {
  switch (m.status) {
    case 'started':
      return 'bg-gradient-to-r from-emerald-600 to-teal-600 ring-2 ring-emerald-400';
    case 'pending_approval':
      return 'bg-gradient-to-r from-amber-500 to-orange-500';
    case 'completed':
      return 'bg-gradient-to-r from-slate-500 to-slate-600 opacity-75';
    default:
      return 'bg-gradient-to-r from-brand-600 to-indigo-600';
  }
};

const getBufferStyle = (m) => {
  const end = new Date(m.ends_at);
  const endMinutes = end.getHours() * 60 + end.getMinutes();

  const rangeStartMinutes = startHour.value * 60;
  const totalRangeMinutes = totalHours.value * 60;

  const left = Math.max(0, ((endMinutes - rangeStartMinutes) / totalRangeMinutes) * 100);
  const width = Math.min(100 - left, (bufferMinutes.value / totalRangeMinutes) * 100);

  return {
    left: `${left}%`,
    width: `${width}%`,
  };
};

// Current time red line
const currentTimePosition = computed(() => {
  const now = new Date();
  const curStr = selectedDateString.value;
  const nowStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

  if (curStr !== nowStr) return null;

  const nowMinutes = now.getHours() * 60 + now.getMinutes();
  const rangeStartMinutes = startHour.value * 60;
  const totalRangeMinutes = totalHours.value * 60;

  if (nowMinutes < rangeStartMinutes || nowMinutes > rangeStartMinutes + totalRangeMinutes) {
    return null;
  }

  return ((nowMinutes - rangeStartMinutes) / totalRangeMinutes) * 100;
});

// Click track to quick book
const onTrackClick = (event, res) => {
  const rect = event.currentTarget.getBoundingClientRect();
  const clickX = event.clientX - rect.left;
  const pct = clickX / rect.width;

  const totalMins = totalHours.value * 60;
  const clickedMins = startHour.value * 60 + totalMins * pct;

  const hour = Math.floor(clickedMins / 60);
  const minute = Math.floor((clickedMins % 60) / 15) * 15; // snap to 15m

  const startHourStr = String(hour).padStart(2, '0');
  const startMinStr = String(minute).padStart(2, '0');

  const endHour = hour + 1;
  const endHourStr = String(endHour).padStart(2, '0');

  openQuickBookModal({
    resource: res,
    date: selectedDateString.value,
    startTime: `${startHourStr}:${startMinStr}`,
    endTime: `${endHourStr}:${startMinStr}`,
  });
};

const openQuickBookModal = (prefill = {}) => {
  bookingForm.title = '';
  bookingForm.date = prefill.date || selectedDateString.value;
  bookingForm.startTime = prefill.startTime || '10:00';
  bookingForm.endTime = prefill.endTime || '11:00';
  bookingForm.pool_id = prefill.resource?.pools?.[0]?.id || selectedPoolId.value || null;
  bookingForm.department_id = null;
  bookingForm.participant_count = 10;
  bookingForm.meeting_type = 'class';
  bookingForm.description = '';
  bookingForm.waiting_room = true;
  bookingForm.join_before_host = false;
  bookingForm.jbh_time = 0;
  bookingForm.share_host_key = true;
  bookingForm.recording_mode = 'none';
  bookingForm.attendance_tracking = true;
  showQuickModal.value = true;
};

const submitQuickBook = async () => {
  try {
    bookingSubmitting.value = true;

    const startsAt = `${bookingForm.date}T${bookingForm.startTime}:00`;
    const endsAt = `${bookingForm.date}T${bookingForm.endTime}:00`;

    const payload = {
      title: bookingForm.title,
      starts_at: startsAt,
      ends_at: endsAt,
      pool_id: bookingForm.pool_id,
      department_id: bookingForm.department_id,
      participant_count: bookingForm.participant_count,
      meeting_type: bookingForm.meeting_type,
      description: bookingForm.description,
      waiting_room: bookingForm.waiting_room,
      join_before_host: bookingForm.join_before_host,
      jbh_time: bookingForm.jbh_time,
      share_host_key: bookingForm.share_host_key,
      recording_mode: bookingForm.recording_mode,
      attendance_tracking: bookingForm.attendance_tracking,
    };

    const res = await axios.post('/spa/calendar/quick-book', payload);
    if (res.data.success) {
      toast.success(res.data.message || 'Meeting slot reserved successfully!');
      showQuickModal.value = false;
      await fetchCalendar();
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to book slot. Please review scheduling conflicts.';
    toast.error(msg);
  } finally {
    bookingSubmitting.value = false;
  }
};

const openMeetingDetails = (m) => {
  selectedMeeting.value = m;
  copied.value = false;
  showDetailsModal.value = true;
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
    await fetchCalendar();
    if (selectedMeeting.value && selectedMeeting.value.public_id === meeting.public_id) {
      selectedMeeting.value.ends_at = res.data.meeting?.ends_at || selectedMeeting.value.ends_at;
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to extend meeting.';
    toast.error(msg);
  }
};

const copyJoinLink = async (url) => {
  try {
    await navigator.clipboard.writeText(url);
    copied.value = true;
    toast.success('Join link copied to clipboard!');
    setTimeout(() => {
      copied.value = false;
    }, 2500);
  } catch {
    toast.error('Could not copy link.');
  }
};

const endMeetingEarly = async (m) => {
  if (!confirm(`Are you sure you want to end "${m.title}" early? This will immediately release the pooled Zoom host license.`)) {
    return;
  }
  try {
    endingEarly.value = true;
    const res = await axios.post(`/spa/meetings/${m.public_id}/end-early`);
    if (res.data.success) {
      toast.success(res.data.message);
      showDetailsModal.value = false;
      await fetchCalendar();
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to end meeting early.');
  } finally {
    endingEarly.value = false;
  }
};

const formatHour = (hour) => {
  const h = hour % 24;
  return `${String(h).padStart(2, '0')}:00`;
};

const formatTime = (val) => {
  if (!val) return '';
  return new Date(val).toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatDateTime = (val) => {
  if (!val) return '';
  return new Date(val).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'started':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
    case 'pending_approval':
      return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
    case 'completed':
      return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20';
    default:
      return 'bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20';
  }
};

onMounted(() => {
  fetchCalendar();
});
</script>
