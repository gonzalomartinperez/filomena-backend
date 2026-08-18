<?php

namespace App\Http\Requests\CasoRequests;

use App\Http\Requests\ApiBaseRequest;
use App\Models\Caso;
use Illuminate\Validation\Rule;

class UpdateCasoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        $examenId = (int) Caso::query()->whereKey($id)->value('examen_id');

        return [
            'titulo' => [
                'required',
                'string',
                Rule::unique('casos', 'titulo')
                    ->ignore($id)
                    ->where('examen_id', $examenId),
            ],
            'orden_relativo' => [
                'required',
                'integer',
                Rule::unique('casos', 'orden_relativo')
                    ->ignore($id)
                    ->where('examen_id', $examenId),
            ],
            'evaluador_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

        ];
    }
}
