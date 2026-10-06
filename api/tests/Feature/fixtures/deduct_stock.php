<?php

/**
 * Worker used by StockConcurrencyTest: boots the app and tries to buy one copy.
 * Usage: php deduct_stock.php <bookId> <quantity>
 * Prints "OK" or "FAIL".
 */

use App\Domain\Order\Interfaces\StockServiceInterface;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) config('database.connections.mongodb.database'), '_test')) {
    fwrite(STDERR, "Refusing to run against a non-test database.\n");
    exit(2);
}

[$bookId, $quantity] = [$argv[1] ?? '', (int) ($argv[2] ?? 1)];

try {
    $app->make(StockServiceInterface::class)->validateAndDeduct([
        ['book_id' => $bookId, 'quantity' => $quantity],
    ]);
    echo 'OK';
} catch (\InvalidArgumentException) {
    echo 'FAIL';
}
