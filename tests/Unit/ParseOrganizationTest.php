<?php

namespace Tests\Unit;

use App\Exceptions\YandexMapsSourceException;
use App\Jobs\ParseOrganization;
use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexMaps\YandexMapsParser;
use App\Services\YandexMaps\YandexMapsUrlResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParseOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_successful_parsing_updates_organization_and_saves_reviews(): void
    {
        $organization = Organization::factory()->create(['parsing_status' => 'pending']);
        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->once()
            ->with($resolvedUrl)
            ->andReturn([
                'name' => 'Coffee Shop',
                'average_rating' => 4.7,
                'ratings_count' => 123,
                'reviews_count' => 51,
            ]);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($resolvedUrl, 1)
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

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

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
        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->once()
            ->with($resolvedUrl)
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
            ->with($resolvedUrl, 1)
            ->andReturn($firstPageReviews);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($resolvedUrl, 2)
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

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

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

        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

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
            ->with($resolvedUrl)
            ->andReturn([
                'name' => 'Coffee Shop',
                'average_rating' => 4.7,
                'ratings_count' => 123,
                'reviews_count' => 1,
            ]);

        $parser->shouldReceive('parseReviews')
            ->twice()
            ->with($resolvedUrl, 1)
            ->andReturn([$review]);

        $job = new ParseOrganization($organization);

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

        $this->assertDatabaseCount('reviews', 1);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
        ]);
    }

    public function test_successful_reparsing_replaces_old_reviews(): void
    {
        $organization = Organization::factory()->create([
            'parsing_status' => 'pending',
        ]);

        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

        Review::factory()->create([
            'organization_id' => $organization->id,
            'external_id' => 'old-review',
            'author_name' => 'Old Author',
        ]);

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->once()
            ->with($resolvedUrl)
            ->andReturn([
                'name' => 'New Organization',
                'average_rating' => 4.8,
                'ratings_count' => 100,
                'reviews_count' => 1,
            ]);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($resolvedUrl, 1)
            ->andReturn([
                [
                    'external_id' => 'new-review',
                    'author_name' => 'New Author',
                    'rating' => 5,
                    'text' => 'Новый отзыв',
                    'published_at' => '2026-09-10T10:00:00Z',
                    'organization_reply' => null,
                    'organization_reply_at' => null,
                ],
            ]);

        $job = new ParseOrganization($organization);

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

        $this->assertDatabaseMissing('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'old-review',
        ]);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'new-review',
            'author_name' => 'New Author',
        ]);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_failed_reparsing_keeps_existing_reviews(): void
    {
        $organization = Organization::factory()->create([
            'parsing_status' => 'pending',
        ]);

        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

        Review::factory()->create([
            'organization_id' => $organization->id,
            'external_id' => 'old-review',
            'author_name' => 'Old Author',
        ]);

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->once()
            ->with($resolvedUrl)
            ->andReturn([
                'name' => 'New Organization',
                'average_rating' => 4.8,
                'ratings_count' => 100,
                'reviews_count' => 1,
            ]);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($resolvedUrl, 1)
            ->andThrow(
                new YandexMapsSourceException(
                    'Не удалось получить отзывы Яндекс.Карт.'
                )
            );

        $job = new ParseOrganization($organization);

        $this->expectException(YandexMapsSourceException::class);

        try {
            $urlResolver = $this->mock(YandexMapsUrlResolver::class);

            $urlResolver->shouldReceive('resolve')
                ->once()
                ->with($organization->yandex_url)
                ->andReturn($resolvedUrl);

            $job->handle($parser, $urlResolver);
        } finally {
            $this->assertDatabaseHas('reviews', [
                'organization_id' => $organization->id,
                'external_id' => 'old-review',
                'author_name' => 'Old Author',
            ]);
        }
    }

    public function test_reparsing_updates_existing_review(): void
    {
        $organization = Organization::factory()->create([
            'parsing_status' => 'pending',
        ]);

        $resolvedUrl = 'https://yandex.ru/maps/org/coffee_shop/123456789/';

        Review::factory()->create([
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
            'rating' => 4,
            'text' => 'Старый текст',
        ]);

        $parser = $this->mock(YandexMapsParser::class);

        $parser->shouldReceive('parse')
            ->once()
            ->with($resolvedUrl)
            ->andReturn([
                'name' => 'Coffee Shop',
                'average_rating' => 5.0,
                'ratings_count' => 123,
                'reviews_count' => 1,
            ]);

        $parser->shouldReceive('parseReviews')
            ->once()
            ->with($resolvedUrl, 1)
            ->andReturn([
                [
                    'external_id' => 'review-1',
                    'author_name' => 'Ivan Ivanov',
                    'rating' => 5,
                    'text' => 'Обновлённый текст',
                    'published_at' => '2026-09-10T10:00:00Z',
                    'organization_reply' => 'Спасибо!',
                    'organization_reply_at' => '2026-09-10T11:00:00Z',
                ],
            ]);

        $job = new ParseOrganization($organization);

        $urlResolver = $this->mock(YandexMapsUrlResolver::class);

        $urlResolver->shouldReceive('resolve')
            ->once()
            ->with($organization->yandex_url)
            ->andReturn($resolvedUrl);

        $job->handle($parser, $urlResolver);

        $this->assertDatabaseCount('reviews', 1);

        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'author_name' => 'Ivan Ivanov',
            'rating' => 5,
            'text' => 'Обновлённый текст',
            'organization_reply' => 'Спасибо!',
        ]);
    }
}
