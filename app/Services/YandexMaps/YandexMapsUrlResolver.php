<?php

namespace App\Services\YandexMaps;

use App\Exceptions\YandexMapsSourceException;
use Illuminate\Support\Facades\Http;

class YandexMapsUrlResolver
{
    public function resolve(string $url): string
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new YandexMapsSourceException(
                'Указана некорректная ссылка на Яндекс.Карты.'
            );
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! $this->isYandexMapsHost($host)) {
            throw new YandexMapsSourceException(
                'Ссылка должна вести на Яндекс.Карты.'
            );
        }

        if ($this->isOrganizationUrl($url)) {
            return rtrim($url, '/').'/';
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/151.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ])
                ->timeout(20)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                ])
                ->get($url);
        } catch (\Throwable $exception) {
            throw new YandexMapsSourceException(
                'Не удалось открыть ссылку на Яндекс.Карты.',
                previous: $exception
            );
        }

        if ($response->failed()) {
            throw new YandexMapsSourceException(
                'Не удалось открыть ссылку на Яндекс.Карты.'
            );
        }

        $canonicalUrl = $this->extractCanonicalUrl($response->body());

        if ($canonicalUrl === null) {
            throw new YandexMapsSourceException(
                'Не удалось определить организацию по ссылке на Яндекс.Карты.'
            );
        }

        return $canonicalUrl;
    }

    private function isOrganizationUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path)
            && preg_match('~/maps/org/[^/]+/\d+/?$~', $path) === 1;
    }

    private function extractCanonicalUrl(string $html): ?string
    {
        if (preg_match(
                '~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']~iu',
                $html,
                $matches
            ) !== 1) {
            return null;
        }

        $canonicalUrl = html_entity_decode(
            $matches[1],
            ENT_QUOTES | ENT_HTML5
        );

        if (! filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = parse_url($canonicalUrl, PHP_URL_HOST);
        $path = parse_url($canonicalUrl, PHP_URL_PATH);

        if (
            ! is_string($host)
            || ! $this->isYandexMapsHost($host)
            || ! is_string($path)
            || preg_match('~/maps/org/[^/]+/\d+/?$~', $path) !== 1
        ) {
            return null;
        }

        return rtrim($canonicalUrl, '/').'/';
    }

    private function isYandexMapsHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === 'yandex.ru'
            || $host === 'www.yandex.ru'
            || $host === 'yandex.com'
            || $host === 'www.yandex.com'
            || str_ends_with($host, '.yandex.ru')
            || str_ends_with($host, '.yandex.com');
    }
}
