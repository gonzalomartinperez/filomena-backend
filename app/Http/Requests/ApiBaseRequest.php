<?php

namespace App\Http\Requests;

use App\Enums\HTTP;
use App\Http\Controllers\Api\ApiBaseController;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiBaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiBaseController::sendResponse(
                HTTP::BAD_REQUEST,
                $validator->errors()
            )
        );
    }
}
