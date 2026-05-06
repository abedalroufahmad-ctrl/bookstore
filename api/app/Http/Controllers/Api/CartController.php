<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use Illuminate\Http\JsonResponse;

class CartController extends BaseApiController
{
    public function __construct(
        private CartServiceInterface $cartService
    ) {}

    public function show(): JsonResponse
    {
        $customer = auth('customer')->user();
        $cart = $this->cartService->getOrCreateActiveCart($customer);
        $items = $this->cartService->getItemsWithDetails($cart);
        $total = $this->cartService->calculateTotal($cart);

        return $this->successResponse([
            'cart' => $cart,
            'items' => $items,
            'total' => round($total, 2),
        ]);
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $cart = $this->cartService->getOrCreateActiveCart($customer);
            $cart = $this->cartService->addBook(
                $cart,
                $request->validated('book_id'),
                $request->validated('quantity', 1)
            );

            $total = $this->cartService->calculateTotal($cart);

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Item added to cart');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function removeItem(string $bookId): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $cart = $this->cartService->getOrCreateActiveCart($customer);
            $cart = $this->cartService->removeBook($cart, $bookId);
            $total = $this->cartService->calculateTotal($cart);

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Item removed from cart');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function updateItem(UpdateCartItemRequest $request, string $bookId): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $cart = $this->cartService->getOrCreateActiveCart($customer);
            $cart = $this->cartService->updateQuantity(
                $cart,
                $bookId,
                $request->validated('quantity')
            );

            $total = $this->cartService->calculateTotal($cart);

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Cart updated');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
