<?php

namespace Tests\Feature;

use App\Jobs\ParseOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_when_user_is_not_authenticated(): void
    {
        $this->getJson('/api/organization')
            ->assertUnauthorized();
    }

    public function test_creates_organization_in_pending_state_and_dispatches_parsing_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $url = 'https://yandex.ru/maps/org/coffee_shop/123456789';

        $this->actingAs($user)
            ->putJson('/api/organization', ['yandex_url' => $url])
            ->assertCreated()
            ->assertJsonPath('data.yandex_url', $url)
            ->assertJsonPath('data.parsing_status', 'pending');

        $organization = Organization::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_url' => $url,
            'parsing_status' => 'pending',
        ]);

        Queue::assertPushed(ParseOrganization::class, function (ParseOrganization $job) use ($organization): bool {
            return $job->organization->is($organization);
        });
    }

    public function test_returns_empty_data_when_user_has_no_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_updates_existing_organization_without_creating_duplicate_and_resets_parsing(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create([
            'parsing_status' => 'completed',
            'name' => 'Old name',
            'parsed_at' => now(),
        ]);
        $url = 'https://yandex.ru/maps/org/new_coffee_shop/987654321';

        $this->actingAs($organization->user)
            ->putJson('/api/organization', ['yandex_url' => $url])
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.yandex_url', $url)
            ->assertJsonPath('data.parsing_status', 'pending');

        $this->assertSame(1, Organization::query()->count());
    }

    public function test_returns_422_when_url_is_not_yandex_maps_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/organization', ['yandex_url' => 'https://example.com/company'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'yandex_url' => 'The yandex url must be a Yandex Maps organization link.',
            ]);
    }
}
