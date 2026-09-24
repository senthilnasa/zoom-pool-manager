import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';

// 1. Core Scheduling
import DashboardPage from '@/pages/DashboardPage.vue';
import MeetingsListPage from '@/pages/meetings/MeetingsListPage.vue';
import MeetingCreatePage from '@/pages/meetings/MeetingCreatePage.vue';
import CalendarPage from '@/pages/calendar/CalendarPage.vue';
import SeriesListPage from '@/pages/series/SeriesListPage.vue';

// 2. Governance, Workflows & Queue
import ApprovalsPage from '@/pages/governance/ApprovalsPage.vue';
import WorkflowsPage from '@/pages/workflows/WorkflowsPage.vue';
import QuotasPage from '@/pages/quotas/QuotasPage.vue';
import DelegationsPage from '@/pages/delegations/DelegationsPage.vue';
import WaitlistPage from '@/pages/workflow/WaitlistPage.vue';

// 3. Institutional Scheduling Config
import TemplatesPage from '@/pages/templates/TemplatesPage.vue';
import SecurityProfilesPage from '@/pages/templates/SecurityProfilesPage.vue';
import BlackoutPeriodsPage from '@/pages/scheduling/BlackoutPeriodsPage.vue';
import BookingPoliciesPage from '@/pages/scheduling/BookingPoliciesPage.vue';

// 4. Operations, Infrastructure & Audit
import HealthPage from '@/pages/operations/HealthPage.vue';
import AlertsPage from '@/pages/operations/AlertsPage.vue';
import BackupsPage from '@/pages/operations/BackupsPage.vue';
import EmergencyPage from '@/pages/operations/EmergencyPage.vue';
import AuditLogPage from '@/pages/audit/AuditLogPage.vue';
import PrivacyCompliancePage from '@/pages/operations/PrivacyCompliancePage.vue';

// 5. Media & Sync
import RecordingsPage from '@/pages/recordings/RecordingsPage.vue';
import AttendancePage from '@/pages/attendance/AttendancePage.vue';
import DriftPage from '@/pages/drift/DriftPage.vue';

// 6. Developer & Integrations
import ApiKeysPage from '@/pages/api/ApiKeysPage.vue';
import OutboundWebhooksPage from '@/pages/api/OutboundWebhooksPage.vue';
import InboundWebhooksPage from '@/pages/api/InboundWebhooksPage.vue';

// 7. Identity & Resource Management
import UsersPage from '@/pages/users/UsersPage.vue';
import DepartmentsPage from '@/pages/departments/DepartmentsPage.vue';
import PoolsPage from '@/pages/pools/PoolsPage.vue';

// 8. Settings & Communications
import GeneralSettingsPage from '@/pages/settings/GeneralSettingsPage.vue';
import ZoomSettingsPage from '@/pages/settings/ZoomSettingsPage.vue';
import SsoSettingsPage from '@/pages/settings/SsoSettingsPage.vue';
import DirectorySyncPage from '@/pages/settings/DirectorySyncPage.vue';
import ScheduledJobsPage from '@/pages/settings/ScheduledJobsPage.vue';
import MailSettingsPage from '@/pages/mail/MailSettingsPage.vue';
import EmailTemplatesPage from '@/pages/mail/EmailTemplatesPage.vue';
import UpdatesPage from '@/pages/settings/UpdatesPage.vue';
import NotificationsPage from '@/pages/notifications/NotificationsPage.vue';
import LegalViewPage from '@/pages/legal/LegalViewPage.vue';

