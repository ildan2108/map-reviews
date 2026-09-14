<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_user_is_not_authenticated(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_logs_in_user_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'demo@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_returns_422_when_credentials_are_invalid(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/login', [
            'email' => 'demo@example.com',
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'The provided credentials are incorrect.',
            ]);

        $this->assertGuest();
    }

    public function test_returns_422_when_login_payload_is_empty(): void
    {
        $this->postJson('/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logs_out_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertNoContent();

        $this->assertGuest();
    }
}
