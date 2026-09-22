<?php

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Operations\Models\DataExportRequest;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PrivacyAndRetentionService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Generate a per-user data export in JSON format (DPDP/GDPR data subject access request).
     */
    public function exportUserData(User $user, User $actor): DataExportRequest
    {
        $exportDir = storage_path('app/exports');
        if (! File::isDirectory($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $filename = "user_export_{$user->id}_".now()->format('Ymd_His').'.json';
        $filePath = "{$exportDir}/{$filename}";

        $userData = [
            'user' => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->name,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'meetings_requested' => DB::table('meetings')->where('requester_user_id', $user->id)->get(),
            'meetings_owned' => DB::table('meetings')->where('owner_user_id', $user->id)->get(),
            'approvals' => DB::table('meeting_approvals')->where('approver_user_id', $user->id)->get(),
            'notifications' => DB::table('notifications')->where('notifiable_id', $user->id)->get(),
            'notification_preferences' => DB::table('notification_preferences')->where('user_id', $user->id)->get(),
        ];

        File::put($filePath, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        /** @var DataExportRequest $request */
        $request = DataExportRequest::create([
            'user_id' => $user->id,
            'requested_by_user_id' => $actor->id,
            'status' => 'completed',
            'file_path' => $filePath,
            'expires_at' => now()->addDays(7),
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        $this->auditService->log(
            'user.data_exported',
            $user,
            null,
            ['export_request_id' => $request->id, 'file_path' => $filePath],
            $actor
        );

        return $request;
    }

    /**
     * Anonymize a user's PII while preserving audit and relational database integrity.
     */
    public function anonymizeUser(User $user, User $actor, string $reason): void
    {
        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $anonymizedEmail = "anonymized_{$user->id}@redacted.local";
        $anonymizedName = "Anonymized User #{$user->id}";

        $user->update([
            'name' => $anonymizedName,
            'email' => $anonymizedEmail,
            'is_active' => false,
            'password' => bcrypt(uniqid('anonymized_', true)),
            'totp_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $this->auditService->log(
            'user.anonymized',
            $user,
            $oldData,
            ['name' => $anonymizedName, 'email' => $anonymizedEmail, 'reason' => $reason],
            $actor
        );
    }

    /**
     * Purge historical records older than specified retention days for configured tables.
     *
     * @param  array<string, int>  $retentionConfig  Array of table => days
     * @return array<string, int> Array of table => deleted rows count
     */
    public function purgeOldRecords(array $retentionConfig, ?User $actor = null): array
    {
        $purged = [];

        $allowedTables = [
            'zoom_webhook_events' => 'created_at',
            'notifications' => 'created_at',
            'email_deliveries' => 'created_at',
            'recording_access_logs' => 'created_at',
        ];

        foreach ($retentionConfig as $table => $days) {
            if (! isset($allowedTables[$table]) || $days <= 0) {
                continue;
            }

            $dateCol = $allowedTables[$table];
            $cutoff = now()->subDays($days);

            $count = DB::table($table)->where($dateCol, '<', $cutoff)->delete();
            $purged[$table] = $count;

            if ($count > 0) {
                $this->auditService->log(
                    'data.retention_purged',
                    null,
                    null,
                    ['table' => $table, 'deleted_count' => $count, 'cutoff_days' => $days],
                    $actor
                );
            }
        }

        return $purged;
    }
}
