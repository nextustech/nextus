<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_login_page_and_login(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $this->get('/login')->assertOk();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'success',
        ]);
    }

    public function test_failed_login_records_activity(): void
    {
        $this->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('login_activities', [
            'email' => 'missing@example.com',
            'status' => 'failed',
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_api_token_endpoint_and_authenticated_user_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $tokenResponse = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-suite',
        ]);

        $tokenResponse->assertOk()->assertJsonStructure(['token']);

        Sanctum::actingAs($user);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
