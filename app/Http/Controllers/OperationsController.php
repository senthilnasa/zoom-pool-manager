<?php

namespace App\Http\Controllers;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Operations\Models\Alert;
use App\Domain\Operations\Models\BackupRecord;
use App\Domain\Operations\Services\BackupService;
use App\Domain\Operations\Services\EmergencyOverrideService;
use App\Domain\Operations\Services\HealthCheckService;
use App\Domain\Operations\Services\OperationsAlertService;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationsController extends Controller
{
    public function __construct(
        protected HealthCheckService $healthService,
        protected OperationsAlertService $alertService,
        protected BackupService $backupService,
        protected EmergencyOverrideService $emergencyService
    ) {}

    /**
     * System health and operations dashboard.
     */
    public function health(Request $request): JsonResponse|View
    {
        $health = $this->healthService->getSystemHealth();

        if ($request->wantsJson()) {
            return response()->json($health, $health['status'] === 'failed' ? 503 : 200);
        }

        $openAlertsCount = Alert::whereNull('resolved_at')->count();

        return view('operations.health', compact('health', 'openAlertsCount'));
    }

    /**
     * Run an on-demand diagnostic test.
     */
    public function testDiagnostic(Request $request, string $check): JsonResponse|RedirectResponse
    {
        $result = match ($check) {
            'database' => $this->healthService->testDatabase(),
            'queue' => $this->healthService->testQueue(),
            default => ['success' => false, 'message' => "Unknown diagnostic test: {$check}"],
        };

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        $flashType = $result['success'] ? 'success' : 'error';

        return back()->with($flashType, $result['message']);
    }

    /**
     * Operational anomaly alerts dashboard.
     */
    public function alerts(Request $request): View
    {
        $status = $request->query('status', 'open');

        $query = Alert::latest('last_seen_at');

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        $alerts = $query->paginate(20)->withQueryString();

        return view('operations.alerts', compact('alerts', 'status'));
    }

    /**
     * Resolve an alert manually.
     */
    public function resolveAlert(Request $request, string $publicId): RedirectResponse
    {
        $alert = Alert::where('public_id', $publicId)->firstOrFail();
        $this->alertService->resolveAlert($alert->key, 'Manually resolved by administrator.');

        return back()->with('success', "Alert '{$alert->title}' marked as resolved.");
    }

    /**
     * Database backups management.
     */
    public function backups(): View
    {
        $backups = $this->backupService->listBackups();

        return view('operations.backups', compact('backups'));
    }

    /**
     * Trigger database backup.
     */
    public function createBackup(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $record = $this->backupService->createDatabaseBackup($user);

        return back()->with('success', "Database backup created: {$record->filename} (".number_format($record->file_size_bytes / 1024, 1).' KB)');
    }

    /**
     * Download backup file.
     */
    public function downloadBackup(string $publicId): BinaryFileResponse|RedirectResponse
    {
        $backup = BackupRecord::where('public_id', $publicId)->firstOrFail();

        if (! File::exists($backup->file_path)) {
            return back()->with('error', 'Backup file does not exist on disk.');
        }

        return response()->download($backup->file_path, $backup->filename);
    }

    /**
     * Emergency IT Override Panel (permission: emergency.use).
     */
    public function emergencyPanel(): View
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->can('emergency.use') && ! $user->hasRole('Super Administrator')) {
            abort(403, 'Unauthorized access to emergency IT panel.');
        }

        $activeMeetings = Meeting::with(['owner', 'zoomResource'])
            ->whereIn('status', ['scheduled', 'approved', 'started'])
            ->orderBy('starts_at')
            ->paginate(15);

        $resources = ZoomResource::where('managed', true)->get();

        return view('operations.emergency', compact('activeMeetings', 'resources'));
    }

    /**
     * Execute emergency override action.
     */
    public function emergencyOverride(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->can('emergency.use') && ! $user->hasRole('Super Administrator')) {
            abort(403, 'Unauthorized access to emergency IT panel.');
        }

        $validated = $request->validate([
            'meeting_public_id' => 'required|string',
            'action' => 'required|string|in:reallocate,cancel',
            'resource_id' => 'nullable|required_if:action,reallocate|exists:zoom_resources,id',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $meeting = Meeting::where('public_id', $validated['meeting_public_id'])->firstOrFail();

        if ($validated['action'] === 'reallocate') {
            $newResource = ZoomResource::findOrFail((int) $validated['resource_id']);
            $this->emergencyService->emergencyReallocate($meeting, $newResource, $user, $validated['reason']);

            return back()->with('success', "Emergency reallocation executed for '{$meeting->title}'.");
        }

        if ($validated['action'] === 'cancel') {
            $this->emergencyService->emergencyCancel($meeting, $user, $validated['reason']);

            return back()->with('success', "Emergency cancellation executed for '{$meeting->title}'.");
        }

        return back();
    }
}
