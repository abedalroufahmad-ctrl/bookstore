<?php

namespace App\Http\Controllers\Api;

use App\Infrastructure\Services\BookService;
use App\Infrastructure\Services\CachedCatalogService;
use App\Models\Author;
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
        if ($request->filled('search') && is_string($request->get('search'))) {
            $filters['search'] = $request->get('search');
        }
        foreach (['category_id', 'warehouse_id', 'publisher_id', 'author_id'] as $key) {
            $value = $request->get($key);
            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            }
        }
        if ($request->filled('min_price') && is_numeric($request->get('min_price'))) {
            $filters['min_price'] = (float) $request->get('min_price');
        }
        if ($request->filled('max_price') && is_numeric($request->get('max_price'))) {
            $filters['max_price'] = (float) $request->get('max_price');
        }
        $filters['in_stock'] = $request->boolean('in_stock', true);
        $filters['has_cover'] = true;
        $filters['is_visible'] = true;
        $filters['is_sold'] = false;
        if ($request->filled('condition') && is_string($request->get('condition'))) {
            $condition = strtolower(trim($request->get('condition')));
            if (in_array($condition, ['new', 'used'], true)) {
                $filters['condition'] = $condition;
            }
        }
        // Load only relations needed by catalog cards.
        $filters['with'] = ['authors', 'warehouse', 'publisher', 'category'];
        // Deep OFFSET is slow at 1M+ docs — clamp public offset navigation.
        $filters['max_page'] = max(1, (int) config('catalog.max_offset_page', 200));

        $perPage = min((int) $request->get('per_page', 32), 100);

        $books = $this->catalogService->getCachedBooks($filters, $perPage);

        $payload = $books->toArray();
        $payload['max_page'] = $filters['max_page'];
        // Keep accurate `total`, but clamp last_page so clients stop offset-paging.
        $payload['last_page'] = min(
            (int) ($payload['last_page'] ?? 1),
            $filters['max_page']
        );
        $items = $books->items();
        if (count($items) >= $perPage && (int) $payload['current_page'] < (int) $payload['last_page']) {
            $last = $items[array_key_last($items)];
            if ($last) {
                $payload['next_cursor'] = $this->bookService->encodeListCursor($last);
            }
        }

        return $this->successResponse($payload);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->bookService->getById($id);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        if (($book->is_visible ?? true) === false) {
            return $this->errorResponse('Book not found', 404);
        }

        $book->loadMissing(['authors', 'category', 'warehouse', 'publisher']);

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
