<?php

namespace App\Console\Commands;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:demo:seed {--force : Force seeding even in non-demo mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed simulated departments, accounts, pools, and meetings for demo or evaluation mode.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('app.demo') && ! $this->option('force')) {
            $this->error('Demo mode is disabled. Pass --force to seed demo data anyway.');

            return self::FAILURE;
        }

        $this->info('Seeding Zoom Pool Manager Demo Data...');

        // 1. Departments
        $cs = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science & Engineering']);
        $biz = Department::firstOrCreate(['code' => 'BIZ'], ['name' => 'School of Business']);
        $med = Department::firstOrCreate(['code' => 'MED'], ['name' => 'Medical & Health Sciences']);

        // 2. Roles
        $adminRole = Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $facultyRole = Role::firstOrCreate(['name' => 'Faculty', 'guard_name' => 'web']);

        // 3. Demo Users
        $demoAdmin = User::firstOrCreate(['email' => 'admin@demo.local'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('password'),
            'department_id' => $cs->id,
            'is_active' => true,
        ]);
        $demoAdmin->assignRole($adminRole);

        $demoProf = User::firstOrCreate(['email' => 'professor@demo.local'], [
            'name' => 'Prof. Alan Turing',
            'password' => Hash::make('password'),
            'department_id' => $cs->id,
            'is_active' => true,
        ]);
        $demoProf->assignRole($facultyRole);

        // 4. Default Policy & Profiles
        BookingPolicy::firstOrCreate(['name' => 'Organization Standard Policy'], [
            'department_id' => null,
            'min_notice_hours' => 2,
            'max_advance_days' => 60,
            'min_buffer_minutes' => 10,
            'default_buffer_minutes' => 10,
            'is_active' => true,
        ]);

        $stdProfile = SecurityProfile::firstOrCreate(['code' => 'STD_ACAD'], [
            'name' => 'Standard Academic Session',
            'settings' => [
                'enforce_waiting_room' => true,
                'enforce_passcode' => true,
                'allow_join_before_host' => false,
                'mute_upon_entry' => true,
                'watermark' => false,
                'ai_companion_disabled' => true,
            ],
            'is_default' => true,
        ]);

        $examProfile = SecurityProfile::firstOrCreate(['code' => 'EXAM_SEC'], [
            'name' => 'High-Stakes Online Exam',
            'settings' => [
                'enforce_waiting_room' => true,
                'enforce_passcode' => true,
                'allow_join_before_host' => false,
                'mute_upon_entry' => true,
                'watermark' => true,
                'ai_companion_disabled' => true,
            ],
            'is_default' => false,
        ]);

        // 5. Templates
        MeetingTemplate::firstOrCreate(['code' => 'LECTURE_60'], [
            'name' => 'Interactive Lecture (60 min)',
            'description' => 'Standard classroom lecture with Q&A',
            'default_duration_minutes' => 60,
            'security_profile_id' => $stdProfile->id,
            'is_active' => true,
        ]);

        MeetingTemplate::firstOrCreate(['code' => 'EXAM_120'], [
            'name' => 'Proctored Assessment (120 min)',
            'description' => 'Strict proctored session with watermark enabled',
            'default_duration_minutes' => 120,
            'security_profile_id' => $examProfile->id,
            'is_active' => true,
        ]);

        // 6. Zoom Connection & Resources
        $connection = ZoomConnection::firstOrCreate(['name' => 'Simulated University Pool'], [
            'account_id' => 'demo_account_id',
            'client_id' => 'demo_client_id',
            'client_secret' => 'demo_client_secret',
            'status' => 'active',
            'enabled' => true,
        ]);

        $pool = ResourcePool::firstOrCreate(['code' => 'CAMPUS_POOL'], [
            'name' => 'General Academic Pool',
            'description' => 'Shared licenses for university departments',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $zoomUser = ZoomUser::firstOrCreate(['zoom_user_id' => "demo_zu_{$i}"], [
                'connection_id' => $connection->id,
                'email' => "host{$i}@demo.univ.edu",
                'user_type' => 2,
                'host_key' => str_pad((string) (100000 + $i * 1111), 6, '0', STR_PAD_LEFT),
                'synced_at' => now(),
            ]);

            $resource = ZoomResource::firstOrCreate(['zoom_user_id' => $zoomUser->id], [
                'name' => "Zoom License #{$i} (300 Seats)",
                'managed' => true,
                'participant_capacity' => 300,
            ]);

            if (! $pool->resources()->where('zoom_resources.id', $resource->id)->exists()) {
                $pool->resources()->attach($resource->id, ['priority' => $i]);
            }
        }

        // 7. Seed Sample Meeting
        Meeting::firstOrCreate(['title' => 'CS201: Data Structures Lecture'], [
            'description' => 'Binary search trees and AVL rebalancing lecture',
            'meeting_type' => 'scheduled',
            'status' => 'scheduled',
            'starts_at' => now()->addHours(3)->setMinute(0)->setSecond(0),
            'ends_at' => now()->addHours(4)->setMinute(0)->setSecond(0),
            'timezone' => 'Asia/Kolkata',
            'buffer_minutes' => 10,
            'participant_count' => 65,
            'requester_user_id' => $demoProf->id,
            'owner_user_id' => $demoProf->id,
            'department_id' => $cs->id,
            'security_profile_id' => $stdProfile->id,
            'zoom_resource_id' => $pool->resources()->first()?->id,
            'join_url' => 'https://zoom.us/j/demo9876543210',
            'zoom_meeting_id' => '9876543210',
        ]);

        $this->info('Demo data seeded successfully!');
        $this->line('  Admin login:    admin@demo.local / password');
        $this->line('  Faculty login:  professor@demo.local / password');

        return self::SUCCESS;
    }
}
