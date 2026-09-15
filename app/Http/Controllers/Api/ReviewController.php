<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organization = $request->user()->organization;

        if ($organization === null) {
            abort(404, 'Организация не подключена.');
        }

        return ReviewResource::collection(
            $organization->reviews()
                ->latest('published_at')
                ->paginate(50)
        );
    }
}
