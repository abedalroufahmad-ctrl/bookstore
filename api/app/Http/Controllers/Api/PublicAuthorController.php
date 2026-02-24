<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Services\AuthorService;
use App\Infrastructure\Services\CachedCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAuthorController extends BaseApiController
{
    public function __construct(
        protected CachedCatalogService $catalogService,
        protected AuthorService $authorService
    ) {}

    /**
     * Public author list for browsing.
     * Cached when CACHE_CATALOG_ENABLED=true.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->filled('search') ? ['search' => $request->get('search')] : [];
        $perPage = min((int) $request->get('per_page', 50), 100);

        $authors = $this->catalogService->getCachedAuthors($filters, $perPage);

        return $this->successResponse($authors);
    }

    public function show(string $id): JsonResponse
    {
        $author = $this->authorService->getById($id);

        if (! $author) {
            return $this->errorResponse('Author not found', 404);
        }

        return $this->successResponse($author);
    }
}
