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
import UserProfilePage from '@/pages/users/UserProfilePage.vue';
import DepartmentsPage from '@/pages/departments/DepartmentsPage.vue';
import PoolsPage from '@/pages/pools/PoolsPage.vue';
import ZoomUsageReportPage from '@/pages/analytics/ZoomUsageReportPage.vue';

// 8. Settings & Communications
import GeneralSettingsPage from '@/pages/settings/GeneralSettingsPage.vue';
import ZoomSettingsPage from '@/pages/settings/ZoomSettingsPage.vue';
import ZohoDeskSettingsPage from '@/pages/settings/ZohoDeskSettingsPage.vue';
import SsoSettingsPage from '@/pages/settings/SsoSettingsPage.vue';
import DirectorySyncPage from '@/pages/settings/DirectorySyncPage.vue';
import ScheduledJobsPage from '@/pages/settings/ScheduledJobsPage.vue';
import MailSettingsPage from '@/pages/mail/MailSettingsPage.vue';
import EmailTemplatesPage from '@/pages/mail/EmailTemplatesPage.vue';
import UpdatesPage from '@/pages/settings/UpdatesPage.vue';
import NotificationsPage from '@/pages/notifications/NotificationsPage.vue';
import LegalViewPage from '@/pages/legal/LegalViewPage.vue';
import DocumentationPage from '@/pages/docs/DocumentationPage.vue';
import AccessDeniedPage from '@/pages/errors/AccessDeniedPage.vue';
import { useAuthStore } from '@/stores/auth';

