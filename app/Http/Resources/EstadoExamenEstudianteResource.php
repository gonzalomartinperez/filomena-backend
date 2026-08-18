<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstadoExamenEstudianteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'examen_id' => (int) $this->examen_id,
            'estudiante_id' => (int) $this->estudiante_id,
            'inicio' => (bool) $this->inicio,
            'hora_inicio' => $this->hora_inicio,
            'finalizado' => (bool) $this->finalizado,
            'hora_finalizacion' => $this->hora_finalizacion,
            'posicion_finalizacion' => $this->posicion_finalizacion ? (int) $this->posicion_finalizacion : null,
            'alertado' => (bool) $this->alertado,
            'alertas_disponibles' => (bool) $this->alertas_disponibles,
            'defendido' => (bool) $this->defendido,
            'caso_id' => $this->caso_id ? (int) $this->caso_id : null,
            'pregunta_id' => $this->pregunta_id ? (int) $this->pregunta_id : null,
        ];
    }
}
