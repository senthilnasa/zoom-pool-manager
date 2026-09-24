<template>
  <aside
    class="w-64 shrink-0 border-r border-slate-200/60 dark:border-slate-800/60 bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl flex flex-col transition-all duration-300 z-30"
  >
    <!-- Brand Header -->
    <div class="h-16 flex items-center justify-between px-5 border-b border-slate-200/60 dark:border-slate-800/60">
      <router-link to="/app/dashboard" class="flex items-center gap-3 group">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 flex items-center justify-center text-white font-black shadow-md shadow-brand-500/20 group-hover:scale-105 transition-transform">
          Z
        </div>
        <div>
          <div class="font-bold text-sm leading-tight text-slate-900 dark:text-white flex items-center gap-1.5">
            <span>Zoom Pool</span>
            <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-brand-500/10 text-brand-600 dark:text-brand-400">Enterprise</span>
          </div>
          <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">
            Manager
          </div>
        </div>
      </router-link>
    </div>

    <!-- Nav Links (Scrollable) -->
    <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">
      <!-- 1. Core Scheduling -->
      <div>
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Core Scheduling
        </div>
        <nav class="space-y-1">
          <router-link
            to="/app/dashboard"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/dashboard') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <LayoutDashboard class="w-4 h-4 shrink-0" />
            <span>Dashboard</span>
          </router-link>

          <router-link
            to="/app/meetings"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/meetings') && !isActive('/app/meetings/create') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Video class="w-4 h-4 shrink-0" />
            <span>Meetings</span>
          </router-link>

          <router-link
            to="/app/meetings/create"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/meetings/create') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <CalendarPlus class="w-4 h-4 shrink-0" />
            <span>Book Meeting</span>
          </router-link>

          <router-link
            to="/app/calendar"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/calendar') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Calendar class="w-4 h-4 shrink-0" />
            <span>Resource Calendar</span>
          </router-link>

          <router-link
            to="/app/series"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/series') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Repeat class="w-4 h-4 shrink-0" />
            <span>Recurring Series</span>
          </router-link>
        </nav>
      </div>

      <!-- 2. Governance, Workflows & Queue -->
      <div v-if="canViewGovernance">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Governance & Workflows
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.can('meeting.approve') || authStore.isAdmin || authStore.isDeptAdmin"
            to="/app/approvals"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/approvals') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <ShieldCheck class="w-4 h-4 shrink-0" />
            <span>Approvals Queue</span>
          </router-link>

          <router-link
            v-if="authStore.can('workflow.manage') || authStore.isAdmin"
            to="/app/workflows"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/workflows') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <GitMerge class="w-4 h-4 shrink-0" />
            <span>Rules Builder</span>
          </router-link>

          <router-link
            v-if="authStore.can('quota.manage') || authStore.isAdmin || authStore.isDeptAdmin"
            to="/app/quotas"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/quotas') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <BarChart3 class="w-4 h-4 shrink-0" />
            <span>Quota Policies</span>
          </router-link>

          <router-link
            v-if="authStore.can('meeting.approve') || authStore.isAdmin"
            to="/app/delegations"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/delegations') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <UserCheck class="w-4 h-4 shrink-0" />
            <span>Delegations</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin || authStore.isDeptAdmin"
            to="/app/waitlist"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/waitlist') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <ListOrdered class="w-4 h-4 shrink-0" />
            <span>Waitlist Queue</span>
          </router-link>
        </nav>
      </div>

      <!-- 3. Scheduling Rules & Archetypes -->
      <div v-if="canViewArchetypes">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Archetypes & Constraints
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.can('template.manage') || authStore.isAdmin"
            to="/app/templates"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/templates') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <FileCode class="w-4 h-4 shrink-0" />
            <span>Meeting Templates</span>
          </router-link>

          <router-link
            v-if="authStore.can('security_profile.manage') || authStore.isAdmin"
            to="/app/security-profiles"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/security-profiles') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <ShieldAlert class="w-4 h-4 shrink-0" />
            <span>Security Profiles</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/blackouts"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/blackouts') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <CalendarX class="w-4 h-4 shrink-0" />
            <span>Blackout Windows</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/policies"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/policies') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Clock class="w-4 h-4 shrink-0" />
            <span>Booking Policies</span>
          </router-link>
        </nav>
      </div>

      <!-- 4. Operations, Infrastructure & Audit -->
      <div v-if="canViewOperations">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Operations & Audit
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.can('health.view') || authStore.isAdmin"
            to="/app/operations/health"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/operations/health') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Activity class="w-4 h-4 shrink-0" />
            <span>Health & Diagnostics</span>
          </router-link>

          <router-link
            v-if="authStore.can('health.view') || authStore.isAdmin"
            to="/app/operations/alerts"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/operations/alerts') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <AlertTriangle class="w-4 h-4 shrink-0 text-amber-500" />
            <span>Operational Alerts</span>
          </router-link>

          <router-link
            v-if="authStore.can('backup.manage') || authStore.isAdmin"
            to="/app/operations/backups"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/operations/backups') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Database class="w-4 h-4 shrink-0" />
            <span>System Backups</span>
          </router-link>

          <router-link
            v-if="authStore.can('audit.view') || authStore.isAdmin"
            to="/app/audit"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/audit') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <ScrollText class="w-4 h-4 shrink-0 text-indigo-500" />
            <span>Audit Trail (SHA-256)</span>
          </router-link>

          <router-link
            v-if="authStore.can('privacy.manage') || authStore.isAdmin"
            to="/app/privacy"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/privacy') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <UserX class="w-4 h-4 shrink-0 text-emerald-500" />
            <span>Privacy & Retention</span>
          </router-link>

          <router-link
            v-if="authStore.can('emergency.use') || authStore.isAdmin"
            to="/app/operations/emergency"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/operations/emergency') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold text-rose-600 dark:text-rose-400' : 'text-rose-600/80 dark:text-rose-400/80 hover:bg-rose-50 dark:hover:bg-rose-950/30'"
          >
            <AlertTriangle class="w-4 h-4 shrink-0 text-rose-500" />
            <span>Emergency IT Panel</span>
          </router-link>
        </nav>
      </div>

      <!-- 5. Media & Sync -->
      <div v-if="canViewMediaSync">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Media & Reconciliation
        </div>
        <nav class="space-y-1">
          <router-link
            to="/app/recordings"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/recordings') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Disc class="w-4 h-4 shrink-0" />
            <span>Cloud Recordings</span>
          </router-link>

          <router-link
            to="/app/attendance"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/attendance') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <UserCheck class="w-4 h-4 shrink-0" />
            <span>Meeting Attendance</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/drift"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/drift') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <GitCompare class="w-4 h-4 shrink-0" />
            <span>Drift Reconciliation</span>
          </router-link>
        </nav>
      </div>

      <!-- 6. Developer & Integrations -->
      <div v-if="canViewDeveloper">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Developer & Integrations
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.can('api.manage') || authStore.isAdmin"
            to="/app/api/keys"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/api/keys') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Key class="w-4 h-4 shrink-0" />
            <span>API Keys & Tokens</span>
          </router-link>

          <router-link
            v-if="authStore.can('api.manage') || authStore.isAdmin"
            to="/app/api/outbound-webhooks"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/api/outbound-webhooks') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Webhook class="w-4 h-4 shrink-0" />
            <span>Outbound Webhooks</span>
          </router-link>

          <router-link
            v-if="authStore.can('api.manage') || authStore.isAdmin"
            to="/app/api/inbound-webhooks"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/api/inbound-webhooks') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Radio class="w-4 h-4 shrink-0" />
            <span>Zoom Intake Logs</span>
          </router-link>

          <a
            v-if="authStore.can('api.manage') || authStore.isAdmin"
            href="/docs/api"
            target="_blank"
            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200 transition-all group"
            title="Open Interactive OpenAPI Documentation (Admin only)"
          >
            <div class="flex items-center gap-3">
              <BookOpen class="w-4 h-4 shrink-0 text-brand-600 dark:text-brand-400" />
              <span>Interactive API Docs</span>
            </div>
            <ExternalLink class="w-3.5 h-3.5 opacity-40 group-hover:opacity-100 transition-opacity" />
          </a>
        </nav>
      </div>

      <!-- 7. Identity & Zoom Infrastructure -->
      <div v-if="canViewIdentity">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          Identity & Resources
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.can('pool.manage') || authStore.can('resource.view') || authStore.isAdmin"
            to="/app/pools"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/pools') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Layers class="w-4 h-4 shrink-0" />
            <span>Resource Pools</span>
          </router-link>

          <router-link
            v-if="authStore.can('user.view') || authStore.isAdmin || authStore.isDeptAdmin"
            to="/app/users"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/users') && route.query.tab !== 'roles' ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Users class="w-4 h-4 shrink-0" />
            <span>User Directory</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin || authStore.can('user.manage')"
            to="/app/users?tab=roles"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/users') && route.query.tab === 'roles' ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <ShieldCheck class="w-4 h-4 shrink-0" />
            <span>Roles & Permissions</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/departments"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/departments') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Building2 class="w-4 h-4 shrink-0" />
            <span>Department Directory</span>
          </router-link>
        </nav>
      </div>

      <!-- 8. Settings & Communications -->
      <div v-if="canViewSettings">
        <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
          System Administration
        </div>
        <nav class="space-y-1">
          <router-link
            v-if="authStore.isAdmin"
            to="/app/settings/general"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/general') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Settings class="w-4 h-4 shrink-0" />
            <span>General Settings</span>
          </router-link>

          <router-link
            v-if="authStore.can('zoom.manage') || authStore.isAdmin"
            to="/app/settings/zoom"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/zoom') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Sliders class="w-4 h-4 shrink-0" />
            <span>Zoom Config</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/settings/sso"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/sso') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <KeyRound class="w-4 h-4 shrink-0" />
            <span>SSO & SAML Login</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/app/settings/directory-sync"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/directory-sync') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <FolderSync class="w-4 h-4 shrink-0" />
            <span>Directory & AD Sync</span>
          </router-link>

          <router-link
            v-if="authStore.can('settings.manage') || authStore.isAdmin"
            to="/app/settings/jobs"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/jobs') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Clock class="w-4 h-4 shrink-0" />
            <span>Scheduled Jobs</span>
          </router-link>

          <router-link
            v-if="authStore.can('settings.manage') || authStore.isAdmin"
            to="/app/mail/settings"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/mail/settings') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Mail class="w-4 h-4 shrink-0" />
            <span>Mail Server</span>
          </router-link>

          <router-link
            v-if="authStore.can('settings.manage') || authStore.isAdmin"
            to="/app/mail/templates"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/mail/templates') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <FileText class="w-4 h-4 shrink-0" />
            <span>Email Templates</span>
          </router-link>

          <router-link
            to="/app/notifications"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/notifications') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <Bell class="w-4 h-4 shrink-0" />
            <span>Notifications</span>
          </router-link>

          <router-link
            v-if="authStore.can('settings.manage') || authStore.isAdmin"
            to="/app/settings/updates"
            class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all"
            :class="isActive('/app/settings/updates') ? 'bg-brand-500/10 text-brand-600 dark:text-brand-400 font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
          >
            <DownloadCloud class="w-4 h-4 shrink-0" />
            <span>System Updates</span>
          </router-link>
        </nav>
      </div>
    </div>

    <!-- Attribution & Footer -->
    <div class="border-t border-slate-200/60 dark:border-slate-800/60">
      <!-- Attribution -->
      <div class="px-4 py-2 text-center border-b border-slate-100 dark:border-slate-800/40">
        <a
          href="https://github.com/senthilnasa"
          target="_blank"
          rel="noopener noreferrer"
          class="text-[10px] text-slate-400 hover:text-brand-500 dark:hover:text-brand-400 transition inline-flex items-center gap-1"
        >
          <span>Made with</span>
          <span class="text-rose-500 animate-pulse">❤️</span>
          <span>by <strong class="font-semibold underline">Senthil Nasa</strong></span>
        </a>
      </div>

      <!-- User / Session Footer -->
      <div class="p-3.5 flex items-center justify-between">
        <div class="flex items-center gap-2.5 overflow-hidden">
          <div class="w-8 h-8 rounded-xl bg-slate-200 dark:bg-slate-800 flex items-center justify-center font-bold text-xs text-slate-700 dark:text-slate-300 shrink-0">
            {{ authStore.user?.name?.charAt(0) || 'U' }}
          </div>
          <div class="truncate">
            <div class="text-xs font-semibold text-slate-900 dark:text-white truncate">
              {{ authStore.user?.name || 'User' }}
            </div>
            <div class="text-[10px] text-slate-400 truncate">
              {{ authStore.user?.roles?.[0] || authStore.user?.email || 'Authenticated' }}
            </div>
          </div>
        </div>
        <button
          @click="authStore.logout"
          class="p-1.5 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
          title="Logout"
        >
          <LogOut class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
  LayoutDashboard,
  Video,
  CalendarPlus,
  Calendar,
  Repeat,
  ShieldCheck,
  GitMerge,
  BarChart3,
  UserCheck,
  ListOrdered,
  FileCode,
  ShieldAlert,
  CalendarX,
  Clock,
  Activity,
  Database,
  AlertTriangle,
  ScrollText,
  UserX,
  Disc,
  GitCompare,
  Key,
  Webhook,
  Radio,
  Layers,
  Users,
  Building2,
  Sliders,
  KeyRound,
  FolderSync,
  Mail,
  FileText,
  Bell,
  DownloadCloud,
  BookOpen,
  ExternalLink,
  Settings,
  LogOut,
} from 'lucide-vue-next';

