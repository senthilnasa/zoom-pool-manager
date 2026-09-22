<?php

namespace Tests\Feature;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\HostControl\Services\FakeMeetingHostProvider;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HardeningAndDemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_command_creates_demo_environment(): void
    {
        $exitCode = Artisan::call('zpm:demo:seed', ['--force' => true]);

        $this->assertEquals(0, $exitCode);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@demo.local',
        ]);

        $this->assertDatabaseHas('departments', [
            'code' => 'CS',
        ]);

        $this->assertDatabaseHas('resource_pools', [
            'code' => 'CAMPUS_POOL',
        ]);

        $this->assertDatabaseHas('meetings', [
            'title' => 'CS201: Data Structures Lecture',
        ]);
    }

    public function test_fake_meeting_host_provider_generates_simulated_credentials(): void
    {
        $provider = new FakeMeetingHostProvider;

        $meeting = new Meeting([
            'public_id' => '01DEMO99887766554433221100',
        ]);

        $startUrl = $provider->getStartUrl($meeting);

        $this->assertStringContainsString('https://zoom.us/s/demo_01DEMO99887766554433221100', $startUrl);
        $this->assertStringContainsString('zak=demo_zak_', $startUrl);
    }

    public function test_fake_meeting_host_provider_resolves_via_container_when_demo_active(): void
    {
        config(['app.demo' => true]);

        $provider = app(MeetingHostProviderInterface::class);
        $this->assertInstanceOf(FakeMeetingHostProvider::class, $provider);

        // Reset
        config(['app.demo' => false]);
    }

    public function test_localization_translations_load_correctly(): void
    {
        $this->assertEquals('Automate Your Zoom Resource Pool', trans('zpm.tagline', [], 'en'));
        $this->assertStringContainsString('திறந்த மூல', trans('zpm.disclaimer', [], 'ta'));
        $this->assertStringContainsString('स्वतंत्र ओपन-सोर्स', trans('zpm.disclaimer', [], 'hi'));
    }

    public function test_demo_banner_renders_when_demo_mode_enabled(): void
    {
        config(['app.demo' => true]);

        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demouser@demo.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('DEMO MODE ACTIVE', false);

        // Reset
        config(['app.demo' => false]);
    }
}
