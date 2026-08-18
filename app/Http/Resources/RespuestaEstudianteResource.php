<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RespuestaEstudianteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'estudiante_id' => $this->estudiante_id,
            'pregunta_id' => $this->pregunta_id,
            'respuesta' => $this->respuesta,
        ];
    }
}
