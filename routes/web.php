<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Installer\InstallerController;
use App\Http\Controllers\LegalController;
use App\Http\Middleware\EnsureInstalled;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Root redirect
Route::get('/', function () {
    if (! app(EnsureInstalled::class)->isInstalled()) {
        return redirect()->route('installer.welcome');
    }

    return redirect()->route('dashboard');
})->name('home');

// Legal Policies & Compliance (Publicly accessible)
Route::get('/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('legal.privacy');
Route::get('/terms-of-service', [LegalController::class, 'termsOfService'])->name('legal.terms');
Route::get('/spa/branding', [LegalController::class, 'branding'])->name('spa.branding');
Route::get('/spa/legal/{type}', [LegalController::class, 'apiDocument'])->name('spa.legal.doc');

// Authentication & Session Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::get('/login/local', [LoginController::class, 'showLocalLoginForm'])->name('login.local');
Route::get('/login/totp', [LoginController::class, 'showTotpForm'])->name('auth.totp');
Route::post('/login/totp', [LoginController::class, 'verifyTotp'])->name('auth.totp.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/logout/other-devices', [LoginController::class, 'logoutOtherDevices'])
    ->name('logout.other-devices')
    ->middleware('auth');

// Single Sign-On (Google, Microsoft Entra ID, SAML 2.0)
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/{provider}/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/{provider}/callback', [SsoController::class, 'callback'])->name('sso.callback');
    Route::get('/saml/{provider}/metadata', [SsoController::class, 'samlMetadata'])->name('saml.metadata');
    Route::post('/saml/{provider}/acs', [SsoController::class, 'samlAcs'])
        ->name('saml.acs')
        ->withoutMiddleware([ValidateCsrfToken::class]);
});

use App\Http\Controllers\Api\SpaAdminController;
use App\Http\Controllers\Api\SpaAuthController;
use App\Http\Controllers\Api\SpaDashboardController;
use App\Http\Controllers\Api\SpaDataController;
use App\Http\Controllers\Api\SpaGeneralSettingsController;
use App\Http\Controllers\Api\SpaIdentityController;
use App\Http\Controllers\Api\SpaJobSettingsController;
use App\Http\Controllers\Api\SpaRoleController;
use App\Http\Controllers\Api\SpaSearchController;
use App\Http\Controllers\Api\ZoomSettingsController;
use App\Http\Controllers\ApiAdminController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\CloudRecordingController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DriftConflictController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\HostControlController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingSeriesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\QuotaController;
use App\Http\Controllers\SpaController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkflowRuleController;

// Inbound Zoom Webhook Intake (SPEC Part F6)
Route::post('/webhooks/zoom/{public_id?}', [WebhookController::class, 'handle'])
    ->name('webhooks.zoom')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::post('/api/webhooks/zoom/{public_id?}', [WebhookController::class, 'handle'])
    ->name('api.webhooks.zoom')
    ->withoutMiddleware([ValidateCsrfToken::class]);

