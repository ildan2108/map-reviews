<?php

namespace App\Services\YandexMaps;

use App\Exceptions\YandexMapsSourceException;
use Illuminate\Support\Facades\Http;

class YandexMapsParser
{
    /**
     * @return array{name: string, average_rating: float|null, ratings_count: int|null, reviews_count: int|null}
     */
    public function parse(string $url): array
    {
        $organizationId = $this->extractOrganizationId($url);
        if ($organizationId === null) {
            throw new YandexMapsSourceException('Не удалось определить идентификатор организации в ссылке Яндекс.Карт.');
        }

        $response = Http::acceptHtml()
            ->timeout(20)
            ->retry(2, 500)
            ->get($url);

        if ($response->failed()) {
            throw new YandexMapsSourceException(
                'Не удалось получить данные карточки Яндекс.Карт: источник недоступен.'
            );
        }

        $html = $response->body();
        if (trim($html) === '') {
            throw new YandexMapsSourceException('Яндекс.Карты вернули пустой ответ.');
        }

        $data = $this->extractJsonLd($html);
        if ($data !== null) {
            $name = $this->stringValue($data['name'] ?? null);
            $rating = $data['aggregateRating'] ?? null;

            if ($name !== null) {
                return [
                    'name' => $name,
                    'average_rating' => $this->floatValue($rating['ratingValue'] ?? null),
                    'ratings_count' => $this->intValue($rating['ratingCount'] ?? null),
                    'reviews_count' => $this->intValue($rating['reviewCount'] ?? null),
                ];
            }
        }

        $name = $this->match($html, [
            '/"name"\s*:\s*"((?:\\.|[^"\\])+)"/u',
            '/<title[^>]*>(.*?)<\/title>/isu',
        ]);
        $rating = $this->match($html, [
            '/"ratingValue"\s*:\s*"?([0-9]+(?:[.,][0-9]+)?)"?/u',
        ]);
        $ratingsCount = $this->match($html, [
            '/"ratingCount"\s*:\s*"?(\d+)"?/u',
        ]);
        $reviewsCount = $this->match($html, [
            '/"reviewCount"\s*:\s*"?(\d+)"?/u',
        ]);

        if ($name === null || $name === '') {
            throw new YandexMapsSourceException(
                'Не удалось распознать карточку организации: формат ответа Яндекс.Карт изменился.'
            );
        }

        return [
            'name' => html_entity_decode(stripslashes($name)),
            'average_rating' => $this->floatValue($rating),
            'ratings_count' => $this->intValue($ratingsCount),
            'reviews_count' => $this->intValue($reviewsCount),
        ];
    }

    private function extractOrganizationId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return preg_match('~/org/[^/]+/(\d+)~', $path, $matches) === 1
            ? $matches[1]
            : null;
    }

    /** @return array<string, mixed>|null */
    private function extractJsonLd(string $html): ?array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);
            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                foreach ($decoded['@graph'] as $item) {
                    if (is_array($item) && isset($item['name'])) {
                        return $item;
                    }
                }
            }

            if (isset($decoded['name'])) {
                return $decoded;
            }
        }

        return null;
    }

    private function match(string $html, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function floatValue(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function intValue(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
