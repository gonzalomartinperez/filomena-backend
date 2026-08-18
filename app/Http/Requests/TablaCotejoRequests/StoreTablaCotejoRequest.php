<?php

declare(strict_types=1);

namespace App\Http\Requests\TablaCotejoRequests;

use App\Http\Requests\ApiBaseRequest;

class StoreTablaCotejoRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        return [
            'caso_id' => ['required', 'integer', 'exists:casos,id'],
            'seccion_id' => ['required', 'integer', 'exists:secciones,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required:items', 'string', 'max:2000'],
            'items.*.valor' => ['required:items', 'numeric'],
            'items.*.pregunta_id' => ['required:items', 'integer', 'exists:preguntas,id'],
            'items.*.id' => ['sometimes', 'integer', 'exists:items,id'],
        ];
    }
}
