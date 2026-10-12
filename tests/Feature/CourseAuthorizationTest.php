<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_can_view_courses_and_active_course(): void
    {
        $response = $this->get('/courses');
        $response->assertStatus(200);

        $response = $this->get('/courses/1');
        $response->assertStatus(200);
    }

    public function test_guest_cannot_view_inactive_course(): void
    {
        // Course 3 is Ana's inactive course
        $response = $this->get('/courses/3');
        $response->assertStatus(403);
    }

    public function test_guest_redirected_to_login_on_protected_routes(): void
    {
        $this->get('/courses/create')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_student_cannot_create_or_edit_or_access_admin(): void
    {
        $carla = User::where('email', 'carla@example.com')->first();

        $this->actingAs($carla)->get('/courses/create')->assertStatus(403);
        $this->actingAs($carla)->get('/courses/4/edit')->assertStatus(403);
        $this->actingAs($carla)->get('/admin')->assertStatus(403);
    }

    public function test_student_can_view_course_without_edit_or_delete_buttons(): void
    {
        $carla = User::where('email', 'carla@example.com')->first();

        $response = $this->actingAs($carla)->get('/courses/1');
        $response->assertStatus(200);
        $response->assertDontSee('Edit</a>', false);
        $response->assertDontSee('Delete</button>', false);
    }

    public function test_instructor_can_create_and_manage_own_courses(): void
    {
        $ana = User::where('email', 'ana@example.com')->first();

        // Can edit own course
        $this->actingAs($ana)->get('/courses/1/edit')->assertStatus(200);

        // Can view own inactive course
        $this->actingAs($ana)->get('/courses/3')->assertStatus(200);

        // Cannot edit another instructor's course
        $this->actingAs($ana)->get('/courses/4/edit')->assertStatus(403);

        // Cannot delete course with enrolled students (Course 1 has Carla)
        $this->actingAs($ana)->delete('/courses/1')->assertStatus(403);

        // Can delete own course without enrolled students (Course 2)
        $this->actingAs($ana)->delete('/courses/2')->assertRedirect('/courses');
    }

    public function test_instructor_cannot_view_another_instructor_inactive_course(): void
    {
        $ben = User::where('email', 'ben@example.com')->first();

        // Cannot view Ana's inactive course
        $this->actingAs($ben)->get('/courses/3')->assertStatus(403);
    }

    public function test_admin_has_full_access(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        // Admin can edit any course
        $this->actingAs($admin)->get('/courses/4/edit')->assertStatus(200);

        // Admin can access /admin
        $this->actingAs($admin)->get('/admin')->assertStatus(200);

        // Admin can delete course 1 even with enrolled students (before hook allows all)
        $this->actingAs($admin)->delete('/courses/1')->assertRedirect('/courses');
    }

    public function test_new_registration_defaults_to_student_and_cannot_access_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertEquals('student', $user->role);

        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }
}
