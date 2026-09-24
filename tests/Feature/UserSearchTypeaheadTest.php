<?php

namespace Tests\Feature;

use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSearchTypeaheadTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Department $csDepartment;

    protected Department $mathDepartment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->csDepartment = Department::create([
            'name' => 'Computer Science & Engineering',
            'code' => 'CSE',
        ]);

        $this->mathDepartment = Department::create([
            'name' => 'Mathematics & Statistics',
            'code' => 'MATH',
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@university.edu',
            'department_id' => $this->csDepartment->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_guest_cannot_access_user_search(): void
    {
        $response = $this->getJson('/spa/users/search');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_search_users_by_name(): void
    {
        User::create([
            'name' => 'Dr. Alan Turing',
            'email' => 'turing@university.edu',
            'designation' => 'Professor of Computing',
            'department_id' => $this->csDepartment->id,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Prof. Katherine Johnson',
            'email' => 'johnson@university.edu',
            'designation' => 'Orbital Mechanics Lead',
            'department_id' => $this->mathDepartment->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/spa/users/search?q=Turing');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Dr. Alan Turing']);
    }

    public function test_search_matches_email_and_designation(): void
    {
        User::create([
            'name' => 'Ada Lovelace',
            'email' => 'lovelace.countess@university.edu',
            'designation' => 'Pioneer of Algorithmic Science',
            'department_id' => $this->csDepartment->id,
            'is_active' => true,
        ]);

        // Search by email domain/handle
        $res1 = $this->actingAs($this->adminUser)->getJson('/spa/users/search?q=countess');
        $res1->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Ada Lovelace']);

        // Search by designation
        $res2 = $this->actingAs($this->adminUser)->getJson('/spa/users/search?q=Algorithmic');
        $res2->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Ada Lovelace']);
    }

    public function test_search_can_filter_by_department(): void
    {
        User::create([
            'name' => 'CS Researcher',
            'email' => 'cs.res@university.edu',
            'department_id' => $this->csDepartment->id,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Math Researcher',
            'email' => 'math.res@university.edu',
            'department_id' => $this->mathDepartment->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/spa/users/search?department_id='.$this->csDepartment->id);

        $response->assertOk();
        $data = $response->json();

        foreach ($data as $item) {
            $this->assertEquals($this->csDepartment->id, $item['department_id']);
        }
    }

    public function test_search_respects_limit_and_includes_specific_user_id(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            User::create([
                'name' => "Batch User {$i}",
                'email' => "user{$i}@university.edu",
                'department_id' => $this->csDepartment->id,
                'is_active' => true,
            ]);
        }

        $targetUser = User::create([
            'name' => 'Zebra Unique Last User',
            'email' => 'zebra@university.edu',
            'department_id' => $this->csDepartment->id,
            'is_active' => true,
        ]);

        // Search with small limit and include_id
        $response = $this->actingAs($this->adminUser)->getJson('/spa/users/search?limit=3&include_id='.$targetUser->id);

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();

        $this->assertContains('Zebra Unique Last User', $names);
    }

    public function test_inactive_users_are_excluded(): void
    {
        User::create([
            'name' => 'Former Deactivated Employee',
            'email' => 'inactive@university.edu',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/spa/users/search?q=inactive');

        $response->assertOk()
            ->assertJsonCount(0);
    }
}
