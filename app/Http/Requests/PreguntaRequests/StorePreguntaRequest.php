<?php

namespace App\Http\Requests\PreguntaRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;

class StorePreguntaRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'consigna' => ['nullable', 'string'],
            'descripcion' => ['required', 'string'],
            'orden_relativo' => ['required', 'integer',
                Rule::unique('preguntas', 'orden_relativo')
                    ->where('caso_id', $this->input('caso_id'))],
            'caso_id' => ['required', 'exists:casos,id',
                Rule::exists('casos', 'id')
                    ->where('examen_id', $this->query('examen'))],
        ];
    }
}
