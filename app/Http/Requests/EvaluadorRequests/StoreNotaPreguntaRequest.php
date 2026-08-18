<?php

namespace App\Http\Requests\EvaluadorRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;

class StoreNotaPreguntaRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        $preguntaId = (int) $this->query('pregunta');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('pregunta_id', $preguntaId),
            ],
            'items.*.aprobado' => ['required', 'boolean'],
        ];
    }
}
