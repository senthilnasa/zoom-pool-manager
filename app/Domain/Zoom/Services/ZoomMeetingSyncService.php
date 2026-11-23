<?php

namespace App\Domain\Zoom\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomUser;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the authoritative Zoom details back into Zoom Pool Manager:
 *
 *  1. Host keys of every synced Zoom host account (GET /users/{userId}).
 *  2. Join URL, passcode and UUID of every booked meeting (GET /meetings/{meetingId}),
 *     and the host key of the Zoom account each meeting is allocated to.
 */
class ZoomMeetingSyncService
{
    /**
     * Meeting statuses that never hold a live Zoom meeting.
     *
     * @var array<int, string>
     */
    protected const SKIPPED_STATUSES = ['draft', 'pending_approval', 'rejected', 'cancelled', 'waitlisted'];

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  bool  $includePast  Also sync meetings whose end time has passed.
     * @param  string|null  $meetingPublicId  Restrict the sync to one meeting (public id or Zoom meeting id).
     * @param  bool  $dryRun  Report what would change without saving anything.
     * @param  bool  $syncHostKeys  Refresh host keys from Zoom before updating meetings.
     * @return array{success: bool, message: string, host_keys: array{checked: int, updated: int, failed: int}, meetings: array{checked: int, updated: int, unchanged: int, missing: int, failed: int}, errors: array<int, string>}
     */
    public function sync(
        bool $includePast = false,
        ?string $meetingPublicId = null,
        bool $dryRun = false,
        bool $syncHostKeys = true,
    ): array {
        $result = [
            'success' => false,
            'message' => '',
            'host_keys' => ['checked' => 0, 'updated' => 0, 'failed' => 0],
            'meetings' => ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'failed' => 0],
            'errors' => [],
        ];

        /** @var ZoomConnection|null $connection */
        $connection = ZoomConnection::first();
        if (! $connection || empty($connection->account_id) || empty($connection->client_id) || empty($connection->client_secret)) {
            $result['message'] = 'Zoom credentials are not configured. Complete Settings > Zoom API first.';

            return $result;
        }

        // Demo mode has no real Zoom account: only copy the local host keys onto meetings.
        $token = null;
        if (! config('app.demo')) {
            $token = $this->accessToken($connection, $result['errors']);
            if ($token === null) {
                $result['message'] = 'Could not authenticate with Zoom: '.($result['errors'][0] ?? 'unknown error');

                return $result;
            }
        }

        if ($token !== null && $syncHostKeys) {
            $this->syncHostKeys($connection, $token, $dryRun, $result);
        }

        $this->syncMeetings($token, $includePast, $meetingPublicId, $dryRun, $result);

        if (! $dryRun) {
            $connection->update(['last_sync_at' => now()]);

            $this->auditService->log(
                event: 'zoom.meetings.synced',
                auditable: $connection,
                newValues: [
                    'host_keys_updated' => $result['host_keys']['updated'],
                    'meetings_updated' => $result['meetings']['updated'],
                    'meetings_missing' => $result['meetings']['missing'],
                ]
            );
        }

