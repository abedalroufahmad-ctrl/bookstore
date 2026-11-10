<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use App\Services\PayPalService;
use App\Services\PublisherCommissionAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends BaseApiController
{
    /**
     * Public catalog settings whitelist (no secrets).
     */
    public function publicIndex(): JsonResponse
    {
        return $this->successResponse($this->publicSettingsPayload());
    }

    public function index(): JsonResponse
    {
        $employee = auth('employee')->user();
        $collectionAccount = [];
        if ($employee && in_array($employee->role, [UserRole::Manager->value, UserRole::PublisherManager->value], true)) {
            $collectionAccount = [
                'commission_collection_paypal_email' => (string) Setting::get('commission_collection_paypal_email', ''),
                'commission_collection_bank_name' => (string) Setting::get('commission_collection_bank_name', ''),
                'commission_collection_bank_account_number' => (string) Setting::get('commission_collection_bank_account_number', ''),
            ];
        }

        // Authenticated admin still gets the same safe payload (no arbitrary keys from DB).
        return $this->successResponse(array_merge($this->publicSettingsPayload(), [
            'paypal_mode' => app(PayPalService::class)->mode(),
            'paypal_available_modes' => app(PayPalService::class)->availableModes(),
            'stripe_available' => trim((string) config('services.stripe.secret')) !== '',
        ], $collectionAccount));
    }

    private function publicSettingsPayload(): array
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        $globalDiscount = $settings['global_discount'] ?? 0;
        $invoiceShippingFee = $settings['invoice_shipping_fee'] ?? 0;
        $weightUnit = $settings['weight_unit'] ?? 'kg';
        $booksPerPage = $this->intSetting($settings, 'books_per_page', $this->intSetting($settings, 'catalog_items_per_page', 27, 1, 100), 1, 100);

        $raw = $settings['payment_methods'] ?? null;
        if (is_array($raw) && isset($raw[0]) && is_array($raw[0])) {
            $paymentMethods = array_values(array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? '',
                    'name' => $item['name'] ?? $item['id'] ?? '',
                    'enabled' => (bool) ($item['enabled'] ?? false),
                ];
            }, $raw));
        } elseif (is_array($raw) && ! empty($raw)) {
            $converted = [];
            foreach ($raw as $id => $enabled) {
                if (is_string($id)) {
                    $converted[] = ['id' => $id, 'name' => $id, 'enabled' => (bool) $enabled];
                }
            }
            $paymentMethods = $converted ?: Setting::defaultPaymentMethods();
        } else {
            $paymentMethods = Setting::defaultPaymentMethods();
        }

        return [
            'global_discount' => is_numeric($globalDiscount) ? (float) $globalDiscount : 0,
            'invoice_shipping_fee' => is_numeric($invoiceShippingFee) ? max(0, (float) $invoiceShippingFee) : 0,
            'weight_unit' => is_string($weightUnit) ? $weightUnit : 'kg',
            'catalog_items_per_page' => $booksPerPage,
            'books_per_page' => $booksPerPage,
            'books_per_row' => $this->intSetting($settings, 'books_per_row', 5, 1, 8),
            'authors_per_page' => $this->intSetting($settings, 'authors_per_page', 28, 1, 100),
            'authors_per_row' => $this->intSetting($settings, 'authors_per_row', 4, 1, 8),
            'categories_per_page' => $this->intSetting($settings, 'categories_per_page', 28, 1, 100),
            'categories_per_row' => $this->intSetting($settings, 'categories_per_row', 3, 1, 8),
            'warehouses_per_page' => $this->intSetting($settings, 'warehouses_per_page', 28, 1, 100),
            'warehouses_per_row' => $this->intSetting($settings, 'warehouses_per_row', 3, 1, 8),
            'payment_methods' => $paymentMethods,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function intSetting(array $settings, string $key, int $default, int $min, int $max): int
    {
        $raw = $settings[$key] ?? $default;
        if (! is_numeric($raw)) {
            return $default;
        }

        return max($min, min($max, (int) $raw));
    }

    public function update(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            return $this->errorResponse('Forbidden. Warehouse managers cannot change global settings. Manage your warehouse settings via the warehouse profile.', 403);
        }

        $isManager = $employee?->role === UserRole::Manager->value;
        $validated = $request->validate([
            'global_discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'invoice_shipping_fee' => ['sometimes', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'string', 'in:kg,g,lb,oz'],
            'catalog_items_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'books_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'books_per_row' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'authors_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'authors_per_row' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'categories_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'categories_per_row' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'warehouses_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'warehouses_per_row' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*.id' => ['required', 'string', 'max:50'],
            'payment_methods.*.name' => ['required', 'string', 'max:255'],
            'payment_methods.*.enabled' => ['sometimes', 'boolean'],
            'paypal_mode' => ['sometimes', 'string', 'in:sandbox,live'],
            'commission_collection_paypal_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'commission_collection_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commission_collection_bank_account_number' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        if (! $isManager) {
            unset(
                $validated['commission_collection_paypal_email'],
                $validated['commission_collection_bank_name'],
                $validated['commission_collection_bank_account_number']
            );
        }

        if (
            isset($validated['paypal_mode'])
            && ! in_array($validated['paypal_mode'], app(PayPalService::class)->availableModes(), true)
        ) {
            return $this->errorResponse(
                "PayPal {$validated['paypal_mode']} credentials are not configured on the server.",
                422
            );
        }

        if (isset($validated['books_per_page'])) {
            $validated['catalog_items_per_page'] = $validated['books_per_page'];
        } elseif (isset($validated['catalog_items_per_page']) && ! isset($validated['books_per_page'])) {
            $validated['books_per_page'] = $validated['catalog_items_per_page'];
        }

        foreach ($validated as $key => $value) {
            if ($key === 'payment_methods' && is_array($value)) {
                $value = array_values(array_map(function ($item) {
                    return [
                        'id' => (string) ($item['id'] ?? ''),
                        'name' => (string) ($item['name'] ?? ''),
                        'enabled' => (bool) ($item['enabled'] ?? false),
                    ];
                }, $value));
            }
            Setting::set($key, $value);
        }

        return $this->successResponse(null, 'Settings updated');
    }

    public function orderTotals(Request $request, PublisherCommissionAccountService $commissionAccountService): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'in:day,month,year'],
            'date' => ['required', 'date_format:Y-m-d'],
            'publisher_id' => ['nullable', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

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

        try {
            $result = $commissionAccountService->getAdminOrderTotals(
                $validated['publisher_id'] ?? null,
                $start,
                $end,
                (int) ($validated['per_page'] ?? 25)
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse($result);
    }
}
