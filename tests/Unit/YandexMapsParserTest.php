<?php

namespace Tests\Unit;

use App\Exceptions\YandexMapsSourceException;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsParserTest extends TestCase
{
    public function test_parses_basic_organization_data_from_json_ld(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/ld+json">'.json_encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'Coffee Shop',
                    'aggregateRating' => [
                        'ratingValue' => '4.7',
                        'ratingCount' => '123',
                        'reviewCount' => '45',
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parse(
            'https://yandex.ru/maps/org/coffee_shop/123456789'
        );

        $this->assertSame([
            'name' => 'Coffee Shop',
            'average_rating' => 4.7,
            'ratings_count' => 123,
            'reviews_count' => 45,
        ], $result);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://yandex.ru/maps/org/coffee_shop/123456789';
        });
    }

    public function test_finds_organization_when_it_is_not_the_first_state_item(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'title' => 'Яндекс Карты',
                                    ],
                                    [
                                        'title' => 'Surf Coffee x Jet',
                                        'ratingData' => [
                                            'ratingValue' => 5,
                                            'ratingCount' => 70,
                                            'reviewCount' => 61,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parse(
            'https://yandex.ru/maps/org/surf_coffee_x_jet/221031523151/reviews/'
        );

        $this->assertSame([
            'name' => 'Surf Coffee x Jet',
            'average_rating' => 5.0,
            'ratings_count' => 70,
            'reviews_count' => 61,
        ], $result);
    }

    public function test_throws_source_exception_when_yandex_returns_empty_response(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response('', 200),
        ]);

        $this->expectException(YandexMapsSourceException::class);
        $this->expectExceptionMessage('Яндекс.Карты вернули пустой ответ.');

        app(YandexMapsParser::class)->parse(
            'https://yandex.ru/maps/org/coffee_shop/123456789'
        );
    }

    public function test_throws_source_exception_when_organization_id_cannot_be_extracted(): void
    {
        $this->expectException(YandexMapsSourceException::class);
        $this->expectExceptionMessage('Не удалось определить идентификатор организации');

        app(YandexMapsParser::class)->parse('https://yandex.ru/maps/search/coffee');
    }

    public function test_parses_reviews_from_state_view(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'reviewResults' => [
                                            'reviews' => [
                                                [
                                                    'reviewId' => 'review-123',
                                                    'author' => [
                                                        'name' => 'Islam G.',
                                                    ],
                                                    'rating' => 5,
                                                    'text' => 'Отличное место!',
                                                    'updatedTime' => '2026-07-13T06:08:59.779Z',
                                                    'businessComment' => [
                                                        'text' => 'Спасибо за отзыв!',
                                                        'updatedTime' => '2026-07-13T07:52:48.741Z',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parseReviews(
            'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/'
        );

        $this->assertSame([
            [
                'external_id' => 'review-123',
                'author_name' => 'Islam G.',
                'rating' => 5,
                'text' => 'Отличное место!',
                'published_at' => '2026-07-13T06:08:59.779Z',
                'organization_reply' => 'Спасибо за отзыв!',
                'organization_reply_at' => '2026-07-13T07:52:48.741Z',
            ],
        ], $result);
    }

    public function test_adds_page_parameter_to_reviews_url(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'reviewResults' => [
                                            'reviews' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        app(YandexMapsParser::class)->parseReviews(
            'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/',
            2
        );

        Http::assertSent(function (Request $request): bool {
            return $request->url() ===
                'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/?page=2';
        });
    }

    public function test_parses_review_without_organization_reply(): void
    {
        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'reviewResults' => [
                                            'reviews' => [
                                                [
                                                    'reviewId' => 'review-123',
                                                    'author' => [
                                                        'name' => 'Islam G.',
                                                    ],
                                                    'rating' => 5,
                                                    'text' => 'Отличное место!',
                                                    'updatedTime' => '2026-07-13T06:08:59.779Z',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parseReviews(
            'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/'
        );

        $this->assertNull($result[0]['organization_reply']);
        $this->assertNull($result[0]['organization_reply_at']);
    }
    public function test_selects_largest_review_collection_from_state(): void
    {
        $smallReviews = [
            [
                'reviewId' => 'review-1',
                'author' => ['name' => 'Author 1'],
                'rating' => 5,
                'text' => 'Review 1',
                'updatedTime' => '2026-09-10T10:00:00Z',
            ],
            [
                'reviewId' => 'review-2',
                'author' => ['name' => 'Author 2'],
                'rating' => 5,
                'text' => 'Review 2',
                'updatedTime' => '2026-09-09T10:00:00Z',
            ],
            [
                'reviewId' => 'review-3',
                'author' => ['name' => 'Author 3'],
                'rating' => 4,
                'text' => 'Review 3',
                'updatedTime' => '2026-09-08T10:00:00Z',
            ],
        ];

        $largeReviews = [];

        for ($i = 1; $i <= 50; $i++) {
            $largeReviews[] = [
                'reviewId' => "large-review-$i",
                'author' => ['name' => "Author $i"],
                'rating' => 5,
                'text' => "Review $i",
                'updatedTime' => '2026-09-10T10:00:00Z',
            ];
        }

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'reviewResults' => [
                                            'reviews' => $smallReviews,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'otherData' => [
                        'reviewResults' => [
                            'reviews' => $largeReviews,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parseReviews(
            'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/'
        );

        $this->assertCount(50, $result);
        $this->assertSame('large-review-1', $result[0]['external_id']);
        $this->assertSame('large-review-50', $result[49]['external_id']);
    }
    public function test_parses_reviews_from_requested_page(): void
    {
        $reviews = [];

        for ($i = 1; $i <= 50; $i++) {
            $reviews[] = [
                'reviewId' => "review-$i",
                'author' => ['name' => "Author $i"],
                'rating' => 5,
                'text' => "Review $i",
                'updatedTime' => '2026-09-10T10:00:00Z',
            ];
        }

        Http::fake([
            'yandex.ru/maps/org/*' => Http::response(
                '<script type="application/json" class="state-view">'.json_encode([
                    'stack' => [
                        [
                            'results' => [
                                'items' => [
                                    [
                                        'reviewResults' => [
                                            'reviews' => $reviews,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR).'</script>',
                200,
            ),
        ]);

        $result = app(YandexMapsParser::class)->parseReviews(
            'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/',
            2
        );

        $this->assertCount(50, $result);
        $this->assertSame('review-1', $result[0]['external_id']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() ===
                'https://yandex.ru/maps/org/coffee_shop/123456789/reviews/?page=2';
        });
    }
}
