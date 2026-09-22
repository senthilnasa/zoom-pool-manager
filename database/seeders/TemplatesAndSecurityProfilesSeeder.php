<?php

namespace Database\Seeders;

use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use Illuminate\Database\Seeder;

class TemplatesAndSecurityProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Default Security Profiles (SPEC Part H5)
        $standardProfile = SecurityProfile::firstOrCreate(['code' => 'STANDARD'], [
            'name' => 'Standard Security',
            'is_default' => true,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'allow_recording' => true,
                'ai_companion' => 'ALLOWED',
            ],
        ]);

        $internalProfile = SecurityProfile::firstOrCreate(['code' => 'INTERNAL'], [
            'name' => 'Internal Organization Only',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'authenticated_users_only' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'allow_recording' => true,
                'ai_companion' => 'ALLOWED',
            ],
        ]);

        $confidentialProfile = SecurityProfile::firstOrCreate(['code' => 'CONFIDENTIAL'], [
            'name' => 'Confidential & Executive',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'authenticated_users_only' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'allow_recording' => false,
                'ai_companion' => 'DISABLED',
            ],
        ]);

        $examProfile = SecurityProfile::firstOrCreate(['code' => 'EXAM'], [
            'name' => 'Online Examination / Proctored',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'participant_rename' => false,
                'allow_recording' => true,
                'ai_companion' => 'DISABLED',
            ],
        ]);

        $interviewProfile = SecurityProfile::firstOrCreate(['code' => 'INTERVIEW'], [
            'name' => 'Interview / Candidate Assessment',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => false,
                'allow_recording' => true,
                'ai_companion' => 'ALLOWED',
            ],
        ]);

        $externalProfile = SecurityProfile::firstOrCreate(['code' => 'EXTERNAL_EVENT'], [
            'name' => 'External Public Event',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'allow_recording' => true,
                'ai_companion' => 'ALLOWED',
            ],
        ]);

        $webinarProfile = SecurityProfile::firstOrCreate(['code' => 'PUBLIC_WEBINAR'], [
            'name' => 'Public Large Webinar',
            'is_default' => false,
            'settings' => [
                'waiting_room' => false,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'allow_recording' => true,
                'ai_companion' => 'ALLOWED',
            ],
        ]);

        // 2. Default Meeting Templates (SPEC Part H5)
        $templates = [
            [
                'code' => 'FACULTY_CLASS',
                'name' => 'Faculty Class',
                'description' => 'Regular academic course lecture or seminar session.',
                'security_profile_id' => $standardProfile->id,
                'default_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'max_participants' => 100,
                'requires_approval' => false,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'FACULTY_MEETING',
                'name' => 'Faculty Meeting',
                'description' => 'Departmental or inter-departmental committee meeting.',
                'security_profile_id' => $internalProfile->id,
                'default_duration_minutes' => 60,
                'max_duration_minutes' => 120,
                'max_participants' => 50,
                'requires_approval' => false,
                'recording_mode' => 'none',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'STUDENT_MEETING',
                'name' => 'Student Meeting',
                'description' => 'Advising, student project, or study group.',
                'security_profile_id' => $standardProfile->id,
                'default_duration_minutes' => 45,
                'max_duration_minutes' => 90,
                'max_participants' => 30,
                'requires_approval' => false,
                'recording_mode' => 'none',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'INTERVIEW',
                'name' => 'Interview',
                'description' => 'Candidate recruitment or student admissions interview.',
                'security_profile_id' => $interviewProfile->id,
                'default_duration_minutes' => 45,
                'max_duration_minutes' => 90,
                'max_participants' => 10,
                'requires_approval' => true,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'PER_OCCURRENCE',
            ],
            [
                'code' => 'EXAM',
                'name' => 'Exam',
                'description' => 'Strictly proctored academic examination.',
                'security_profile_id' => $examProfile->id,
                'default_duration_minutes' => 120,
                'max_duration_minutes' => 240,
                'max_participants' => 150,
                'requires_approval' => true,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'DISABLED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'TRAINING',
                'name' => 'Training',
                'description' => 'Staff or student professional development training.',
                'security_profile_id' => $standardProfile->id,
                'default_duration_minutes' => 90,
                'max_duration_minutes' => 240,
                'max_participants' => 100,
                'requires_approval' => false,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'EXTERNAL_EVENT',
                'name' => 'External Event',
                'description' => 'Public conference, guest presentation, or outreach event.',
                'security_profile_id' => $externalProfile->id,
                'default_duration_minutes' => 90,
                'max_duration_minutes' => 300,
                'max_participants' => 200,
                'requires_approval' => true,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'CONFIDENTIAL',
                'name' => 'Confidential',
                'description' => 'Executive council, disciplinary hearing, or sensitive matter.',
                'security_profile_id' => $confidentialProfile->id,
                'default_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'max_participants' => 25,
                'requires_approval' => true,
                'recording_mode' => 'none',
                'ai_companion_policy' => 'DISABLED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
            [
                'code' => 'WEBINAR',
                'name' => 'Webinar',
                'description' => 'High-capacity institutional webinar broadcast.',
                'security_profile_id' => $webinarProfile->id,
                'default_duration_minutes' => 90,
                'max_duration_minutes' => 300,
                'max_participants' => 500,
                'requires_approval' => true,
                'recording_mode' => 'cloud',
                'ai_companion_policy' => 'ALLOWED',
                'series_mode' => 'SINGLE_RESOURCE',
            ],
        ];

        foreach ($templates as $data) {
            MeetingTemplate::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
