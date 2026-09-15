<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexMaps\YandexMapsParser;
use App\Services\YandexMaps\YandexMapsUrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function handle(
        YandexMapsParser $parser,
        YandexMapsUrlResolver $urlResolver
    ): void {
        $this->organization->update([
            'parsing_status' => 'processing',
            'parsing_error' => null,
            'parsing_started_at' => now(),
        ]);

        $resolvedUrl = $urlResolver->resolve(
            $this->organization->yandex_url
        );

        $data = $parser->parse($resolvedUrl);
        $reviews = $this->parseReviews($parser, $resolvedUrl);

        DB::transaction(function () use ($data, $reviews): void {
            $this->organization->update($data);

            $this->organization->reviews()->delete();

            if ($reviews !== []) {
                Review::insert($reviews);
            }

            $this->organization->update([
                'parsing_status' => 'completed',
                'parsing_error' => null,
                'parsed_at' => now(),
            ]);
        });
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

    /**
     * @return array<int, array{
     *     organization_id: int,
     *     external_id: string,
     *     author_name: string|null,
     *     rating: int|null,
     *     text: string|null,
     *     published_at: string|null,
     *     organization_reply: string|null,
     *     organization_reply_at: string|null,
     *     created_at: \Illuminate\Support\Carbon,
     *     updated_at: \Illuminate\Support\Carbon
     * }>
     */
    private function parseReviews(
        YandexMapsParser $parser,
        string $url
    ): array
    {
        $page = 1;
        $result = [];

        do {
            $reviews = $parser->parseReviews(
                $url,
                $page
            );

            $reviewsCount = count($reviews);

            if ($reviewsCount === 0) {
                break;
            }

            $now = now();

            foreach ($reviews as $review) {
                $result[] = [
                    ...$review,
                    'organization_id' => $this->organization->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $page++;
        } while ($reviewsCount === 50);

        return $result;
    }
}
