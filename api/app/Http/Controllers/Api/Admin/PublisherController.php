<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CommissionSettlementStoreRequest;
use App\Http\Requests\Admin\PublisherSettingsUpdateRequest;
use App\Http\Requests\Admin\PublisherStoreRequest;
use App\Http\Requests\Admin\PublisherUpdateRequest;
use App\Infrastructure\Services\PublisherService;
use App\Services\PublisherCommissionAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublisherController extends BaseApiController
{
    public function __construct(
        protected PublisherService $publisherService,
        protected PublisherCommissionAccountService $commissionAccountService
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
        if (! $this->canManagePublisherSettings($id)) {
            return $this->errorResponse('Forbidden', 403);
        }

        $publisher = $this->publisherService->getById($id);
        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        return $this->successResponse($publisher->settings ?? (object) []);
    }

    public function updateSettings(PublisherSettingsUpdateRequest $request, string $id): JsonResponse
    {
        if (! $this->canManagePublisherSettings($id)) {
            return $this->errorResponse('Forbidden', 403);
        }

        $publisher = $this->publisherService->getById($id);
        if (! $publisher) {
            return $this->errorResponse('Publisher not found', 404);
        }

        $isManager = auth('employee')->user()->role === UserRole::Manager->value;

        $settings = $request->validated();
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

    public function commissionAccount(Request $request, string $id): JsonResponse
    {
        if (! $this->canManagePublisherSettings($id)) {
            return $this->errorResponse('Forbidden', 403);
        }

        try {
            $summary = $this->commissionAccountService->getAccountSummary($id);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $perPage = min((int) $request->get('per_page', 24), 100);
        $ledger = $this->commissionAccountService->getLedger($id, $perPage);

        return $this->successResponse([
            'account' => $summary,
            'ledger' => $ledger,
        ]);
    }

    public function orderTotals(Request $request, string $id): JsonResponse
    {
        if (! $this->canManagePublisherSettings($id)) {
            return $this->errorResponse('Forbidden', 403);
        }

        try {
            $this->commissionAccountService->getAccountSummary($id);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $validated = $request->validate([
            'period' => ['required', 'in:all,day,month,year'],
            'date' => ['required_unless:period,all', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $start = $end = null;
        if ($validated['period'] !== 'all') {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], config('app.timezone'));
            $start = match ($validated['period']) {
                'month' => $date->startOfMonth(),
                'year' => $date->startOfYear(),
                default => $date->startOfDay(),
            };
            $end = match ($validated['period']) {
                'month' => $date->endOfMonth(),
                'year' => $date->endOfYear(),
                default => $date->endOfDay(),
            };
        }

        return $this->successResponse(
            $this->commissionAccountService->getOrderTotals(
                $id,
                $start,
                $end,
                (int) ($validated['per_page'] ?? 25)
            )
        );
    }

    public function storeCommissionSettlement(CommissionSettlementStoreRequest $request, string $id): JsonResponse
    {
        if (! $this->canManagePublisherSettings($id)) {
            return $this->errorResponse('Forbidden', 403);
        }

        $data = $request->validated();
        $employeeId = (string) (auth('employee')->id() ?? '');

        try {
            $entry = $this->commissionAccountService->recordSettlement(
                $id,
                (float) $data['amount'],
                $employeeId !== '' ? $employeeId : null,
                $data['note'] ?? null,
                $data['reference'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'not found') ? 404 : 422;

            return $this->errorResponse($message, $status);
        }

        $summary = $this->commissionAccountService->getAccountSummary($id);

        return $this->successResponse([
            'entry' => $entry,
            'account' => $summary,
        ], 'Commission transfer recorded', 201);
    }

    /** Payout settings (bank / PayPal / commission): global managers or the publisher's own manager. */
    private function canManagePublisherSettings(string $publisherId): bool
    {
        $user = auth('employee')->user();
        if (! $user) {
            return false;
        }
        if ($user->role === UserRole::Manager->value) {
            return true;
        }

        return UserRole::isPublisherScoped($user->role)
            && $user->getManagedPublisherId() === $publisherId;
    }
}
