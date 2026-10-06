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

        // Compact working image for OCR.space (~1MB free-tier limit) while keeping text sharp.
        $ocrPath = $this->makeOcrWorkingImage($diskPath, 1800, 88) ?? $diskPath;

        try {
            $isbn = $this->readBarcodeIsbn($ocrPath) ?? $this->readBarcodeIsbn($diskPath);

            // Full-cover cloud OCR (best for Arabic calligraphy with Engine 2).
            $cloudOcr = $this->ocrViaOcrSpace($ocrPath);

            // Publisher / bottom band (white-on-dark names) — always useful.
            $bandOcr = $this->ocrCoverPublisherBand($ocrPath);

            // Extra title crop only when the full-page pass looks weak.
            $titleOcr = '';
            $cloudProbe = trim($cloudOcr."\n".$bandOcr);
            if ($cloudProbe === ''
                || $this->ocrUsefulnessScore($cloudProbe) < 8
                || ! $this->guessTitleFromOcr($cloudProbe)) {
                $titleCrop = $this->cropCoverRegion($ocrPath, 'north', '100%x55%+0+0');
                $titleOcr = $titleCrop ? $this->ocrViaOcrSpace($titleCrop) : '';
                if ($titleCrop && is_file($titleCrop)) {
                    @unlink($titleCrop);
                }
            }

            // Local Arabic Tesseract as a third signal (always; cloud demo keys are noisy).
            $prepPath = $this->preprocessForOcr($ocrPath) ?? $ocrPath;
            $localOcr = $this->runOcrLocal($prepPath);
            if ($prepPath !== $ocrPath && is_file($prepPath)) {
                @unlink($prepPath);
            }

            $ocrText = $this->mergeOcrTexts([$cloudOcr, $titleOcr, $bandOcr, $localOcr]);

            $isbn = $isbn
                ?? $this->extractIsbn($ocrText)
                ?? $this->ocrIsbnDigits($ocrPath)
                ?? $this->ocrIsbnDigits($diskPath);
        } finally {
            if ($ocrPath !== $diskPath && is_file($ocrPath)) {
                @unlink($ocrPath);
            }
        }

        $catalog = $isbn ? $this->lookupCatalog($isbn) : [];

        $parsed = $this->parseArabicCoverFields($ocrText);
        $ocrTitle = $parsed['title'] ?? null;
        if (! $this->isStrongTitleCandidate($ocrTitle)) {
            $ocrTitle = $this->guessTitleFromOcr($ocrText);
        }

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

    /**
     * Downscale for OCR/upload: keeps text readable but cuts transfer + decode time.
     */
    private function makeOcrWorkingImage(string $absolutePath, int $maxEdge = 1600, int $quality = 82): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $out = sys_get_temp_dir().'/bookstore_ocr_work_'.uniqid('', true).'.jpg';
        $which = new Process(['which', 'convert']);
        $which->run();
        if ($which->isSuccessful()) {
            $process = new Process([
                'convert',
                $absolutePath,
                '-auto-orient',
                '-resize', $maxEdge.'x'.$maxEdge.'>',
                '-quality', (string) $quality,
                $out,
            ]);
            $process->setTimeout(30);
            try {
                $process->run();
            } catch (\Throwable) {
                // fall through to GD
            }
            if ($process->isSuccessful() && is_file($out) && filesize($out) > 0) {
                return $out;
            }
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! is_array($info)) {
            return null;
        }
        $mime = (string) ($info['mime'] ?? '');
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
        $source = @$loader($absolutePath);
        if (! $source) {
            return null;
        }
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);

            return null;
        }
        $scale = min(1.0, $maxEdge / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $dst = imagecreatetruecolor($dstW, $dstH);
        if (! $dst) {
            imagedestroy($source);

            return null;
        }
        imagecopyresampled($dst, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);
        $ok = imagejpeg($dst, $out, max(40, min(95, $quality)));
        imagedestroy($dst);

        return ($ok && is_file($out)) ? $out : null;
    }

    /** Single-pass Tesseract fallback when cloud OCR is unavailable. */
    private function runOcrQuick(string $absolutePath): string
    {
        return $this->runOcrLocal($absolutePath);
    }

    /** Local ara+eng OCR — two PSMs, merged by usefulness. */
    private function runOcrLocal(string $absolutePath): string
    {
        if (! is_file($absolutePath) || ! $this->tesseractAvailable()) {
            return '';
        }

        $langs = $this->availableOcrLanguages();
        $best = '';
        foreach (['6', '4'] as $psm) {
            $text = $this->tesseract($absolutePath, $langs, $psm);
            if ($this->ocrUsefulnessScore($text) > $this->ocrUsefulnessScore($best)
                || (mb_strlen($text) > mb_strlen($best) && $this->arabicLetterCount($text) >= $this->arabicLetterCount($best))) {
                $best = $text;
            }
        }

        return trim($best);
    }

    private function preprocessForOcr(string $absolutePath): ?string
    {
        $which = new Process(['which', 'convert']);
        $which->run();
        if (! $which->isSuccessful()) {
            return null;
        }

        $out = sys_get_temp_dir().'/bookstore_ocr_prep_'.uniqid('', true).'.png';
        $process = new Process([
            'convert',
            $absolutePath,
            '-colorspace', 'Gray',
            '-contrast-stretch', '2%x2%',
            '-sharpen', '0x1',
            $out,
        ]);
        $process->setTimeout(30);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return ($process->isSuccessful() && is_file($out)) ? $out : null;
    }

    private function cropCoverRegion(string $absolutePath, string $gravity, string $crop): ?string
    {
        $which = new Process(['which', 'convert']);
        $which->run();
        if (! $which->isSuccessful() || ! is_file($absolutePath)) {
            return null;
        }

        $out = sys_get_temp_dir().'/bookstore_ocr_crop_'.uniqid('', true).'.jpg';
        $process = new Process([
            'convert',
            $absolutePath,
            '-gravity', ucfirst(strtolower($gravity)),
            '-crop', $crop,
            '+repage',
            '-quality', '88',
            $out,
        ]);
        $process->setTimeout(20);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return ($process->isSuccessful() && is_file($out) && filesize($out) > 0) ? $out : null;
    }

    /**
     * Merge OCR outputs: keep the best full block, then append unique useful lines.
     *
     * @param  list<string>  $chunks
     */
    private function mergeOcrTexts(array $chunks): string
    {
        $chunks = array_values(array_filter(array_map(
            static fn ($t) => trim((string) $t),
            $chunks
        ), static fn ($t) => $t !== ''));
        if ($chunks === []) {
            return '';
        }

        usort($chunks, function (string $a, string $b): int {
            $sa = $this->ocrUsefulnessScore($a);
            $sb = $this->ocrUsefulnessScore($b);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return $this->arabicLetterCount($b) <=> $this->arabicLetterCount($a);
        });

        $primary = $chunks[0];
        $seen = [];
        foreach (preg_split('/\R+/', $primary) ?: [] as $line) {
            $norm = mb_strtolower($this->stripArabicDiacritics(trim($line)));
            if ($norm !== '') {
                $seen[$norm] = true;
            }
        }

        $extra = [];
        foreach (array_slice($chunks, 1) as $chunk) {
            foreach (preg_split('/\R+/', $chunk) ?: [] as $line) {
                $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
                if ($line === '' || mb_strlen($line) < 3) {
                    continue;
                }
                if (! $this->isUsefulOcrLine($line)) {
                    continue;
                }
                $norm = mb_strtolower($this->stripArabicDiacritics($line));
                if (isset($seen[$norm])) {
                    continue;
                }
                // Skip fragments already contained in the primary block.
                if (str_contains(mb_strtolower($this->stripArabicDiacritics($primary)), $norm)) {
                    continue;
                }
                $seen[$norm] = true;
                $extra[] = $line;
            }
        }

        return trim($primary.($extra !== [] ? "\n".implode("\n", $extra) : ''));
    }

    private function isUsefulOcrLine(string $line): bool
    {
        if ($this->isCoverNoiseLine($line)) {
            return false;
        }
        if ($this->arabicLetterCount($line) >= 4) {
            return true;
        }
        if (preg_match('/ISBN|97[89]\d{10}|\b\d{9}[\dXx]\b/i', $line)) {
            return true;
        }

        return (bool) preg_match('/[A-Za-z]{4,}/', $line);
    }

    private function isCoverNoiseLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return true;
        }
        $norm = mb_strtolower($this->stripArabicDiacritics($line));
        if (preg_match('/^(الوزن|الوزن\s|السعر|الثمن|عدد\s*الصفحات|الطبعة|سلسلة|www\.|http)/u', $norm)) {
            return true;
        }
        if (preg_match('/^\d+([.,]\d+)?\s*(غ|جم|كغ|kg|g|\$|€|ل\.?ل)?$/iu', $norm)) {
            return true;
        }
        // Tiny OCR fragments / symbol soup.
        if (mb_strlen($line) <= 2) {
            return true;
        }

        return (bool) preg_match('/^[^\p{Arabic}A-Za-z0-9]+$/u', $line);
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
                if (preg_match('/ب'.preg_quote($token, '/').'/u', $hay)
                    && ! preg_match('/دار\s*(?:ال)?'.preg_quote($token, '/').'/u', $hay)) {
                    return;
                }
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

        $out = sys_get_temp_dir().'/bookstore_pub_band_'.uniqid('', true).'.jpg';
        $process = new Process([
            'convert',
            $absolutePath,
            '-gravity', 'South',
            '-crop', '100%x24%+0+0',
            '+repage',
            '-colorspace', 'Gray',
            '-negate',
            '-resize', '180%',
            '-normalize',
            '-quality', '85',
            $out,
        ]);
        $process->setTimeout(30);
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
        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return '';
        }
        $tempToDelete = null;
        // Free OCR.space keys reject ~1MB+ uploads; shrink further if needed.
        if (strlen($bytes) > 900000) {
            $shrunk = $this->makeOcrWorkingImage($absolutePath, 1200, 70);
            if ($shrunk && is_file($shrunk)) {
                $smaller = @file_get_contents($shrunk);
                if (is_string($smaller) && $smaller !== '' && strlen($smaller) < strlen($bytes)) {
                    $bytes = $smaller;
                    $absolutePath = $shrunk;
                    $tempToDelete = $shrunk;
                } else {
                    @unlink($shrunk);
                }
            }
        }
        try {
            $response = Http::timeout(25)
                ->withHeaders(['apikey' => $apiKey])
                ->attach('file', $bytes, basename($absolutePath))
                ->post('https://api.ocr.space/parse/image', [
                    'language' => 'auto',
                    'isOverlayRequired' => 'false',
                    'OCREngine' => '2',
                    'scale' => 'true',
                    'detectOrientation' => 'true',
                ]);
        } catch (\Throwable) {
            if ($tempToDelete) {
                @unlink($tempToDelete);
            }

            return '';
        }
        if ($tempToDelete) {
            @unlink($tempToDelete);
        }

        if (! $response->successful()) {
            return '';
        }

        if ($response->json('IsErroredOnProcessing')) {
            return '';
        }

        $parsed = $response->json('ParsedResults.0.ParsedText');
        if (! is_string($parsed) || trim($parsed) === '') {
            return '';
        }

        return trim(str_replace("\r", '', $parsed));
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
                'بقدم', 'الكتور', 'فصوصك', 'فصوصل', 'فصوك', 'سكبار', 'بفلم', 'يعتماز', 'اعتماز',
                'واررالقاع', 'وار القلم', 'وار القاح', 'وارالقاح', 'دارالقاع', 'دار القاع', 'دار القاح',
            ],
            [
                'بقلم', 'الدكتور', 'فصول', 'فصول', 'فصول', 'بكار', 'بقلم', 'بقلم', 'بقلم',
                'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم', 'دار القلم',
            ],
            $normalized
        );
        // Only inject دار القلم for clear OCR garble of that name (و↔د), not every "دار …".
        if (preg_match('/(?:^|\n|\s)(?:وار)\s*(?:ال)?ق[اأ]?[محع]/u', $normalized)
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
            $titleParts = [];
            foreach (array_slice($lines, 0, $authorIdx) as $line) {
                if (! $this->isCoverNoiseLine($line) && $this->arabicLetterCount($line) >= 3) {
                    $titleParts[] = $line;
                }
            }
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
            $titleParts = [];
            foreach ($lines as $line) {
                $pubs = $this->extractPublisherNamesFromLine($line);
                if ($pubs !== []) {
                    foreach ($pubs as $pub) {
                        $publishers[] = $pub;
                    }
                    continue;
                }
                if ($this->isCoverNoiseLine($line)) {
                    continue;
                }
                if ($this->arabicLetterCount($line) >= 4) {
                    $titleParts[] = $line;
                }
            }
            // Prefer the strongest single title line over concatenating noise.
            if (count($titleParts) > 1) {
                $bestLine = null;
                $bestScore = -1;
                foreach ($titleParts as $line) {
                    $score = $this->titleLineScore($line);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestLine = $line;
                    }
                }
                $titleParts = $bestLine !== null ? [$bestLine] : array_slice($titleParts, 0, 1);
            }
        }

        $title = trim(implode(' ', $titleParts));
        $title = $title !== '' ? mb_substr($title, 0, 200) : null;
        if ($title !== null && ! $this->isStrongTitleCandidate($title)) {
            $guessed = $this->guessTitleFromOcr($normalized);
            if ($guessed) {
                $title = $guessed;
            }
        }

        // Scan all lines for any publisher names we missed.
        foreach ($lines as $line) {
            foreach ($this->extractPublisherNamesFromLine($line) as $pub) {
                $publishers[] = $pub;
            }
        }

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
                $token = $m[1];
                // Avoid بقلم matching publisher token "قلم".
                if (preg_match('/ب'.preg_quote($token, '/').'/u', $hay)
                    && ! preg_match('/دار\s*(?:ال)?'.preg_quote($token, '/').'/u', $hay)) {
                    return;
                }
                if (str_contains($hay, $token) && mb_strlen($token) >= 4) {
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
        // "عمروبن" → "عمرو بن"
        $name = preg_replace('/(\p{Arabic}{2,})بن(?=\s|$)/u', '$1 بن', $name) ?? $name;
        $name = preg_replace('/\bبن(\p{Arabic}{2,})/u', 'بن $1', $name) ?? $name;
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
            if ($this->isCoverNoiseLine($line) || ! $this->isPlausibleTitle($line)) {
                continue;
            }
            if (preg_match('/isbn|price|\$|€|£|www\.|http|دار النشر|الطبعة|بقلم|تأليف|تاليف|^دار\b/iu', $line)) {
                continue;
            }
            $score = $this->titleLineScore($line);
            $candidates[] = ['line' => $line, 'score' => $score];
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return mb_substr($candidates[0]['line'], 0, 200);
    }

    private function titleLineScore(string $line): int
    {
        $line = trim($line);
        $norm = mb_strtolower($this->stripArabicDiacritics($line));
        $score = 0;
        $arabic = $this->arabicLetterCount($line);
        $len = mb_strlen($line);
        $words = preg_split('/\s+/u', $line) ?: [];
        $wordCount = count(array_values(array_filter($words, static fn ($w) => $w !== '')));

        $score += min(24, $arabic);

        // Short punchy titles ("البخلاء") beat long attribution lines.
        if ($wordCount === 1 && $arabic >= 5 && $arabic <= 16) {
            $score += 28;
        } elseif ($wordCount >= 2 && $wordCount <= 4 && $len <= 36) {
            $score += 12;
        } elseif ($wordCount >= 5 || $len > 45) {
            $score -= 18;
        }

        if ($len >= 4 && $len <= 24) {
            $score += 10;
        }

        if ($this->isCoverNoiseLine($line)) {
            $score -= 50;
        }
        if (preg_match('/^دار\b/u', $norm)) {
            $score -= 45;
        }
        if (preg_match('/\b(?:بن|ابن)\b/u', $norm)) {
            $score -= 28;
        }
        if (preg_match('/^(بقلم|تأليف|تاليف|إعداد|اعداد|يعتماز|اعتماز)/u', $norm)) {
            $score -= 35;
        }

        preg_match_all('/[A-Za-z]/', $line, $lat);
        $latin = count($lat[0] ?? []);
        if ($arabic > 0 && $latin > $arabic) {
            $score -= 20;
        }

        return $score;
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
