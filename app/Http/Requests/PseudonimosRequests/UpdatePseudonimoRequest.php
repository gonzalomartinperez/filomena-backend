<?php

namespace App\Http\Requests\PseudonimosRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;

class UpdatePseudonimoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        $pseudonimoId = $this->route('pseudonimoId');

        return [
            'nombre' => [
                'required',
                'string',
                Rule::unique('pseudonimos', 'nombre')->ignore($pseudonimoId, 'id'), ],
        ];
    }
}
