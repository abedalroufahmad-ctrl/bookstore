<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadCoverController extends BaseApiController
{
    private const THUMB_WIDTH = 340;
    private const THUMB_HEIGHT = 480;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        $file = $request->file('cover_image');
        $baseName = Str::uuid() . '_' . time();

        // Store original
        $originalPath = $file->storeAs(
            'covers',
            $baseName . '_original.' . $file->getClientOriginalExtension(),
            'public'
        );

        // Create and store thumbnail 340x480
        $thumbPath = $this->createThumbnail($file, $baseName);

        $base = rtrim(config('app.url'), '/');
        $originalUrl = $base . '/storage/' . $originalPath;
        $thumbUrl = $thumbPath ? $base . '/storage/' . $thumbPath : $originalUrl;

        return $this->successResponse([
            'cover_image' => $originalUrl,
            'cover_image_thumb' => $thumbUrl,
        ]);
    }

    private function createThumbnail($file, string $baseName): ?string
    {
        $path = $file->getRealPath();
        $mime = $file->getMimeType();

        $source = match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($path),
            str_contains($mime, 'png') => @imagecreatefrompng($path),
            str_contains($mime, 'gif') => @imagecreatefromgif($path),
            str_contains($mime, 'webp') => @imagecreatefromwebp($path),
            default => null,
        };

        if (!$source) {
            return null;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);
            return null;
        }

        $thumb = imagecreatetruecolor(self::THUMB_WIDTH, self::THUMB_HEIGHT);
        if (!$thumb) {
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

        $ext = $file->getClientOriginalExtension();
        $thumbFilename = $baseName . '_thumb.' . $ext;
        $storagePath = 'covers/' . $thumbFilename;

        $fullPath = Storage::disk('public')->path($storagePath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = match (strtolower($ext)) {
            'jpg', 'jpeg' => imagejpeg($thumb, $fullPath, 90),
            'png' => imagepng($thumb, $fullPath, 9),
            'gif' => imagegif($thumb, $fullPath),
            'webp' => imagewebp($thumb, $fullPath, 90),
            default => imagejpeg($thumb, $fullPath, 90),
        };
        imagedestroy($thumb);

        return $saved ? $storagePath : null;
    }
}
