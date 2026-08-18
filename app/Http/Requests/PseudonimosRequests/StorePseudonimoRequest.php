<?php

namespace App\Http\Requests\PseudonimosRequests;

use App\Http\Requests\ApiBaseRequest;

class StorePseudonimoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
        ];
    }
}
