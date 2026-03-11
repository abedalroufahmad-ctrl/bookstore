<?php

namespace App\Http\Controllers\Api;

use App\Models\Author;
use App\Infrastructure\Services\BookService;
use App\Infrastructure\Services\CachedCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookController extends BaseApiController
{
    public function __construct(
        protected CachedCatalogService $catalogService,
        protected BookService $bookService
    ) {}

    /**
     * Public book catalog for browsing (no auth required).
     * Only returns books with stock_quantity > 0 by default.
     * Cached when CACHE_CATALOG_ENABLED=true.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->get('search');
        }
        if ($request->filled('category_id')) {
            $filters['category_id'] = $request->get('category_id');
        }
        if ($request->filled('author_id')) {
            $filters['author_id'] = $request->get('author_id');
        }
        if ($request->filled('min_price')) {
            $filters['min_price'] = $request->get('min_price');
        }
        if ($request->filled('max_price')) {
            $filters['max_price'] = $request->get('max_price');
        }
        $filters['in_stock'] = $request->boolean('in_stock', true);
        $filters['has_cover'] = true;

        $perPage = min((int) $request->get('per_page', 32), 100);

        $books = $this->catalogService->getCachedBooks($filters, $perPage);

        return $this->successResponse($books);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->bookService->getById($id);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        $book->loadMissing(['authors', 'category']);

        // Fallback: if authors relation is empty but author_ids exists, fetch authors manually
        $authorIds = $book->author_ids ?? [];
        $hasAuthors = $book->relationLoaded('authors') && $book->authors->isNotEmpty();
        if (! $hasAuthors && ! empty($authorIds)) {
            $authors = Author::whereIn('_id', $authorIds)->get();
            $book->setRelation('authors', $authors);
        }

        return $this->successResponse($book);
    }
}
