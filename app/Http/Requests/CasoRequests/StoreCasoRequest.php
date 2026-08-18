<?php

namespace App\Http\Requests\CasoRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;

class StoreCasoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'titulo' => [
                'required',
                'string',
                Rule::unique('casos', 'titulo')
                    ->where('examen_id', $this->query('examen')),
            ],
            'orden_relativo' => [
                'required',
                'integer',
                Rule::unique('casos', 'orden_relativo')
                    ->where('examen_id', $this->query('examen')),
            ],
            'evaluador_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

        ];
    }
}
