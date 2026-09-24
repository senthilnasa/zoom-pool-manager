<?php

namespace Tests\Feature;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDesignationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.designation@univ.edu',
            'password' => bcrypt('password'),
            'designation' => 'Director of Academic Computing',
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
    }

    public function test_can_create_user_with_designation(): void
    {
        $payload = [
            'name' => 'Prof. Alan Turing',
            'email' => 'a.turing@univ.edu',
            'password' => 'SecurePass123!',
            'designation' => 'Chair of Mathematical Foundations',
            'role' => 'User',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/spa/users', $payload);
        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'a.turing@univ.edu',
            'designation' => 'Chair of Mathematical Foundations',
        ]);
    }

    public function test_can_update_user_designation(): void
    {
        $user = User::create([
            'name' => 'Dr. Grace Hopper',
            'email' => 'g.hopper@univ.edu',
            'password' => bcrypt('password'),
            'designation' => 'Associate Lecturer',
            'is_active' => true,
        ]);

        $payload = [
            'id' => $user->id,
            'name' => 'Dr. Grace Hopper',
            'email' => 'g.hopper@univ.edu',
            'designation' => 'Distinguished Professor of Computer Science',
            'role' => 'User',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/spa/users', $payload);
        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'designation' => 'Distinguished Professor of Computer Science',
        ]);
    }

    public function test_user_search_matches_designation(): void
    {
        User::create([
            'name' => 'Katherine Johnson',
            'email' => 'k.johnson@univ.edu',
            'password' => bcrypt('password'),
            'designation' => 'Senior Orbital Dynamics Specialist',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Richard Feynman',
            'email' => 'r.feynman@univ.edu',
            'password' => bcrypt('password'),
            'designation' => 'Theoretical Quantum Physicist',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/spa/users?search=Orbital');
        $response->assertOk();

        $items = $response->json('users.data');
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertEquals('Katherine Johnson', $items[0]['name']);
        $this->assertEquals('Senior Orbital Dynamics Specialist', $items[0]['designation']);
    }
}
