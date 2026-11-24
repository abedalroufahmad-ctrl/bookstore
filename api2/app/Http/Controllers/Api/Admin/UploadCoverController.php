<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\BookCoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadCoverController extends BaseApiController
{
    public function __construct(
        protected BookCoverService $bookCoverService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        $stored = $this->bookCoverService->storeCover($request->file('cover_image'));

        return $this->successResponse($stored);
    }
}
