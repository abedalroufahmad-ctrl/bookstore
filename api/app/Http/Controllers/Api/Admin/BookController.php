<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\BookStoreRequest;
use App\Http\Requests\Admin\BookUpdateRequest;
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

        // Publisher managers can only create books under their own publisher.
        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $publisherId = $employee->getManagedPublisherId();
            if (! $publisherId) {
                return $this->errorResponse('Forbidden. You are not assigned to a publisher.', 403);
            }
            $data['publisher_id'] = $publisherId;
        }

        $book = $this->bookService->create($data);
        $book = $this->bookService->getById((string) $book->getKey());

        return $this->successResponse($book, 'Book created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->bookService->getById($id);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)
            && ! $employee->managesPublisher((string) ($book->publisher_id ?? ''))) {
            return $this->errorResponse('Forbidden. You can only access your publisher\'s books.', 403);
        }

        $book->loadMissing(['authors', 'publisher']);

        return $this->successResponse($book);
    }

    public function update(BookUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $existing = $this->bookService->getById($id);
            if (! $existing) {
                return $this->errorResponse('Book not found', 404);
            }
            if (! $employee->managesPublisher((string) ($existing->publisher_id ?? ''))) {
                return $this->errorResponse('Forbidden. You can only update your publisher\'s books.', 403);
            }
            // Keep the book under this publisher.
            $data['publisher_id'] = $employee->getManagedPublisherId();
        }

        $book = $this->bookService->update($id, $data);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse($book, 'Book updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isPublisherScoped($employee->role)) {
            $existing = $this->bookService->getById($id);
            if (! $existing) {
                return $this->errorResponse('Book not found', 404);
            }
            if (! $employee->managesPublisher((string) ($existing->publisher_id ?? ''))) {
                return $this->errorResponse('Forbidden. You can only delete your publisher\'s books.', 403);
            }
        }

        if (! $this->bookService->delete($id)) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse(null, 'Book deleted');
    }
}
