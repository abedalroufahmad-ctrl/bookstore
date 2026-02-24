<?php

namespace App\Http\Controllers\Api\Admin;

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
        $perPage = min((int) $request->get('per_page', 15), 100);

        $books = $this->bookService->getAll($filters, $perPage);

        return $this->successResponse($books);
    }

    public function store(BookStoreRequest $request): JsonResponse
    {
        $book = $this->bookService->create($request->validated());

        return $this->successResponse($book->fresh(), 'Book created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->bookService->getById($id);

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse($book);
    }

    public function update(BookUpdateRequest $request, string $id): JsonResponse
    {
        $book = $this->bookService->update($id, $request->validated());

        if (! $book) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse($book, 'Book updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->bookService->delete($id)) {
            return $this->errorResponse('Book not found', 404);
        }

        return $this->successResponse(null, 'Book deleted');
    }
}