        $hk = $result['host_keys'];
        $m = $result['meetings'];
        $result['success'] = $hk['failed'] === 0 && $m['failed'] === 0;
        $result['message'] = sprintf(
            '%sHost keys: %d checked, %d updated, %d failed. Meetings: %d checked, %d updated, %d unchanged, %d not found in Zoom, %d failed.',
            $dryRun ? '[dry run] ' : '',
            $hk['checked'], $hk['updated'], $hk['failed'],
            $m['checked'], $m['updated'], $m['unchanged'], $m['missing'], $m['failed'],
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function syncHostKeys(ZoomConnection $connection, string $token, bool $dryRun, array &$result): void
    {
        $users = ZoomUser::where('connection_id', $connection->id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->get();

        foreach ($users as $zoomUser) {
            $result['host_keys']['checked']++;

            try {
                $res = $this->zoom($token)->get('https://api.zoom.us/v2/users/'.rawurlencode($zoomUser->zoom_user_id));
            } catch (\Throwable $e) {
                $this->fail($result, 'host_keys', "Host {$zoomUser->email}: {$e->getMessage()}");

                continue;
            }

            if (! $res->successful()) {
                $this->fail($result, 'host_keys', "Host {$zoomUser->email}: HTTP {$res->status()} ".$this->zoomError($res));

                continue;
            }

            $hostKey = (string) ($res->json('host_key') ?? '');
            if ($hostKey === '' || $hostKey === (string) $zoomUser->host_key) {
                continue;
            }

            $result['host_keys']['updated']++;
            if (! $dryRun) {
                $zoomUser->host_key = $hostKey;
                $zoomUser->synced_at = now();
                $zoomUser->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function syncMeetings(?string $token, bool $includePast, ?string $meetingPublicId, bool $dryRun, array &$result): void
    {
        $query = Meeting::query()
            ->with('zoomResource.zoomUser')
            ->whereNotNull('zoom_meeting_id')
            ->where('zoom_meeting_id', '!=', '');

        if ($meetingPublicId !== null && $meetingPublicId !== '') {
            $query->where(fn ($q) => $q->where('public_id', $meetingPublicId)->orWhere('zoom_meeting_id', $meetingPublicId));
        } else {
            $query->whereNotIn('status', self::SKIPPED_STATUSES);
            if (! $includePast) {
                $query->where('ends_at', '>=', now());
            }
        }

        $query->orderBy('id')->chunkById(100, function ($meetings) use ($token, $dryRun, &$result) {
            /** @var Meeting $meeting */
            foreach ($meetings as $meeting) {
                $result['meetings']['checked']++;
                $this->syncMeeting($meeting, $token, $dryRun, $result);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function syncMeeting(Meeting $meeting, ?string $token, bool $dryRun, array &$result): void
    {
        $changes = [];

        if ($token !== null) {
            try {
                $res = $this->zoom($token)->get('https://api.zoom.us/v2/meetings/'.rawurlencode((string) $meeting->zoom_meeting_id));
            } catch (\Throwable $e) {
                $this->fail($result, 'meetings', "Meeting {$meeting->public_id} ({$meeting->zoom_meeting_id}): {$e->getMessage()}");

                return;
            }

            if ($res->status() === 404) {
                // Zoom code 3001: the meeting was deleted in Zoom or was never created there.
                $result['meetings']['missing']++;
                $result['errors'][] = "Meeting {$meeting->public_id} ({$meeting->zoom_meeting_id}) was not found in Zoom.";

                return;
            }

            if (! $res->successful()) {
                $this->fail($result, 'meetings', "Meeting {$meeting->public_id} ({$meeting->zoom_meeting_id}): HTTP {$res->status()} ".$this->zoomError($res));

                return;
            }

            $joinUrl = (string) ($res->json('join_url') ?? '');
            $passcode = (string) ($res->json('password') ?? '');
            $uuid = (string) ($res->json('uuid') ?? '');

            if ($joinUrl !== '' && $joinUrl !== (string) $meeting->join_url) {
                $changes['join_url'] = $joinUrl;
            }
            if ($passcode !== '' && $passcode !== (string) $meeting->passcode) {
                $changes['passcode'] = $passcode;
            }
            if ($uuid !== '' && $uuid !== (string) $meeting->zoom_uuid) {
                $changes['zoom_uuid'] = $uuid;
            }
        }

        $hostKey = (string) ($meeting->zoomResource?->zoomUser->host_key ?? '');
        if ($hostKey !== '' && $hostKey !== (string) $meeting->host_key) {
            $changes['host_key'] = $hostKey;
        }

        if ($changes === []) {
            $result['meetings']['unchanged']++;

            return;
        }

        $result['meetings']['updated']++;
        if (! $dryRun) {
            $meeting->fill($changes);
            $meeting->save();
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function accessToken(ZoomConnection $connection, array &$errors): ?string
    {
        try {
            $res = Http::timeout(10)
                ->withHeaders(['Authorization' => 'Basic '.base64_encode($connection->client_id.':'.$connection->client_secret)])
                ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($connection->account_id));
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();

            return null;
        }

        $token = $res->successful() ? (string) $res->json('access_token') : '';
        if ($token === '') {
            $errors[] = "HTTP {$res->status()} ".$this->zoomError($res);

            return null;
        }

        return $token;
    }

    protected function zoom(string $token): PendingRequest
    {
        return Http::timeout(15)
            ->withToken($token)
            ->withHeaders(['User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0')])
            // Back off and retry when Zoom rate-limits us (HTTP 429).
            ->retry(3, 1000, fn ($e) => $e instanceof RequestException && $e->response->status() === 429, throw: false);
    }

    protected function zoomError(Response $res): string
    {
        return (string) ($res->json('message') ?? '');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function fail(array &$result, string $bucket, string $error): void
    {
        $result[$bucket]['failed']++;
        $result['errors'][] = $error;
        Log::warning("Zoom meeting sync: {$error}");
    }
}
