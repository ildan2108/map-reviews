<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_user_is_not_authenticated(): void
    {
        $response = $this->getJson('/api/organization/reviews');

        $response->assertUnauthorized();
    }

    public function test_returns_reviews_for_authenticated_users_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);

        Review::factory()->create([
            'organization_id' => $organization->id,
            'author_name' => 'Ivan',
            'rating' => 5,
            'text' => 'Отличное место!',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/organization/reviews');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'Ivan')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.text', 'Отличное место!');
    }

    public function test_returns_404_when_user_has_no_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/organization/reviews');

        $response
            ->assertNotFound()
            ->assertJsonPath('message', 'Организация не подключена.');
    }

    public function test_paginates_reviews_by_50(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);

        Review::factory()
            ->count(61)
            ->create([
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/organization/reviews');

        $response
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 61);
    }

    public function test_returns_second_page_of_reviews(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);

        Review::factory()
            ->count(61)
            ->create([
                'organization_id' => $organization->id,
            ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/organization/reviews?page=2');

        $response
            ->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 61);
    }

    public function test_does_not_return_reviews_from_another_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
        ]);

        $anotherOrganization = Organization::factory()->create();

        Review::factory()->create([
            'organization_id' => $organization->id,
            'author_name' => 'My review',
        ]);

        Review::factory()->create([
            'organization_id' => $anotherOrganization->id,
            'author_name' => 'Other review',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/organization/reviews');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'My review');
    }
}
