<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\BookCoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyzeCoverController extends BaseApiController
{
    public function __construct(
        protected BookCoverService $bookCoverService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        try {
            $result = $this->bookCoverService->storeAndAnalyze(
                $request->file('cover_image'),
                true
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Failed to analyze cover image.',
                500
            );
        }

        return $this->successResponse($result, 'Cover analyzed');
    }
}
