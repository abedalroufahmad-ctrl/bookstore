<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadAuthorPhotoController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $file = $request->file('photo');
        $baseName = Str::uuid().'_'.time();
        $mime = (string) $file->getMimeType();
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
        $path = $file->storeAs(
            'authors',
            $baseName.'.'.$ext,
            'public'
        );

        $base = rtrim(config('app.url'), '/');
        $url = $base.'/storage/'.$path;

        return $this->successResponse(['photo' => $url]);
    }
}
