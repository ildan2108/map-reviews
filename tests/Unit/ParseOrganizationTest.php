<?php

namespace Tests\Unit;

use App\Exceptions\YandexMapsSourceException;
use App\Jobs\ParseOrganization;
use App\Models\Organization;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParseOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_successful_parsing_updates_organization_and_marks_it_completed(): void
    {
        $organization = Organization::factory()->create(['parsing_status' => 'pending']);

        $parser = $this->mock(YandexMapsParser::class);
        $parser->shouldReceive('parse')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn([
                'name' => 'Coffee Shop',
                'average_rating' => 4.7,
                'ratings_count' => 123,
                'reviews_count' => 45,
            ]);

        $job = new ParseOrganization($organization);
        $job->handle($parser);

        $organization->refresh();

        $this->assertSame('completed', $organization->parsing_status);
        $this->assertSame('Coffee Shop', $organization->name);
        $this->assertSame(4.7, $organization->average_rating);
        $this->assertSame(123, $organization->ratings_count);
        $this->assertSame(45, $organization->reviews_count);
        $this->assertNotNull($organization->parsing_started_at);
        $this->assertNotNull($organization->parsed_at);
        $this->assertNull($organization->parsing_error);
    }

    public function test_source_error_marks_organization_as_failed_with_readable_message(): void
    {
        $organization = Organization::factory()->create(['parsing_status' => 'processing']);
        $exception = new YandexMapsSourceException('Яндекс.Карты вернули пустой ответ.');

        $job = new ParseOrganization($organization);
        $job->failed($exception);

        $organization->refresh();

        $this->assertSame('failed', $organization->parsing_status);
        $this->assertSame($exception->getMessage(), $organization->parsing_error);
    }
}
