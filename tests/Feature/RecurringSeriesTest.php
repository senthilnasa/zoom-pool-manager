<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Meetings\Services\OccurrenceDetachmentService;
use App\Domain\Meetings\Services\RecurrenceExpansionService;
use App\Domain\Meetings\Services\SeriesAllocationService;
use App\Domain\Scheduling\Models\BlackoutPeriod;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use App\Http\Middleware\EnsureInstalled;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecurringSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ResourcePool $pool;

    protected ZoomResource $resourceA;

    protected ZoomResource $resourceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        file_put_contents(storage_path(EnsureInstalled::LOCK_FILE), json_encode(['installed_at' => now()->toIso8601String()]));

        Setting::set('org.min_buffer_minutes', 10);
        Setting::set('org.default_buffer_minutes', 15);
        Setting::set('org.min_notice_hours', 0);
        Setting::set('org.max_advance_days', 90);
        Setting::set('org.max_duration_minutes', 300);

        $dept = Department::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $this->user = User::create([
            'name' => 'Prof. Euler',
            'email' => 'euler@univ.edu',
            'password' => bcrypt('secret123'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Campus Zoom',
            'account_id' => 'zoom-acc-1',
            'client_id' => 'zoom-client-1',
            'client_secret' => 'zoom-secret-1',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zmUserA = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_math_1',
            'email' => 'math1@univ.edu',
            'synced_at' => now(),
            'status' => 'active',
        ]);

        $zmUserB = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_math_2',
            'email' => 'math2@univ.edu',
            'synced_at' => now(),
            'status' => 'active',
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Math Pool',
            'code' => 'MATH_POOL',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $this->resourceA = ZoomResource::create([
            'zoom_user_id' => $zmUserA->id,
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
            'priority' => 1,
        ]);

        $this->resourceB = ZoomResource::create([
            'zoom_user_id' => $zmUserB->id,
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
            'priority' => 2,
        ]);

        DB::table('resource_pool_members')->insert([
            ['pool_id' => $this->pool->id, 'resource_id' => $this->resourceA->id, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['pool_id' => $this->pool->id, 'resource_id' => $this->resourceB->id, 'priority' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        BookingPolicy::create([
            'name' => 'Math Dept Policy',
            'department_id' => $dept->id,
            'min_notice_hours' => 0,
            'max_advance_days' => 60,
            'min_buffer_minutes' => 10,
            'default_buffer_minutes' => 15,
            'max_duration_minutes' => 240,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        parent::tearDown();
    }

    public function test_recurrence_expansion_service_skips_blackouts(): void
    {
        $service = app(RecurrenceExpansionService::class);

        $startsAt = Carbon::parse('2026-10-05 09:00:00'); // Monday

        // Blackout period on Wednesday 2026-10-07
        BlackoutPeriod::create([
            'name' => 'Campus Holiday',
            'type' => 'holiday',
            'starts_at' => Carbon::parse('2026-10-07 00:00:00'),
            'ends_at' => Carbon::parse('2026-10-07 23:59:59'),
        ]);

        // Repeat Mon, Wed, Fri for 3 occurrences
        $occurrences = $service->expand(
            rruleString: 'FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=3',
            startsAt: $startsAt,
            durationMinutes: 60
        );

        $this->assertCount(3, $occurrences);
        // First is Mon (not skipped)
        $this->assertFalse($occurrences[0]['is_skipped']);
        $this->assertEquals('2026-10-05', $occurrences[0]['starts_at']->toDateString());

        // Second is Wed (skipped due to holiday)
        $this->assertTrue($occurrences[1]['is_skipped']);
        $this->assertStringContainsString('Campus Holiday', (string) $occurrences[1]['skip_reason']);
        $this->assertEquals('2026-10-07', $occurrences[1]['starts_at']->toDateString());

        // Third is Fri (not skipped)
        $this->assertFalse($occurrences[2]['is_skipped']);
        $this->assertEquals('2026-10-09', $occurrences[2]['starts_at']->toDateString());
    }

    public function test_single_resource_series_creation_allocates_identical_resource(): void
    {
        $service = app(SeriesAllocationService::class);

        $startsAt = Carbon::now()->addDays(5)->setHour(10)->setMinute(0);

        $series = $service->createSeries(
            requester: $this->user,
            data: [
                'title' => 'Linear Algebra I',
                'description' => 'Semester lectures',
                'meeting_type' => 'class',
                'rrule' => 'FREQ=WEEKLY;COUNT=3',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'participant_count' => 40,
                'series_mode' => 'SINGLE_RESOURCE',
                'preferred_pool_id' => $this->pool->id,
            ]
        );

        $this->assertInstanceOf(MeetingSeries::class, $series);
        $this->assertEquals('SINGLE_RESOURCE', $series->series_mode);
        $this->assertNotNull($series->zoom_resource_id);

        $meetings = $series->meetings()->orderBy('starts_at')->get();
        $this->assertCount(3, $meetings);

        // Every occurrence must share the exact same resource
        foreach ($meetings as $m) {
            $this->assertEquals($series->zoom_resource_id, $m->zoom_resource_id);
            $this->assertDatabaseHas('resource_reservations', [
                'meeting_id' => $m->id,
                'resource_id' => $series->zoom_resource_id,
                'status' => 'held',
            ]);
        }
    }

    public function test_single_resource_series_fails_when_resource_has_conflict_on_one_date(): void
    {
        $service = app(SeriesAllocationService::class);

        $startsAt = Carbon::now()->addDays(5)->setHour(10)->setMinute(0);

        // Block Resource A and B on the 2nd week occurrence
        $conflictDate = $startsAt->copy()->addWeek();
        ResourceReservation::create([
            'resource_id' => $this->resourceA->id,
            'occupied_from' => $conflictDate->copy()->subMinutes(10),
            'occupied_until' => $conflictDate->copy()->addMinutes(80),
            'status' => 'confirmed',
        ]);
        ResourceReservation::create([
            'resource_id' => $this->resourceB->id,
            'occupied_from' => $conflictDate->copy()->subMinutes(10),
            'occupied_until' => $conflictDate->copy()->addMinutes(80),
            'status' => 'confirmed',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No single resource in the pool is available for all occurrences');

        $service->createSeries(
            requester: $this->user,
            data: [
                'title' => 'Calculus Lecture',
                'rrule' => 'FREQ=WEEKLY;COUNT=3',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'participant_count' => 40,
                'series_mode' => 'SINGLE_RESOURCE',
                'preferred_pool_id' => $this->pool->id,
            ]
        );
    }

    public function test_occurrence_detachment_allows_independent_reallocation(): void
    {
        $seriesService = app(SeriesAllocationService::class);
        $detachmentService = app(OccurrenceDetachmentService::class);

        $startsAt = Carbon::now()->addDays(5)->setHour(10)->setMinute(0);

        $series = $seriesService->createSeries(
            requester: $this->user,
            data: [
                'title' => 'Abstract Algebra',
                'rrule' => 'FREQ=WEEKLY;COUNT=3',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'participant_count' => 20,
                'series_mode' => 'SINGLE_RESOURCE',
                'preferred_pool_id' => $this->pool->id,
            ]
        );

        $occurrence2 = $series->meetings()->where('occurrence_index', 2)->firstOrFail();
        $this->assertFalse($occurrence2->is_detached_from_series);

        // Detach occurrence 2 and reallocate to resourceB with new time
        $newStartsAt = $occurrence2->starts_at->copy()->addHours(2);
        $newEndsAt = $occurrence2->ends_at->copy()->addHours(2);

        $detached = $detachmentService->detachOccurrence(
            meeting: $occurrence2,
            actor: $this->user,
            newStartsAt: $newStartsAt,
            newEndsAt: $newEndsAt,
            newResource: $this->resourceB
        );

        $this->assertTrue($detached->fresh()->is_detached_from_series);
        $this->assertEquals($this->resourceB->id, $detached->fresh()->zoom_resource_id);

        // Occurrence 1 and 3 remain on original resource
        $occurrence1 = $series->meetings()->where('occurrence_index', 1)->firstOrFail();
        $this->assertEquals($series->zoom_resource_id, $occurrence1->zoom_resource_id);
        $this->assertFalse($occurrence1->is_detached_from_series);
    }

    public function test_cancel_series_cancels_all_future_occurrences_and_releases_reservations(): void
    {
        $seriesService = app(SeriesAllocationService::class);
        $detachmentService = app(OccurrenceDetachmentService::class);

        $startsAt = Carbon::now()->addDays(2)->setHour(11)->setMinute(0);

        $series = $seriesService->createSeries(
            requester: $this->user,
            data: [
                'title' => 'Topology Seminar',
                'rrule' => 'FREQ=WEEKLY;COUNT=3',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'participant_count' => 15,
                'series_mode' => 'SINGLE_RESOURCE',
                'preferred_pool_id' => $this->pool->id,
            ]
        );

        $detachmentService->cancelSeries($series, $this->user, 'Course cancelled due to low enrollment');

        $this->assertEquals('cancelled', $series->fresh()->status);

        $meetings = $series->meetings()->get();
        foreach ($meetings as $m) {
            $this->assertEquals('cancelled', $m->status);
            $this->assertStringContainsString('low enrollment', (string) $m->cancelled_reason);

            $reservation = ResourceReservation::where('meeting_id', $m->id)->first();
            $this->assertEquals('released', $reservation?->status);
        }
    }

    public function test_series_web_routes_render_successfully(): void
    {
        $seriesService = app(SeriesAllocationService::class);
        $startsAt = Carbon::now()->addDays(3)->setHour(9)->setMinute(0);

        $series = $seriesService->createSeries(
            requester: $this->user,
            data: [
                'title' => 'Geometry Lecture Series',
                'rrule' => 'FREQ=WEEKLY;COUNT=2',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_minutes' => 60,
                'participant_count' => 20,
                'series_mode' => 'SINGLE_RESOURCE',
                'preferred_pool_id' => $this->pool->id,
            ]
        );

        // Index
        $this->actingAs($this->user)
            ->get(route('series.index'))
            ->assertOk()
            ->assertSee('Geometry Lecture Series');

        // Create
        $this->actingAs($this->user)
            ->get(route('series.create'))
            ->assertOk()
            ->assertSee('Schedule Recurring Series');

        // Show
        $this->actingAs($this->user)
            ->get(route('series.show', $series->public_id))
            ->assertOk()
            ->assertSee('Series Overview')
            ->assertSee('Detach');
    }
}
