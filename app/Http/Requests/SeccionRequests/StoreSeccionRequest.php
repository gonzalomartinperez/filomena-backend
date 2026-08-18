<?php

namespace App\Http\Requests\SeccionRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;

class StoreSeccionRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('secciones', 'nombre')
                    ->where('examen_id', $this->query('examen')),
            ],
            'orden_relativo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('secciones', 'orden_relativo')
                    ->where('examen_id', $this->query('examen')),
            ],
        ];
    }
}
