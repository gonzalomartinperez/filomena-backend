<?php

namespace App\Http\Requests\PreguntaRequests;

use App\Http\Requests\ApiBaseRequest;
use App\Models\Pregunta;
use Illuminate\Validation\Rule;

class UpdatePreguntaRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $examenId = (int) Pregunta::query()->whereKey($id)->value('examen_id');

        return [
            'consigna' => ['nullable', 'string'],
            'descripcion' => ['required', 'string'],
            'caso_id' => ['required', 'exists:casos,id',
                Rule::exists('casos', 'id')
                    ->where('examen_id', $examenId)],
            'orden_relativo' => [
                'required',
                'integer',
                Rule::unique('preguntas', 'orden_relativo')
                    ->ignore($id)
                    ->where(function ($query) {
                        $query->where('caso_id', request('caso_id'));
                    }),
            ],
        ];
    }
}
