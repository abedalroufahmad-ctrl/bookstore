<?php

/**
 * Read books_store from local MongoDB and write MySQL INSERT statements
 * that match api2's schema. Mongo ObjectIds become sequential integer ids,
 * and foreign keys (including ids inside JSON) are rewritten to those integers.
 *
 * Usage: php database/export_mongodb_to_sql.php
 */

require __DIR__.'/../../api/vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

const ID_KEYS = [
    'book_id' => ['books'],
    'author_id' => ['authors'],
    'author_ids' => ['authors'],
    'publisher_id' => ['publishers'],
    'publisher_ids' => ['publishers'],
    'warehouse_id' => ['warehouses'],
    'warehouse_ids' => ['warehouses'],
    'customer_id' => ['customers'],
    'employee_id' => ['employees'],
    'category_id' => ['categories'],
    'order_id' => ['orders'],
    'country_id' => ['countries'],
    'countryId' => ['countries'],
    'manager_id' => ['employees'],
    'user_id' => ['customers', 'employees'],
];

$uri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
$databaseName = getenv('MONGODB_DATABASE') ?: 'books_store';
$outPath = __DIR__.'/mongodb_data.sql';

$client = new Client($uri);
$db = $client->selectDatabase($databaseName);
$typeMap = ['root' => 'array', 'document' => 'array', 'array' => 'array'];

$collections = [
    'customers',
    'publishers',
    'warehouses',
    'employees',
    'authors',
    'categories',
    'books',
    'carts',
    'orders',
    'payments',
    'publisher_commission_entries',
    'settings',
    'countries',
    'cities',
    'currencies',
];

$docs = [];
foreach ($collections as $name) {
    $rows = [];
    foreach ($db->selectCollection($name)->find([], ['typeMap' => $typeMap, 'sort' => ['_id' => 1]]) as $doc) {
        $rows[] = normalize($doc);
    }
    $docs[$name] = $rows;
}

// currencies.code is unique. Mongo stored one row per country, so EUR/USD repeat.
$currencyAlias = [];
$currencyDocs = [];
$seenCurrencyCodes = [];
foreach ($docs['currencies'] as $doc) {
    $code = strtoupper(trim((string) ($doc['code'] ?? '')));
    if ($code === '') {
        continue;
    }
    $doc['code'] = $code;
    if (isset($seenCurrencyCodes[$code])) {
        $currencyAlias[(string) $doc['_id']] = $seenCurrencyCodes[$code];
        continue;
    }
    $seenCurrencyCodes[$code] = (string) $doc['_id'];
    $currencyDocs[] = $doc;
}
$docs['currencies'] = $currencyDocs;

$maps = [];
foreach ($collections as $name) {
    $maps[$name] = [];
    $next = 1;
    foreach ($docs[$name] as $doc) {
        $maps[$name][(string) $doc['_id']] = $next++;
    }
}
foreach ($currencyAlias as $duplicateId => $keptId) {
    $maps['currencies'][$duplicateId] = $maps['currencies'][$keptId];
}

$fh = fopen($outPath, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Cannot write {$outPath}\n");
    exit(1);
}

