<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use Illuminate\Support\Facades\DB;

class EstadoExamenEvaluadorService
{
    /**
     * Recalcula y guarda el progreso de un evaluador dentro de un examen.
     */
    public static function upsert(Examen $examen, int $evaluadorId): void
    {
        $hasCasosAsignados = Caso::query()
            ->where('examen_id', $examen->id)
            ->whereHas('evaluador', fn ($q) => $q->where('users.id', $evaluadorId))
            ->exists();

        if (! $hasCasosAsignados) {
            EstadoExamenEvaluador::query()
                ->where('examen_id', $examen->id)
                ->where('evaluador_id', $evaluadorId)
                ->delete();
        } else {
            $preguntaIds = DB::table('preguntas')
                ->join('casos', 'casos.id', '=', 'preguntas.caso_id')
                ->where('casos.examen_id', $examen->id)
                ->whereNotNull('preguntas.consigna')
                ->whereRaw("TRIM(preguntas.consigna) <> ''")
                ->pluck('preguntas.id');

            $estudiantesIds = DB::table('examen_estudiante_pseudonimo')
                ->where('examen_id', $examen->id)
                ->pluck('estudiante_id')
                ->unique();

            $correccionesAsignadas = $preguntaIds->count() * $estudiantesIds->count();

            $subItemsPorPregunta = DB::table('items')
                ->select('pregunta_id', DB::raw('COUNT(*) as total_items'))
                ->where('examen_id', $examen->id)
                ->groupBy('pregunta_id');

            $subNotasPorEstudiantePregunta = DB::table('notas_items as ni')
                ->join('items as i', 'i.id', '=', 'ni.item_id')
                ->select('ni.estudiante_id', 'i.pregunta_id', DB::raw('COUNT(*) as notas'))
                ->where('i.examen_id', $examen->id)
                ->groupBy('ni.estudiante_id', 'i.pregunta_id');

            $correccionesRealizadas = DB::query()
                ->fromSub($subNotasPorEstudiantePregunta, 'nc')
                ->joinSub($subItemsPorPregunta, 'ic', 'ic.pregunta_id', '=', 'nc.pregunta_id')
                ->join('examen_estudiante_pseudonimo as eep', function ($join) use ($examen) {
                    $join->on('eep.estudiante_id', '=', 'nc.estudiante_id')
                        ->where('eep.examen_id', '=', $examen->id);
                })
                ->where('ic.total_items', '>', 0)
                ->whereColumn('nc.notas', '=', 'ic.total_items')
                ->count();

            EstadoExamenEvaluador::updateOrCreate(
                [
                    'evaluador_id' => $evaluadorId,
                    'examen_id' => $examen->id,
                ],
                [
                    'correcciones_asignadas' => $correccionesAsignadas,
                    'correcciones_realizadas' => $correccionesRealizadas,
                ]
            );
        }
    }
}
