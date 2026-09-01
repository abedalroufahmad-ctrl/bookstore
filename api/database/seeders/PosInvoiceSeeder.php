<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Warehouse;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo POS invoices covering walk-in vs named customers, quantities,
 * baskets, discounts, other warehouses, and day/month/year report periods.
 */
class PosInvoiceSeeder extends Seeder
{
    /** @var array<string, true> */
    private array $usedBookIds = [];

    public function run(): void
    {
        $employee = Employee::where('role', 'direct_sales')->first();
        if (! $employee) {
            $this->command?->warn('No direct_sales employee found. Run EmployeeSeeder first.');

            return;
        }

        $homeWarehouseId = (string) ($employee->warehouse_id ?: Warehouse::first()?->getKey());
        if ($homeWarehouseId === '') {
            $this->command?->warn('No warehouse available for POS invoices.');

            return;
        }

        $otherWarehouseId = $this->firstWarehouseWithStockExcept($homeWarehouseId);
        $orders = app(OrderService::class);
        $now = Carbon::now();

        $created = 0;
        $created += $this->seedInvoice($orders, $employee, 'today-walkin-single', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => null,
            'when' => $now->copy()->subHours(2),
            'lines' => [['qty' => 1]],
        ]) ? 1 : 0;

        $created += $this->seedInvoice($orders, $employee, 'today-named-single', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => 'ليلى الحسن',
            'when' => $now->copy()->subHour(),
            'lines' => [['qty' => 1]],
        ]) ? 1 : 0;

        $created += $this->seedInvoice($orders, $employee, 'today-named-qty', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => 'Sara Haddad',
            'when' => $now->copy()->subMinutes(25),
            'lines' => [['qty' => 3]],
        ]) ? 1 : 0;

        $created += $this->seedInvoice($orders, $employee, 'today-named-basket', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => 'عمر خالد',
            'when' => $now->copy()->subMinutes(10),
            'lines' => [['qty' => 1], ['qty' => 2], ['qty' => 1]],
        ]) ? 1 : 0;

        if ($otherWarehouseId) {
            $created += $this->seedInvoice($orders, $employee, 'today-other-warehouse', [
                'warehouse_id' => $otherWarehouseId,
                'customer_name' => 'Maya Nassar',
                'when' => $now->copy()->subMinutes(5),
                'lines' => [['qty' => 1], ['qty' => 1]],
            ]) ? 1 : 0;
        }

        $discounted = Book::where('discount_percent', '>', 0)
            ->where('stock_quantity', '>=', 1)
            ->get()
            ->first(fn (Book $book) => $book->isPurchasable());
        if ($discounted) {
            $created += $this->seedInvoice($orders, $employee, 'today-discount', [
                'warehouse_id' => (string) $discounted->warehouse_id,
                'customer_name' => 'أحمد العلي',
                'when' => $now->copy()->subMinutes(3),
                'lines' => [['book' => $discounted, 'qty' => 1]],
            ]) ? 1 : 0;
        }

        $created += $this->seedInvoice($orders, $employee, 'month-mid', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => 'Huda Saleh',
            'when' => $now->copy()->startOfMonth()->subDays(12)->setTime(14, 20),
            'lines' => [['qty' => 2], ['qty' => 1]],
        ]) ? 1 : 0;

        $created += $this->seedInvoice($orders, $employee, 'year-q1-walkin', [
            'warehouse_id' => $otherWarehouseId ?: $homeWarehouseId,
            'customer_name' => null,
            'when' => $now->copy()->startOfYear()->addMonths(2)->setTime(11, 5),
            'lines' => [['qty' => 1]],
        ]) ? 1 : 0;

        $created += $this->seedInvoice($orders, $employee, 'last-year-named', [
            'warehouse_id' => $homeWarehouseId,
            'customer_name' => 'نادر يوسف',
            'when' => $now->copy()->subYear()->month(11)->day(18)->setTime(16, 40),
            'lines' => [['qty' => 1], ['qty' => 1], ['qty' => 1]],
        ]) ? 1 : 0;

        $warehouseIds = Warehouse::all()
            ->mapWithKeys(fn ($w) => [$w->name => (string) $w->getKey()])
            ->all();
        $wh = function (string $name) use ($warehouseIds, $homeWarehouseId): string {
            return $warehouseIds[$name] ?? $homeWarehouseId;
        };

        $extra = [
            ['today-walkin-evening', $homeWarehouseId, null, $now->copy()->subMinutes(1), [['qty' => 1]]],
            ['today-named-fadi', $homeWarehouseId, 'فادي منصور', $now->copy()->subMinutes(8), [['qty' => 1], ['qty' => 1]]],
            ['today-high-qty', $homeWarehouseId, 'Rania Daher', $now->copy()->subMinutes(12), [['qty' => 5]]],
            ['today-large-basket', $homeWarehouseId, 'سمير قاسم', $now->copy()->subMinutes(18), [['qty' => 1], ['qty' => 1], ['qty' => 1], ['qty' => 2], ['qty' => 1]]],
            ['today-cairo-named', $wh('Cairo Warehouse'), 'Youssef Farid', $now->copy()->subMinutes(30), [['qty' => 2]]],
            ['today-dubai-walkin', $wh('Dubai Warehouse'), null, $now->copy()->subMinutes(40), [['qty' => 1], ['qty' => 1]]],
            ['today-homs-qty', $wh('Homs Warehouse'), 'لينا درويش', $now->copy()->subMinutes(50), [['qty' => 4]]],
            ['today-aleppo-basket', $wh('مستودع حلب'), 'غسان حيدر', $now->copy()->subMinutes(55), [['qty' => 1], ['qty' => 2]]],
            ['aug-05-damascus', $wh('Damascus Warehouse'), 'Reem Atassi', $now->copy()->month(8)->day(5)->setTime(10, 15), [['qty' => 1]]],
            ['aug-08-damascus-walkin', $wh('Damascus Warehouse'), null, $now->copy()->month(8)->day(8)->setTime(13, 40), [['qty' => 2], ['qty' => 1]]],
            ['aug-22-riyadh', $wh('Riyadh Warehouse'), null, $now->copy()->month(8)->day(22)->setTime(18, 5), [['qty' => 1]]],
            ['aug-28-cairo', $wh('Cairo Warehouse'), 'منى عبد الله', $now->copy()->month(8)->day(28)->setTime(9, 50), [['qty' => 1], ['qty' => 1], ['qty' => 1]]],
            ['jul-09-beirut', $wh('Beirut Warehouse'), 'Karim Saab', $now->copy()->month(7)->day(9)->setTime(16, 10), [['qty' => 3]]],
            ['jul-21-riyadh', $wh('Riyadh Warehouse'), 'هند الشمري', $now->copy()->month(7)->day(21)->setTime(12, 0), [['qty' => 1], ['qty' => 1]]],
            ['jun-14-test', $wh('Test Warehouse'), 'Paul Haddad', $now->copy()->month(6)->day(14)->setTime(11, 25), [['qty' => 1], ['qty' => 2]]],
            ['may-03-cairo', $wh('Cairo Warehouse'), null, $now->copy()->month(5)->day(3)->setTime(15, 45), [['qty' => 2]]],
            ['apr-18-dubai', $wh('Dubai Warehouse'), 'Noor Alhashimi', $now->copy()->month(4)->day(18)->setTime(17, 30), [['qty' => 1]]],
            ['feb-07-amman-walkin', $homeWarehouseId, null, $now->copy()->month(2)->day(7)->setTime(10, 5), [['qty' => 1], ['qty' => 1]]],
            ['oct-2025-homs', $wh('Homs Warehouse'), 'باسم الخطيب', $now->copy()->subYear()->month(10)->day(4)->setTime(14, 55), [['qty' => 1], ['qty' => 1]]],
            ['dec-2025-amman', $homeWarehouseId, null, $now->copy()->subYear()->month(12)->day(22)->setTime(19, 10), [['qty' => 2]]],
        ];

        foreach ($extra as [$key, $warehouseId, $name, $when, $lines]) {
            $created += $this->seedInvoice($orders, $employee, $key, [
                'warehouse_id' => $warehouseId,
                'customer_name' => $name,
                'when' => $when,
                'lines' => $lines,
            ]) ? 1 : 0;
        }

        $this->command?->info("POS demo invoices created: {$created} (existing seed keys are skipped).");
    }

    /**
     * @param  array{warehouse_id: string, customer_name: ?string, when: Carbon, lines: list<array{qty: int, book?: Book}>}  $spec
     */
    private function seedInvoice(OrderService $orders, Employee $employee, string $key, array $spec): bool
    {
        $txn = 'POS-SEED-'.$key;
        if (Payment::where('transaction_id', $txn)->exists()) {
            $this->command?->line("Skip {$key} (already seeded).");

            return false;
        }

        $warehouseId = $spec['warehouse_id'];
        $items = [];
        foreach ($spec['lines'] as $line) {
            $qty = max(1, (int) $line['qty']);
            $book = $line['book'] ?? $this->nextBook($warehouseId, $qty);
            if (! $book) {
                $this->command?->warn("Skip {$key}: not enough in-stock books in warehouse {$warehouseId}.");

                return false;
            }
            $this->usedBookIds[(string) $book->getKey()] = true;
            $items[] = [
                'book_id' => (string) $book->getKey(),
                'quantity' => $qty,
            ];
        }

        try {
            $order = $orders->createPosInvoice(
                $items,
                $warehouseId,
                (string) $employee->getKey(),
                $spec['customer_name']
            );
        } catch (\Throwable $e) {
            $this->command?->error("Failed {$key}: ".$e->getMessage());

            return false;
        }

        $when = $spec['when'];
        $order->created_at = $when;
        $order->updated_at = $when;
        $order->save();

        $payment = Payment::where('order_id', (string) $order->getKey())->first();
        if ($payment) {
            $payment->transaction_id = $txn;
            $payment->created_at = $when;
            $payment->updated_at = $when;
            $payment->save();
        }

        $label = $spec['customer_name'] ?: 'walk-in';
        $this->command?->info("Created {$key} ({$label}) total={$order->total} at {$when->toDateTimeString()}");

        return true;
    }

    private function nextBook(string $warehouseId, int $minQty): ?Book
    {
        $candidates = Book::where('warehouse_id', $warehouseId)
            ->where('stock_quantity', '>=', $minQty)
            ->orderByDesc('stock_quantity')
            ->limit(120)
            ->get();

        foreach ($candidates as $book) {
            $id = (string) $book->getKey();
            if (isset($this->usedBookIds[$id])) {
                continue;
            }
            if (! $book->isPurchasable()) {
                continue;
            }
            if ($book->isUsed() && $minQty > 1) {
                continue;
            }

            return $book;
        }

        return null;
    }

    private function firstWarehouseWithStockExcept(string $exceptId): ?string
    {
        foreach (Warehouse::all() as $warehouse) {
            $id = (string) $warehouse->getKey();
            if ($id === $exceptId) {
                continue;
            }
            if ($this->nextBook($id, 1)) {
                return $id;
            }
        }

        return null;
    }
}
