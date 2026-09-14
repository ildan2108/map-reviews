<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Jobs\ParseOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse|OrganizationResource
    {
        $organization = $request->user()->organization;

        if ($organization === null) {
            return response()->json(['data' => null]);
        }

        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request): OrganizationResource
    {
        $organization = $request->user()->organization()->updateOrCreate(
            [],
            [
                'yandex_url' => $request->validated('yandex_url'),
                'parsing_status' => 'pending',
                'parsing_error' => null,
                'parsing_started_at' => null,
                'parsed_at' => null,
                'name' => null,
                'average_rating' => null,
                'ratings_count' => null,
                'reviews_count' => null,
            ],
        );

        ParseOrganization::dispatch($organization);

        return new OrganizationResource($organization);
    }
}
