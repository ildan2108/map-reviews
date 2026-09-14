<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexMapsUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = Str::lower(parse_url($value, PHP_URL_HOST) ?? '');
        $path = parse_url($value, PHP_URL_PATH) ?? '';
        $isYandexHost = $host === 'yandex.ru'
            || $host === 'yandex.com'
            || Str::endsWith($host, ['.yandex.ru', '.yandex.com']);
        $isMapsUrl = Str::startsWith($path, '/maps') || Str::startsWith($host, 'maps.');

        if (! $isYandexHost || ! $isMapsUrl) {
            $fail('The :attribute must be a Yandex Maps organization link.');
        }
    }
}
