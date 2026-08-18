<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'valor' => $this->valor,
            'descripcion' => $this->descripcion,
            'examen_id' => $this->examen_id,
            'caso_id' => $this->caso_id,
            'pregunta_id' => $this->pregunta_id,
            'seccion_id' => $this->seccion_id,
            'tabla_cotejo_id' => $this->tabla_cotejo_id,
        ];
    }
}
