<?php

namespace App\Http\Requests\CasoRequests;

use App\Http\Requests\ApiBaseRequest;

class ImportCasosRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:xls,xlsx,csv,ods', 'max:20480'],
            'examen' => ['required', 'integer', 'exists:examenes,id'],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'examen' => $this->query('examen'),
        ]);
    }
}
