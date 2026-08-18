<?php

namespace App\Http\Requests\ExamenRequests;

use App\Http\Requests\ApiBaseRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateExamenRequest extends ApiBaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'fecha' => ['nullable', 'date'],
            'codigo' => ['required', 'string', 'max:255'],
            'publicado' => ['sometimes', 'boolean'],
            'finalizado' => ['sometimes', 'boolean'],
            'defendido' => ['sometimes', 'boolean'],
            'descargable' => ['sometimes', 'boolean'],
            'retrocedible' => ['sometimes', 'boolean'],
        ];
    }
}