const routes = [
    // Documentation Redirects
    { path: '/documentation', redirect: '/app/documentation' },
    { path: '/docs', redirect: '/app/documentation' },
    // Legacy / Top-Level Redirects
    { path: '/dashboard', redirect: '/app/dashboard' },
    { path: '/meetings', redirect: '/app/meetings' },
    { path: '/meetings/create', redirect: '/app/meetings/create' },
    { path: '/calendar', redirect: '/app/calendar' },
    { path: '/series', redirect: '/app/series' },
    { path: '/series/create', redirect: '/app/series' },
    { path: '/approvals', redirect: '/app/approvals' },
    { path: '/workflows', redirect: '/app/workflows' },
    { path: '/admin/workflows', redirect: '/app/workflows' },
    { path: '/templates', redirect: '/app/templates' },
    { path: '/admin/templates', redirect: '/app/templates' },
    { path: '/security-profiles', redirect: '/app/security-profiles' },
    { path: '/admin/security-profiles', redirect: '/app/security-profiles' },
    { path: '/admin/quotas', redirect: '/app/quotas' },
    { path: '/quotas', redirect: '/app/quotas' },
    { path: '/settings/delegations', redirect: '/app/delegations' },
    { path: '/delegations', redirect: '/app/delegations' },
    { path: '/waitlist', redirect: '/app/waitlist' },
    { path: '/policies', redirect: '/app/policies' },
    { path: '/admin/policies', redirect: '/app/policies' },
    { path: '/blackouts', redirect: '/app/blackouts' },
    { path: '/admin/blackouts', redirect: '/app/blackouts' },
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
    { path: '/webhooks', redirect: '/app/api/inbound-webhooks' },
    { path: '/debug/webhooks', redirect: '/app/api/inbound-webhooks' },
    { path: '/admin/mail', redirect: '/app/mail/settings' },
    { path: '/admin/email-templates', redirect: '/app/mail/templates' },
    { path: '/settings/zoom', redirect: '/app/settings/zoom' },
    { path: '/admin/zoom', redirect: '/app/settings/zoom' },
    { path: '/settings/jobs', redirect: '/app/settings/jobs' },
    { path: '/system/updates', redirect: '/app/settings/updates' },
    { path: '/admin/system/updates', redirect: '/app/settings/updates' },
    { path: '/settings/sso', redirect: '/app/settings/sso' },
    { path: '/admin/settings/sso', redirect: '/app/settings/sso' },
    { path: '/admin/sso', redirect: '/app/settings/sso' },
    { path: '/admin/settings', redirect: '/app/settings/general' },
    { path: '/settings', redirect: '/app/settings/general' },
    { path: '/settings/general', redirect: '/app/settings/general' },
    { path: '/settings/directory-sync', redirect: '/app/settings/directory-sync' },
    { path: '/admin/directory-sync', redirect: '/app/settings/directory-sync' },
    { path: '/access-denied', redirect: '/app/access-denied' },
    { path: '/notifications', redirect: '/app/notifications' },
    { path: '/settings/zoho-desk', redirect: '/app/settings/zoho-desk' },
    { path: '/admin/settings/zoho-desk', redirect: '/app/settings/zoho-desk' },
    { path: '/admin/zoho-desk', redirect: '/app/settings/zoho-desk' },
    { path: '/reports/zoom-usage', redirect: '/app/reports/zoom-usage' },
    { path: '/pools/usage', redirect: '/app/reports/zoom-usage' },

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
                meta: { title: 'Workflow Approvals', deptAdminOrAdmin: true, permission: 'meeting.approve' },
            },
            {
                path: 'workflows',
                name: 'workflows.index',
                component: WorkflowsPage,
                meta: { title: 'Workflow Rules Builder', permission: 'workflow.manage' },
            },
            {
                path: 'quotas',
                name: 'quotas.index',
                component: QuotasPage,
                meta: { title: 'Quota Management', deptAdminOrAdmin: true, permission: 'quota.manage' },
            },
            {
                path: 'delegations',
                name: 'delegations.index',
                component: DelegationsPage,
                meta: { title: 'Approval Delegations', deptAdminOrAdmin: true, permission: 'meeting.approve' },
            },
            {
                path: 'waitlist',
                name: 'waitlist.index',
                component: WaitlistPage,
                meta: { title: 'Meeting Waitlist Queue', deptAdminOrAdmin: true },
            },

            // Institutional Scheduling Config
            {
                path: 'templates',
                name: 'templates.index',
                component: TemplatesPage,
                meta: { title: 'Meeting Templates', permission: 'template.manage' },
            },
            {
                path: 'security-profiles',
                name: 'security-profiles.index',
                component: SecurityProfilesPage,
                meta: { title: 'Security Profiles', permission: 'security_profile.manage' },
            },
            {
                path: 'blackouts',
                name: 'blackouts.index',
                component: BlackoutPeriodsPage,
                meta: { title: 'Blackout Windows', adminOnly: true, permission: 'blackout.manage' },
            },
            {
                path: 'policies',
                name: 'policies.index',
                component: BookingPoliciesPage,
                meta: { title: 'Booking Policies', adminOnly: true, permission: 'policy.manage' },
            },

            // Operations & Diagnostics
            {
                path: 'operations/health',
                name: 'operations.health',
                component: HealthPage,
                meta: { title: 'System Health & Diagnostics', permission: 'health.view' },
            },
            {
                path: 'operations/alerts',
                name: 'operations.alerts',
                component: AlertsPage,
                meta: { title: 'Operational Alerts', permission: 'health.view' },
            },
            {
                path: 'operations/backups',
                name: 'operations.backups',
                component: BackupsPage,
                meta: { title: 'System Backups', permission: 'backup.manage' },
            },
            {
                path: 'operations/emergency',
                name: 'operations.emergency',
                component: EmergencyPage,
                meta: { title: 'Emergency IT Override', permission: 'emergency.use' },
            },
            {
                path: 'audit',
                name: 'audit.index',
                component: AuditLogPage,
                meta: { title: 'Cryptographic Audit Trail', permission: 'audit.view' },
            },
            {
                path: 'privacy',
                name: 'privacy.index',
                component: PrivacyCompliancePage,
                meta: { title: 'Privacy & Data Retention', permission: 'privacy.manage' },
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
                meta: { title: 'State Drift Reconciliation', adminOnly: true },
            },

            // Developer & Integrations
            {
                path: 'api/keys',
                name: 'api.keys',
                component: ApiKeysPage,
                meta: { title: 'API Keys & Tokens', permission: 'api.manage' },
            },
            {
                path: 'api/outbound-webhooks',
                name: 'api.outbound-webhooks',
                component: OutboundWebhooksPage,
                meta: { title: 'Outbound Webhooks', permission: 'api.manage' },
            },
            {
                path: 'api/inbound-webhooks',
                name: 'api.inbound-webhooks',
                component: InboundWebhooksPage,
                meta: { title: 'Zoom Webhook Intake & Live Debug', permission: 'api.manage' },
            },
            {
                path: 'webhooks',
                redirect: '/app/api/inbound-webhooks',
            },
            {
                path: 'debug/webhooks',
                redirect: '/app/api/inbound-webhooks',
            },

            // Identity & Resources
            {
                path: 'pools',
                name: 'pools.index',
                component: PoolsPage,
                meta: { title: 'Zoom Resource Pools', permission: ['pool.manage', 'resource.view'] },
            },
            {
                path: 'reports/zoom-usage',
                name: 'reports.zoom-usage',
                component: ZoomUsageReportPage,
                meta: { title: 'Zoom Account Usage & Concurrency', permission: ['pool.manage', 'resource.view'] },
            },
            {
                path: 'pools/usage',
                redirect: '/app/reports/zoom-usage',
            },
            {
                path: 'users',
                name: 'users.index',
                component: UsersPage,
                meta: { title: 'User Directory & Access Control', deptAdminOrAdmin: true, permission: 'user.view' },
            },
            {
                path: 'users/:id/profile',
                name: 'users.profile',
                component: UserProfilePage,
                meta: { title: 'User Profile & Summary' },
            },
            {
                path: 'users/:id',
                redirect: (to) => `/app/users/${to.params.id}/profile`,
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
                meta: { title: 'Department Directory', adminOnly: true },
            },

            // Settings & Communications
            {
                path: 'settings/general',
                name: 'settings.general',
                component: GeneralSettingsPage,
                meta: { title: 'Institutional Settings', adminOnly: true },
            },
            {
                path: 'settings/zoom',
                name: 'settings.zoom',
                component: ZoomSettingsPage,
                meta: { title: 'Zoom Configuration', permission: 'zoom.manage' },
            },
            {
                path: 'settings/zoho-desk',
                name: 'settings.zoho-desk',
                component: ZohoDeskSettingsPage,
                meta: { title: 'Zoho Desk Integration', adminOnly: true },
            },
            {
                path: 'settings/sso',
                name: 'settings.sso',
                component: SsoSettingsPage,
                meta: { title: 'SSO & SAML Login Configuration', adminOnly: true },
            },
            {
                path: 'settings/directory-sync',
                name: 'settings.directory-sync',
                component: DirectorySyncPage,
                meta: { title: 'Directory & AD Sync', adminOnly: true },
            },
            {
                path: 'settings/jobs',
                name: 'settings.jobs',
                component: ScheduledJobsPage,
                meta: { title: 'Scheduled Jobs & Automation Cadence', permission: 'settings.manage' },
            },
            {
                path: 'mail/settings',
                name: 'mail.settings',
                component: MailSettingsPage,
                meta: { title: 'Mail Server Configuration', adminOnly: true, permission: 'settings.manage' },
            },
            {
                path: 'mail/templates',
                name: 'mail.templates',
                component: EmailTemplatesPage,
                meta: { title: 'Email Templates', adminOnly: true, permission: 'settings.manage' },
            },
            {
                path: 'settings/updates',
                name: 'settings.updates',
                component: UpdatesPage,
                meta: { title: 'System Updates & Releases', adminOnly: true, permission: 'settings.manage' },
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
            {
                path: 'documentation',
                name: 'documentation',
                component: DocumentationPage,
                meta: { title: 'Documentation & Guides' },
            },
            {
                path: 'docs',
                redirect: '/app/documentation',
            },
            {
                path: 'access-denied',
                name: 'access-denied',
                component: AccessDeniedPage,
                meta: { title: 'Access Denied' },
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

function hasRoutePermission(authStore, permission) {
    if (!permission) return true;
    if (authStore.isAdmin) return true;
    if (Array.isArray(permission)) {
        return permission.some((perm) => authStore.can(perm));
    }
    return authStore.can(permission);
}

router.beforeEach((to, from, next) => {
    // Whitelisted routes that never require authorization checks
    if (
        to.name === 'access-denied' ||
        to.path === '/app/access-denied' ||
        to.name === 'legal.privacy' ||
        to.name === 'legal.terms' ||
        to.name === 'documentation' ||
        to.path.startsWith('/app/documentation')
    ) {
        return next();
    }

    const authStore = useAuthStore();

    // If not authenticated, redirect to login
    if (!authStore.isAuthenticated) {
        window.location.href = '/login';
        return;
    }

    // Admins bypass all module restrictions
    if (authStore.isAdmin) {
        return next();
    }

    // 1. Admin-only route guard
    if (to.meta?.adminOnly) {
        return next({
            name: 'access-denied',
            query: { from: to.fullPath },
        });
    }

    // 2. Department Admin or Admin check
    if (to.meta?.deptAdminOrAdmin) {
        const isDeptAdmin = authStore.isDeptAdmin;
        const hasPerm = to.meta.permission ? hasRoutePermission(authStore, to.meta.permission) : false;
        if (!isDeptAdmin && !hasPerm) {
            return next({
                name: 'access-denied',
                query: { from: to.fullPath },
            });
        }
    }

    // 3. Granular permission check
    if (to.meta?.permission) {
        if (!hasRoutePermission(authStore, to.meta.permission)) {
            return next({
                name: 'access-denied',
                query: { from: to.fullPath },
            });
        }
    }

    return next();
});

router.afterEach((to) => {
    const orgName = window.__ZPM__?.branding?.org_name || 'Zoom Pool Manager';
    document.title = to.meta?.title
        ? `${to.meta.title} - ${orgName}`
        : orgName;
});

export default router;
