<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
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
        $organization = $request->user()->organization()->updateOrCreate([], $request->validated());

        return new OrganizationResource($organization);
    }
}
