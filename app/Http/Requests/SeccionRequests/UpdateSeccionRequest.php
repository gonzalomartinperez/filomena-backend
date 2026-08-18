<?php

namespace App\Http\Requests\SeccionRequests;

use App\Http\Requests\ApiBaseRequest;
use App\Models\Seccion;
use Illuminate\Validation\Rule;

class UpdateSeccionRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        $id = (int) $this->route('id');
        $examenId = (int) Seccion::query()->whereKey($id)->value('examen_id');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('secciones', 'nombre')
                    ->ignore($id)
                    ->where('examen_id', $examenId),
            ],
            'orden_relativo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('secciones', 'orden_relativo')
                    ->ignore($id)
                    ->where('examen_id', $examenId),
            ],
        ];
    }
}
