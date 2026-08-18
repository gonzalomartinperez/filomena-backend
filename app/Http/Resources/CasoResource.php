<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CasoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'orden_relativo' => $this->orden_relativo,
            'examen_id' => $this->examen_id,
            'evaluador_id' => $this->evaluador_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'evaluador' => $this->whenLoaded('evaluador', fn () => new UserResource($this->evaluador)),
            'preguntas' => $this->whenLoaded('preguntas', fn () => PreguntaResource::collection($this->preguntas)),
        ];
    }
}
