<?php

declare(strict_types=1);

namespace App\Http\Requests\TablaCotejoRequests;

use App\Http\Requests\ApiBaseRequest;

class UpdateTablaCotejoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'caso_id' => ['required', 'integer', 'exists:casos,id'],
            'seccion_id' => ['required', 'integer', 'exists:secciones,id'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['sometimes', 'integer', 'exists:items,id'],
            'items.*.descripcion' => ['required:items.*.id', 'string', 'max:2000'],
            'items.*.valor' => ['required:items.*.id', 'numeric'],
            'items.*.pregunta_id' => ['required:items.*.id', 'integer', 'exists:preguntas,id'],
        ];
    }
}
