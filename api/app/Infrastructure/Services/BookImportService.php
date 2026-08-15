<?php

namespace App\Infrastructure\Services;

use App\Domain\Book\Enums\BookCondition;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BookImportService
{
    /**
     * Import books from Excel/ODS/CSV into a warehouse (store).
     *
     * @param  array{skip_cover?: bool, publisher_id?: ?string, dry_run?: bool}  $options
     * @return array{created: int, skipped: int, errors: int, messages: array<int, string>}
     */
    public function importFromFile(string $filePath, string $warehouseId, array $options = []): array
    {
        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            throw new \InvalidArgumentException('Warehouse (store) not found.');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        if (empty($rows)) {
            throw new \InvalidArgumentException('The spreadsheet is empty.');
        }

        $headers = array_map('trim', array_map('strval', $rows[0]));
        $columnMap = $this->detectColumnMap($headers);

        $skipCover = (bool) ($options['skip_cover'] ?? true);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $forcedPublisherId = $options['publisher_id'] ?? null;

        $created = 0;
        $skipped = 0;
        $errors = 0;
        $messages = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            $title = $this->getCell($row, $columnMap, 'title');
            $authorName = $this->getCell($row, $columnMap, 'author');
            $categoryName = $this->getCell($row, $columnMap, 'category');
            $isbn = $this->normalizeIsbn($this->getCell($row, $columnMap, 'isbn'));

            if (empty($title) && empty($isbn)) {
                continue;
            }

            if (empty($title)) {
                $messages[] = "Row {$rowNum}: skipped (no title)";
                $skipped++;

                continue;
            }

            if (empty($isbn)) {
                $isbn = 'IMPORT-'.Str::uuid();
            }

            if (Book::where('isbn', $isbn)->where('warehouse_id', (string) $warehouse->getKey())->exists()) {
                $messages[] = "Row {$rowNum}: skipped (ISBN already exists in this store)";
                $skipped++;

                continue;
            }

            try {
                $authorNames = $this->splitByComma($authorName);
                if ($authorNames === []) {
                    $authorNames = ['Unknown'];
                }

                $categoryNames = $this->splitByComma($categoryName);
                if ($categoryNames === []) {
                    $categoryNames = ['General'];
                }

                $authorIds = [];
                foreach ($authorNames as $name) {
                    $author = Author::firstOrCreate(['name' => $name]);
                    $authorIds[] = (string) $author->getKey();
                }

                $category = Category::firstOrCreate(
                    ['subject_title_en' => $categoryNames[0]],
                    ['dewey_code' => substr(md5($categoryNames[0]), 0, 3), 'subject_title_en' => $categoryNames[0]]
                );

                $price = (float) ($this->getCell($row, $columnMap, 'price') ?: 0);
                $condition = BookCondition::normalize($this->getCell($row, $columnMap, 'condition'));
                $stockRaw = $this->getCell($row, $columnMap, 'stock');
                $stock = $stockRaw !== null && $stockRaw !== '' ? (int) $stockRaw : ($condition === BookCondition::Used ? 1 : 10);
                if ($condition === BookCondition::Used) {
                    $stock = min(1, max(0, $stock));
                }

                $visibilityRaw = strtolower((string) ($this->getCell($row, $columnMap, 'visibility') ?? 'visible'));
                $isVisible = ! in_array($visibilityRaw, ['hidden', 'hide', '0', 'false', 'no', 'مخفي'], true);

                $publisherId = $forcedPublisherId;
                if (! $publisherId) {
                    $publisherName = trim((string) ($this->getCell($row, $columnMap, 'publisher') ?: ''));
                    if ($publisherName !== '') {
                        $publisher = Publisher::firstOrCreate(['name' => $publisherName], ['name' => $publisherName]);
                        $publisherId = (string) $publisher->getKey();
                    } elseif ($warehouse->publisher_id) {
                        $publisherId = (string) $warehouse->publisher_id;
                    }
                }

                $pages = $this->getCellAsInt($row, $columnMap, 'pages');
                $year = $this->getCell($row, $columnMap, 'year') ? (int) $this->getCell($row, $columnMap, 'year') : null;
                $size = $this->getCell($row, $columnMap, 'size') ?: null;
                $description = $this->getCell($row, $columnMap, 'description') ?: '';

                if (! $dryRun) {
                    Book::create([
                        'title' => trim($title),
                        'author_ids' => $authorIds,
                        'category_id' => (string) $category->getKey(),
                        'warehouse_id' => (string) $warehouse->getKey(),
                        'isbn' => $isbn,
                        'price' => $price,
                        'stock_quantity' => $stock,
                        'description' => $description,
                        'publisher_id' => $publisherId,
                        'pages' => $pages,
                        'publish_year' => $year,
                        'size' => $size,
                        'condition' => $condition->value,
                        'is_visible' => $isVisible,
                        'is_sold' => false,
                        'cover_image' => null,
                        'cover_image_thumb' => null,
                    ]);
                }

                $created++;
            } catch (\Throwable $e) {
                $messages[] = "Row {$rowNum}: error - {$e->getMessage()}";
                $errors++;
            }
        }

        if (! $dryRun && $created > 0) {
            Cache::increment('bookstore_catalog_version');
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'messages' => array_slice($messages, 0, 50),
            'skip_cover' => $skipCover,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function detectColumnMap(array $headers): array
    {
        $aliases = [
            'title' => ['title', 'اسم الكتاب', 'عنوان الكتاب', 'عنوان', 'العنوان', 'book', 'name', 'الكتاب'],
            'author' => ['author', 'المؤلف', 'authors', 'مؤلف', 'writer', 'كاتب'],
            'category' => ['category', 'التصنيف', 'subject', 'تصنيف', 'قسم', 'الفئة'],
            'isbn' => ['isbn', 'رقم isbn', 'رقم الكتاب'],
            'price' => ['price', 'السعر', 'cost', 'الثمن'],
            'stock' => ['stock', 'quantity', 'الكمية', 'المخزون', 'عدد النسخ'],
            'description' => ['description', 'الوصف', 'desc', 'وصف'],
            'publisher' => ['publisher', 'الناشر', 'دار النشر'],
            'pages' => ['pages', 'الصفحات', 'عدد الصفحات'],
            'year' => ['year', 'سنة', 'publish_year', 'سنة النشر'],
            'size' => ['size', 'الحجم', 'مقاس'],
            'condition' => ['condition', 'الحالة', 'status', 'حالة', 'new/used', 'جديد', 'مستعمل'],
            'visibility' => ['visibility', 'visible', 'hidden', 'الظهور', 'مخفي', 'ظاهر', 'is_visible'],
        ];

        $columnMap = [];
        foreach ($aliases as $key => $names) {
            foreach ($headers as $idx => $h) {
                $h = trim((string) $h);
                if ($h === '') {
                    continue;
                }
                $hLower = mb_strtolower($h);
                foreach ($names as $alias) {
                    if ($hLower === mb_strtolower($alias) || str_contains($hLower, mb_strtolower($alias))) {
                        $columnMap[$key] = $idx;
                        break 2;
                    }
                }
            }
        }

        if (! isset($columnMap['title']) && $headers !== []) {
            $columnMap['title'] = 0;
        }

        return $columnMap;
    }

    private function getCell(array $row, array $columnMap, string $key): ?string
    {
        $idx = $columnMap[$key] ?? null;
        if ($idx === null) {
            return null;
        }
        $val = $row[$idx] ?? null;
        if ($val === null || $val === '') {
            return null;
        }
        $str = trim((string) $val);

        return $str !== '' ? $str : null;
    }

    private function getCellAsInt(array $row, array $columnMap, string $key): ?int
    {
        $val = $this->getCell($row, $columnMap, $key);
        if ($val === null || $val === '') {
            return null;
        }
        if (is_numeric($val)) {
            return (int) (float) $val;
        }
        $cleaned = preg_replace('/[^0-9]/', '', $val);

        return $cleaned !== '' ? (int) $cleaned : null;
    }

    private function normalizeIsbn(?string $isbn): ?string
    {
        if (empty($isbn)) {
            return null;
        }
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);

        return $isbn !== '' ? $isbn : null;
    }

    /**
     * @return array<int, string>
     */
    private function splitByComma(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;|،]/', $value) ?: [])));
    }
}
