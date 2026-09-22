<?php

namespace App\Console\Commands;

use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Communication\Services\MeetingNotificationService;
use App\Domain\Meetings\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMeetingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:notifications:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 15-minute start reminders with join links for upcoming meetings';

    /**
     * Execute the console command.
     */
    public function handle(MeetingNotificationService $notificationService): int
    {
        $now = Carbon::now();
        $cutoff = (clone $now)->addMinutes(20);

        // Find meetings starting soon
        $meetings = Meeting::whereIn('status', ['allocating', 'scheduled'])
            ->where('starts_at', '>=', $now)
            ->where('starts_at', '<=', $cutoff)
            ->with(['owner', 'requester', 'invitees'])
            ->get();

        $sentCount = 0;

        foreach ($meetings as $meeting) {
            // Check if reminder was already sent for this meeting
            $alreadySent = EmailDelivery::where('meeting_id', $meeting->id)
                ->where('template_key', 'start_reminder')
                ->exists();

            if (! $alreadySent) {
                $notificationService->notifyStartReminder($meeting);
                $sentCount++;
                $this->info("Dispatched reminder for meeting [{$meeting->public_id}]: {$meeting->title}");
            }
        }

        $this->info("Completed. Total reminders dispatched: {$sentCount}");

        return self::SUCCESS;
    }
}
