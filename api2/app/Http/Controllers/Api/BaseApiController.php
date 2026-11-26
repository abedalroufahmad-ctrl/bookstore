<?php

namespace App\Http\Controllers\Api;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class BaseApiController extends BaseController
{
    use ApiResponseTrait;
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Business-rule failures are thrown as plain \InvalidArgumentException and are safe to show.
     * Library subclasses (MongoDB driver, Carbon, ...) carry internal details and are masked.
     */
    protected function domainErrorResponse(\InvalidArgumentException $e, int $status = 422): JsonResponse
    {
        if (get_class($e) === \InvalidArgumentException::class || config('app.debug')) {
            return $this->errorResponse($e->getMessage(), $status);
        }

        report($e);

        return $this->errorResponse('The request could not be processed.', $status);
    }
}
