<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportBooksFromOds extends Command
{
    protected $signature = 'books:import-ods
                            {file : Path to the ODS file}
                            {--warehouse= : Warehouse ID (uses first warehouse if not set)}
                            {--skip-cover : Skip downloading cover images from book links}
                            {--dry-run : Show what would be imported without making changes}
                            {--clear : Delete all books, authors, and categories before importing}
                            {--title-col= : Column index for title (0-based)}
                            {--author-col= : Column index for author}
                            {--category-col= : Column index for category}
                            {--link-col= : Column index for book link/URL}
                            {--isbn-col= : Column index for ISBN}
                            {--price-col= : Column index for price}
                            {--stock-col= : Column index for stock}
                            {--size-col= : Column index for size}
                            {--weight-col= : Column index for weight}
                            {--edition-col= : Column index for edition (format: year\\edition_number, e.g. 2024\\3)}
                            {--pages-col= : Column index for pages}
                            {--default-category=General : Default category when not found}
                            {--cover-verbose : Show cover fetch attempts and failures}';

    protected $description = 'Import books from an ODS spreadsheet. Expects columns: title, author, category, link (book URL), isbn, price, etc.';

    private array $columnMap = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (! is_file($filePath)) {
            $this->error("File not found: {$filePath}");

            return 1;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows)) {
            $this->error('The spreadsheet is empty.');

            return 1;
        }

        // First row = headers
        $headers = array_map('trim', array_map('strval', $rows[0]));
        $this->detectColumnMap($headers);
        $this->applyColumnOverrides();
        $this->info('Column mapping: ' . json_encode($this->columnMap, JSON_PRETTY_PRINT));

        $warehouse = $this->getWarehouse();
        if (! $warehouse) {
            $this->error('No warehouse found. Create one first via Admin → Warehouses.');

            return 1;
        }

        $dryRun = $this->option('dry-run');
        $skipCover = $this->option('skip-cover');
        $clear = $this->option('clear');

        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be made.');
        }

        if ($clear && ! $dryRun) {
            $this->info('Clearing existing books, authors, and categories...');
            Book::query()->delete();
            Author::query()->delete();
            Category::query()->delete();
            $this->info('Cleared.');
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            $title = $this->getCell($row, 'title');
            $authorName = $this->getCell($row, 'author');
            $categoryName = $this->getCell($row, 'category');
            $link = $this->getCell($row, 'link');
            $isbn = $this->normalizeIsbn($this->getCell($row, 'isbn'));

            if (empty($title) && empty($isbn)) {
                continue;
            }

            if (empty($title)) {
                $this->warn("Row {$rowNum}: Skipping - no title");
                $skipped++;
                continue;
            }

            if (empty($isbn)) {
                $isbn = 'IMPORT-' . Str::uuid();
            }

            if (! $clear && Book::where('isbn', $isbn)->exists()) {
                $this->warn("Row {$rowNum}: Skipping - ISBN already exists: {$isbn}");
                $skipped++;
                continue;
            }

            $authorNames = $this->splitByComma($authorName);
            if (empty($authorNames)) {
                $this->warn("Row {$rowNum}: No author - using 'Unknown'");
                $authorNames = ['Unknown'];
            }

            $defaultCategory = $this->option('default-category') ?: 'General';
            $categoryNames = $this->splitByComma($categoryName);
            if (empty($categoryNames)) {
                $this->warn("Row {$rowNum}: No category - using '{$defaultCategory}'");
                $categoryNames = [$defaultCategory];
            }

            try {
                $authorIds = [];
                foreach ($authorNames as $name) {
                    $author = Author::firstOrCreate(['name' => $name]);
                    $authorIds[] = (string) $author->getKey();
                }

                $category = Category::firstOrCreate(
                    ['subject_title' => $categoryNames[0]],
                    ['dewey_code' => substr(md5($categoryNames[0]), 0, 3), 'subject_title' => $categoryNames[0]]
                );
                foreach (array_slice($categoryNames, 1) as $catName) {
                    Category::firstOrCreate(
                        ['subject_title' => $catName],
                        ['dewey_code' => substr(md5($catName), 0, 3), 'subject_title' => $catName]
                    );
                }

                $price = (float) ($this->getCell($row, 'price') ?: 0);
                $stock = (int) ($this->getCell($row, 'stock') ?: 10);
                $description = $this->getCell($row, 'description') ?: '';
                $publisher = $this->getCell($row, 'publisher') ?: null;
                $pages = $this->getCellAsInt($row, 'pages');
                $year = $this->getCell($row, 'year') ? (int) $this->getCell($row, 'year') : null;
                $size = $this->getCell($row, 'size') ?: null;
                $weightRaw = $this->getCell($row, 'weight');
                $weight = $weightRaw !== null && $weightRaw !== '' ? (float) preg_replace('/[^0-9.]/', '', $weightRaw) : null;
                [$editionYear, $editionNumber] = $this->parseEdition($this->getCell($row, 'edition'));
                if ($editionYear !== null && $year === null) {
                    $year = $editionYear;
                }

                $coverImage = null;
                $coverThumb = null;

                if (! $skipCover && ! $dryRun && ! empty($link)) {
                    [$coverImage, $coverThumb] = $this->fetchCoverFromUrl($link, $isbn);
                }

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
                        'publisher' => $publisher,
                        'pages' => $pages,
                        'publish_year' => $year,
                        'size' => $size,
                        'weight' => $weight,
                        'edition_number' => $editionNumber,
                        'cover_image' => $coverImage,
                        'cover_image_thumb' => $coverThumb ?? $coverImage,
                    ]);
                }

                $this->info("Row {$rowNum}: Imported '{$title}'");
                $created++;
            } catch (\Throwable $e) {
                $this->error("Row {$rowNum}: Error - {$e->getMessage()}");
                $errors++;
            }
        }

        if (! $dryRun) {
            Cache::flush();
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, Skipped: {$skipped}, Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }

    private function detectColumnMap(array $headers): void
    {
        $aliases = [
            'title' => ['title', 'اسم الكتاب', 'عنوان الكتاب', 'عنوان', 'العنوان', 'book', 'name', 'الكتاب', 'اسم', 'كتاب', 'العنوان الرئيسي', 'اسم المنتج'],
            'author' => ['author', 'المؤلف', 'authors', 'مؤلف', 'المؤلفون', 'writer', 'كاتب'],
            'category' => ['category', 'التصنيف', 'subject', 'dewey', 'تصنيف', 'قسم', 'نوع', 'الموضوع', 'الفئة', 'صنف', 'التصنيف الموضوعي'],
            'link' => ['link', 'url', 'رابط', 'book link', 'booklink', 'الرابط', 'رابط الكتاب', 'الرابط المباشر', 'href'],
            'isbn' => ['isbn', 'رقم isbn', 'رقم الكتاب', 'الرقم', 'isbn number', 'الترقيم'],
            'price' => ['price', 'السعر', 'cost', 'الثمن', 'السعر الحالي', 'السعر الأصلي', 'قيمة'],
            'stock' => ['stock', 'quantity', 'الكمية', 'المخزون', 'عدد النسخ', 'متوفر'],
            'description' => ['description', 'الوصف', 'desc', 'وصف', 'نبذة', 'ملخص'],
            'publisher' => ['publisher', 'الناشر', 'دار النشر', 'الدار'],
            'pages' => ['pages', 'page count', 'pagecount', 'الصفحات', 'عدد الصفحات', 'صفحات', 'عدد صفحات', 'صفحة', 'no of pages'],
            'year' => ['year', 'سنة', 'publish_year', 'publish year', 'سنة النشر', 'العام'],
            'size' => ['size', 'الحجم', 'حجم', 'dimensions', 'مقاس'],
            'weight' => ['weight', 'الوزن', 'وزن', 'weight kg', 'كجم'],
            'edition' => ['edition', 'الطبعة', 'طبعة', 'edition number', 'رقم الطبعة'],
        ];

        foreach ($aliases as $key => $names) {
            foreach ($headers as $idx => $h) {
                $h = trim((string) $h);
                if ($h === '') {
                    continue;
                }
                $hLower = mb_strtolower($h);
                foreach ($names as $alias) {
                    if ($hLower === mb_strtolower($alias) || str_contains($hLower, mb_strtolower($alias))) {
                        $this->columnMap[$key] = $idx;
                        break 2;
                    }
                }
            }
        }
        // Fallback: if title not detected, use first column (index 0)
        if (! isset($this->columnMap['title']) && ! empty($headers)) {
            $this->columnMap['title'] = 0;
        }
    }

    private function applyColumnOverrides(): void
    {
        $overrides = ['title-col', 'author-col', 'category-col', 'link-col', 'isbn-col', 'price-col', 'stock-col', 'size-col', 'weight-col', 'edition-col', 'pages-col'];
        $map = ['title-col' => 'title', 'author-col' => 'author', 'category-col' => 'category', 'link-col' => 'link', 'isbn-col' => 'isbn', 'price-col' => 'price', 'stock-col' => 'stock', 'size-col' => 'size', 'weight-col' => 'weight', 'edition-col' => 'edition', 'pages-col' => 'pages'];
        foreach ($overrides as $opt) {
            $val = $this->option($opt);
            if ($val !== null && $val !== '' && is_numeric($val)) {
                $this->columnMap[$map[$opt]] = (int) $val;
            }
        }
    }

    private function getCell(array $row, string $key): ?string
    {
        $idx = $this->columnMap[$key] ?? null;
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

    /** Extract integer from cell (handles float from spreadsheet, locale commas, Arabic digits, etc.). */
    private function getCellAsInt(array $row, string $key): ?int
    {
        $val = $this->getCell($row, $key);
        if ($val === null || $val === '') {
            return null;
        }
        $val = $this->normalizeArabicDigits($val);
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

    /** Convert Arabic-Indic digits (٠١٢٣...) to Western digits. */
    private function normalizeArabicDigits(string $value): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $western, $value);
    }

    /** Split by comma and return trimmed non-empty parts. */
    private function splitByComma(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $value));

        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    /**
     * Parse edition field. Format: "year\edition_number" (e.g. "2024\3" = year 2024, edition 3).
     * Also supports plain "3" or "2024" as edition number or year.
     * Converts Arabic ordinal words to numbers: الأولى=1, الثانية=2, etc.
     * Returns [year, edition_number].
     */
    private function parseEdition(?string $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }
        $value = trim($value);
        $parts = preg_split('/\\\\/', $value, 2);
        $year = null;
        $editionNumber = null;
        if (count($parts) >= 1 && $parts[0] !== '') {
            $firstNum = $this->parseEditionPartToNumber($parts[0]);
            if ($firstNum !== null) {
                if ($firstNum >= 1000) {
                    $year = (int) substr((string) $firstNum, 0, 4);
                } else {
                    $editionNumber = $firstNum;
                }
            }
        }
        if (count($parts) >= 2 && $parts[1] !== '') {
            $secondNum = $this->parseEditionPartToNumber($parts[1]);
            if ($secondNum !== null) {
                $editionNumber = $secondNum;
            }
        }

        return [$year, $editionNumber];
    }

    /**
     * Parse a part (year or edition) to number. Handles Arabic ordinals: الأولى=1, الثانية=2, etc.
     */
    private function parseEditionPartToNumber(string $value): ?int
    {
        $value = trim($value);
        $value = $this->normalizeArabicDigits($value);
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits !== '') {
            return (int) $digits;
        }
        $valueNorm = preg_replace('/\s+/u', '', $value);
        $valueNorm = mb_strtolower($valueNorm, 'UTF-8');
        $arabicOrdinals = [
            1 => ['الأولى', 'الاولى', 'اﻷولى', 'اولى', 'أولى', 'الأول', 'الاول', 'أول', 'اول', 'واحدة', 'واحد'],
            2 => ['الثانية', 'الثانيه', 'ثانية', 'ثانيه', 'الثاني', 'ثاني', 'ثنتين', 'اثنتين'],
            3 => ['الثالثة', 'الثالثه', 'ثالثة', 'ثالثه', 'الثالث', 'ثالث'],
            4 => ['الرابعة', 'الرابعه', 'رابعة', 'رابعه', 'الرابع', 'رابع'],
            5 => ['الخامسة', 'الخامسه', 'خامسة', 'خامسه', 'الخامس', 'خامس'],
            6 => ['السادسة', 'السادسه', 'سادسة', 'سادسه', 'السادس', 'سادس'],
            7 => ['السابعة', 'السابعه', 'سابعة', 'سابعه', 'السابع', 'سابع'],
            8 => ['الثامنة', 'الثامنه', 'ثامنة', 'ثامنه', 'الثامن', 'ثامن'],
            9 => ['التاسعة', 'التاسعه', 'تاسعة', 'تاسعه', 'التاسع', 'تاسع'],
            10 => ['العاشرة', 'العاشره', 'عاشرة', 'عاشره', 'العاشر', 'عاشر'],
        ];
        foreach ($arabicOrdinals as $num => $words) {
            foreach ($words as $word) {
                $wordNorm = preg_replace('/\s+/u', '', mb_strtolower($word, 'UTF-8'));
                if ($valueNorm === $wordNorm || str_contains($valueNorm, $wordNorm)) {
                    return $num;
                }
            }
        }

        return null;
    }

    private function getWarehouse(): ?Warehouse
    {
        $id = $this->option('warehouse');
        if ($id) {
            return Warehouse::find($id);
        }

        return Warehouse::first();
    }

    private function fetchCoverFromUrl(string $link, string $isbn): array
    {
        $link = trim($link);
        if ($link === '') {
            return [null, null];
        }

        $existing = $this->getExistingCoverForIsbn($isbn);
        if ($existing !== null) {
            $this->verbose("Cover: using existing for ISBN {$isbn}");
            return [$existing, $existing];
        }

        $imgUrl = null;

        // Link is plain filename: use darfikr base directly
        if (preg_match('#^([a-zA-Z0-9_\-]+\.(?:jpg|jpeg|png|gif|webp))$#i', $link, $m)) {
            $imgUrl = self::DARFIKR_IMAGE_BASE . '/' . $m[1];
        } elseif (preg_match('#^([a-zA-Z0-9_\-]+)$#', $link, $m)) {
            $imgUrl = self::DARFIKR_IMAGE_BASE . '/' . $m[1] . '.jpg';
        }
        // Link is full image URL
        elseif (preg_match('#/large/public/([a-zA-Z0-9_\-\.]+\.(?:jpg|jpeg|png|gif|webp))#i', $link, $m)) {
            $imgUrl = self::DARFIKR_IMAGE_BASE . '/' . $m[1];
        }
        // Link is a page URL: fetch page and extract cover from source
        elseif (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            $imgUrl = $this->extractCoverFromPageSource($link);
        }

        if (empty($imgUrl)) {
            $this->verbose("Cover: could not get image from: {$link}");
            return [null, null];
        }

        $this->verbose("Cover: trying {$imgUrl}");
        return $this->downloadAndStoreCover($imgUrl, $isbn);
    }

    private function extractCoverFromPageSource(string $pageUrl): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->get($pageUrl);
            if (! $response->successful()) {
                $this->verbose("Cover: HTTP {$response->status()} fetching page (no redirect to fikr.com)");
                return null;
            }
            $html = $response->body();

            // Dar Fikr only: .../large/public/xxx.jpg
            if (preg_match('#https?://darfikr\.com/[^"\'\s<>]+/large/public/([^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp))#i', $html, $m)) {
                return preg_replace('/\?.*/', '', $m[0]);
            }
            if (preg_match('#["\'](/W684O023R140L985D/public/files/styles/large/public/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp))["\']#i', $html, $m)) {
                return 'https://darfikr.com' . preg_replace('/\?.*/', '', $m[1]);
            }
            // og:image only if darfikr.com
            if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $m)) {
                if (str_contains($m[1], 'darfikr.com')) {
                    return $m[1];
                }
            }
            if (preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $m)) {
                if (str_contains($m[1], 'darfikr.com')) {
                    return $m[1];
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->verbose("Cover: error fetching page: {$e->getMessage()}");

            return null;
        }
    }

    private function verbose(string $message): void
    {
        if ($this->option('cover-verbose')) {
            $this->line('  ' . $message);
        }
    }

    private const DARFIKR_IMAGE_BASE = 'https://darfikr.com/W684O023R140L985D/public/files/styles/large/public';

    /** Return existing cover URL for ISBN if one exists in storage, otherwise null. */
    private function getExistingCoverForIsbn(string $isbn): ?string
    {
        $safeIsbn = preg_replace('/[^a-zA-Z0-9-]/', '_', $isbn);
        $files = Storage::disk('public')->files('covers');
        foreach ($files as $path) {
            $basename = basename($path);
            if (str_starts_with($basename, $safeIsbn . '_')) {
                $base = rtrim(config('app.url'), '/');
                return $base . '/storage/' . $path;
            }
        }

        return null;
    }

    private function downloadAndStoreCover(string $imgUrl, string $isbn): array
    {
        try {
            $response = Http::timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->get($imgUrl);
            if (! $response->successful()) {
                $this->verbose("Cover: HTTP {$response->status()} for {$imgUrl}");
                return [null, null];
            }

            $contentType = $response->header('Content-Type') ?? '';
            if (! preg_match('#^image/(jpeg|jpg|png|gif|webp)#i', $contentType)) {
                $this->verbose("Cover: got {$contentType} (not image) - darfikr.com may redirect to fikr.com");
                return [null, null];
            }

            $content = $response->body();
            $ext = 'jpg';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            } elseif (str_contains($contentType, 'gif')) {
                $ext = 'gif';
            }

            $safeIsbn = preg_replace('/[^a-zA-Z0-9-]/', '_', $isbn);
            $filename = $safeIsbn . '_' . time() . '_original.' . $ext;
            $path = 'covers/' . $filename;

            Storage::disk('public')->put($path, $content);

            $base = rtrim(config('app.url'), '/');
            $fullUrl = $base . '/storage/' . $path;

            return [$fullUrl, $fullUrl];
        } catch (\Throwable $e) {
            $this->warn("Could not download cover: {$e->getMessage()}");

            return [null, null];
        }
    }
}
