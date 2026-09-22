<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AvailabilityController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService
    ) {}

    /**
     * Check resource pool availability for a proposed meeting slot.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['nullable', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'pool_id' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var MeetingTemplate|null $template */
        $template = ! empty($validated['template_id']) ? MeetingTemplate::find($validated['template_id']) : null;
        /** @var SecurityProfile|null $profile */
        $profile = ! empty($validated['security_profile_id']) ? SecurityProfile::find($validated['security_profile_id']) : null;
        /** @var ResourcePool|null $pool */
        $pool = ! empty($validated['pool_id']) ? ResourcePool::find($validated['pool_id']) : null;

        $participantCount = (int) ($validated['participant_count'] ?? 1);
        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        $result = $this->meetingService->previewConflicts(
            startsAt: $startsAt,
            endsAt: $endsAt,
            participantCount: $participantCount,
            user: $user,
            template: $template,
            securityProfile: $profile,
            pool: $pool,
            requestInput: $request->all()
        );

        return response()->json([
            'available' => ! $result->hasConflict && $result->availableResourceCount > 0,
            'slot' => [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => $participantCount,
            ],
            'available_resource_count' => $result->availableResourceCount,
            'conflicts' => $result->conflicts,
        ]);
    }
}
