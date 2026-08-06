<?php

namespace App\Http\Controllers\Api;

use App\Models\Publisher;
use Illuminate\Http\JsonResponse;

class PublicPublisherController extends BaseApiController
{
    /**
     * Public publisher detail (for catalog links).
     */
    public function show(string $id): JsonResponse
    {
        $publisher = Publisher::query()->find($id);

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher);
    }
}