const routes = [
    // Legacy / Top-Level Redirects
    { path: '/dashboard', redirect: '/app/dashboard' },
    { path: '/meetings', redirect: '/app/meetings' },
    { path: '/meetings/create', redirect: '/app/meetings/create' },
    { path: '/calendar', redirect: '/app/calendar' },
    { path: '/series', redirect: '/app/series' },
    { path: '/series/create', redirect: '/app/series' },
    { path: '/approvals', redirect: '/app/approvals' },
    { path: '/admin/workflows', redirect: '/app/workflows' },
    { path: '/admin/quotas', redirect: '/app/quotas' },
    { path: '/settings/delegations', redirect: '/app/delegations' },
    { path: '/recordings', redirect: '/app/recordings' },
    { path: '/attendance', redirect: '/app/attendance' },
    { path: '/admin/drift', redirect: '/app/drift' },
    { path: '/admin/health', redirect: '/app/operations/health' },
    { path: '/operations/health', redirect: '/app/operations/health' },
    { path: '/admin/alerts', redirect: '/app/operations/alerts' },
    { path: '/admin/backups', redirect: '/app/operations/backups' },
    { path: '/admin/emergency', redirect: '/app/operations/emergency' },
    { path: '/admin/api-keys', redirect: '/app/api/keys' },
    { path: '/admin/outbound-webhooks', redirect: '/app/api/outbound-webhooks' },
    { path: '/admin/webhooks', redirect: '/app/api/inbound-webhooks' },
    { path: '/admin/mail', redirect: '/app/mail/settings' },
    { path: '/admin/email-templates', redirect: '/app/mail/templates' },
    { path: '/settings/zoom', redirect: '/app/settings/zoom' },
    { path: '/admin/zoom', redirect: '/app/settings/zoom' },
    { path: '/settings/jobs', redirect: '/app/settings/jobs' },
    { path: '/system/updates', redirect: '/app/settings/updates' },
    { path: '/admin/system/updates', redirect: '/app/settings/updates' },
    { path: '/notifications', redirect: '/app/notifications' },

    // Primary SPA Application Layout Shell
    {
        path: '/app',
        component: AppLayout,
        children: [
            {
                path: '',
                redirect: '/app/dashboard',
            },
            // Core Scheduling
            {
                path: 'dashboard',
                name: 'dashboard',
                component: DashboardPage,
                meta: { title: 'Dashboard' },
            },
            {
                path: 'meetings',
                name: 'meetings.index',
                component: MeetingsListPage,
                meta: { title: 'Scheduled Meetings' },
            },
            {
                path: 'meetings/create',
                name: 'meetings.create',
                component: MeetingCreatePage,
                meta: { title: 'Book Meeting' },
            },
            {
                path: 'calendar',
                name: 'calendar',
                component: CalendarPage,
                meta: { title: 'Resource Calendar' },
            },
            {
                path: 'series',
                name: 'series.index',
                component: SeriesListPage,
                meta: { title: 'Recurring Series' },
            },

            // Governance & Workflows
            {
                path: 'approvals',
                name: 'approvals.index',
                component: ApprovalsPage,
                meta: { title: 'Workflow Approvals' },
            },
            {
                path: 'workflows',
                name: 'workflows.index',
                component: WorkflowsPage,
                meta: { title: 'Workflow Rules Builder' },
            },
            {
                path: 'quotas',
                name: 'quotas.index',
                component: QuotasPage,
                meta: { title: 'Quota Management' },
            },
            {
                path: 'delegations',
                name: 'delegations.index',
                component: DelegationsPage,
                meta: { title: 'Approval Delegations' },
            },
            {
                path: 'waitlist',
                name: 'waitlist.index',
                component: WaitlistPage,
                meta: { title: 'Meeting Waitlist Queue' },
            },

            // Institutional Scheduling Config
            {
                path: 'templates',
                name: 'templates.index',
                component: TemplatesPage,
                meta: { title: 'Meeting Templates' },
            },
            {
                path: 'security-profiles',
                name: 'security-profiles.index',
                component: SecurityProfilesPage,
                meta: { title: 'Security Profiles' },
            },
            {
                path: 'blackouts',
                name: 'blackouts.index',
                component: BlackoutPeriodsPage,
                meta: { title: 'Blackout Windows' },
            },
            {
                path: 'policies',
                name: 'policies.index',
                component: BookingPoliciesPage,
                meta: { title: 'Booking Policies' },
            },

            // Operations & Diagnostics
            {
                path: 'operations/health',
                name: 'operations.health',
                component: HealthPage,
                meta: { title: 'System Health & Diagnostics' },
            },
            {
                path: 'operations/alerts',
                name: 'operations.alerts',
                component: AlertsPage,
                meta: { title: 'Operational Alerts' },
            },
            {
                path: 'operations/backups',
                name: 'operations.backups',
                component: BackupsPage,
                meta: { title: 'System Backups' },
            },
            {
                path: 'operations/emergency',
                name: 'operations.emergency',
                component: EmergencyPage,
                meta: { title: 'Emergency IT Override' },
            },
            {
                path: 'audit',
                name: 'audit.index',
                component: AuditLogPage,
                meta: { title: 'Cryptographic Audit Trail' },
            },
            {
                path: 'privacy',
                name: 'privacy.index',
                component: PrivacyCompliancePage,
                meta: { title: 'Privacy & Data Retention' },
            },

            // Media & Sync
            {
                path: 'recordings',
                name: 'recordings.index',
                component: RecordingsPage,
                meta: { title: 'Cloud Recordings' },
            },
            {
                path: 'attendance',
                name: 'attendance.index',
                component: AttendancePage,
                meta: { title: 'Meeting Attendance' },
            },
            {
                path: 'drift',
                name: 'drift.index',
                component: DriftPage,
                meta: { title: 'State Drift Reconciliation' },
            },

            // Developer & Integrations
            {
                path: 'api/keys',
                name: 'api.keys',
                component: ApiKeysPage,
                meta: { title: 'API Keys & Tokens' },
            },
            {
                path: 'api/outbound-webhooks',
                name: 'api.outbound-webhooks',
                component: OutboundWebhooksPage,
                meta: { title: 'Outbound Webhooks' },
            },
            {
                path: 'api/inbound-webhooks',
                name: 'api.inbound-webhooks',
                component: InboundWebhooksPage,
                meta: { title: 'Zoom Webhook Intake' },
            },

            // Identity & Resources
            {
                path: 'pools',
                name: 'pools.index',
                component: PoolsPage,
                meta: { title: 'Zoom Resource Pools' },
            },
            {
                path: 'users',
                name: 'users.index',
                component: UsersPage,
                meta: { title: 'User Directory & Access Control' },
            },
            {
                path: 'roles',
                name: 'roles.index',
                redirect: '/app/users?tab=roles',
            },
            {
                path: 'departments',
                name: 'departments.index',
                component: DepartmentsPage,
                meta: { title: 'Department Directory' },
            },

            // Settings & Communications
            {
                path: 'settings/general',
                name: 'settings.general',
                component: GeneralSettingsPage,
                meta: { title: 'Institutional Settings' },
            },
            {
                path: 'settings/zoom',
                name: 'settings.zoom',
                component: ZoomSettingsPage,
                meta: { title: 'Zoom Configuration' },
            },
            {
                path: 'settings/sso',
                name: 'settings.sso',
                component: SsoSettingsPage,
                meta: { title: 'SSO & SAML Login Configuration' },
            },
            {
                path: 'settings/directory-sync',
                name: 'settings.directory-sync',
                component: DirectorySyncPage,
                meta: { title: 'Directory & AD Sync' },
            },
            {
                path: 'settings/jobs',
                name: 'settings.jobs',
                component: ScheduledJobsPage,
                meta: { title: 'Scheduled Jobs & Automation Cadence' },
            },
            {
                path: 'mail/settings',
                name: 'mail.settings',
                component: MailSettingsPage,
                meta: { title: 'Mail Server Configuration' },
            },
            {
                path: 'mail/templates',
                name: 'mail.templates',
                component: EmailTemplatesPage,
                meta: { title: 'Email Templates' },
            },
            {
                path: 'settings/updates',
                name: 'settings.updates',
                component: UpdatesPage,
                meta: { title: 'System Updates & Releases' },
            },
            {
                path: 'notifications',
                name: 'notifications.index',
                component: NotificationsPage,
                meta: { title: 'Notifications Center' },
            },
            {
                path: 'privacy-policy',
                name: 'legal.privacy',
                component: LegalViewPage,
                meta: { title: 'Privacy Policy' },
            },
            {
                path: 'terms-of-service',
                name: 'legal.terms',
                component: LegalViewPage,
                meta: { title: 'Terms of Service' },
            },
        ],
    },
    // Fallback for any unknown /app/* route
    {
        path: '/:pathMatch(.*)*',
        redirect: '/app/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.afterEach((to) => {
    const orgName = window.__ZPM__?.branding?.org_name || 'Zoom Pool Manager';
    document.title = to.meta?.title
        ? `${to.meta.title} — ${orgName}`
        : orgName;
});

export default router;
