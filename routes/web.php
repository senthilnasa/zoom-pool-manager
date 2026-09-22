<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Installer\InstallerController;
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
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkflowRuleController;

// Inbound Zoom Webhook Intake (SPEC Part F6)
Route::post('/webhooks/zoom/{public_id}', [WebhookController::class, 'handle'])
    ->name('webhooks.zoom')
    ->withoutMiddleware([ValidateCsrfToken::class]);

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
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
