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
}
