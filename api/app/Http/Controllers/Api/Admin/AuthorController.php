<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AuthorStoreRequest;
use App\Http\Requests\Admin\AuthorUpdateRequest;
use App\Infrastructure\Services\AuthorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends BaseApiController
{
    public function __construct(
        protected AuthorService $authorService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['search' => $request->get('search')];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $authors = $this->authorService->getAll($filters, $perPage);

        return $this->successResponse($authors);
    }

    public function store(AuthorStoreRequest $request): JsonResponse
    {
        $author = $this->authorService->create($request->validated());

        return $this->successResponse($author->fresh(), 'Author created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $author = $this->authorService->getById($id);

        if (! $author) {
            return $this->errorResponse('Author not found', 404);
        }

        return $this->successResponse($author);
    }

    public function update(AuthorUpdateRequest $request, string $id): JsonResponse
    {
        $author = $this->authorService->update($id, $request->validated());

        if (! $author) {
            return $this->errorResponse('Author not found', 404);
        }

        return $this->successResponse($author, 'Author updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->authorService->delete($id)) {
            return $this->errorResponse('Author not found', 404);
        }

        return $this->successResponse(null, 'Author deleted');
    }
}
