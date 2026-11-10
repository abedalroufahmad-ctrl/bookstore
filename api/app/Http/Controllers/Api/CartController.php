<?php

namespace App\Http\Controllers\Api;

use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseApiController
{
    public function __construct(
        private CartServiceInterface $cartService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $currencyCode = $this->currencyCode($request);
        $customer = auth('customer')->user();
        $cart = $this->cartService->getOrCreateActiveCart($customer);
        $items = $this->cartService->getItemsWithDetails($cart, $currencyCode);
        $total = $this->cartService->calculateTotal($cart, $currencyCode);

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
                $request->validated('quantity', 1),
                $request->validated('currency_code', 'USD')
            );

            $total = $this->cartService->calculateTotal($cart, $request->validated('currency_code', 'USD'));

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Item added to cart');
        } catch (\InvalidArgumentException $e) {
            return $this->domainErrorResponse($e);
        }
    }

    public function removeItem(Request $request, string $bookId): JsonResponse
    {
        try {
            $customer = auth('customer')->user();
            $cart = $this->cartService->getOrCreateActiveCart($customer);
            $cart = $this->cartService->removeBook($cart, $bookId);
            $total = $this->cartService->calculateTotal($cart, $this->currencyCode($request));

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Item removed from cart');
        } catch (\InvalidArgumentException $e) {
            return $this->domainErrorResponse($e);
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
                $request->validated('quantity'),
                $request->validated('currency_code', 'USD')
            );

            $total = $this->cartService->calculateTotal($cart, $request->validated('currency_code', 'USD'));

            return $this->successResponse([
                'cart' => $cart,
                'total' => round($total, 2),
            ], 'Cart updated');
        } catch (\InvalidArgumentException $e) {
            return $this->domainErrorResponse($e);
        }
    }

    private function currencyCode(Request $request): string
    {
        $validated = $request->validate([
            'currency_code' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ]);

        return $validated['currency_code'] ?? 'USD';
    }
}
