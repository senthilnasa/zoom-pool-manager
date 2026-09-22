<?php

namespace Tests\Feature;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingStateMachine;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MeetingStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected MeetingStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Dr. Test User',
            'email' => 'drtest@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $this->stateMachine = new MeetingStateMachine(app(AuditService::class));
    }

    public function test_can_transition_through_happy_path_lifecycle(): void
    {
        $meeting = Meeting::create([
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'title' => 'Test Lifecycle',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
            'status' => 'draft',
            'participant_count' => 10,
        ]);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'pending_approval'));
        $this->stateMachine->transitionTo($meeting, 'pending_approval', $this->user, 'User submitted request');
        $this->assertEquals('pending_approval', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'approved'));
        $this->stateMachine->transitionTo($meeting, 'approved', $this->user, 'Department manager approved');
        $this->assertEquals('approved', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'allocating'));
        $this->stateMachine->transitionTo($meeting, 'allocating', $this->user, 'Resource reservation triggered');
        $this->assertEquals('allocating', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'scheduled'));
        $this->stateMachine->transitionTo($meeting, 'scheduled', $this->user, 'Zoom API provisioned meeting');
        $this->assertEquals('scheduled', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'started'));
        $this->stateMachine->transitionTo($meeting, 'started', $this->user, 'Meeting webhook: started');
        $this->assertEquals('started', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'ended'));
        $this->stateMachine->transitionTo($meeting, 'ended', $this->user, 'Meeting webhook: ended');
        $this->assertEquals('ended', $meeting->fresh()->status);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'completed'));
        $this->stateMachine->transitionTo($meeting, 'completed', $this->user, 'Archived');
        $this->assertEquals('completed', $meeting->fresh()->status);

        $this->assertCount(7, $meeting->statusHistory);
    }

    public function test_illegal_transition_throws_exception(): void
    {
        $meeting = Meeting::create([
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'title' => 'Illegal Transition Test',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
            'status' => 'completed',
            'participant_count' => 10,
        ]);

        $this->assertFalse($this->stateMachine->canTransitionTo($meeting, 'started'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Illegal state transition from [completed] to [started]');

        $this->stateMachine->transitionTo($meeting, 'started', $this->user);
    }

    public function test_cancellation_is_permitted_from_scheduled_state(): void
    {
        $meeting = Meeting::create([
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'title' => 'Cancelable Meeting',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
            'status' => 'scheduled',
            'participant_count' => 5,
        ]);

        $this->assertTrue($this->stateMachine->canTransitionTo($meeting, 'cancelled'));
        $this->stateMachine->transitionTo($meeting, 'cancelled', $this->user, 'User cancelled');

        $this->assertEquals('cancelled', $meeting->fresh()->status);
        $this->assertEquals('User cancelled', $meeting->fresh()->cancelled_reason);
        $this->assertFalse($this->stateMachine->canTransitionTo($meeting, 'scheduled'));
    }
}
