<?php

namespace App\Http\Traits;

use App\Support\MessageLocalizer;
use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Success response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => MessageLocalizer::localize($message),
            'data' => $data ?? (object) [],
        ], $code);
    }

    /**
     * Error response.
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => MessageLocalizer::localize($message),
            'data' => $data ?? (object) [],
        ], $code);
    }
}
