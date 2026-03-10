<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Services\CachedCatalogService;
use App\Infrastructure\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCategoryController extends BaseApiController
{
    public function __construct(
        protected CachedCatalogService $catalogService,
        protected CategoryService $categoryService
    ) {}

    /**
     * Public category list for browsing (Dewey classification).
     * Cached when CACHE_CATALOG_ENABLED=true.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->filled('search') ? ['search' => $request->get('search')] : [];
        $perPage = min((int) $request->get('per_page', 32), 100);

        $categories = $this->catalogService->getCachedCategories($filters, $perPage);

        return $this->successResponse($categories);
    }

    public function show(string $id): JsonResponse
    {
        $category = $this->categoryService->getById($id);

        if (! $category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse($category);
    }
}
