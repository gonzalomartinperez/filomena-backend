<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstadoExamenEvaluadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id' => (int) ($this->id ?? data_get($this->resource, 'id', 0)),
            'examen_id' => (int) data_get($this->resource, 'examen_id', 0),
            'evaluador_id' => (int) data_get($this->resource, 'evaluador_id', 0),
            'finalizado' => (bool) data_get($this->resource, 'finalizado', false),
            'correcciones_asignadas' => (int) data_get($this->resource, 'correcciones_asignadas', 0),
            'correcciones_realizadas' => (int) data_get($this->resource, 'correcciones_realizadas', 0),
            'created_at' => optional(data_get($this->resource, 'created_at'))?->toISOString(),
            'updated_at' => optional(data_get($this->resource, 'updated_at'))?->toISOString(),
        ];

        $casos = collect(data_get($this->resource, 'casos', []))->map(function ($c) {
            return [
                'id' => isset($c['id']) ? (int) $c['id'] : null,
                'finalizado' => (bool) ($c['finalizado'] ?? false),
                'preguntas_totales' => isset($c['preguntas_totales']) ? (int) $c['preguntas_totales'] : 0,
                'preguntas_corregidas' => isset($c['preguntas_corregidas']) ? (int) $c['preguntas_corregidas'] : 0,
            ];
        })->values();

        $preguntas = collect(data_get($this->resource, 'preguntas', []))->map(function ($p) {
            return [
                'id' => isset($p['id']) ? (int) $p['id'] : null,
                'caso_id' => isset($p['caso_id']) ? (int) $p['caso_id'] : null,
                'finalizada' => (bool) ($p['finalizada'] ?? false),
                'items_totales' => isset($p['items_totales']) ? (int) $p['items_totales'] : 0,
                'items_corregidos' => isset($p['items_corregidos']) ? (int) $p['items_corregidos'] : 0,
            ];
        })->values();

        $estudiantes = collect(data_get($this->resource, 'estudiantes', []))->map(function ($a) {
            return [
                'id' => isset($a['id']) ? (int) $a['id'] : null,
                'pseudonimo' => $a['pseudonimo'] ?? null,
                'casos_finalizados' => array_map('intval', $a['casos_finalizados'] ?? []),
                'preguntas_finalizadas' => array_map('intval', $a['preguntas_finalizadas'] ?? []),
            ];
        })->values();

        return array_merge($base, [
            'casos' => $casos,
            'preguntas' => $preguntas,
            'estudiantes' => $estudiantes,
        ]);
    }
}
