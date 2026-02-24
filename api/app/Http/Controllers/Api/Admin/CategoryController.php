<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CategoryStoreRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Infrastructure\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['search' => $request->get('search')];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $categories = $this->categoryService->getAll($filters, $perPage);

        return $this->successResponse($categories);
    }

    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->successResponse($category->fresh(), 'Category created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = $this->categoryService->getById($id);

        if (! $category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse($category);
    }

    public function update(CategoryUpdateRequest $request, string $id): JsonResponse
    {
        $category = $this->categoryService->update($id, $request->validated());

        if (! $category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse($category, 'Category updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->categoryService->delete($id)) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse(null, 'Category deleted');
    }
}
