<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingCustomField;
use App\Domain\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NocMeetingController extends Controller
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Get real-time & upcoming meetings for NOC wallboards, operations centers, and monitoring.
     * Accessible via API key / token without browser session or CSRF requirement.
     */
    public function meetings(Request $request): JsonResponse
    {
        // 1. Authenticate via NOC API Token or standard API Key
        if (! $this->authorizeNocAccess($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing NOC API Key. Provide via X-API-KEY, X-NOC-Token, Authorization Bearer header, or ?api_key= query parameter.',
            ], 401);
        }

        // 2. Parse time window: 'from' (default: now) to 'to' / 'to_time' / 'to_date' / 'next_date' / 'hours'
        $now = Carbon::now();
        $from = $now->copy();

        if ($request->filled('from')) {
            try {
                $from = Carbon::parse((string) $request->input('from'));
            } catch (\Throwable) {
                $from = $now->copy();
            }
        }

        // Determine 'to' cutoff time
        $to = null;

        if ($request->filled('to')) {
            try {
                $to = Carbon::parse((string) $request->input('to'));
            } catch (\Throwable) {
                $to = null;
            }
        } elseif ($request->filled('to_date') || $request->filled('next_date')) {
            $dateStr = (string) ($request->input('to_date') ?? $request->input('next_date'));
            try {
                $to = Carbon::parse($dateStr)->endOfDay();
            } catch (\Throwable) {
                $to = null;
            }
        } elseif ($request->filled('to_time')) {
            $timeStr = (string) $request->input('to_time');
            try {
                $to = Carbon::parse($timeStr);
                // If the specified time has already passed today, apply it to tomorrow
                if ($to->isBefore($from)) {
                    $to->addDay();
                }
            } catch (\Throwable) {
                $to = null;
            }
        } elseif ($request->filled('hours')) {
            $hours = max(1, (int) $request->input('hours'));
            $to = $from->copy()->addHours($hours);
        }

        // Default cutoff: next 24 hours if not specified
        if (! $to) {
            $to = $from->copy()->addHours(24);
        }

        // Ensure $to is after $from
        if ($to->isBefore($from)) {
            $to = $from->copy()->addHours(24);
        }

        // 3. Build query
        $query = Meeting::with([
            'owner:id,name,email,department_id',
            'requester:id,name,email,department_id',
            'department:id,name,code',
            'zoomResource:id,name',
            'zoomResource.zoomUser:id,resource_id,email,display_name',
        ]);

        // Status filter
        $statuses = ['started', 'scheduled', 'allocating'];
        if ($request->filled('status')) {
            $statusInput = (string) $request->input('status');
            $statuses = array_values(array_filter(array_map('trim', explode(',', $statusInput))));
        }
        $query->whereIn('status', $statuses);

        // Time window filter: happening from now ($from) up to ($to)
        // Meeting is active if ends_at >= $from AND starts_at <= $to
        $query->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to);

        // Optional department filter
        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }

        // Optional pool filter
        if ($request->filled('pool_id')) {
            $poolId = (int) $request->input('pool_id');
            $query->whereHas('zoomResource.pools', function ($q) use ($poolId) {
                $q->where('resource_pools.id', $poolId);
            });
        }

        $meetings = $query->orderBy('starts_at', 'asc')->get();

        // 4. Load custom fields metadata for human-friendly formatting
        $customFieldsMeta = MeetingCustomField::all()->keyBy('field_key');

        $formattedMeetings = $meetings->map(function (Meeting $m) use ($now, $customFieldsMeta) {
            $isLive = ($m->status === 'started') || ($m->status === 'scheduled' && $m->starts_at->isPast() && $m->ends_at->isFuture());
            $startsInMinutes = (int) round($now->diffInMinutes($m->starts_at, false));
            $endsInMinutes = (int) round($now->diffInMinutes($m->ends_at, false));

            $rawCustomFields = $m->custom_fields ?? [];
            $formattedCustomFields = [];

            if (is_array($rawCustomFields)) {
                foreach ($rawCustomFields as $key => $val) {
                    $meta = $customFieldsMeta->get($key);
                    $fieldName = $meta !== null ? $meta->name : ucwords(str_replace('_', ' ', (string) $key));
                    $fieldType = $meta !== null ? $meta->field_type : 'text';
                    $formattedCustomFields[] = [
                        'key' => (string) $key,
                        'name' => $fieldName,
                        'type' => $fieldType,
                        'value' => $val,
                    ];
                }
            }

            return [
                'id' => $m->id,
                'public_id' => $m->public_id,
                'title' => $m->title,
                'description' => $m->description,
                'meeting_type' => $m->meeting_type,
                'status' => $m->status,
                'is_live' => $isLive,
                'starts_at' => $m->starts_at->toIso8601String(),
                'ends_at' => $m->ends_at->toIso8601String(),
                'duration_minutes' => (int) $m->starts_at->diffInMinutes($m->ends_at),
                'starts_in_minutes' => $startsInMinutes,
                'ends_in_minutes' => $endsInMinutes,
                'timezone' => $m->timezone,
                'participant_count' => $m->participant_count,
                'waiting_room' => (bool) $m->waiting_room,
                'join_before_host' => (bool) $m->join_before_host,
                'jbh_time' => (int) $m->jbh_time,
                'recording_mode' => $m->recording_mode,
                'zoom_meeting_id' => $m->zoom_meeting_id,
                'join_url' => $m->join_url,
                'passcode' => $m->passcode,
                'host_key' => $m->share_host_key ? $m->host_key : null,
                'custom_fields' => $rawCustomFields,
                'custom_fields_formatted' => $formattedCustomFields,
                'requester' => $m->requester ? [
                    'id' => $m->requester->id,
                    'name' => $m->requester->name,
                    'email' => $m->requester->email,
                ] : null,
                'owner' => $m->owner ? [
                    'id' => $m->owner->id,
                    'name' => $m->owner->name,
                    'email' => $m->owner->email,
                ] : null,
                'department' => $m->department ? [
                    'id' => $m->department->id,
                    'name' => $m->department->name,
                    'code' => $m->department->code,
                ] : null,
                'host_resource' => $m->zoomResource ? [
                    'id' => $m->zoomResource->id,
                    'name' => $m->zoomResource->name,
                    'email' => $m->zoomResource->zoomUser?->email,
                ] : null,
            ];
        });

        $liveCount = $formattedMeetings->where('is_live', true)->count();
        $upcomingCount = $formattedMeetings->where('is_live', false)->count();

        return response()->json([
            'success' => true,
            'timestamp' => $now->toIso8601String(),
            'query_window' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'hours_span' => round($from->diffInHours($to, true), 1),
            ],
            'total_count' => $formattedMeetings->count(),
            'live_count' => $liveCount,
            'upcoming_count' => $upcomingCount,
            'meetings' => $formattedMeetings->values(),
        ]);
    }

    /**
     * Authorize NOC access via token or API key.
     */
    protected function authorizeNocAccess(Request $request): bool
    {
        $token = $request->header('X-NOC-Token')
            ?? $request->header('X-API-KEY')
            ?? $request->header('X-Api-Key')
            ?? $request->query('api_key')
            ?? $request->query('token');

        if (! $token && $request->hasHeader('Authorization')) {
            $authHeader = $request->header('Authorization');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }

        if (empty($token)) {
            return false;
        }

        // 1. Check against configured NOC Token in Settings
        $configuredNocToken = (string) Setting::get('noc.api_token');
        if (empty($configuredNocToken)) {
            // Seed a consistent default NOC token based on APP_KEY if unconfigured
            $configuredNocToken = 'noc_live_'.substr(hash('sha256', config('app.key', 'zpm_noc_default_secret')), 0, 32);
            Setting::set('noc.api_token', $configuredNocToken);
        }

        if (hash_equals($configuredNocToken, (string) $token)) {
            return true;
        }

        // 2. Check against environment variable if set
        $envNocToken = getenv('NOC_API_TOKEN');
        if (! empty($envNocToken) && hash_equals((string) $envNocToken, (string) $token)) {
            return true;
        }

        // 3. Fallback: Authenticate via ApiKeyService
        $apiKey = $this->apiKeyService->authenticate((string) $token);
        if ($apiKey) {
            return true;
        }

        return false;
    }
}
