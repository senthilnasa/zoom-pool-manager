<?php

namespace Tests\Feature;

use App\Domain\Operations\Services\PrivacyAndRetentionService;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected PrivacyAndRetentionService $privacyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->regularUser = User::create([
            'name' => 'Alice Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->privacyService = app(PrivacyAndRetentionService::class);
    }

    public function test_export_user_data_generates_json_file_and_audit(): void
    {
        $export = $this->privacyService->exportUserData($this->regularUser, $this->adminUser);

        $this->assertNotNull($export);
        $this->assertEquals($this->regularUser->id, $export->user_id);
        $this->assertEquals($this->adminUser->id, $export->requested_by_user_id);
        $this->assertEquals('completed', $export->status);
        $this->assertTrue(File::exists($export->file_path));

        $content = json_decode((string) File::get($export->file_path), true);
        $this->assertIsArray($content);
        $this->assertEquals('Alice Member', $content['user']['name']);
        $this->assertEquals('member@example.com', $content['user']['email']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.data_exported',
            'auditable_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
        ]);

        // Cleanup
        if (File::exists($export->file_path)) {
            File::delete($export->file_path);
        }
    }

    public function test_anonymize_user_redacts_pii_and_deactivates_account(): void
    {
        $this->privacyService->anonymizeUser($this->regularUser, $this->adminUser, 'GDPR Right to Be Forgotten');

        $this->regularUser->refresh();

        $this->assertEquals("Anonymized User #{$this->regularUser->id}", $this->regularUser->name);
        $this->assertEquals("anonymized_{$this->regularUser->id}@redacted.local", $this->regularUser->email);
        $this->assertFalse($this->regularUser->is_active);
        $this->assertNull($this->regularUser->totp_secret);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.anonymized',
            'auditable_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_purge_old_records_deletes_records_beyond_retention_cutoff(): void
    {
        // Insert old and new records in zoom_webhook_events
        DB::table('zoom_webhook_events')->insert([
            'public_id' => (string) Str::ulid(),
            'event_id' => 'evt_old_123',
            'event_type' => 'meeting.ended',
            'payload' => json_encode(['test' => true]),
            'status' => 'processed',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        DB::table('zoom_webhook_events')->insert([
            'public_id' => (string) Str::ulid(),
            'event_id' => 'evt_new_456',
            'event_type' => 'meeting.started',
            'payload' => json_encode(['test' => true]),
            'status' => 'processed',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $results = $this->privacyService->purgeOldRecords([
            'zoom_webhook_events' => 30,
        ], $this->adminUser);

        $this->assertEquals(1, $results['zoom_webhook_events']);

        $this->assertDatabaseMissing('zoom_webhook_events', [
            'event_id' => 'evt_old_123',
        ]);

        $this->assertDatabaseHas('zoom_webhook_events', [
            'event_id' => 'evt_new_456',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'data.retention_purged',
            'actor_user_id' => $this->adminUser->id,
        ]);
    }
}
