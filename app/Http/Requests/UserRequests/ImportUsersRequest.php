<?php

namespace App\Http\Requests\UserRequests;

use App\Enums\Roles;
use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ImportUsersRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:xls,xlsx,csv,ods',
                'max:20480',
            ],
            'rol' => [
                'required',
                new Enum(Roles::class),
                Rule::in(Roles::values()),
            ],
            'examen' => ['nullable', 'integer', 'exists:examenes,id'],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'rol' => $this->query('rol'),
            'examen' => $this->query('examen'),
        ]);
    }
}
