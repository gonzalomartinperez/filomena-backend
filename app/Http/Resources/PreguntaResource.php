<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreguntaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consigna' => $this->consigna,
            'descripcion' => $this->descripcion,
            'orden_relativo' => $this->orden_relativo,
            'caso_id' => $this->caso_id,
        ];
    }
}