// SPA CSRF Token & Session Keepalive (accessible without auth for refresh and keepalive)
Route::get('/spa/csrf-token', [SpaAuthController::class, 'csrfToken'])->name('spa.csrf-token');

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    // Vue 3 SPA Application Shell
    Route::get('/app/{any?}', [SpaController::class, 'index'])->where('any', '.*')->name('spa');

    // SPA JSON Endpoints
    Route::prefix('spa')->name('spa.')->group(function () {
        Route::get('/auth/me', [SpaAuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/theme', [SpaAuthController::class, 'updateTheme'])->name('auth.theme');
        Route::get('/dashboard/stats', [SpaDashboardController::class, 'stats'])->name('dashboard.stats');
        Route::get('/meetings', [SpaDataController::class, 'meetings'])->name('meetings');
        Route::get('/meetings/export', [SpaDataController::class, 'exportMeetings'])->name('meetings.export');
        Route::get('/meetings/options', [SpaDataController::class, 'meetingOptions'])->name('meetings.options');
        Route::get('/recordings', [SpaDataController::class, 'recordings'])->name('recordings');
        Route::post('/recordings', [SpaDataController::class, 'storeRecording'])->name('recordings.store');
        Route::post('/recordings/sync', [SpaDataController::class, 'syncRecordings'])->name('recordings.sync');
        Route::post('/recordings/sync-from-zoom', [SpaDataController::class, 'syncRecordings'])->name('recordings.sync-from-zoom');
        Route::get('/attendance', [SpaDataController::class, 'attendance'])->name('attendance');
        Route::get('/attendance/{publicId}', [SpaDataController::class, 'attendanceDetails'])->name('attendance.details');
        Route::post('/attendance/sync', [SpaDataController::class, 'syncAttendance'])->name('attendance.sync');
        Route::get('/attendance/{publicId}/export', [SpaDataController::class, 'exportAttendanceCsv'])->name('attendance.export');
        Route::get('/approvals', [SpaDataController::class, 'approvals'])->name('approvals');
        Route::get('/series', [SpaDataController::class, 'series'])->name('series');
        Route::get('/calendar', [SpaDataController::class, 'calendar'])->name('calendar');
        Route::post('/calendar/quick-book', [SpaDataController::class, 'quickBook'])->name('calendar.quick-book');
        Route::get('/meetings/{publicId}/ics', [SpaDataController::class, 'icsDownload'])->name('meetings.ics-download');
        Route::put('/meetings/{publicId}', [SpaDataController::class, 'updateMeeting'])->name('meetings.update');
        Route::post('/meetings/{publicId}/extend', [SpaDataController::class, 'extendMeeting'])->name('meetings.extend');
        Route::post('/meetings/{publicId}/invitees', [SpaDataController::class, 'addInvitee'])->name('meetings.invitees.store');
        Route::post('/meetings/{publicId}/end-early', [SpaDataController::class, 'endEarly'])->name('meetings.end-early');
        Route::delete('/recordings/{id}', [SpaDataController::class, 'deleteRecording'])->name('recordings.delete');

        // General Institutional & Platform Settings
        Route::get('/settings/general', [SpaGeneralSettingsController::class, 'show'])->name('settings.general.show');
        Route::put('/settings/general', [SpaGeneralSettingsController::class, 'update'])->name('settings.general.update');
        Route::post('/settings/general/logo', [SpaGeneralSettingsController::class, 'uploadLogo'])->name('settings.general.logo.upload');
        Route::delete('/settings/general/logo', [SpaGeneralSettingsController::class, 'deleteLogo'])->name('settings.general.logo.delete');

        // Zoom Settings in SPA
        Route::get('/settings/zoom', [ZoomSettingsController::class, 'show'])->name('settings.zoom.show');
        Route::post('/settings/zoom', [ZoomSettingsController::class, 'update'])->name('settings.zoom.update');
        Route::post('/settings/zoom/test', [ZoomSettingsController::class, 'test'])->name('settings.zoom.test');

        // SSO & SAML Identity Providers (Google, Microsoft, SAML 2.0)
        Route::get('/settings/identity-providers', [SpaIdentityController::class, 'identityProviders'])->name('settings.idp.index');
        Route::post('/settings/identity-providers', [SpaIdentityController::class, 'storeIdentityProvider'])->name('settings.idp.store');
        Route::get('/settings/identity-providers/{publicId}', [SpaIdentityController::class, 'showIdentityProvider'])->name('settings.idp.show');
        Route::put('/settings/identity-providers/{publicId}', [SpaIdentityController::class, 'updateIdentityProvider'])->name('settings.idp.update');
        Route::delete('/settings/identity-providers/{publicId}', [SpaIdentityController::class, 'deleteIdentityProvider'])->name('settings.idp.delete');
        Route::post('/settings/identity-providers/{publicId}/test', [SpaIdentityController::class, 'testIdentityProvider'])->name('settings.idp.test');
        Route::get('/settings/identity-providers/{publicId}/sp-metadata', [SpaIdentityController::class, 'spMetadata'])->name('settings.idp.sp-metadata');

        // Directory & Active Directory Synchronization (Microsoft Entra ID, Google Workspace, LDAP)
        Route::get('/settings/directory-sync', [SpaIdentityController::class, 'directorySyncConfigs'])->name('settings.directory.index');
        Route::post('/settings/directory-sync', [SpaIdentityController::class, 'storeDirectorySyncConfig'])->name('settings.directory.store');
        Route::get('/settings/directory-sync/{publicId}', [SpaIdentityController::class, 'showDirectorySyncConfig'])->name('settings.directory.show');
        Route::put('/settings/directory-sync/{publicId}', [SpaIdentityController::class, 'updateDirectorySyncConfig'])->name('settings.directory.update');
        Route::delete('/settings/directory-sync/{publicId}', [SpaIdentityController::class, 'deleteDirectorySyncConfig'])->name('settings.directory.delete');
        Route::post('/settings/directory-sync/{publicId}/sync-now', [SpaIdentityController::class, 'syncDirectoryNow'])->name('settings.directory.sync-now');
        Route::post('/settings/directory-sync/{publicId}/test', [SpaIdentityController::class, 'testDirectoryConnection'])->name('settings.directory.test');

        // Background Scheduled Jobs & Automation Cadence
        Route::get('/settings/jobs', [SpaJobSettingsController::class, 'index'])->name('settings.jobs.index');
        Route::put('/settings/jobs/{key}', [SpaJobSettingsController::class, 'update'])->name('settings.jobs.update');
        Route::post('/settings/jobs/{key}/run', [SpaJobSettingsController::class, 'run'])->name('settings.jobs.run');

        // System updates
        Route::get('/settings/updates', [SystemUpdateController::class, 'index'])->name('settings.updates.index');
        Route::post('/settings/updates/check', [SystemUpdateController::class, 'check'])->name('settings.updates.check');
        Route::post('/settings/updates/apply', [SystemUpdateController::class, 'apply'])->name('settings.updates.apply');

        // Pools & Zoom Resources
        Route::get('/pools', [SpaAdminController::class, 'pools'])->name('pools');
        Route::post('/pools', [SpaAdminController::class, 'storePool'])->name('pools.store');
        Route::post('/pools/{id}/toggle', [SpaAdminController::class, 'togglePool'])->name('pools.toggle');
        Route::get('/resources', [SpaAdminController::class, 'resources'])->name('resources');
        Route::post('/resources/{id}/toggle', [SpaAdminController::class, 'toggleResource'])->name('resources.toggle');
        Route::post('/resources/sync-from-zoom', [SpaAdminController::class, 'syncZoomUsers'])->name('resources.sync-from-zoom');
        Route::post('/resources/{id}/pools', [SpaAdminController::class, 'assignResourcePools'])->name('resources.assign-pools');

        // Users & Departments
        Route::get('/users/search', [SpaAdminController::class, 'searchUsers'])->name('users.search');
        Route::get('/users', [SpaAdminController::class, 'users'])->name('users');
        Route::post('/users', [SpaAdminController::class, 'storeUser'])->name('users.store');
        Route::post('/users/{id}/toggle', [SpaAdminController::class, 'toggleUser'])->name('users.toggle');
        Route::get('/users/{id}/permissions', [SpaRoleController::class, 'userPermissions'])->name('users.permissions');
        Route::get('/departments', [SpaAdminController::class, 'departments'])->name('departments');
        Route::post('/departments', [SpaAdminController::class, 'storeDepartment'])->name('departments.store');
        Route::delete('/departments/{id}', [SpaAdminController::class, 'deleteDepartment'])->name('departments.delete');

        // Role Management & Permissions
        Route::get('/roles', [SpaRoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/permissions-matrix', [SpaRoleController::class, 'permissionsMatrix'])->name('roles.matrix');
        Route::post('/roles', [SpaRoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{id}', [SpaRoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [SpaRoleController::class, 'destroy'])->name('roles.destroy');

        // Global Search
        Route::get('/search', [SpaSearchController::class, 'search'])->name('search');

        // Meeting Templates & Security Profiles
        Route::get('/templates', [SpaAdminController::class, 'templates'])->name('templates');
        Route::post('/templates', [SpaAdminController::class, 'storeTemplate'])->name('templates.store');
        Route::get('/security-profiles', [SpaAdminController::class, 'securityProfiles'])->name('security-profiles');
        Route::post('/security-profiles', [SpaAdminController::class, 'storeSecurityProfile'])->name('security-profiles.store');

        // Blackouts & Policies
        Route::get('/blackouts', [SpaAdminController::class, 'blackouts'])->name('blackouts');
        Route::post('/blackouts', [SpaAdminController::class, 'storeBlackout'])->name('blackouts.store');
        Route::delete('/blackouts/{id}', [SpaAdminController::class, 'deleteBlackout'])->name('blackouts.delete');
        Route::get('/policies', [SpaAdminController::class, 'policies'])->name('policies');
        Route::post('/policies', [SpaAdminController::class, 'storePolicy'])->name('policies.store');

        // Waitlist
        Route::get('/waitlist', [SpaAdminController::class, 'waitlist'])->name('waitlist');
        Route::post('/waitlist/{id}/promote', [SpaAdminController::class, 'promoteWaitlist'])->name('waitlist.promote');
        Route::post('/waitlist/{id}/cancel', [SpaAdminController::class, 'cancelWaitlist'])->name('waitlist.cancel');

        // Cryptographic Audit Trail
        Route::get('/audit-logs', [SpaAdminController::class, 'auditLogs'])->name('audit-logs');
        Route::post('/audit-logs/verify', [SpaAdminController::class, 'verifyAuditChain'])->name('audit-logs.verify');

        // Privacy & Retention
        Route::get('/privacy', [SpaAdminController::class, 'privacyStats'])->name('privacy');
        Route::post('/privacy/export', [SpaAdminController::class, 'exportUser'])->name('privacy.export');
        Route::post('/privacy/anonymize', [SpaAdminController::class, 'anonymizeUser'])->name('privacy.anonymize');
        Route::post('/privacy/purge', [SpaAdminController::class, 'purgeRetention'])->name('privacy.purge');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('meetings')->name('meetings.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->name('index');
        Route::get('/create', [MeetingController::class, 'create'])->name('create');
        Route::post('/preview-conflicts', [MeetingController::class, 'previewConflicts'])->name('preview-conflicts');
        Route::post('/', [MeetingController::class, 'store'])->name('store');
        Route::get('/{publicId}', [MeetingController::class, 'show'])->name('show');
        Route::post('/{publicId}/cancel', [MeetingController::class, 'cancel'])->name('cancel');

        // Host Control routes (JIT start_url, host key reveal, post-meeting rotation)
        Route::get('/{publicId}/start', [HostControlController::class, 'start'])->name('start');
        Route::post('/{publicId}/host-key', [HostControlController::class, 'revealHostKey'])->name('host-key');
        Route::post('/{publicId}/host-key/rotate', [HostControlController::class, 'rotateHostKey'])->name('host-key.rotate');
        Route::get('/{publicId}/ics', [MeetingController::class, 'ics'])->name('ics');
    });

    Route::prefix('series')->name('series.')->group(function () {
        Route::get('/', [MeetingSeriesController::class, 'index'])->name('index');
        Route::get('/create', [MeetingSeriesController::class, 'create'])->name('create');
        Route::post('/', [MeetingSeriesController::class, 'store'])->name('store');
        Route::get('/{publicId}', [MeetingSeriesController::class, 'show'])->name('show');
        Route::post('/{seriesPublicId}/detach/{meetingPublicId}', [MeetingSeriesController::class, 'detachOccurrence'])->name('detach-occurrence');
        Route::post('/{publicId}/cancel', [MeetingSeriesController::class, 'cancel'])->name('cancel');
    });

    // In-App Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    });

    // Workflow Approvals
    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('index');
        Route::get('/{publicId}', [ApprovalController::class, 'show'])->name('show');
        Route::post('/{publicId}/decide', [ApprovalController::class, 'decide'])->name('decide');
    });

    // Approval Delegations
    Route::prefix('settings/delegations')->name('delegations.')->group(function () {
        Route::get('/', [DelegationController::class, 'index'])->name('index');
        Route::post('/', [DelegationController::class, 'store'])->name('store');
        Route::delete('/{publicId}', [DelegationController::class, 'destroy'])->name('destroy');
    });

    // Workflow Rules Administration
    Route::prefix('admin/workflows')->name('workflows.')->group(function () {
        Route::get('/', [WorkflowRuleController::class, 'index'])->name('index');
        Route::get('/create', [WorkflowRuleController::class, 'create'])->name('create');
        Route::post('/', [WorkflowRuleController::class, 'store'])->name('store');
        Route::get('/{publicId}/edit', [WorkflowRuleController::class, 'edit'])->name('edit');
        Route::put('/{publicId}', [WorkflowRuleController::class, 'update'])->name('update');
        Route::delete('/{publicId}', [WorkflowRuleController::class, 'destroy'])->name('destroy');
        Route::post('/{publicId}/toggle', [WorkflowRuleController::class, 'toggle'])->name('toggle');
        Route::post('/simulate', [WorkflowRuleController::class, 'simulate'])->name('simulate');
    });

    // Quotas Administration
    Route::prefix('admin/quotas')->name('quotas.')->group(function () {
        Route::get('/', [QuotaController::class, 'index'])->name('index');
        Route::post('/', [QuotaController::class, 'store'])->name('store');
        Route::delete('/{publicId}', [QuotaController::class, 'destroy'])->name('destroy');
    });

    // Mail Provider & Deliveries Administration
    Route::prefix('admin/mail')->name('admin.mail.')->group(function () {
        Route::get('/', [MailSettingsController::class, 'index'])->name('index');
        Route::post('/', [MailSettingsController::class, 'update'])->name('update');
        Route::post('/test-connection', [MailSettingsController::class, 'testConnection'])->name('test-connection');
        Route::post('/test-send', [MailSettingsController::class, 'testSend'])->name('test-send');
        Route::get('/deliveries', [MailSettingsController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{id}/retry', [MailSettingsController::class, 'retryDelivery'])->name('deliveries.retry');
    });

    // Email Templates Administration
    Route::prefix('admin/email-templates')->name('admin.templates.')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [EmailTemplateController::class, 'edit'])->name('edit');
        Route::put('/{id}', [EmailTemplateController::class, 'update'])->name('update');
        Route::post('/{id}/preview', [EmailTemplateController::class, 'preview'])->name('preview');
    });

    // Cloud Recordings (SPEC Part H11)
    Route::prefix('recordings')->name('recordings.')->group(function () {
        Route::get('/', [CloudRecordingController::class, 'index'])->name('index');
        Route::get('/{publicId}', [CloudRecordingController::class, 'show'])->name('show');
        Route::get('/{publicId}/play', [CloudRecordingController::class, 'play'])->name('play');
    });

    // Drift Reconciliation (SPEC Part F7)
    Route::prefix('admin/drift')->name('drift.')->group(function () {
        Route::get('/', [DriftConflictController::class, 'index'])->name('index');
        Route::post('/scan', [DriftConflictController::class, 'scan'])->name('scan');
        Route::post('/{publicId}/resolve', [DriftConflictController::class, 'resolve'])->name('resolve');
    });

    // Webhooks Log & Replay (SPEC Part F6)
    Route::prefix('admin/webhooks')->name('webhooks.')->group(function () {
        Route::get('/', [WebhookController::class, 'index'])->name('index');
        Route::post('/{publicId}/replay', [WebhookController::class, 'replay'])->name('replay');
    });

    Route::get('/calendar', [MeetingController::class, 'calendar'])->name('calendar');
});

// Installer Wizard Routes
Route::prefix('installer')->name('installer.')->group(function () {
    Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
    Route::get('/database', [InstallerController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallerController::class, 'testDatabase'])->name('database.test');
    Route::post('/database', [InstallerController::class, 'saveDatabase'])->name('database.save');
    Route::get('/admin', [InstallerController::class, 'admin'])->name('admin');
    Route::get('/admin/mfa', [InstallerController::class, 'generateMfa'])->name('admin.mfa');
    Route::post('/admin', [InstallerController::class, 'saveAdmin'])->name('admin.save');
    Route::get('/organization', [InstallerController::class, 'organization'])->name('organization');
    Route::post('/organization', [InstallerController::class, 'saveOrganization'])->name('organization.save');
    Route::get('/finish', [InstallerController::class, 'finish'])->name('finish');
});

// Operations, Health & Diagnostics (SPEC Part H12 / M11)
Route::get('/admin/health', [OperationsController::class, 'health'])->name('admin.health');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('/health/test/{check}', [OperationsController::class, 'testDiagnostic'])->name('health.test');
    Route::get('/alerts', [OperationsController::class, 'alerts'])->name('alerts');
    Route::post('/alerts/{publicId}/resolve', [OperationsController::class, 'resolveAlert'])->name('alerts.resolve');
    Route::get('/backups', [OperationsController::class, 'backups'])->name('backups');
    Route::post('/backups', [OperationsController::class, 'createBackup'])->name('backups.create');
    Route::get('/backups/{publicId}/download', [OperationsController::class, 'downloadBackup'])->name('backups.download');
    Route::get('/emergency', [OperationsController::class, 'emergencyPanel'])->name('emergency');
    Route::post('/emergency/override', [OperationsController::class, 'emergencyOverride'])->name('emergency.override');

    // API Keys & Outbound Webhooks (SPEC Part G / M12)
    Route::get('/api-keys', [ApiAdminController::class, 'indexKeys'])->name('api-keys.index');
    Route::post('/api-keys', [ApiAdminController::class, 'storeKey'])->name('api-keys.store');
    Route::post('/api-keys/{publicId}/revoke', [ApiAdminController::class, 'revokeKey'])->name('api-keys.revoke');

    Route::get('/outbound-webhooks', [ApiAdminController::class, 'indexWebhooks'])->name('outbound-webhooks.index');
    Route::post('/outbound-webhooks', [ApiAdminController::class, 'storeWebhook'])->name('outbound-webhooks.store');
    Route::post('/outbound-webhooks/{publicId}/test', [ApiAdminController::class, 'testWebhook'])->name('outbound-webhooks.test');
    Route::post('/outbound-webhooks/{publicId}/toggle', [ApiAdminController::class, 'toggleWebhook'])->name('outbound-webhooks.toggle');
    // System Updates & Releases
    Route::get('/system/updates', [SystemUpdateController::class, 'index'])->name('system.updates.index');
    Route::post('/system/updates/check', [SystemUpdateController::class, 'check'])->name('system.updates.check');
    Route::post('/system/updates/apply', [SystemUpdateController::class, 'apply'])->name('system.updates.apply');
});
