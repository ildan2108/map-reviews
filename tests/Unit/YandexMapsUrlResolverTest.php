<?php

namespace Tests\Unit;

use App\Exceptions\YandexMapsSourceException;
use App\Services\YandexMaps\YandexMapsUrlResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsUrlResolverTest extends TestCase
{
    public function test_resolves_short_yandex_maps_link(): void
    {
        Http::fake([
            'yandex.ru/maps/-/*' => Http::response('', 301, [
                'Location' => '/maps/43/kazan/?poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D149342094028',
            ]),
            'yandex.ru/maps/43/kazan/*' => Http::response(
                '<link rel="canonical" href="https://yandex.ru/maps/org/test_organization/149342094028/">',
                200
            ),
        ]);

        $result = app(YandexMapsUrlResolver::class)->resolve(
            'https://yandex.ru/maps/-/CTxUYT6S'
        );

        $this->assertSame(
            'https://yandex.ru/maps/org/test_organization/149342094028/',
            $result
        );
    }

    public function test_resolves_map_url_with_poi_to_canonical_organization_url(): void
    {
        Http::fake([
            'yandex.ru/maps/43/kazan/*' => Http::response(
                '<link rel="canonical" href="https://yandex.ru/maps/org/test_organization/149342094028/">',
                200
            ),
        ]);

        $url = 'https://yandex.ru/maps/43/kazan/?poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D149342094028';

        $result = app(YandexMapsUrlResolver::class)->resolve($url);

        $this->assertSame(
            'https://yandex.ru/maps/org/test_organization/149342094028/',
            $result
        );
    }

    public function test_keeps_direct_organization_url(): void
    {
        $url = 'https://yandex.ru/maps/org/test_organization/149342094028/';

        Http::fake();

        $result = app(YandexMapsUrlResolver::class)->resolve($url);

        $this->assertSame($url, $result);

        Http::assertNothingSent();
    }

    public function test_rejects_non_yandex_url(): void
    {
        $this->expectException(YandexMapsSourceException::class);

        app(YandexMapsUrlResolver::class)->resolve(
            'https://example.com/maps/org/123/'
        );
    }

    public function test_rejects_invalid_url(): void
    {
        $this->expectException(YandexMapsSourceException::class);

        app(YandexMapsUrlResolver::class)->resolve(
            'not-a-url'
        );
    }

    public function test_throws_exception_when_yandex_is_unavailable(): void
    {
        Http::fake([
            'yandex.ru/maps/*' => Http::response('', 503),
        ]);

        $this->expectException(YandexMapsSourceException::class);

        app(YandexMapsUrlResolver::class)->resolve(
            'https://yandex.ru/maps/-/CTxUYT6S'
        );
    }

    public function test_throws_exception_when_canonical_url_is_missing(): void
    {
        Http::fake([
            'yandex.ru/maps/43/kazan/*' => Http::response(
                '<html></html>',
                200
            ),
        ]);

        $this->expectException(YandexMapsSourceException::class);

        app(YandexMapsUrlResolver::class)->resolve(
            'https://yandex.ru/maps/43/kazan/?poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D149342094028'
        );
    }
}
