<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingInvitee;
use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeriesAllocationService
{
    public function __construct(
        protected RecurrenceExpansionService $expansionService,
        protected EffectivePolicyResolver $policyResolver,
        protected ConflictDetectionService $conflictService,
        protected AllocationEngine $allocationEngine,
        protected MeetingStateMachine $stateMachine,
        protected AuditService $auditService
    ) {}

    /**
     * Create a recurring meeting series and allocate resources based on series_mode.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    public function createSeries(
        User $requester,
        array $data,
        array $inviteeEmails = []
    ): MeetingSeries {
        $rruleString = (string) $data['rrule'];
        $startsAt = Carbon::parse($data['starts_at']);
        $durationMinutes = (int) ($data['duration_minutes'] ?? 60);
        $participantCount = (int) ($data['participant_count'] ?? 10);
        $seriesMode = (string) ($data['series_mode'] ?? 'SINGLE_RESOURCE');

        /** @var MeetingTemplate|null $template */
        $template = ! empty($data['template_id']) ? MeetingTemplate::find($data['template_id']) : null;

        /** @var SecurityProfile|null $securityProfile */
        $securityProfile = null;
        if (! empty($data['security_profile_id'])) {
            $securityProfile = SecurityProfile::find($data['security_profile_id']);
        } elseif ($template !== null) {
            $securityProfile = $template->securityProfile;
        }
        if (! $securityProfile) {
            $securityProfile = SecurityProfile::where('is_default', true)->first();
        }

        /** @var ResourcePool|null $pool */
        $pool = ! empty($data['preferred_pool_id']) ? ResourcePool::find($data['preferred_pool_id']) : $template?->defaultPool;

        $resolvedPolicy = $this->policyResolver->resolve(
            user: $requester,
            department: $requester->department,
            securityProfile: $securityProfile,
            template: $template,
            requestInput: $data
        );

        // Expand occurrences
        $occurrences = $this->expansionService->expand(
            rruleString: $rruleString,
            startsAt: $startsAt,
            durationMinutes: $durationMinutes,
            departmentId: $requester->department_id
        );

        $validOccurrences = array_filter($occurrences, fn ($occ) => ! $occ['is_skipped']);

        if (empty($validOccurrences)) {
            throw new RuntimeException('All occurrences in the recurrence rule fall within blackout periods or holidays.');
        }

        return DB::transaction(function () use (
            $requester,
            $data,
            $rruleString,
            $startsAt,
            $validOccurrences,
            $participantCount,
            $seriesMode,
            $pool,
            $resolvedPolicy,
            $template,
            $securityProfile,
            $inviteeEmails
        ) {
            $series = MeetingSeries::create([
                'requester_user_id' => $requester->id,
                'owner_user_id' => $data['owner_user_id'] ?? $requester->id,
                'department_id' => $requester->department_id,
                'rrule' => $rruleString,
                'timezone' => $data['timezone'] ?? $requester->timezone ?? 'Asia/Kolkata',
                'start_date' => $startsAt->toDateString(),
                'until_date' => ! empty($data['until_date']) ? Carbon::parse($data['until_date'])->toDateString() : end($validOccurrences)['starts_at']->toDateString(),
                'occurrence_count' => count($validOccurrences),
                'series_mode' => $seriesMode,
                'recording_mode' => $data['recording_mode'] ?? $resolvedPolicy->recordingMode,
                'waiting_room' => isset($data['waiting_room']) ? (bool) $data['waiting_room'] : (bool) ($securityProfile?->settings['waiting_room'] ?? true),
                'join_before_host' => isset($data['join_before_host']) ? (bool) $data['join_before_host'] : false,
                'jbh_time' => isset($data['jbh_time']) ? (int) $data['jbh_time'] : 0,
                'attendance_tracking' => isset($data['attendance_tracking']) ? (bool) $data['attendance_tracking'] : true,
                'share_host_key' => isset($data['share_host_key']) ? (bool) $data['share_host_key'] : false,
                'status' => 'active',
                'source' => $data['source'] ?? 'web',
            ]);

            if ($seriesMode === 'SINGLE_RESOURCE') {
                $this->allocateSingleResourceSeries($series, $validOccurrences, $participantCount, $resolvedPolicy, $pool, $data, $requester, $template, $securityProfile, $inviteeEmails);
            } elseif ($seriesMode === 'SPLIT_WHEN_NEEDED') {
                $this->allocateSplitWhenNeededSeries($series, $validOccurrences, $participantCount, $resolvedPolicy, $pool, $data, $requester, $template, $securityProfile, $inviteeEmails);
            } else {
                // PER_OCCURRENCE
                $this->allocatePerOccurrenceSeries($series, $validOccurrences, $participantCount, $resolvedPolicy, $pool, $data, $requester, $template, $securityProfile, $inviteeEmails);
            }

            $this->auditService->log('series.created', $series, null, [
                'mode' => $seriesMode,
                'occurrence_count' => count($validOccurrences),
            ], $requester);

            return $series;
        });
    }

    /**
     * Allocate 1 identical ZoomResource across all occurrences.
     *
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, index: int, is_skipped: bool, skip_reason: ?string}>  $occurrences
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    protected function allocateSingleResourceSeries(
        MeetingSeries $series,
        array $occurrences,
        int $participantCount,
        mixed $resolvedPolicy,
        ?ResourcePool $pool,
        array $data,
        User $requester,
        ?MeetingTemplate $template,
        ?SecurityProfile $securityProfile,
        array $inviteeEmails
    ): void {
        // Query candidate resources in pool
        $query = ZoomResource::where('managed', true)->where('status', 'active');
        if ($pool) {
            $query->whereHas('pools', fn ($q) => $q->where('resource_pools.id', $pool->id));
        }
        $candidates = $query->orderBy('id', 'asc')->get();

        /** @var ZoomResource|null $selectedResource */
        $selectedResource = null;

        foreach ($candidates as $candidate) {
            $hasConflict = false;

            foreach ($occurrences as $occ) {
                $check = $this->conflictService->check(
                    startsAt: $occ['starts_at'],
                    endsAt: $occ['ends_at'],
                    participantCount: $participantCount,
                    policy: $resolvedPolicy,
                    pool: $pool,
                    specificResource: $candidate,
                    requester: $requester
                );

                if ($check->hasConflict) {
                    $hasConflict = true;
                    break;
                }
            }

            if (! $hasConflict) {
                $selectedResource = $candidate;
                break;
            }
        }

        if (! $selectedResource) {
            throw new RuntimeException('No single resource in the pool is available for all occurrences in this series. Consider choosing SPLIT_WHEN_NEEDED or PER_OCCURRENCE mode.');
        }

        $series->zoom_resource_id = $selectedResource->id;
        $series->save();

        foreach ($occurrences as $occ) {
            $meeting = $this->createOccurrenceMeeting(
                series: $series,
                occ: $occ,
                resource: $selectedResource,
                participantCount: $participantCount,
                data: $data,
                requester: $requester,
                template: $template,
                securityProfile: $securityProfile,
                resolvedPolicy: $resolvedPolicy,
                inviteeEmails: $inviteeEmails
            );

            // Reserve and confirm resource
            $reservation = $this->allocationEngine->holdResource(
                startsAt: $occ['starts_at'],
                endsAt: $occ['ends_at'],
                participantCount: $participantCount,
                policy: $resolvedPolicy,
                pool: $pool,
                preferredResource: $selectedResource,
                meetingId: $meeting->id
            );

            $this->allocationEngine->confirmReservation($reservation, $meeting->id);
            app(MeetingLifecycleService::class)->provisionZoomDetails($meeting);
            $meeting->status = 'scheduled';
            $meeting->save();
        }
    }

    /**
     * Allocate primary resource where possible, and substitute on conflicts.
     *
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, index: int, is_skipped: bool, skip_reason: ?string}>  $occurrences
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    protected function allocateSplitWhenNeededSeries(
        MeetingSeries $series,
        array $occurrences,
        int $participantCount,
        mixed $resolvedPolicy,
        ?ResourcePool $pool,
        array $data,
        User $requester,
        ?MeetingTemplate $template,
        ?SecurityProfile $securityProfile,
        array $inviteeEmails
    ): void {
        $this->allocatePerOccurrenceSeries($series, $occurrences, $participantCount, $resolvedPolicy, $pool, $data, $requester, $template, $securityProfile, $inviteeEmails);
    }

    /**
     * Allocate each occurrence independently using pool strategy.
     *
     * @param  array<int, array{starts_at: Carbon, ends_at: Carbon, index: int, is_skipped: bool, skip_reason: ?string}>  $occurrences
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    protected function allocatePerOccurrenceSeries(
        MeetingSeries $series,
        array $occurrences,
        int $participantCount,
        mixed $resolvedPolicy,
        ?ResourcePool $pool,
        array $data,
        User $requester,
        ?MeetingTemplate $template,
        ?SecurityProfile $securityProfile,
        array $inviteeEmails
    ): void {
        $strategy = $pool !== null ? $pool->pool_strategy : 'least_hours_today';

        foreach ($occurrences as $occ) {
            $reservation = $this->allocationEngine->holdResource(
                startsAt: $occ['starts_at'],
                endsAt: $occ['ends_at'],
                participantCount: $participantCount,
                policy: $resolvedPolicy,
                pool: $pool,
                strategy: $strategy
            );

            /** @var ZoomResource $resource */
            $resource = ZoomResource::findOrFail($reservation->resource_id);

            $meeting = $this->createOccurrenceMeeting(
                series: $series,
                occ: $occ,
                resource: $resource,
                participantCount: $participantCount,
                data: $data,
                requester: $requester,
                template: $template,
                securityProfile: $securityProfile,
                resolvedPolicy: $resolvedPolicy,
                inviteeEmails: $inviteeEmails
            );

            $this->allocationEngine->confirmReservation($reservation, $meeting->id);
            app(MeetingLifecycleService::class)->provisionZoomDetails($meeting);
            $meeting->status = 'scheduled';
            $meeting->save();
        }
    }

    /**
     * Helper to create individual occurrence meeting.
     *
     * @param  array{starts_at: Carbon, ends_at: Carbon, index: int, is_skipped: bool, skip_reason: ?string}  $occ
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    protected function createOccurrenceMeeting(
        MeetingSeries $series,
        array $occ,
        ZoomResource $resource,
        int $participantCount,
        array $data,
        User $requester,
        ?MeetingTemplate $template,
        ?SecurityProfile $securityProfile,
        mixed $resolvedPolicy,
        array $inviteeEmails
    ): Meeting {
        $title = $data['title'];
        if (! empty($occ['index']) && count($series->meetings ?? []) > 1) {
            $title .= " (Session #{$occ['index']})";
        }

        $meeting = Meeting::create([
            'series_id' => $series->id,
            'occurrence_index' => $occ['index'],
            'title' => $title,
            'description' => $data['description'] ?? null,
            'meeting_type' => $data['meeting_type'] ?? 'class',
            'starts_at' => $occ['starts_at'],
            'ends_at' => $occ['ends_at'],
            'timezone' => $data['timezone'] ?? $requester->timezone ?? 'Asia/Kolkata',
            'participant_count' => $participantCount,
            'requester_user_id' => $requester->id,
            'owner_user_id' => $data['owner_user_id'] ?? $requester->id,
            'department_id' => $requester->department_id,
            'template_id' => $template?->id,
            'security_profile_id' => $securityProfile?->id,
            'ai_companion_policy' => $resolvedPolicy->aiCompanionPolicy,
            'recording_mode' => $data['recording_mode'] ?? $resolvedPolicy->recordingMode,
            'waiting_room' => isset($data['waiting_room']) ? (bool) $data['waiting_room'] : (bool) ($securityProfile?->settings['waiting_room'] ?? true),
            'join_before_host' => isset($data['join_before_host']) ? (bool) $data['join_before_host'] : false,
            'jbh_time' => isset($data['jbh_time']) ? (int) $data['jbh_time'] : 0,
            'attendance_tracking' => isset($data['attendance_tracking']) ? (bool) $data['attendance_tracking'] : true,
            'share_host_key' => isset($data['share_host_key']) ? (bool) $data['share_host_key'] : false,
            'external_participants' => ! empty($data['external_participants']),
            'registration_enabled' => ! empty($data['registration_enabled']),
            'zoom_resource_id' => $resource->id,
            'status' => 'allocating',
            'source' => $data['source'] ?? 'web',
            'is_detached_from_series' => false,
        ]);

        foreach ($inviteeEmails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $internal = User::where('email', $email)->first();
                MeetingInvitee::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $internal?->id,
                    'email' => $email,
                    'name' => $internal?->name,
                    'status' => 'pending',
                ]);
            }
        }

        return $meeting;
    }
}
