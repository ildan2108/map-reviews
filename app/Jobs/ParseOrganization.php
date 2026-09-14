<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\Review;

class ParseOrganization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Organization $organization)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(YandexMapsParser $parser): void
    {
        $this->organization->update([
            'parsing_status' => 'processing',
            'parsing_error' => null,
            'parsing_started_at' => now(),
        ]);

        $data = $parser->parse($this->organization->yandex_url);

        $this->organization->update($data);

        $this->parseReviews($parser);

        $this->organization->update([
            'parsing_status' => 'completed',
            'parsing_error' => null,
            'parsed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Yandex Maps organization parsing failed.', [
            'organization_id' => $this->organization->id,
            'url' => $this->organization->yandex_url,
            'exception' => $exception,
        ]);

        $this->organization->update([
            'parsing_status' => 'failed',
            'parsing_error' => $this->userMessage($exception),
        ]);
    }

    private function userMessage(Throwable $exception): string
    {
        if ($exception instanceof \App\Exceptions\YandexMapsSourceException) {
            return $exception->getMessage();
        }

        return 'Не удалось загрузить данные организации. Попробуйте повторить позже.';
    }
    private function parseReviews(YandexMapsParser $parser): void
    {
        $page = 1;

        do {
            $reviews = $parser->parseReviews(
                $this->organization->yandex_url,
                $page
            );

            $reviewsCount = count($reviews);

            if ($reviewsCount === 0) {
                break;
            }

            $reviews = array_map(
                fn (array $review): array => [
                    ...$review,
                    'organization_id' => $this->organization->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $reviews
            );

            Review::upsert(
                $reviews,
                ['organization_id', 'external_id'],
                [
                    'author_name',
                    'rating',
                    'text',
                    'published_at',
                    'organization_reply',
                    'organization_reply_at',
                    'updated_at',
                ]
            );

            $page++;
        } while ($reviewsCount === 50);
    }
}
