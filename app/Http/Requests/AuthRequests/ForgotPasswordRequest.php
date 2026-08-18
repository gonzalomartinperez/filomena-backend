<?php

namespace App\Http\Requests\AuthRequests;

use App\Http\Requests\ApiBaseRequest;

class ForgotPasswordRequest extends ApiBaseRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => 'required|email|exists:users,email'];
    }
}
