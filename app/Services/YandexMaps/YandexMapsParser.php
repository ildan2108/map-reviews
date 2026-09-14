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
            throw new YandexMapsSourceException(
                'Не удалось определить идентификатор организации в ссылке Яндекс.Карт.'
            );
        }

        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml',
        ])
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

        $state = $this->extractState($html);

        if ($state !== null) {
            $data = $this->extractOrganizationData($state);

            if ($data !== null) {
                return $data;
            }
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
            '/"ratingCount"\s*:\s*"?(\\d+)"?/u',
        ]);

        $reviewsCount = $this->match($html, [
            '/"reviewCount"\s*:\s*"?(\\d+)"?/u',
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

    /**
     * @return array{
     *     name: string,
     *     average_rating: float|null,
     *     ratings_count: int|null,
     *     reviews_count: int|null
     * }|null
     */
    private function extractOrganizationData(array $state): ?array
    {
        $item = $state['stack'][0]['results']['items'][0] ?? null;

        if (! is_array($item)) {
            return null;
        }

        $name = $this->stringValue($item['title'] ?? null);

        if ($name === null) {
            return null;
        }

        $rating = $item['ratingData'] ?? [];

        return [
            'name' => $name,
            'average_rating' => $this->floatValue($rating['ratingValue'] ?? null),
            'ratings_count' => $this->intValue($rating['ratingCount'] ?? null),
            'reviews_count' => $this->intValue($rating['reviewCount'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private function extractJsonLd(string $html): ?array
    {
        if (! preg_match_all(
            '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu',
            $html,
            $matches
        )) {
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

    /**
     * @return array<int, array{
     *     external_id: string,
     *     author_name: string|null,
     *     rating: int|null,
     *     text: string|null,
     *     published_at: string|null,
     *     organization_reply: string|null,
     *     organization_reply_at: string|null
     * }>
     */
    public function parseReviews(string $url, int $page = 1): array
    {
        $page = max(1, $page);

        $reviewsUrl = $this->buildReviewsUrl($url, $page);

        $response = Http::withHeaders([
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->timeout(20)
            ->retry(2, 500)
            ->get($reviewsUrl);

        if ($response->failed()) {
            throw new YandexMapsSourceException(
                'Не удалось получить отзывы Яндекс.Карт: источник недоступен.'
            );
        }

        $html = $response->body();

        if (trim($html) === '') {
            throw new YandexMapsSourceException('Яндекс.Карты вернули пустой ответ.');
        }

        $state = $this->extractState($html);

        if ($state === null) {
            throw new YandexMapsSourceException(
                'Не удалось распознать данные отзывов Яндекс.Карт: формат ответа изменился.'
            );
        }

        $reviews = $state['stack'][0]['results']['items'][0]['reviewResults']['reviews'] ?? null;

        if (! is_array($reviews)) {
            throw new YandexMapsSourceException(
                'Не удалось найти отзывы в ответе Яндекс.Карт.'
            );
        }

        $result = [];

        foreach ($reviews as $review) {
            $normalizedReview = $this->normalizeReview($review);

            if ($normalizedReview !== null) {
                $result[] = $normalizedReview;
            }
        }

        return $result;
    }

    private function buildReviewsUrl(string $url, int $page): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'page=' . $page;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractState(string $html): ?array
    {
        if (! preg_match(
            '/<script[^>]*class=["\']state-view["\'][^>]*>(.*?)<\/script>/isu',
            $html,
            $matches
        )) {
            return null;
        }

        $state = json_decode(trim($matches[1]), true);

        return is_array($state) ? $state : null;
    }

    /**
     * @return array{
     *     external_id: string,
     *     author_name: string|null,
     *     rating: int|null,
     *     text: string|null,
     *     published_at: string|null,
     *     organization_reply: string|null,
     *     organization_reply_at: string|null
     * }|null
     */
    private function normalizeReview(mixed $review): ?array
    {
        if (! is_array($review)) {
            return null;
        }

        $externalId = $this->stringValue($review['reviewId']);

        if ($externalId === null) {
            return null;
        }

        return [
            'external_id' => $externalId,
            'author_name' => $this->stringValue($review['author']['name'] ?? null),
            'rating' => $this->intValue($review['rating']),
            'text' => $this->stringValue($review['text']),
            'published_at' => $this->stringValue($review['updatedTime']),
            'organization_reply' => $this->stringValue($review['businessComment']['text'] ?? null),
            'organization_reply_at' => $this->stringValue($review['businessComment']['updatedTime'] ?? null),
        ];
    }
}
