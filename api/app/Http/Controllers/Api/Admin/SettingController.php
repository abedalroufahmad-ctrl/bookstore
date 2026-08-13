<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
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
        // Authenticated admin still gets the same safe payload (no arbitrary keys from DB).
        return $this->successResponse($this->publicSettingsPayload());
    }

    private function publicSettingsPayload(): array
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        $globalDiscount = $settings['global_discount'] ?? 0;
        $weightUnit = $settings['weight_unit'] ?? 'kg';
        $catalogItems = $settings['catalog_items_per_page'] ?? 24;

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
            'weight_unit' => is_string($weightUnit) ? $weightUnit : 'kg',
            'catalog_items_per_page' => is_numeric($catalogItems) ? (int) $catalogItems : 24,
            'payment_methods' => $paymentMethods,
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $employee = auth('employee')->user();
        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            return $this->errorResponse('Forbidden. Warehouse managers cannot change global settings. Manage your warehouse settings via the warehouse profile.', 403);
        }

        $validated = $request->validate([
            'global_discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'weight_unit' => ['sometimes', 'string', 'in:kg,g,lb,oz'],
            'catalog_items_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*.id' => ['required', 'string', 'max:50'],
            'payment_methods.*.name' => ['required', 'string', 'max:255'],
            'payment_methods.*.enabled' => ['sometimes', 'boolean'],
        ]);

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
}
