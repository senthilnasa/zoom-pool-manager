<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\DirectorySyncConfig;
use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SsoAndDirectorySyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'IT Admin',
            'email' => 'admin.it@univ.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
    }

    public function test_identity_providers_crud_and_secret_masking(): void
    {
        // 1. Initial list should be empty or array
        $res = $this->actingAs($this->admin)->getJson('/spa/settings/identity-providers');
        $res->assertStatus(200);
        $res->assertJsonStructure(['providers']);

        // 2. Create Google Provider
        $createGoogle = $this->actingAs($this->admin)->postJson('/spa/settings/identity-providers', [
            'name' => 'University Google Workspace',
            'driver' => 'google',
            'client_id' => 'test-google-client-id.apps.googleusercontent.com',
            'client_secret' => 'super_secret_google_key',
            'allowed_domains' => ['univ.edu'],
            'enabled' => true,
        ]);
        $createGoogle->assertStatus(201);
        $googlePublicId = $createGoogle->json('provider.public_id');
        $this->assertNotEmpty($googlePublicId);

        // 3. Create SAML Provider
        $createSaml = $this->actingAs($this->admin)->postJson('/spa/settings/identity-providers', [
            'name' => 'Campus Shibboleth SAML',
            'driver' => 'saml',
            'metadata_url' => 'https://idp.univ.edu/idp/profile/SAML2/Redirect/SSO',
            'certificate_primary' => "-----BEGIN CERTIFICATE-----\nMIIDSample...\n-----END CERTIFICATE-----",
            'allowed_domains' => ['univ.edu', 'med.univ.edu'],
            'enabled' => true,
        ]);
        $createSaml->assertStatus(201);
        $samlPublicId = $createSaml->json('provider.public_id');

        // 4. Verify Secret Masking in list
        $listRes = $this->actingAs($this->admin)->getJson('/spa/settings/identity-providers');
        $listRes->assertStatus(200);
        $providers = $listRes->json('providers');
        $this->assertCount(2, $providers);

        $googleItem = collect($providers)->firstWhere('public_id', $googlePublicId);
        $this->assertTrue($googleItem['has_client_secret']);
        $this->assertArrayNotHasKey('client_secret', $googleItem); // Ensure secret is never exposed

        $samlItem = collect($providers)->firstWhere('public_id', $samlPublicId);
        $this->assertTrue($samlItem['has_primary_cert']);

        // 5. Update Provider
        $updateRes = $this->actingAs($this->admin)->putJson("/spa/settings/identity-providers/{$googlePublicId}", [
            'name' => 'Updated Google SSO',
            'enabled' => false,
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('identity_providers', [
            'public_id' => $googlePublicId,
            'name' => 'Updated Google SSO',
            'enabled' => false,
        ]);

        // 6. Delete Provider
        $delRes = $this->actingAs($this->admin)->deleteJson("/spa/settings/identity-providers/{$googlePublicId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('identity_providers', ['public_id' => $googlePublicId]);
    }

    public function test_saml_sp_metadata_generation_and_diagnostics(): void
    {
        $saml = IdentityProvider::create([
            'name' => 'Okta Enterprise SAML',
            'driver' => 'saml',
            'metadata_url' => 'https://okta.univ.edu/app/sso',
            'certificate_primary' => "-----BEGIN CERTIFICATE-----\nMIIB...\n-----END CERTIFICATE-----",
            'enabled' => true,
        ]);

        // 1. Diagnostics endpoint
        $diagRes = $this->actingAs($this->admin)->postJson("/spa/settings/identity-providers/{$saml->public_id}/test");
        $diagRes->assertStatus(200);
        $diagRes->assertJson(['success' => true]);

        // 2. SP Metadata XML endpoint
        $metaRes = $this->actingAs($this->admin)->get("/spa/settings/identity-providers/{$saml->public_id}/sp-metadata");
        $metaRes->assertStatus(200);
        $metaRes->assertHeader('Content-Type', 'application/samlmetadata+xml; charset=UTF-8');
        $xml = $metaRes->getContent();
        $this->assertStringContainsString('<md:EntityDescriptor', $xml);
        $this->assertStringContainsString('<md:AssertionConsumerService', $xml);
        $this->assertStringContainsString($saml->public_id, $xml);
    }

    public function test_directory_sync_config_crud(): void
    {
        // 1. List
        $res = $this->actingAs($this->admin)->getJson('/spa/settings/directory-sync');
        $res->assertStatus(200);

        // 2. Create Microsoft Entra ID Config
        $createRes = $this->actingAs($this->admin)->postJson('/spa/settings/directory-sync', [
            'name' => 'Microsoft Entra ID Staff Sync',
            'provider_type' => 'microsoft_entra',
            'tenant_id' => 'test-azure-tenant-id-123',
            'client_id' => 'test-azure-client-id-456',
            'client_secret' => 'super_secret_azure_client_secret',
            'domain_filter' => 'univ.edu',
            'default_role' => 'Standard User',
            'auto_create_departments' => true,
            'is_active' => true,
        ]);
        $createRes->assertStatus(201);
        $publicId = $createRes->json('config.public_id');

        // 3. Verify in DB
        $this->assertDatabaseHas('directory_sync_configs', [
            'public_id' => $publicId,
            'name' => 'Microsoft Entra ID Staff Sync',
            'provider_type' => 'microsoft_entra',
        ]);

        // 4. Test connection
        $testRes = $this->actingAs($this->admin)->postJson("/spa/settings/directory-sync/{$publicId}/test");
        $testRes->assertStatus(200);
        $testRes->assertJson(['success' => true]);

        // 5. Update
        $updRes = $this->actingAs($this->admin)->putJson("/spa/settings/directory-sync/{$publicId}", [
            'name' => 'Updated Entra ID Sync',
            'sync_interval_minutes' => 30,
        ]);
        $updRes->assertStatus(200);
        $this->assertDatabaseHas('directory_sync_configs', [
            'public_id' => $publicId,
            'name' => 'Updated Entra ID Sync',
            'sync_interval_minutes' => 30,
        ]);

        // 6. Delete
        $delRes = $this->actingAs($this->admin)->deleteJson("/spa/settings/directory-sync/{$publicId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('directory_sync_configs', ['public_id' => $publicId]);
    }

    public function test_directory_sync_execution_provisions_users_and_departments(): void
    {
        $config = DirectorySyncConfig::create([
            'name' => 'Campus Directory Connector',
            'provider_type' => 'microsoft_entra',
            'domain_filter' => 'univ.edu',
            'default_role' => 'Standard User',
            'auto_create_departments' => true,
            'is_active' => true,
        ]);

        // Trigger sync
        $syncRes = $this->actingAs($this->admin)->postJson("/spa/settings/directory-sync/{$config->public_id}/sync-now");
        $syncRes->assertStatus(200);
        $syncRes->assertJson(['success' => true]);

        $this->assertGreaterThan(0, $syncRes->json('total_scanned'));
        $this->assertGreaterThan(0, $syncRes->json('created'));

        // Verify users were provisioned
        $this->assertDatabaseHas('users', [
            'email' => 'dr.carter@univ.edu',
            'name' => 'Dr. Robert Carter',
        ]);

        $user = User::where('email', 'dr.carter@univ.edu')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Standard User'));

        // Verify department was auto-created and assigned
        $this->assertNotNull($user->department_id);
        $dept = Department::find($user->department_id);
        $this->assertEquals('Computer Science', $dept->name);

        // Verify telemetry was saved
        $config->refresh();
        $this->assertEquals('success', $config->last_sync_status);
        $this->assertNotNull($config->last_synced_at);
        $this->assertEquals(5, $config->last_sync_stats['total_scanned']);
    }

    public function test_directory_sync_artisan_command(): void
    {
        DirectorySyncConfig::create([
            'name' => 'Scheduled Artisan Connector',
            'provider_type' => 'google_workspace',
            'domain_filter' => 'univ.edu',
            'default_role' => 'Standard User',
            'auto_create_departments' => true,
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('zpm:directory:sync');
        $this->assertEquals(0, $exitCode);

        // Verify Google Directory accounts were provisioned
        $this->assertDatabaseHas('users', [
            'email' => 'alan.turing@univ.edu',
            'name' => 'Alan Turing',
        ]);
        $this->assertDatabaseHas('departments', [
            'name' => 'Informatics',
        ]);
    }
}
