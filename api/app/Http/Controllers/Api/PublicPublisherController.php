<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Services\PublisherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPublisherController extends BaseApiController
{
    public function __construct(
        protected PublisherService $publisherService
    ) {}

    /**
     * Public publisher list (browsing).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->filled('search') ? ['search' => $request->get('search')] : [];
        $perPage = min((int) $request->get('per_page', 32), 100);

        $publishers = $this->publisherService->getAll($filters, $perPage);

        return $this->successResponse($publishers);
    }

    /**
     * Public publisher detail (for catalog links).
     */
    public function show(string $id): JsonResponse
    {
        $publisher = $this->publisherService->getById($id);

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher);
    }
}