fwrite($fh, "-- Data exported from MongoDB {$databaseName} on ".gmdate('Y-m-d H:i:s')." UTC\n");
fwrite($fh, "-- Integer ids replace Mongo ObjectIds. JSON references are rewritten to those ids.\n");
fwrite($fh, "SET NAMES utf8mb4;\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($fh, "START TRANSACTION;\n\n");

$counts = [];
$counts['customers'] = insert($fh, 'customers', $docs['customers'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', ''),
        'email' => str($doc, 'email', ''),
        'password' => str($doc, 'password', ''),
        'phone' => str($doc, 'phone'),
        'address' => str($doc, 'address'),
        'country' => str($doc, 'country'),
        'city' => str($doc, 'city'),
        'postal_code' => str($doc, 'postal_code'),
        'payment_info' => jsonCol($doc['payment_info'] ?? null, $maps),
        'remember_token' => null,
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['publishers'] = insert($fh, 'publishers', $docs['publishers'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', ''),
        'address' => str($doc, 'address'),
        'phone' => str($doc, 'phone'),
        'email' => str($doc, 'email'),
        'website' => str($doc, 'website'),
        'settings' => jsonCol($doc['settings'] ?? null, $maps),
        'commission_owed' => num($doc, 'commission_owed', 0),
        'commission_accrued_total' => num($doc, 'commission_accrued_total', 0),
        'commission_paid_total' => num($doc, 'commission_paid_total', 0),
        'commission_balances' => jsonCol($doc['commission_balances'] ?? null, $maps),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['warehouses'] = insert($fh, 'warehouses', $docs['warehouses'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', ''),
        'address' => str($doc, 'address'),
        'country' => str($doc, 'country'),
        'city' => str($doc, 'city'),
        'phone' => str($doc, 'phone'),
        'email' => str($doc, 'email'),
        'location' => jsonCol($doc['location'] ?? null, $maps),
        'payment_methods' => jsonCol($doc['payment_methods'] ?? null, $maps),
        'shipping_options' => jsonCol($doc['shipping_options'] ?? null, $maps),
        'manager_id' => ref($maps, 'employees', $doc['manager_id'] ?? null),
        'publisher_id' => ref($maps, 'publishers', $doc['publisher_id'] ?? null),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['employees'] = insert($fh, 'employees', $docs['employees'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', ''),
        'email' => str($doc, 'email', ''),
        'phone' => str($doc, 'phone'),
        'password' => str($doc, 'password', ''),
        'role' => str($doc, 'role', 'employee'),
        'warehouse_id' => ref($maps, 'warehouses', $doc['warehouse_id'] ?? null),
        'warehouse_ids' => jsonCol(refList($maps, 'warehouses', $doc['warehouse_ids'] ?? null), $maps, false),
        'publisher_id' => ref($maps, 'publishers', $doc['publisher_id'] ?? null),
        'remember_token' => null,
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['authors'] = insert($fh, 'authors', $docs['authors'], $maps, function ($doc, $id) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', ''),
        'biography' => str($doc, 'biography'),
        'date_of_birth' => dateOnly($doc, 'date_of_birth'),
        'date_of_death' => dateOnly($doc, 'date_of_death'),
        'photo' => str($doc, 'photo'),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['categories'] = insert($fh, 'categories', $docs['categories'], $maps, function ($doc, $id) {
    return [
        'id' => $id,
        'dewey_code' => str($doc, 'dewey_code'),
        'subject_title_en' => str($doc, 'subject_title_en'),
        'subject_title_ar' => str($doc, 'subject_title_ar'),
        'subject_number' => str($doc, 'subject_number'),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['books'] = insert($fh, 'books', $docs['books'], $maps, function ($doc, $id) use ($maps) {
    $authorIds = refList($maps, 'authors', $doc['author_ids'] ?? null);
    $publisherIds = refList($maps, 'publishers', $doc['publisher_ids'] ?? null);
    $publisherId = ref($maps, 'publishers', $doc['publisher_id'] ?? null);
    if ($publisherId === null && $publisherIds !== []) {
        $publisherId = $publisherIds[0];
    }
    if ($publisherIds === [] && $publisherId !== null) {
        $publisherIds = [$publisherId];
    }

    return [
        'id' => $id,
        'title' => str($doc, 'title', ''),
        'author_ids' => jsonCol($authorIds, $maps, false),
        'category_id' => ref($maps, 'categories', $doc['category_id'] ?? null),
        'size' => str($doc, 'size'),
        'weight' => num($doc, 'weight'),
        'cover_image' => str($doc, 'cover_image'),
        'cover_image_thumb' => str($doc, 'cover_image_thumb'),
        'has_cover' => boolInt($doc, 'has_cover', false),
        'description' => str($doc, 'description'),
        'price' => num($doc, 'price', 0),
        'prices' => jsonCol($doc['prices'] ?? null, $maps),
        'pages' => intOrNull($doc, 'pages'),
        'isbn' => str($doc, 'isbn'),
        'publish_year' => intOrNull($doc, 'publish_year'),
        'edition_number' => intOrNull($doc, 'edition_number'),
        'binding_type' => str($doc, 'binding_type'),
        'paper_type' => str($doc, 'paper_type'),
        'publisher_id' => $publisherId,
        'publisher_ids' => jsonCol($publisherIds, $maps, false),
        'warehouse_id' => ref($maps, 'warehouses', $doc['warehouse_id'] ?? null),
        'stock_quantity' => (int) ($doc['stock_quantity'] ?? 0),
        'discount_percent' => num($doc, 'discount_percent'),
        'condition' => str($doc, 'condition', 'new') ?? 'new',
        'is_visible' => boolInt($doc, 'is_visible', true),
        'is_sold' => boolInt($doc, 'is_sold', false),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$pivotAuthors = [];
$pivotPublishers = [];
foreach ($docs['books'] as $doc) {
    $bookId = (string) $maps['books'][(string) $doc['_id']];
    foreach (refList($maps, 'authors', $doc['author_ids'] ?? null) as $authorId) {
        $pivotAuthors[$authorId.'|'.$bookId] = ['author_id' => $authorId, 'book_id' => $bookId];
    }
    $publisherIds = refList($maps, 'publishers', $doc['publisher_ids'] ?? null);
    $primary = ref($maps, 'publishers', $doc['publisher_id'] ?? null);
    if ($primary !== null) {
        $publisherIds[] = $primary;
    }
    foreach (array_unique($publisherIds) as $publisherId) {
        $pivotPublishers[$publisherId.'|'.$bookId] = ['publisher_id' => $publisherId, 'book_id' => $bookId];
    }
}
$counts['author_book'] = insertRaw($fh, 'author_book', array_values($pivotAuthors));
$counts['book_publisher'] = insertRaw($fh, 'book_publisher', array_values($pivotPublishers));

$counts['carts'] = insert($fh, 'carts', $docs['carts'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'customer_id' => ref($maps, 'customers', $doc['customer_id'] ?? null) ?? '',
        'items' => jsonCol($doc['items'] ?? null, $maps),
        'status' => str($doc, 'status', 'active') ?? 'active',
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['orders'] = insert($fh, 'orders', $docs['orders'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'customer_id' => ref($maps, 'customers', $doc['customer_id'] ?? null),
        'customer_name' => str($doc, 'customer_name'),
        'is_direct_sale' => boolInt($doc, 'is_direct_sale', false),
        'employee_id' => ref($maps, 'employees', $doc['employee_id'] ?? null),
        'warehouse_id' => ref($maps, 'warehouses', $doc['warehouse_id'] ?? null),
        'items' => jsonCol($doc['items'] ?? null, $maps),
        'status' => str($doc, 'status', 'pending') ?? 'pending',
        'stock_reserved' => boolInt($doc, 'stock_reserved', false),
        'books_subtotal' => num($doc, 'books_subtotal'),
        'shipping_fee' => num($doc, 'shipping_fee'),
        'shipping_method' => str($doc, 'shipping_method'),
        'total' => num($doc, 'total', 0),
        'currency_code' => str($doc, 'currency_code', 'USD') ?? 'USD',
        'shipping_address' => jsonCol($doc['shipping_address'] ?? null, $maps),
        'payment_info' => jsonCol($doc['payment_info'] ?? null, $maps),
        'payment_method' => str($doc, 'payment_method'),
        'payment_status' => str($doc, 'payment_status'),
        'stripe_checkout_session_id' => str($doc, 'stripe_checkout_session_id'),
        'stripe_checkout_session_expires_at' => intOrNull($doc, 'stripe_checkout_session_expires_at'),
        'platform_commission_percent' => num($doc, 'platform_commission_percent'),
        'platform_commission_amount' => num($doc, 'platform_commission_amount'),
        'publisher_payout_amount' => num($doc, 'publisher_payout_amount'),
        'payout_paypal_email' => str($doc, 'payout_paypal_email'),
        'payout_paypal_merchant_id' => str($doc, 'payout_paypal_merchant_id'),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['payments'] = insert($fh, 'payments', $docs['payments'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'order_id' => ref($maps, 'orders', $doc['order_id'] ?? null),
        'user_id' => refEither($maps, ['customers', 'employees'], $doc['user_id'] ?? null),
        'amount' => num($doc, 'amount'),
        'currency' => str($doc, 'currency'),
        'method' => str($doc, 'method'),
        'payment_method' => str($doc, 'payment_method'),
        'payment_status' => str($doc, 'payment_status'),
        'status' => str($doc, 'status'),
        'transaction_id' => str($doc, 'transaction_id'),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['publisher_commission_entries'] = insert($fh, 'publisher_commission_entries', $docs['publisher_commission_entries'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'publisher_id' => ref($maps, 'publishers', $doc['publisher_id'] ?? null) ?? '',
        'order_id' => ref($maps, 'orders', $doc['order_id'] ?? null),
        'type' => str($doc, 'type', '') ?? '',
        'amount' => num($doc, 'amount', 0),
        'currency_code' => str($doc, 'currency_code', 'USD') ?? 'USD',
        'note' => str($doc, 'note'),
        'reference' => str($doc, 'reference'),
        'created_by' => refEither($maps, ['employees', 'customers'], $doc['created_by'] ?? null),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['settings'] = insert($fh, 'settings', $docs['settings'], $maps, function ($doc, $id) use ($maps) {
    $value = $doc['value'] ?? null;
    if (is_array($value)) {
        $stored = json_encode(remapValue($value, $maps), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_bool($value)) {
        $stored = $value ? '1' : '0';
    } elseif ($value === null) {
        $stored = null;
    } else {
        $stored = (string) $value;
    }

    return [
        'id' => $id,
        'key' => str($doc, 'key', '') ?? '',
        'value' => $stored,
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['countries'] = insert($fh, 'countries', $docs['countries'], $maps, function ($doc, $id) use ($maps) {
    return [
        'id' => $id,
        'name' => str($doc, 'name', '') ?? '',
        'code' => str($doc, 'code'),
        'translations' => jsonCol($doc['translations'] ?? null, $maps),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['cities'] = insert($fh, 'cities', $docs['cities'], $maps, function ($doc, $id) use ($maps) {
    $countryId = $doc['country_id'] ?? $doc['countryId'] ?? null;

    return [
        'id' => $id,
        'name' => str($doc, 'name', '') ?? '',
        'country_id' => ref($maps, 'countries', $countryId) ?? '',
        'translations' => jsonCol($doc['translations'] ?? null, $maps),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

$counts['currencies'] = insert($fh, 'currencies', $docs['currencies'], $maps, function ($doc, $id) {
    return [
        'id' => $id,
        'code' => str($doc, 'code', '') ?? '',
        'symbol' => str($doc, 'symbol'),
        'name' => str($doc, 'name'),
        'nameArabic' => str($doc, 'nameArabic'),
        'exchangeRate' => num($doc, 'exchangeRate', 1),
        'isActive' => boolInt($doc, 'isActive', true),
        'isDefault' => boolInt($doc, 'isDefault', false),
        'countryCode' => str($doc, 'countryCode'),
        'created_at' => dt($doc, 'created_at'),
        'updated_at' => dt($doc, 'updated_at'),
    ];
});

fwrite($fh, "COMMIT;\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

echo "Wrote {$outPath}\n";
foreach ($counts as $table => $count) {
    echo str_pad($table, 32), $count, "\n";
}

function insert($fh, string $table, array $docs, array $maps, callable $row): int
{
    $rows = [];
    foreach ($docs as $doc) {
        $mongoId = (string) $doc['_id'];
        $id = $maps[$table][$mongoId] ?? null;
        if ($id === null) {
            continue;
        }
        $rows[] = $row($doc, $id);
    }

    return insertRaw($fh, $table, $rows);
}

function insertRaw($fh, string $table, array $rows): int
{
    if ($rows === []) {
        return 0;
    }
    $columns = array_keys($rows[0]);
    $columnSql = implode(', ', array_map(fn ($c) => '`'.$c.'`', $columns));
    $chunks = array_chunk($rows, 100);
    foreach ($chunks as $chunk) {
        fwrite($fh, "INSERT INTO `{$table}` ({$columnSql}) VALUES\n");
        $lines = [];
        foreach ($chunk as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = sqlValue($row[$column] ?? null);
            }
            $lines[] = '('.implode(', ', $values).')';
        }
        fwrite($fh, implode(",\n", $lines).";\n\n");
    }

    return count($rows);
}

function normalize(mixed $value): mixed
{
    if ($value instanceof ObjectId) {
        return (string) $value;
    }
    if ($value instanceof UTCDateTime) {
        return $value->toDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if ($value instanceof DateTimeInterface) {
        return (new DateTimeImmutable('@'.$value->getTimestamp()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = normalize($item);
        }

        return $out;
    }

    return $value;
}

function ref(array $maps, string $collection, mixed $id): ?string
{
    if ($id === null || $id === '') {
        return null;
    }
    $key = (string) $id;
    if (! isset($maps[$collection][$key])) {
        return null;
    }

    return (string) $maps[$collection][$key];
}

function refEither(array $maps, array $collections, mixed $id): ?string
{
    foreach ($collections as $collection) {
        $mapped = ref($maps, $collection, $id);
        if ($mapped !== null) {
            return $mapped;
        }
    }

    return null;
}

function refList(array $maps, string $collection, mixed $ids): array
{
    if (is_string($ids)) {
        $decoded = json_decode($ids, true);
        $ids = is_array($decoded) ? $decoded : [$ids];
    }
    if (! is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        $mapped = ref($maps, $collection, $id);
        if ($mapped !== null) {
            $out[] = $mapped;
        }
    }

    return array_values(array_unique($out));
}

function remapValue(mixed $value, array $maps): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && isset(ID_KEYS[$key])) {
            if (is_array($item)) {
                $out[$key] = refList($maps, ID_KEYS[$key][0], $item);
            } else {
                $out[$key] = refEither($maps, ID_KEYS[$key], $item);
            }
            continue;
        }
        $out[$key] = is_array($item) ? remapValue($item, $maps) : $item;
    }

    return $out;
}

function jsonCol(mixed $value, array $maps, bool $remap = true): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $value = $decoded;
        } else {
            return $value;
        }
    }
    if ($remap) {
        $value = remapValue($value, $maps);
    }
    if ($value === [] || $value === null) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function str(array $doc, string $key, ?string $default = null): ?string
{
    if (! array_key_exists($key, $doc) || $doc[$key] === null || $doc[$key] === '') {
        return $default;
    }
    if (is_bool($doc[$key])) {
        return $doc[$key] ? '1' : '0';
    }
    if (is_array($doc[$key])) {
        return json_encode($doc[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $doc[$key];
}

function num(array $doc, string $key, ?float $default = null): ?float
{
    if (! array_key_exists($key, $doc) || $doc[$key] === null || $doc[$key] === '') {
        return $default;
    }
    if (! is_numeric($doc[$key])) {
        return $default;
    }

    return (float) $doc[$key];
}

function intOrNull(array $doc, string $key): ?int
{
    if (! array_key_exists($key, $doc) || $doc[$key] === null || $doc[$key] === '' || ! is_numeric($doc[$key])) {
        return null;
    }

    return (int) $doc[$key];
}

function boolInt(array $doc, string $key, bool $default): int
{
    if (! array_key_exists($key, $doc) || $doc[$key] === null || $doc[$key] === '') {
        return $default ? 1 : 0;
    }

    return filter_var($doc[$key], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

function dt(array $doc, string $key): string
{
    $value = $doc[$key] ?? null;
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        return substr(str_replace('T', ' ', $value), 0, 19);
    }

    return gmdate('Y-m-d H:i:s');
}

function dateOnly(array $doc, string $key): ?string
{
    $value = dtOrNull($doc, $key);
    if ($value === null) {
        return null;
    }

    return substr($value, 0, 10);
}

function dtOrNull(array $doc, string $key): ?string
{
    if (! array_key_exists($key, $doc) || $doc[$key] === null || $doc[$key] === '') {
        return null;
    }
    $value = $doc[$key];
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        return substr(str_replace('T', ' ', $value), 0, 19);
    }

    return null;
}

function sqlValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        if (! is_finite($value)) {
            return 'NULL';
        }
        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    return "'".str_replace(
        ["\\", "'", "\0", "\n", "\r"],
        ["\\\\", "\\'", '\\0', '\\n', '\\r'],
        (string) $value
    )."'";
}
