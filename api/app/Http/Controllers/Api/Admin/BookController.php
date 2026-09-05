<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\BookStoreRequest;
use App\Http\Requests\Admin\BookUpdateRequest;
use App\Http\Requests\Admin\BulkDeleteBooksRequest;
use App\Infrastructure\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends BaseApiController
{
    public function __construct(
        protected BookService $bookService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->get('search');
        }
        if ($request->filled('category_id')) {
            $filters['category_id'] = $request->get('category_id');
        }
        if ($request->filled('warehouse_id')) {
            $filters['warehouse_id'] = $request->get('warehouse_id');
        }
        if ($request->filled('min_price')) {
            $filters['min_price'] = $request->get('min_price');
        }
        if ($request->filled('max_price')) {
            $filters['max_price'] = $request->get('max_price');
        }
        if ($request->has('in_stock')) {
            $filters['in_stock'] = $request->boolean('in_stock');
        }
        if ($request->boolean('no_cover')) {
            $filters['no_cover'] = true;
        }
        if ($request->filled('condition') && is_string($request->get('condition'))) {
            $condition = strtolower(trim($request->get('condition')));
            if (in_array($condition, ['new', 'used'], true)) {
                $filters['condition'] = $condition;
            }
        }
        if ($request->has('is_visible')) {
            $filters['is_visible'] = $request->boolean('is_visible');
        }
        if ($request->has('is_sold')) {
            $filters['is_sold'] = $request->boolean('is_sold');
        }
        if ($employee && UserRole::isLimitedToAssignedWarehouses($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (empty($managedIds)) {
                $filters['warehouse_ids'] = ['__none__'];
            } else {
                $filters['warehouse_ids'] = $managedIds;
            }
        }
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $filters['publisher_id'] = $employee->getManagedPublisherId() ?? '__none__';
        }
        $perPage = min((int) $request->get('per_page', 32), 100);

        $books = $this->bookService->getAll($filters, $perPage);

        return $this->successResponse($books);
    }

    public function store(BookStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data = $this->normalizePublisherPayload($data);

        // Publisher managers can only create books under their own publisher.
        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $publisherId = $employee->getManagedPublisherId();
            if (! $publisherId) {
                return $this->errorResponse('Forbidden. You are not assigned to a publisher.', 403);
            }
            $data['publisher_id'] = $publisherId;
            $data['publisher_ids'] = [$publisherId];
        }

        $warehouseIds = array_values(array_unique(array_filter(array_map(
            'strval',
            (array) ($data['warehouse_ids'] ?? [($data['warehouse_id'] ?? null)])
        ))));
        unset($data['warehouse_ids']);

        if ($warehouseIds === []) {
            return $this->errorResponse('At least one warehouse is required.', 422);
        }

        $created = [];
        foreach ($warehouseIds as $warehouseId) {
            $payload = $data;
            $payload['warehouse_id'] = $warehouseId;
            $book = $this->bookService->create($payload);
            $created[] = $this->bookService->getById((string) $book->getKey());
        }

        $first = $created[0] ?? null;
        $message = count($created) > 1
            ? 'Book created in '.count($created).' warehouses'
            : 'Book created';

        return $this->successResponse([
            'book' => $first,
            'books' => $created,
            'created_count' => count($created),
        ], $message, 201);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,ods,csv', 'max:20480'],
            'warehouse_id' => ['required', 'string'],
            'skip_cover' => ['nullable', 'boolean'],
        ]);

        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $publisherId = $employee->getManagedPublisherId();
            if (! $publisherId) {
                return $this->errorResponse('Forbidden. You are not assigned to a publisher.', 403);
            }
        }

        $file = $request->file('file');
        $path = $file->getRealPath();
        if (! $path) {
            return $this->errorResponse('Could not read uploaded file.', 422);
        }

        $importer = app(\App\Infrastructure\Services\BookImportService::class);
        $result = $importer->importFromFile(
            $path,
            (string) $request->input('warehouse_id'),
            [
                'skip_cover' => $request->boolean('skip_cover', true),
                'publisher_id' => ($employee && UserRole::isPublisherScoped($employee->role))
                    ? $employee->getManagedPublisherId()
                    : null,
            ]
        );

        return $this->successResponse($result, 'Import completed');
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->bookService->getById($id);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)
            && ! $book->hasPublisher($employee->getManagedPublisherId())) {
            return $this->errorResponse('Forbidden. You can only access your publisher\'s books.', 403);
        }

        $book->loadMissing(['authors', 'publisher', 'publishers']);

        return $this->successResponse($book);
    }

    public function update(BookUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('publisher_ids', $data) || array_key_exists('publisher_id', $data)) {
            $data = $this->normalizePublisherPayload($data);
        }

        $employee = auth('employee')->user();
        $existing = $this->bookService->getById($id);
        if (! $existing) {
            return $this->errorResponse('Book not found', 404);
        }

        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            if (! $existing->hasPublisher($employee->getManagedPublisherId())) {
                return $this->errorResponse('Forbidden. You can only update your publisher\'s books.', 403);
            }
            // Keep the book under this publisher (may keep co-publishers already on the book).
            $managed = $employee->getManagedPublisherId();
            $ids = $existing->publisherIdList();
            if ($managed && ! in_array($managed, $ids, true)) {
                $ids[] = $managed;
            }
            $data['publisher_ids'] = $ids !== [] ? $ids : ($managed ? [$managed] : []);
            $data['publisher_id'] = $managed;
        }

        $warehouseIds = null;
        if (array_key_exists('warehouse_ids', $data)) {
            $warehouseIds = array_values(array_unique(array_filter(array_map('strval', (array) $data['warehouse_ids']))));
            unset($data['warehouse_ids']);

            $currentWarehouseId = (string) ($existing->warehouse_id ?? '');
            if ($warehouseIds !== [] && ! in_array($currentWarehouseId, $warehouseIds, true)) {
                $data['warehouse_id'] = $warehouseIds[0];
            } elseif (! array_key_exists('warehouse_id', $data)) {
                // Keep this listing on its current warehouse.
                unset($data['warehouse_id']);
            }
        }

        $book = $this->bookService->update($id, $data);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        $createdExtras = [];
        if (is_array($warehouseIds) && $warehouseIds !== []) {
            $isbn = (string) ($book->isbn ?? '');
            $basePayload = [
                'title' => $book->title,
                'author_ids' => $book->author_ids ?? [],
                'category_id' => $book->category_id,
                'size' => $book->size,
                'weight' => $book->weight,
                'cover_image' => $book->cover_image,
                'cover_image_thumb' => $book->cover_image_thumb,
                'description' => $book->description,
                'price' => $book->price,
                'pages' => $book->pages,
                'isbn' => $isbn,
                'publish_year' => $book->publish_year,
                'edition_number' => $book->edition_number,
                'binding_type' => $book->binding_type,
                'paper_type' => $book->paper_type,
                'publisher_id' => $book->publisher_id,
                'publisher_ids' => $book->publisherIdList(),
                'stock_quantity' => $book->stock_quantity,
                'discount_percent' => $book->discount_percent,
                'condition' => $book->condition ?? 'new',
                'is_visible' => $book->is_visible ?? true,
                'is_sold' => false,
            ];

            foreach ($warehouseIds as $warehouseId) {
                if ($warehouseId === (string) $book->warehouse_id) {
                    continue;
                }
                if ($isbn !== '' && \App\Models\Book::where('isbn', $isbn)->where('warehouse_id', $warehouseId)->exists()) {
                    continue;
                }
                $payload = $basePayload;
                $payload['warehouse_id'] = $warehouseId;
                $created = $this->bookService->create($payload);
                $createdExtras[] = $this->bookService->getById((string) $created->getKey());
            }
        }

        return $this->successResponse([
            'book' => $book,
            'created_in_warehouses' => $createdExtras,
            'created_count' => count($createdExtras),
        ], 'Book updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $existing = $this->bookService->getById($id);
            if (! $existing) {
                return $this->errorResponse('Book not found', 404);
            }
            if (! $existing->hasPublisher($employee->getManagedPublisherId())) {
                return $this->errorResponse('Forbidden. You can only delete your publisher\'s books.', 403);
            }
        }

        if (! $this->bookService->delete($id)) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse(null, 'Book deleted');
    }

    public function bulkDestroy(BulkDeleteBooksRequest $request): JsonResponse
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $request->validated('ids')))));
        $employee = auth('employee')->user();
        $toDelete = [];
        $forbidden = 0;
        $missing = 0;

        foreach ($ids as $id) {
            $existing = $this->bookService->getById($id, []);
            if (! $existing) {
                $missing++;
                continue;
            }
            if ($employee && UserRole::isPublisherScoped($employee->role)
                && ! $existing->hasPublisher($employee->getManagedPublisherId())) {
                $forbidden++;
                continue;
            }
            $toDelete[] = $id;
        }

        $deleted = $this->bookService->deleteMany($toDelete);

        return $this->successResponse([
            'deleted' => $deleted,
            'forbidden' => $forbidden,
            'missing' => $missing,
        ], 'Books deleted');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePublisherPayload(array $data): array
    {
        $ids = [];
        if (! empty($data['publisher_ids']) && is_array($data['publisher_ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('strval', $data['publisher_ids']))));
        } elseif (! empty($data['publisher_id'])) {
            $ids = [(string) $data['publisher_id']];
        }
        $data['publisher_ids'] = $ids;
        $data['publisher_id'] = $ids[0] ?? null;

        return $data;
    }
}
