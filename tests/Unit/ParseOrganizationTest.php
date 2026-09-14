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

    public function test_successful_parsing_updates_organization_and_saves_reviews(): void
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
                'reviews_count' => 51,
            ]);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($organization->yandex_url, 1)
            ->andReturn([
                [
                    'external_id' => 'review-1',
                    'author_name' => 'Ivan Ivanov',
                    'rating' => 5,
                    'text' => 'Отличное место',
                    'published_at' => '2026-09-01T10:00:00Z',
                    'organization_reply' => 'Спасибо за отзыв!',
                    'organization_reply_at' => '2026-09-01T11:00:00Z',
                ],
                [
                    'external_id' => 'review-2',
                    'author_name' => 'Petr Petrov',
                    'rating' => 4,
                    'text' => 'Хорошее место',
                    'published_at' => '2026-09-02T10:00:00Z',
                    'organization_reply' => null,
                    'organization_reply_at' => null,
                ],
            ]);

        $job = new ParseOrganization($organization);
        $job->handle($parser);

        $organization->refresh();

        $this->assertSame('completed', $organization->parsing_status);
        $this->assertSame('Coffee Shop', $organization->name);
        $this->assertSame(4.7, $organization->average_rating);
        $this->assertSame(123, $organization->ratings_count);
        $this->assertSame(51, $organization->reviews_count);
        $this->assertNotNull($organization->parsing_started_at);
        $this->assertNotNull($organization->parsed_at);
        $this->assertNull($organization->parsing_error);

        $this->assertDatabaseCount('reviews', 2);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
            'rating' => 5,
            'text' => 'Отличное место',
            'organization_reply' => 'Спасибо за отзыв!',
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-2',
            'author_name' => 'Petr Petrov',
            'rating' => 4,
            'text' => 'Хорошее место',
            'organization_reply' => null,
        ]);
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
    public function test_successful_parsing_processes_multiple_review_pages(): void
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
                'reviews_count' => 51,
            ]);

        $firstPageReviews = [];

        for ($i = 1; $i <= 50; $i++) {
            $firstPageReviews[] = [
                'external_id' => "review-$i",
                'author_name' => "Author $i",
                'rating' => 5,
                'text' => "Review $i",
                'published_at' => '2026-09-01T10:00:00Z',
                'organization_reply' => null,
                'organization_reply_at' => null,
            ];
        }

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($organization->yandex_url, 1)
            ->andReturn($firstPageReviews);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($organization->yandex_url, 2)
            ->andReturn([
                [
                    'external_id' => 'review-51',
                    'author_name' => 'Author 51',
                    'rating' => 4,
                    'text' => 'Последний отзыв',
                    'published_at' => '2026-09-02T10:00:00Z',
                    'organization_reply' => 'Спасибо!',
                    'organization_reply_at' => '2026-09-02T11:00:00Z',
                ],
            ]);

        $job = new ParseOrganization($organization);
        $job->handle($parser);

        $this->assertDatabaseCount('reviews', 51);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-51',
            'author_name' => 'Author 51',
            'organization_reply' => 'Спасибо!',
        ]);

        $organization->refresh();

        $this->assertSame('completed', $organization->parsing_status);
    }
    public function test_repeated_parsing_does_not_create_duplicate_reviews(): void
    {
        $organization = Organization::factory()->create([
            'parsing_status' => 'pending',
        ]);

        $review = [
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
            'rating' => 5,
            'text' => 'Отличное место',
            'published_at' => '2026-09-01T10:00:00Z',
            'organization_reply' => null,
            'organization_reply_at' => null,
        ];

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->twice()
            ->with($organization->yandex_url)
            ->andReturn([
                'name' => 'Coffee Shop',
                'average_rating' => 4.7,
                'ratings_count' => 123,
                'reviews_count' => 1,
            ]);

        $parser->shouldReceive('parseReviews')
            ->twice()
            ->with($organization->yandex_url, 1)
            ->andReturn([$review]);

        $job = new ParseOrganization($organization);

        $job->handle($parser);
        $job->handle($parser);

        $this->assertDatabaseCount('reviews', 1);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
        ]);
    }
}
