<?php

namespace Tests\Feature;

use App\Domain\Operations\Models\BackupRecord;
use App\Domain\Operations\Services\BackupService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsBackupsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'backup.manage', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $department = Department::create(['name' => 'IT Operations', 'code' => 'IT']);

        $this->adminUser = User::create([
            'name' => 'Backup Admin',
            'email' => 'backup@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');
    }

    public function test_backup_service_creates_verified_database_dump(): void
    {
        $service = app(BackupService::class);
        $record = $service->createDatabaseBackup($this->adminUser);

        $this->assertNotNull($record);
        $this->assertFileExists($record->file_path);
        $this->assertGreaterThan(0, $record->file_size_bytes);
        $this->assertNotEmpty($record->checksum);
        $this->assertNotNull($record->verified_at);
        $this->assertEquals('completed', $record->status);

        // Cleanup generated test backup file
        if (File::exists($record->file_path)) {
            File::delete($record->file_path);
        }
    }

    public function test_backup_web_interface_and_download_flow(): void
    {
        $service = app(BackupService::class);
        $record = $service->createDatabaseBackup($this->adminUser);

        // 1. View Backups List
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.backups'));

        $response->assertStatus(200);
        $response->assertSee($record->filename);

        // 2. Download Backup
        $downloadResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.backups.download', $record->public_id));

        $downloadResponse->assertStatus(200);

        // Cleanup
        if (File::exists($record->file_path)) {
            File::delete($record->file_path);
        }
    }

    public function test_backup_console_command_executes_successfully(): void
    {
        $this->artisan('zpm:backup:run')
            ->assertSuccessful();

        $latest = BackupRecord::latest('created_at')->first();
        $this->assertNotNull($latest);

        if (File::exists($latest->file_path)) {
            File::delete($latest->file_path);
        }
    }
}
