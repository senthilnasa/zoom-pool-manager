<?php

namespace Database\Seeders;

use App\Domain\Communication\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'meeting_requested',
                'name' => 'Meeting Approval Requested',
                'subject_template' => 'Approval Requested: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>A new meeting request requires your sign-off: <strong>{{meeting.title}}</strong>.</p><p><strong>Starts:</strong> {{meeting.starts_at}}<br><strong>Duration:</strong> {{meeting.duration_minutes}} min<br><strong>Requested by:</strong> {{recipient.name}}</p><p><a href="{{review_url}}" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Review Approval Request</a></p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nA meeting request requires your sign-off: {{meeting.title}}.\nStarts: {{meeting.starts_at}}\nDuration: {{meeting.duration_minutes}} min\n\nReview at: {{review_url}}",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'meeting.duration_minutes', 'review_url', 'recipient.name'],
            ],
            [
                'key' => 'meeting_approved',
                'name' => 'Meeting Request Approved',
                'subject_template' => 'Meeting Approved: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>Good news! Your meeting request <strong>{{meeting.title}}</strong> has been approved.</p><p><strong>Starts:</strong> {{meeting.starts_at}}<br><strong>Join Link:</strong> <a href="{{meeting.join_url}}">{{meeting.join_url}}</a></p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nYour meeting {{meeting.title}} has been approved.\nStarts: {{meeting.starts_at}}\nJoin: {{meeting.join_url}}",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'meeting.join_url', 'recipient.name'],
            ],
            [
                'key' => 'meeting_rejected',
                'name' => 'Meeting Request Rejected',
                'subject_template' => 'Meeting Request Declined: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>Your meeting request <strong>{{meeting.title}}</strong> was declined.</p><p><strong>Reason:</strong> {{reason}}</p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nYour meeting request {{meeting.title}} was declined.\nReason: {{reason}}",
                'available_variables' => ['meeting.title', 'reason', 'recipient.name'],
            ],
            [
                'key' => 'meeting_confirmed',
                'name' => 'Meeting Booking Confirmed',
                'subject_template' => 'Confirmed: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>Your meeting <strong>{{meeting.title}}</strong> is confirmed. A calendar invite (.ics) is attached.</p><p><strong>Starts:</strong> {{meeting.starts_at}}<br><strong>Join URL:</strong> <a href="{{meeting.join_url}}">{{meeting.join_url}}</a><br><strong>Passcode:</strong> {{meeting.passcode}}</p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nYour meeting {{meeting.title}} is confirmed.\nStarts: {{meeting.starts_at}}\nJoin: {{meeting.join_url}}\nPasscode: {{meeting.passcode}}",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'meeting.ends_at', 'meeting.join_url', 'meeting.passcode', 'recipient.name'],
            ],
            [
                'key' => 'meeting_cancelled',
                'name' => 'Meeting Cancelled',
                'subject_template' => 'Cancelled: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>The meeting <strong>{{meeting.title}}</strong> scheduled for {{meeting.starts_at}} has been cancelled.</p><p><strong>Reason:</strong> {{reason}}</p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nThe meeting {{meeting.title}} on {{meeting.starts_at}} has been cancelled.\nReason: {{reason}}",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'reason', 'recipient.name'],
            ],
            [
                'key' => 'meeting_waitlisted',
                'name' => 'Meeting Placed on Waitlist',
                'subject_template' => 'Waitlisted: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>All Zoom resources are currently occupied for the requested slot. Your meeting <strong>{{meeting.title}}</strong> has been added to the priority waitlist.</p><p>If another meeting cancels, your slot will be allocated automatically.</p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nYour meeting {{meeting.title}} has been placed on the priority waitlist. If a resource opens up, you will be allocated automatically.",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'recipient.name'],
            ],
            [
                'key' => 'waitlist_allocated',
                'name' => 'Waitlist Slot Allocated',
                'subject_template' => 'Good News: {{meeting.title}} Allocated from Waitlist',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>A Zoom resource slot has freed up! Your waitlisted meeting <strong>{{meeting.title}}</strong> has been confirmed.</p><p><strong>Starts:</strong> {{meeting.starts_at}}<br><strong>Join URL:</strong> <a href="{{meeting.join_url}}">{{meeting.join_url}}</a></p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nA Zoom resource has freed up! Your meeting {{meeting.title}} is now confirmed.\nStarts: {{meeting.starts_at}}\nJoin: {{meeting.join_url}}",
                'available_variables' => ['meeting.title', 'meeting.starts_at', 'meeting.join_url', 'recipient.name'],
            ],
            [
                'key' => 'start_reminder',
                'name' => '15-Minute Start Reminder',
                'subject_template' => 'Starting in 15 Minutes: {{meeting.title}}',
                'body_html_template' => '<p>Hello {{recipient.name}},</p><p>Your meeting <strong>{{meeting.title}}</strong> starts in 15 minutes.</p><p><a href="{{meeting.join_url}}" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Join Meeting</a></p>',
                'body_text_template' => "Hello {{recipient.name}},\n\nYour meeting {{meeting.title}} starts in 15 minutes.\nJoin: {{meeting.join_url}}",
                'available_variables' => ['meeting.title', 'meeting.join_url', 'recipient.name'],
            ],
        ];

        foreach ($templates as $t) {
            EmailTemplate::updateOrCreate(
                ['key' => $t['key']],
                [
                    'name' => $t['name'],
                    'subject_template' => $t['subject_template'],
                    'body_html_template' => $t['body_html_template'],
                    'body_text_template' => $t['body_text_template'],
                    'available_variables' => $t['available_variables'],
                    'is_active' => true,
                    'locale' => 'en',
                ]
            );
        }
    }
}
