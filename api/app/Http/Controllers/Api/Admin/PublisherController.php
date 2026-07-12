<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\PublisherStoreRequest;
use App\Http\Requests\Admin\PublisherUpdateRequest;
use App\Infrastructure\Services\PublisherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublisherController extends BaseApiController
{
    public function __construct(
        protected PublisherService $publisherService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['search' => $request->get('search')];
        $perPage = min((int) $request->get('per_page', 32), 100);

        $publishers = $this->publisherService->getAll($filters, $perPage);

        return $this->successResponse($publishers);
    }

    public function store(PublisherStoreRequest $request): JsonResponse
    {
        $publisher = $this->publisherService->create($request->validated());

        return $this->successResponse($publisher->fresh(), 'Publisher created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $publisher = $this->publisherService->getById($id, ['warehouses']);

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher);
    }

    public function update(PublisherUpdateRequest $request, string $id): JsonResponse
    {
        $publisher = $this->publisherService->update($id, $request->validated());

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher, 'Publisher updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->publisherService->delete($id)) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse(null, 'Publisher deleted');
    }
}
