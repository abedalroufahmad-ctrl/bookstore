<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt - return JSON for API.
     */
    protected function failedValidation(Validator $validator): void
    {
        $firstError = collect($validator->errors()->all())->first();

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $firstError ? \App\Support\MessageLocalizer::localize($firstError) : \App\Support\MessageLocalizer::localize('Validation failed.'),
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422)
        );
    }
}
