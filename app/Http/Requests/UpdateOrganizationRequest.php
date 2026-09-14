<?php

namespace App\Http\Requests;

use App\Rules\YandexMapsUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, YandexMapsUrl|string>>
     */
    public function rules(): array
    {
        return [
            'yandex_url' => ['bail', 'required', 'string', 'max:2048', 'url:http,https', new YandexMapsUrl],
        ];
    }
}
