<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Publisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BookCoverService
{
    private const THUMB_WIDTH = 340;

    private const THUMB_HEIGHT = 480;

    /**
     * Store original + thumbnail and optionally OCR + catalog lookup.
     *
     * @return array{
     *   cover_image: string,
     *   cover_image_thumb: string,
     *   ocr_text?: string|null,
     *   suggested?: array<string, mixed>
     * }
     */
    public function storeAndAnalyze(UploadedFile $file, bool $analyze = true): array
    {
        $baseName = Str::uuid().'_'.time();
        $ext = $this->safeImageExtension($file->getMimeType());

        $originalPath = $file->storeAs(
            'covers',
            $baseName.'_original.'.$ext,
            'public'
        );

        $diskPath = Storage::disk('public')->path($originalPath);
        $thumbPath = $this->createThumbnailFromPath($diskPath, (string) $file->getMimeType(), $baseName);
        $base = rtrim(config('app.url'), '/');
        $stored = [
            'cover_image' => $base.'/storage/'.$originalPath,
            'cover_image_thumb' => $thumbPath
                ? $base.'/storage/'.$thumbPath
                : $base.'/storage/'.$originalPath,
        ];

        if (! $analyze) {
            return $stored;
        }

        $ocrPath = $this->preprocessForOcr($diskPath) ?? $diskPath;
        $localOcr = $this->runOcr($ocrPath);
        if ($ocrPath !== $diskPath && is_file($ocrPath)) {
            @unlink($ocrPath);
        }

        // Cloud OCR (OCR.space) handles Arabic cover calligraphy far better than Tesseract.
        $cloudOcr = $this->ocrViaOcrSpace($diskPath);
        $bandOcr = $this->ocrCoverPublisherBand($diskPath);
        if ($bandOcr !== '') {
            $cloudOcr = trim($cloudOcr."\n".$bandOcr);
        }
        $ocrText = $this->pickBestOcrText($localOcr, $cloudOcr);

        $isbn = $this->extractIsbn($ocrText)
            ?? $this->ocrIsbnDigits($diskPath)
            ?? $this->readBarcodeIsbn($diskPath);
        $catalog = $isbn ? $this->lookupCatalog($isbn) : [];

        $parsed = $this->parseArabicCoverFields($ocrText);
        $ocrTitle = $parsed['title'] ?? $this->guessTitleFromOcr($ocrText);

        // Prefer catalog data from ISBN; otherwise use structured OCR parse.
        $suggestedTitle = $catalog['title']
            ?? ($this->isStrongTitleCandidate($ocrTitle) ? $ocrTitle : null);
        $suggestedAuthors = $catalog['authors']
            ?? ($parsed['authors'] !== [] ? $parsed['authors'] : $this->matchAuthorsFromOcr($ocrText));
        $suggestedAuthors = $this->dedupePersonNames(is_array($suggestedAuthors) ? $suggestedAuthors : []);
        $suggestedPublishers = $parsed['publishers'] !== []
            ? $parsed['publishers']
            : array_values(array_filter([
                $catalog['publisher'] ?? null,
                $this->matchPublisherFromOcr($ocrText),
            ]));
        $suggestedPublishers = $this->dedupePublisherNames($suggestedPublishers);
        $suggestedPublisher = $suggestedPublishers[0] ?? null;

        // If we still only have partial OCR fields, try catalog title search.
        if ($catalog === [] && $suggestedTitle) {
            $fromTitle = $this->lookupGoogleBooksByTitle($suggestedTitle);
            if ($fromTitle !== []) {
                $catalog = $fromTitle;
                $suggestedTitle = $catalog['title'] ?? $suggestedTitle;
                $suggestedAuthors = $this->dedupePersonNames($catalog['authors'] ?? $suggestedAuthors);
                if (! empty($catalog['publisher'])) {
                    array_unshift($suggestedPublishers, (string) $catalog['publisher']);
                    $suggestedPublishers = $this->dedupePublisherNames($suggestedPublishers);
                    $suggestedPublisher = $suggestedPublishers[0] ?? null;
                }
            }
        }

        $suggested = array_filter([
            'title' => $suggestedTitle,
            'isbn' => $isbn ?? ($catalog['isbn'] ?? null),
            'authors' => $suggestedAuthors,
            'publish_year' => $catalog['publish_year'] ?? $this->extractYear($ocrText),
            'pages' => $catalog['pages'] ?? null,
            'description' => $catalog['description'] ?? null,
            'publisher' => $suggestedPublisher,
            'publishers' => $suggestedPublishers,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        return array_merge($stored, [
            'ocr_text' => $ocrText !== '' ? mb_substr($ocrText, 0, 2000) : null,
            // Always an object in JSON so clients can treat it as a map.
            'suggested' => empty($suggested) ? new \stdClass() : $suggested,
        ]);
    }

    /**
     * @return array{cover_image: string, cover_image_thumb: string}
     */
    public function storeCover(UploadedFile $file): array
    {
        return $this->storeAndAnalyze($file, false);
    }

    private function tessdataDir(): string
    {
        $dir = storage_path('app/tessdata');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $this->ensureTessdataFiles($dir);

        return $dir;
    }

    private function ensureTessdataFiles(string $dir): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        foreach (['ara', 'eng'] as $lang) {
            $path = $dir.'/'.$lang.'.traineddata';
            if (is_file($path) && filesize($path) > 100000) {
                continue;
            }
            // Prefer system copy when present.
            $system = '/usr/share/tesseract-ocr/5/tessdata/'.$lang.'.traineddata';
            if (is_file($system)) {
                @copy($system, $path);
                continue;
            }
            try {
                Http::timeout(120)
                    ->sink($path)
                    ->get('https://github.com/tesseract-ocr/tessdata/raw/main/'.$lang.'.traineddata');
            } catch (\Throwable) {
                // OCR may still work with system tessdata.
            }
        }
    }

    private function preprocessForOcr(string $absolutePath): ?string
    {
        $which = new Process(['which', 'convert']);
        $which->run();
        if (! $which->isSuccessful()) {
            return null;
        }

        $out = sys_get_temp_dir().'/bookstore_ocr_'.uniqid('', true).'.png';
        $process = new Process([
            'convert',
            $absolutePath,
            '-colorspace', 'Gray',
            '-resize', '200%',
            '-contrast-stretch', '2%x2%',
            '-sharpen', '0x1',
            $out,
        ]);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return ($process->isSuccessful() && is_file($out)) ? $out : null;
    }

    private function runOcr(string $absolutePath): string
    {
        if (! is_file($absolutePath) || ! $this->tesseractAvailable()) {
            return '';
        }

        $langs = $this->availableOcrLanguages();
        $best = '';
        foreach ([6, 4, 3, 11] as $psm) {
            $text = $this->tesseract($absolutePath, $langs, (string) $psm);
            if (mb_strlen($text) > mb_strlen($best)) {
                $best = $text;
            }
        }

        return trim($best);
    }

    private function ocrIsbnDigits(string $absolutePath): ?string
    {
        if (! is_file($absolutePath) || ! $this->tesseractAvailable()) {
            return null;
        }

        $text = $this->tesseract(
            $absolutePath,
            'eng',
            '6',
            ['tessedit_char_whitelist' => '0123456789Xx- ISBN']
        );

        return $this->extractIsbn($text);
    }

    private function readBarcodeIsbn(string $absolutePath): ?string
    {
        $which = new Process(['which', 'zbarimg']);
        $which->run();
        if (! $which->isSuccessful()) {
            return null;
        }

        $process = new Process(['zbarimg', '-q', '--raw', $absolutePath]);
        $process->setTimeout(30);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }
        if (! $process->isSuccessful()) {
            return null;
        }

        $lines = preg_split('/\R+/', trim($process->getOutput())) ?: [];
        foreach ($lines as $line) {
            $digits = preg_replace('/[^0-9Xx]/', '', $line) ?? '';
            if (($digits !== '') && (strlen($digits) === 13 || strlen($digits) === 10) && $this->isPlausibleIsbn($digits)) {
                return strtoupper($digits);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $configs
     */
    private function tesseract(string $absolutePath, string $langs, string $psm, array $configs = []): string
    {
        $cmd = [
            'tesseract',
            $absolutePath,
            'stdout',
            '-l',
            $langs,
            '--psm',
            $psm,
        ];
        foreach ($configs as $key => $value) {
            $cmd[] = '-c';
            $cmd[] = $key.'='.$value;
        }

        $process = new Process($cmd);
        $env = [];
        $tessdata = $this->tessdataDir();
        if (is_dir($tessdata)) {
            $env['TESSDATA_PREFIX'] = $tessdata;
        }
        $process->setEnv($env);
        $process->setTimeout(90);

        try {
            $process->run();
        } catch (\Throwable) {
            return '';
        }

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    private function tesseractAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        $which = new Process(['which', 'tesseract']);
        $which->run();
        $available = $which->isSuccessful() && trim($which->getOutput()) !== '';

        return $available;
    }

    private function availableOcrLanguages(): string
    {
        $tessdata = $this->tessdataDir();
        $langs = [];

        if (is_file($tessdata.'/ara.traineddata')) {
            $langs[] = 'ara';
        }
        if (is_file($tessdata.'/eng.traineddata')) {
            $langs[] = 'eng';
        }

        if ($langs === []) {
            // Fall back to system tessdata.
            $process = new Process(['tesseract', '--list-langs']);
            $process->run();
            $out = $process->getOutput()."\n".$process->getErrorOutput();
            if (str_contains($out, 'ara')) {
                $langs[] = 'ara';
            }
            if (str_contains($out, 'eng')) {
                $langs[] = 'eng';
            }
        }

        return $langs === [] ? 'eng' : implode('+', $langs);
    }

    private function extractIsbn(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $normalized = str_replace(["\n", "\r"], ' ', $text);

        if (preg_match_all('/(?:ISBN(?:-1[03])?[:\s]*)?(97[89][-\s]?\d[-\s]?\d{1,7}[-\s]?\d{1,7}[-\s]?\d{1,7}[-\s]?\d)/iu', $normalized, $m)) {
            foreach ($m[1] as $raw) {
                $digits = preg_replace('/[^0-9Xx]/', '', $raw);
                if ($digits && (strlen($digits) === 13 || strlen($digits) === 10) && $this->isPlausibleIsbn($digits)) {
                    return strtoupper($digits);
                }
            }
        }

        if (preg_match_all('/\b(\d{9}[\dXx]|\d{13})\b/u', $normalized, $m2)) {
            foreach ($m2[1] as $raw) {
                $digits = preg_replace('/[^0-9Xx]/', '', $raw);
                if ($digits && (strlen($digits) === 13 || strlen($digits) === 10) && $this->isPlausibleIsbn($digits)) {
                    return strtoupper($digits);
                }
            }
        }

        return null;
    }

    private function isPlausibleIsbn(string $digits): bool
    {
        $digits = strtoupper($digits);
        if (strlen($digits) === 13) {
            return str_starts_with($digits, '978') || str_starts_with($digits, '979');
        }
        if (strlen($digits) === 10) {
            return (bool) preg_match('/^\d{9}[\dX]$/', $digits);
        }

        return false;
    }

    private function extractYear(string $text): ?int
    {
        if (preg_match('/\b(19[5-9]\d|20[0-2]\d)\b/', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function matchAuthorsFromOcr(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $hay = mb_strtolower($this->stripArabicDiacritics($text));
        $matched = [];
        Author::query()->get(['name'])->each(function (Author $author) use ($hay, &$matched) {
            $name = trim((string) ($author->name ?? ''));
            // Ignore short OCR fragment names like "الدكت".
            if (mb_strlen($name) < 6) {
                return;
            }
            $nameNorm = mb_strtolower($this->stripArabicDiacritics($name));
            if ($nameNorm !== '' && str_contains($hay, $nameNorm)) {
                $matched[] = $name;
            }
        });

        return $this->dedupePersonNames($matched);
    }

    private function matchPublisherFromOcr(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $hay = mb_strtolower($this->stripArabicDiacritics($text));
        $best = null;
        $bestLen = 0;
        Publisher::query()->get(['name'])->each(function (Publisher $publisher) use ($hay, &$best, &$bestLen) {
            $name = trim((string) ($publisher->name ?? ''));
            if (mb_strlen($name) < 2) {
                return;
            }
            $nameNorm = mb_strtolower($this->stripArabicDiacritics($name));
            if (str_contains($hay, $nameNorm) && mb_strlen($nameNorm) > $bestLen) {
                $best = $name;
                $bestLen = mb_strlen($nameNorm);

                return;
            }
            // Match significant token after "دار" (e.g. الشامية, القلم).
            if (preg_match('/دار\s*(?:ال)?(\p{Arabic}{3,})/u', $nameNorm, $m)) {
                $token = $m[1];
                if (str_contains($hay, $token) && mb_strlen($token) > $bestLen) {
                    $best = $name;
                    $bestLen = mb_strlen($token);
                }
            }
        });

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupCatalog(string $isbn): array
    {
        $fromGoogle = $this->lookupGoogleBooks($isbn);
        if ($fromGoogle !== []) {
            return $fromGoogle;
        }

        return $this->lookupOpenLibrary($isbn);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupGoogleBooks(string $isbn): array
    {
        try {
            $res = Http::timeout(10)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => 'isbn:'.$isbn,
                'maxResults' => 1,
            ]);
            if (! $res->successful()) {
                return [];
            }

            return $this->mapGoogleVolume($res->json('items.0.volumeInfo'), $isbn);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupGoogleBooksByTitle(string $title): array
    {
        try {
            $res = Http::timeout(10)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => 'intitle:'.$title,
                'maxResults' => 1,
                'langRestrict' => 'ar',
            ]);
            if (! $res->successful()) {
                // Retry without langRestrict for bilingual covers.
                $res = Http::timeout(10)->get('https://www.googleapis.com/books/v1/volumes', [
                    'q' => 'intitle:'.$title,
                    'maxResults' => 1,
                ]);
            }
            if (! $res->successful()) {
                return [];
            }

            return $this->mapGoogleVolume($res->json('items.0.volumeInfo'), null);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  mixed  $item
     * @return array<string, mixed>
     */
    private function mapGoogleVolume(mixed $item, ?string $fallbackIsbn): array
    {
        if (! is_array($item)) {
            return [];
        }
        $authors = [];
        foreach ($item['authors'] ?? [] as $a) {
            if (is_string($a) && trim($a) !== '') {
                $authors[] = trim($a);
            }
        }
        $year = null;
        if (! empty($item['publishedDate']) && preg_match('/(\d{4})/', (string) $item['publishedDate'], $ym)) {
            $year = (int) $ym[1];
        }
        $isbn = $fallbackIsbn;
        foreach ($item['industryIdentifiers'] ?? [] as $id) {
            if (($id['type'] ?? '') === 'ISBN_13' && ! empty($id['identifier'])) {
                $isbn = (string) $id['identifier'];
                break;
            }
            if (($id['type'] ?? '') === 'ISBN_10' && ! empty($id['identifier']) && ! $isbn) {
                $isbn = (string) $id['identifier'];
            }
        }

        return array_filter([
            'title' => isset($item['title']) ? trim((string) $item['title']) : null,
            'isbn' => $isbn,
            'authors' => $authors,
            'publish_year' => $year,
            'pages' => isset($item['pageCount']) ? (int) $item['pageCount'] : null,
            'description' => isset($item['description']) ? trim((string) $item['description']) : null,
            'publisher' => isset($item['publisher']) ? trim((string) $item['publisher']) : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupOpenLibrary(string $isbn): array
    {
        try {
            $key = 'ISBN:'.$isbn;
            $res = Http::timeout(10)->get('https://openlibrary.org/api/books', [
                'bibkeys' => $key,
                'format' => 'json',
                'jscmd' => 'data',
            ]);
            if (! $res->successful()) {
                return [];
            }
            $book = $res->json($key);
            if (! is_array($book)) {
                return [];
            }
            $authors = [];
            foreach ($book['authors'] ?? [] as $a) {
                if (is_array($a) && ! empty($a['name'])) {
                    $authors[] = trim((string) $a['name']);
                }
            }
            $year = null;
            if (! empty($book['publish_date']) && preg_match('/(\d{4})/', (string) $book['publish_date'], $ym)) {
                $year = (int) $ym[1];
            }
            $publishers = $book['publishers'][0]['name'] ?? null;

            return array_filter([
                'title' => isset($book['title']) ? trim((string) $book['title']) : null,
                'isbn' => $isbn,
                'authors' => $authors,
                'publish_year' => $year,
                'pages' => isset($book['number_of_pages']) ? (int) $book['number_of_pages'] : null,
                'description' => isset($book['notes']) ? trim((string) $book['notes']) : null,
                'publisher' => $publishers ? trim((string) $publishers) : null,
            ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function pickBestOcrText(string $local, string $cloud): string
    {
        if ($cloud === '') {
            return $local;
        }
        if ($local === '') {
            return $cloud;
        }

        // Prefer OCR.space for Arabic covers: Tesseract often has more characters but less usable text.
        $cloudScore = $this->ocrUsefulnessScore($cloud);
        $localScore = $this->ocrUsefulnessScore($local);
        if ($cloudScore !== $localScore) {
            return $cloudScore > $localScore ? $cloud : $local;
        }

        return $this->arabicLetterCount($cloud) >= $this->arabicLetterCount($local)
            ? $cloud
            : $local;
    }

    private function ocrUsefulnessScore(string $text): int
    {
        $score = 0;
        if (preg_match('/بقلم|تأليف|تاليف|بقدم/u', $text)) {
            $score += 5;
        }
        if (preg_match('/دار/u', $text)) {
            $score += 3;
        }
        if (preg_match('/ISBN|97[89]/i', $text)) {
            $score += 4;
        }
        // Penalize dense Latin junk mixed into Arabic OCR.
        preg_match_all('/[A-Za-z]/', $text, $lat);
        preg_match_all('/\p{Arabic}/u', $text, $ar);
        $latin = count($lat[0] ?? []);
        $arabic = count($ar[0] ?? []);
        if ($arabic > 0 && $latin > $arabic) {
            $score -= 3;
        }
        $score += min(10, intdiv($arabic, 20));

        return $score;
    }

    private function arabicLetterCount(string $text): int
    {
        preg_match_all('/\p{Arabic}/u', $text, $m);

        return count($m[0] ?? []);
    }

    /**
     * Extra OCR on the bottom band (publisher names are often white-on-dark there).
     */
    private function ocrCoverPublisherBand(string $absolutePath): string
    {
        $which = new Process(['which', 'convert']);
        $which->run();
        if (! $which->isSuccessful() || ! is_file($absolutePath)) {
            return '';
        }

        $out = sys_get_temp_dir().'/bookstore_pub_band_'.uniqid('', true).'.png';
        $process = new Process([
            'convert',
            $absolutePath,
            '-gravity', 'South',
            '-crop', '100%x22%+0+0',
            '+repage',
            '-colorspace', 'Gray',
            '-negate',
            '-resize', '300%',
            '-normalize',
            $out,
        ]);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (\Throwable) {
            return '';
        }
        if (! $process->isSuccessful() || ! is_file($out)) {
            return '';
        }

        $text = $this->ocrViaOcrSpace($out);
        @unlink($out);

        return $text;
    }

    /**
     * Keep longest names; drop fragments that are substrings of another name.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function dedupePersonNames(array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn ($n) => trim((string) $n),
            $names
        ), static fn ($n) => $n !== '')));
        usort($names, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $kept = [];
        foreach ($names as $name) {
            if (mb_strlen($name) < 4) {
                continue;
            }
            $lower = mb_strtolower($name);
            foreach ($kept as $longer) {
                if (str_contains(mb_strtolower($longer), $lower)) {
                    continue 2;
                }
            }
            $kept[] = $name;
        }

        return $kept;
    }

    /**
     * Merge دار الشامية / الدار الشامية duplicates; prefer the longer spelling.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function dedupePublisherNames(array $names): array
    {
        $best = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($this->stripArabicDiacritics($name));
            $key = preg_replace('/^الدار\s+/u', 'دار ', $key) ?? $key;
            $key = preg_replace('/^دار\s+ال/u', 'دار ', $key) ?? $key;
            $key = trim(preg_replace('/\s+/u', ' ', $key) ?? $key);
            if ($key === '') {
                continue;
            }
            if (! isset($best[$key]) || mb_strlen($name) > mb_strlen($best[$key])) {
                $best[$key] = $name;
            }
        }

        return array_values($best);
    }

    /**
     * OCR.space Engine 2 reads Arabic book covers much better than Tesseract calligraphy.
     */
    private function ocrViaOcrSpace(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        $apiKey = (string) (env('OCR_SPACE_API_KEY') ?: 'helloworld');
        try {
            $response = Http::timeout(45)
                ->withHeaders(['apikey' => $apiKey])
                ->attach('file', (string) file_get_contents($absolutePath), basename($absolutePath))
                ->post('https://api.ocr.space/parse/image', [
                    'language' => 'auto',
                    'isOverlayRequired' => 'false',
                    'OCREngine' => '2',
                    'scale' => 'true',
                    'detectOrientation' => 'true',
                ]);
        } catch (\Throwable) {
            return '';
        }

        if (! $response->successful()) {
            return '';
        }

        $parsed = $response->json('ParsedResults.0.ParsedText');
        if (! is_string($parsed) || trim($parsed) === '') {
            return '';
        }

        return trim($parsed);
    }

    /**
     * @return array{title: ?string, authors: list<string>, publisher: ?string, publishers: list<string>}
     */
    private function parseArabicCoverFields(string $text): array
    {
        $empty = ['title' => null, 'authors' => [], 'publisher' => null, 'publishers' => []];
        if ($text === '') {
            return $empty;
        }

        $normalized = $this->stripArabicDiacritics($text);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        // Common OCR misreads on Arabic covers.
        $normalized = str_replace(
            [
                'بقدم', 'الكتور', 'فصوصك', 'فصوصل', 'فصوك', 'سكبار', 'بفلم',
                'واررالقاع', 'وار القلم', 'وار القاح', 'وارالقاح', 'دارالقاع', 'دار القاع', 'دار القاح',
            ],
            [
                'بقلم', 'الدكتور', 'فصول', 'فصول', 'فصول', 'بكار', 'بقلم',
                'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم',
            ],
            $normalized
        );
        // Garbled white-on-dark OCR for دار القلم (و↔د, ح↔م).
        if (preg_match('/(?:^|\n|\s)(?:دار|وار)\s*(?:ال)?ق[اأ]?[محع]/u', $normalized)
            && ! preg_match('/دار\s*القلم/u', $normalized)) {
            $normalized .= "\nدار القلم";
        }

        $rawLines = preg_split('/\n+/', $normalized) ?: [];
        $lines = [];
        foreach ($rawLines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if ($lines === []) {
            return $empty;
        }

        $authorIdx = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^(بقلم|تأليف|تاليف|إعداد|اعداد)\b/u', $line)
                || preg_match('/^(بقلم|تأليف|تاليف)\s*\p{Arabic}/u', $line)) {
                $authorIdx = $i;
                break;
            }
        }

        $titleParts = [];
        $authors = [];
        $publishers = [];

        if ($authorIdx !== null) {
            $titleParts = array_slice($lines, 0, $authorIdx);
            $authorLine = $lines[$authorIdx];
            $authorLine = preg_replace('/^(بقلم|تأليف|تاليف|إعداد|اعداد)\s*/u', '', $authorLine) ?? '';
            $authorLine = trim($authorLine);
            if ($authorLine === '' && isset($lines[$authorIdx + 1])) {
                $authorLine = $lines[$authorIdx + 1];
            }
            foreach ($this->splitArabicPeople($authorLine) as $person) {
                $authors[] = $person;
            }

            $restStart = ($authorLine !== '' && isset($lines[$authorIdx + 1]) && $lines[$authorIdx + 1] === $authorLine)
                ? $authorIdx + 2
                : $authorIdx + 1;
            for ($i = $restStart; $i < count($lines); $i++) {
                foreach ($this->extractPublisherNamesFromLine($lines[$i]) as $pub) {
                    $publishers[] = $pub;
                }
            }
        } else {
            foreach ($lines as $line) {
                $pubs = $this->extractPublisherNamesFromLine($line);
                if ($pubs !== []) {
                    foreach ($pubs as $pub) {
                        $publishers[] = $pub;
                    }
                    continue;
                }
                if ($this->arabicLetterCount($line) >= 4) {
                    $titleParts[] = $line;
                }
            }
            $titleParts = array_slice($titleParts, 0, 3);
        }

        // Scan all lines for any publisher names we missed.
        foreach ($lines as $line) {
            foreach ($this->extractPublisherNamesFromLine($line) as $pub) {
                $publishers[] = $pub;
            }
        }

        $title = trim(implode(' ', $titleParts));
        $title = $title !== '' ? mb_substr($title, 0, 200) : null;

        foreach ($this->matchAllPublishersFromOcr($normalized) as $matched) {
            $publishers[] = $matched;
        }

        $publishers = array_values(array_unique(array_filter($publishers)));

        $matchedAuthors = $this->matchAuthorsFromOcr($normalized);
        foreach ($matchedAuthors as $name) {
            if (! in_array($name, $authors, true)) {
                $authors[] = $name;
            }
        }

        $authors = $this->dedupePersonNames($authors);
        $publishers = $this->dedupePublisherNames($publishers);

        return [
            'title' => $title,
            'authors' => $authors,
            'publisher' => $publishers[0] ?? null,
            'publishers' => $publishers,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitArabicPeople(string $line): array
    {
        $line = $this->cleanArabicPersonName($line);
        if ($line === '') {
            return [];
        }
        // Only split on "and" as a separate word — never on و inside الدكتور etc.
        $parts = preg_split('/\s*(?:,|،|&|\/|\s+و\s+)\s*/u', $line) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $person = $this->cleanArabicPersonName($part);
            // Skip tiny fragments from bad splits (e.g. single letters).
            if ($person !== '' && mb_strlen($person) >= 5 && $this->arabicLetterCount($person) >= 4) {
                $out[] = $person;
            }
        }

        // If splitting produced junk fragments, keep the full name.
        if (count($out) <= 1) {
            return $line !== '' ? [$line] : [];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractPublisherNamesFromLine(string $line): array
    {
        if (! preg_match('/دار/u', $line)) {
            return [];
        }
        $found = [];
        // Match each "دار …" phrase; allow short names like دار القلم.
        if (preg_match_all('/دار(?:\s+ال|\s*|ال)\p{Arabic}+(?:\s+\p{Arabic}+){0,4}/u', $line, $m)) {
            foreach ($m[0] as $chunk) {
                $name = $this->cleanPublisherName($chunk);
                if ($name !== '' && $this->arabicLetterCount($name) >= 4) {
                    $found[] = $name;
                }
            }
        }
        if ($found === []) {
            $name = $this->cleanPublisherName($line);
            if ($name !== '') {
                $found[] = $name;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    private function matchAllPublishersFromOcr(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $hay = mb_strtolower($this->stripArabicDiacritics($text));
        $matched = [];
        Publisher::query()->get(['name'])->each(function (Publisher $publisher) use ($hay, &$matched) {
            $name = trim((string) ($publisher->name ?? ''));
            if (mb_strlen($name) < 2) {
                return;
            }
            $nameNorm = mb_strtolower($this->stripArabicDiacritics($name));
            if (str_contains($hay, $nameNorm)) {
                $matched[] = $name;

                return;
            }
            if (preg_match('/دار\s*(?:ال)?(\p{Arabic}{3,})/u', $nameNorm, $m)) {
                if (str_contains($hay, $m[1])) {
                    $matched[] = $name;
                }
            }
        });

        return array_values(array_unique($matched));
    }

    private function stripArabicDiacritics(string $text): string
    {
        $out = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return is_string($out) ? $out : $text;
    }

    private function cleanArabicPersonName(string $name): string
    {
        $name = $this->stripArabicDiacritics($name);
        $name = preg_replace('/^(بقلم|تأليف|تاليف|إعداد|اعداد)\s*/u', '', $name) ?? $name;
        // "الدكتورعبد" → "الدكتور عبد"
        $name = preg_replace(
            '/(الدكتور|الشيخ|الأستاذ|الاستاذ|الأديبة|الكاتب)(?=\p{Arabic})/u',
            '$1 ',
            $name
        ) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return mb_substr($name, 0, 120);
    }

    private function cleanPublisherName(string $line): string
    {
        $line = $this->stripArabicDiacritics($line);
        // Drop trailing address fragments after common separators.
        $line = preg_replace('/\s*[-–—|]\s*.*$/u', '', $line) ?? $line;
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

        return mb_substr($line, 0, 120);
    }

    private function guessTitleFromOcr(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $lines = preg_split('/\R+/', $text) ?: [];
        $candidates = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if (! $this->isPlausibleTitle($line)) {
                continue;
            }
            if (preg_match('/isbn|price|\$|€|£|www\.|http|دار النشر|الطبعة/iu', $line)) {
                continue;
            }
            // Prefer lines with Arabic letters or longer Latin titles.
            $hasArabic = (bool) preg_match('/\p{Arabic}/u', $line);
            $score = mb_strlen($line) + ($hasArabic ? 20 : 0);
            $candidates[] = ['line' => $line, 'score' => $score];
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return mb_substr($candidates[0]['line'], 0, 200);
    }

    private function isPlausibleTitle(?string $line): bool
    {
        if ($line === null || $line === '') {
            return false;
        }
        $line = trim($line);
        $len = mb_strlen($line);
        if ($len < 4 || $len > 200) {
            return false;
        }
        if (preg_match('/^\d+([.,]\d+)?$/', $line)) {
            return false;
        }
        // Reject obvious OCR noise / symbols.
        if (preg_match('/[>|\\\\_{}\[\]~^`|]{2,}/u', $line)) {
            return false;
        }

        // Count letters (Arabic + Latin) vs other characters.
        preg_match_all('/[\p{Arabic}A-Za-z]/u', $line, $letters);
        $letterCount = count($letters[0] ?? []);
        if ($letterCount < 4) {
            return false;
        }
        // Reject OCR noise: too many non-letters relative to letters.
        if ($letterCount / max($len, 1) < 0.7) {
            return false;
        }
        // Reject lines that look like scattered Latin OCR junk mixed in Arabic covers.
        $latinJunk = preg_match_all('/\b[a-z]{1,3}\b/i', $line);
        if ($latinJunk >= 3 && preg_match('/\p{Arabic}/u', $line)) {
            return false;
        }

        return true;
    }

    /**
     * Stricter gate for auto-filling / catalog title search.
     */
    private function isStrongTitleCandidate(?string $line): bool
    {
        if (! $this->isPlausibleTitle($line)) {
            return false;
        }
        $line = trim((string) $line);
        if (mb_strlen($line) < 6) {
            return false;
        }

        preg_match_all('/\p{Arabic}/u', $line, $ar);
        preg_match_all('/[A-Za-z]/', $line, $lat);
        $arabic = count($ar[0] ?? []);
        $latin = count($lat[0] ?? []);
        $letters = max($arabic + $latin, 1);

        // Prefer a dominant script (Arabic covers or Latin covers), not mixed junk.
        if ($arabic / $letters < 0.75 && $latin / $letters < 0.85) {
            return false;
        }

        // Reject Latin OCR noise made of many tiny words ("ot eS ket wee").
        $words = preg_split('/\s+/u', $line) ?: [];
        $words = array_values(array_filter($words, static fn ($w) => $w !== ''));
        if ($latin / $letters >= 0.85 && count($words) >= 3) {
            $short = 0;
            foreach ($words as $w) {
                if (mb_strlen(preg_replace('/[^\p{Arabic}A-Za-z]/u', '', $w) ?? '') < 4) {
                    $short++;
                }
            }
            if ($short / count($words) >= 0.5) {
                return false;
            }
        }

        // Arabic titles often include short particles (في، من، على).
        if ($arabic / $letters >= 0.75 && $this->arabicLetterCount($line) >= 8) {
            return true;
        }

        return true;
    }

    private function createThumbnailFromPath(string $path, string $mime, string $baseName): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $loader = match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => 'imagecreatefromjpeg',
            str_contains($mime, 'png') => 'imagecreatefrompng',
            str_contains($mime, 'gif') => 'imagecreatefromgif',
            str_contains($mime, 'webp') => 'imagecreatefromwebp',
            default => null,
        };

        if ($loader === null || ! function_exists($loader)) {
            return null;
        }

        $source = @$loader($path);
        if (! $source) {
            return null;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);

            return null;
        }

        $thumb = imagecreatetruecolor(self::THUMB_WIDTH, self::THUMB_HEIGHT);
        if (! $thumb) {
            imagedestroy($source);

            return null;
        }

        imagecopyresampled(
            $thumb, $source,
            0, 0, 0, 0,
            self::THUMB_WIDTH, self::THUMB_HEIGHT,
            $srcW, $srcH
        );
        imagedestroy($source);

        $ext = $this->safeImageExtension($mime);
        $thumbFilename = $baseName.'_thumb.'.$ext;
        $storagePath = 'covers/'.$thumbFilename;
        $fullPath = Storage::disk('public')->path($storagePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = match (strtolower($ext)) {
            'png' => 'imagepng',
            'gif' => 'imagegif',
            'webp' => 'imagewebp',
            default => 'imagejpeg',
        };

        if (! function_exists($writer)) {
            imagedestroy($thumb);

            return null;
        }

        $saved = match ($writer) {
            'imagepng' => imagepng($thumb, $fullPath, 9),
            'imagegif' => imagegif($thumb, $fullPath),
            'imagewebp' => imagewebp($thumb, $fullPath, 90),
            default => imagejpeg($thumb, $fullPath, 90),
        };
        imagedestroy($thumb);

        return $saved ? $storagePath : null;
    }

    private function safeImageExtension(?string $mime): string
    {
        return match (true) {
            str_contains((string) $mime, 'png') => 'png',
            str_contains((string) $mime, 'gif') => 'gif',
            str_contains((string) $mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }
}