const route = useRoute();
const authStore = useAuthStore();

const isActive = (path) => route.path === path;

const canViewGovernance = computed(() => {
  return (
    authStore.isAdmin ||
    authStore.isDeptAdmin ||
    authStore.can('meeting.approve') ||
    authStore.can('workflow.manage') ||
    authStore.can('quota.manage')
  );
});

const canViewArchetypes = computed(() => {
  return (
    authStore.isAdmin ||
    authStore.can('template.manage') ||
    authStore.can('security_profile.manage')
  );
});

const canViewOperations = computed(() => {
  return (
    authStore.isAdmin ||
    authStore.can('health.view') ||
    authStore.can('backup.manage') ||
    authStore.can('audit.view') ||
    authStore.can('privacy.manage') ||
    authStore.can('emergency.use')
  );
});

const canViewMediaSync = computed(() => true);

const canViewDeveloper = computed(() => {
  return authStore.isAdmin || authStore.can('api.manage');
});

const canViewIdentity = computed(() => {
  return (
    authStore.isAdmin ||
    authStore.isDeptAdmin ||
    authStore.can('user.view') ||
    authStore.can('pool.manage') ||
    authStore.can('resource.view')
  );
});

const canViewSettings = computed(() => {
  return (
    authStore.isAdmin ||
    authStore.can('zoom.manage') ||
    authStore.can('settings.manage')
  );
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.4);
}
</style>
