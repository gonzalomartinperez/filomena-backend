<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha' => $this->fecha,
            'codigo' => $this->codigo,
            'publicado' => $this->publicado,
            'finalizado' => $this->finalizado,
            'defendido' => $this->defendido,
            'descargable' => $this->descargable,
            'retrocedible' => $this->retrocedible,
        ];
    }
}
