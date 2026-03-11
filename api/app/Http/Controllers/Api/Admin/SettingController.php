<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Domain\Auth\Enums\UserRole;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends BaseApiController
{
    public static function defaultPaymentMethods(): array
    {
        return [
            ['id' => 'cod', 'name' => 'Cash on Delivery (COD)', 'enabled' => true],
            ['id' => 'stripe', 'name' => 'Credit/Debit Card (Stripe)', 'enabled' => false],
            ['id' => 'paypal', 'name' => 'PayPal', 'enabled' => false],
        ];
    }

    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        if (! isset($settings['global_discount'])) {
            $settings['global_discount'] = 0;
        }

        if (! isset($settings['weight_unit'])) {
            $settings['weight_unit'] = 'kg';
        }

        if (! isset($settings['catalog_items_per_page'])) {
            $settings['catalog_items_per_page'] = 35;
        }

        $raw = $settings['payment_methods'] ?? null;
        if (is_array($raw) && isset($raw[0]) && is_array($raw[0])) {
            $settings['payment_methods'] = array_values(array_map(function ($item) {
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
            $settings['payment_methods'] = $converted ?: self::defaultPaymentMethods();
        } else {
            $settings['payment_methods'] = self::defaultPaymentMethods();
        }

        return $this->successResponse($settings);
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
