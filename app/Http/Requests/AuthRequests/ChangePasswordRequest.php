<?php

namespace App\Http\Requests\AuthRequests;

use App\Http\Requests\ApiBaseRequest;

class ChangePasswordRequest extends ApiBaseRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('password_confirmation') && ! $this->has('new_password_confirmation')) {
            $this->merge(['new_password_confirmation' => $this->input('password_confirmation')]);
        }
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed|different:password',
        ];
    }
}
