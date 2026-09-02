<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\PublisherStoreRequest;
use App\Http\Requests\Admin\PublisherUpdateRequest;
use App\Infrastructure\Services\PublisherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublisherController extends BaseApiController
{
    public function __construct(
        protected PublisherService $publisherService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['search' => $request->get('search')];
        $perPage = min((int) $request->get('per_page', 32), 100);

        $publishers = $this->publisherService->getAll($filters, $perPage);

        return $this->successResponse($publishers);
    }

    public function store(PublisherStoreRequest $request): JsonResponse
    {
        $publisher = $this->publisherService->create($request->validated());

        return $this->successResponse($publisher->fresh(), 'Publisher created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $publisher = $this->publisherService->getById($id, ['warehouses']);

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher);
    }

    public function update(PublisherUpdateRequest $request, string $id): JsonResponse
    {
        $publisher = $this->publisherService->update($id, $request->validated());

        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher, 'Publisher updated');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! $this->publisherService->delete($id)) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse(null, 'Publisher deleted');
    }

    public function getSettings(string $id): JsonResponse
    {
        $user = auth('employee')->user();
        if (UserRole::isPublisherScoped($user->role) && $user->getManagedPublisherId() !== $id) {
            return $this->errorResponse('Forbidden', 403);
        }

        $publisher = $this->publisherService->getById($id);
        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher->settings ?? (object)[]);
    }

    public function updateSettings(Request $request, string $id): JsonResponse
    {
        $user = auth('employee')->user();
        if (UserRole::isPublisherScoped($user->role) && $user->getManagedPublisherId() !== $id) {
            return $this->errorResponse('Forbidden', 403);
        }

        $publisher = $this->publisherService->getById($id);
        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        $isManager = $user->role === UserRole::Manager->value;

        $rules = [
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string|max:50',
            'return_policy' => 'nullable|string',
            'default_discount' => 'nullable|numeric|min:0|max:100',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string',
            'paypal_email' => 'nullable|email|max:255',
            'paypal_merchant_id' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:128',
        ];
        if ($isManager) {
            $rules['platform_commission_percent'] = 'nullable|numeric|min:0|max:100';
        }

        $settings = $request->validate($rules);
        $merged = array_merge($publisher->settings ?? [], $settings);
        if (! $isManager) {
            $merged['platform_commission_percent'] = $publisher->settings['platform_commission_percent'] ?? 0;
        } elseif (array_key_exists('platform_commission_percent', $settings)) {
            $merged['platform_commission_percent'] = (float) $settings['platform_commission_percent'];
        }

        $publisher->settings = $merged;
        $publisher->save();

        return $this->successResponse($publisher->settings, 'Settings updated');
    }
}
