<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Enums\Roles;
use App\Http\Resources\EstadoExamenEstudianteResource;
use App\Http\Resources\EstadoExamenEvaluadorResource;
use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Models\User;
use Illuminate\Http\Request;

class AdminManagementController extends ApiBaseController
{
    /**
     * Muestra el porcentaje de finalizacion de entrega de un examen, es decir, % de alumnos que finalizaron
     */
    public function showFinalizados($id)
    {
        $examen = Examen::with('estadosExamenesEstudiantes')->find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $total = $examen->estadosExamenesEstudiantes->count();
        if ($total === 0) {
            return $this->sendResponse(HTTP::OK, ['porcentaje' => 0]);
        }

        $finalizados = $examen->estadosExamenesEstudiantes->where('finalizado', true)->count();

        $porcentaje = round(($finalizados / $total) * 100, 2);

        return $this->sendResponse(HTTP::OK, ['porcentaje_finalizados' => $porcentaje], 'Porcentaje de finalización de estudiantes recuperado exitosamente');
    }

    /**
     * Muestra el porcentaje de finalizacion de correccion de un examen, es decir, % de evaluadores que finalizaron de evaluar
     */
    public function showEvaluados($id)
    {
        $examen = Examen::with('estadosExamenesEvaluadores')->find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $total = $examen->estadosExamenesEvaluadores->count();
        if ($total === 0) {
            return $this->sendResponse(HTTP::OK, ['porcentaje' => 0]);
        }

        $finalizados = $examen->estadosExamenesEvaluadores->where('finalizado', true)->count();

        $porcentaje = round(($finalizados / $total) * 100, 2);

        return $this->sendResponse(HTTP::OK, ['porcentaje_finalizados' => $porcentaje], 'Porcentaje de finalización de evaluadores recuperado exitosamente');
    }

    public function alumnosFinalizados($id)
    {
        $estados = EstadoExamenEstudiante::where('examen_id', $id)
            ->where('finalizado', true)
            ->where('posicion_finalizacion', '>', 0)
            ->get();

        if ($estados->isEmpty()) {
            return $this->sendResponse(HTTP::OK, []);
        }

        $estudiantesIds = $estados->pluck('estudiante_id')->toArray();

        $pseudonimos = ExamenEstudiantePseudonimo::with('pseudonimo')
            ->whereIn('estudiante_id', $estudiantesIds)
            ->where('examen_id', $id)
            ->get()
            ->keyBy('estudiante_id');

        $resultado = $estados->map(function ($estado) use ($pseudonimos) {
            $anon = $pseudonimos->get($estado->estudiante_id);

            return [
                'pseudonimo' => $anon?->pseudonimo?->nombre,
                'posicion_finalizacion' => $estado->posicion_finalizacion,
            ];
        })->filter(fn ($x) => $x['pseudonimo'] !== null)
            ->sortBy('posicion_finalizacion')
            ->values();

        return $this->sendResponse(HTTP::OK, $resultado, 'Estudiantes con examen finalizado recuperados exitosamente');
    }

    private function calcularPorcentajeRespondido($examenId, $estudiantes)
    {
        // Recupero las respuestas de todos los estudiantes de un examen
        $examen = Examen::find($examenId);

        $query = RespuestaEstudiante::query()
            ->join('preguntas', 'respuestas_estudiantes.pregunta_id', '=', 'preguntas.id')
            ->where('preguntas.examen_id', $examenId)
            ->select('respuestas_estudiantes.estudiante_id', 'respuestas_estudiantes.pregunta_id', 'respuestas_estudiantes.respuesta');

        if ($examen->retrocedible) {
            $query->whereNotNull('respuestas_estudiantes.respuesta');
        }

        $respuestas = $query->get();

        // Recupero la cantidad total de preguntas del examen
        $totalPreguntasExamen = Pregunta::query()
            ->where('examen_id', $examenId)
            ->whereNotNull('consigna')
            ->count();

        $arregloRespuestas = [];

        // Itero sobre cada respuesta para agruparlas por estudiante
        foreach ($respuestas as $respuesta) {
            $estudianteId = $respuesta->estudiante_id;
            $preguntaId = $respuesta->pregunta_id;
            $valorRespuesta = $respuesta->respuesta;

            // Agrupo las respuestas por estudiante
            $arregloRespuestas[$estudianteId][$preguntaId] = $valorRespuesta;
        }

        $arregloPorcentajes = [];

        // Calculo el porcentaje para cada estudiante
        foreach ($estudiantes as $estudiante) {
            $estudianteId = $estudiante->id;

            if (! array_key_exists($estudianteId, $arregloRespuestas)) {
                // El estudiante no respondió nada todavía, su porcentaje es 0
                $arregloPorcentajes[$estudianteId] = 0;
            } else {
                // Calculo cuántas preguntas respondió
                $respuestasRespondidas = count($arregloRespuestas[$estudianteId]);

                // Calculo el porcentaje
                $porcentajeRespondidas = 100 * ($respuestasRespondidas / $totalPreguntasExamen);

                if (($porcentajeRespondidas > 99) && ($porcentajeRespondidas < 100)) {
                    // Devuelvo 99, no quiero que sea 100 hasta que sea realmente 100
                    $porcentajeEntero = 99;
                } else {
                    $porcentajeEntero = round($porcentajeRespondidas);
                }

                $arregloPorcentajes[$estudianteId] = $porcentajeEntero;
            }
        }

        return $arregloPorcentajes;
    }

    public function monitorEstudiantes($examenId)
    {
        $examen = Examen::findOrFail($examenId);

        // 1) Traigo estudiantes con inicio=1 + pseudónimo.
        //    Preordeno en SQL: no finalizados primero; entre finalizados, por posición; empate por pseudónimo.
        $estudiantes = $examen->estadosExamenesEstudiantes()
            ->where('estados_examenes_estudiantes.inicio', 1)
            ->join('users', 'users.id', '=', 'estados_examenes_estudiantes.estudiante_id')
            ->join('examen_estudiante_pseudonimo as eep', function ($join) use ($examenId) {
                $join->on('eep.estudiante_id', '=', 'users.id')
                    ->where('eep.examen_id', '=', $examenId);
            })
            ->leftJoin('pseudonimos as p', 'p.id', '=', 'eep.pseudonimo_id')
            ->select(
                'users.*',
                'p.nombre as pseudonimo',
                // selecciono campos de estado para poder ordenar en SQL sin romper DISTINCT
                'estados_examenes_estudiantes.finalizado as _finalizado',
                'estados_examenes_estudiantes.posicion_finalizacion as _posicion_finalizacion'
            )
            ->orderByRaw('CASE WHEN estados_examenes_estudiantes.finalizado = 1 THEN 1 ELSE 0 END ASC') // no finalizados primero
            ->orderByRaw('CASE WHEN estados_examenes_estudiantes.finalizado = 1 THEN estados_examenes_estudiantes.posicion_finalizacion END ASC') // finalizados por posición
            ->orderBy('p.nombre') // empate por pseudónimo
            ->distinct()
            ->get();

        // 2) % de avance
        $porcentajes = $this->calcularPorcentajeRespondido($examenId, $estudiantes);

        // 3) Evito N+1: traigo de una todos los estados actuales con su pregunta/caso
        $estadosActuales = EstadoExamenEstudiante::query()
            ->join('preguntas', 'estados_examenes_estudiantes.pregunta_id', '=', 'preguntas.id')
            ->join('casos', 'preguntas.caso_id', '=', 'casos.id')
            ->whereIn('estados_examenes_estudiantes.estudiante_id', $estudiantes->pluck('id'))
            ->where('preguntas.examen_id', $examenId)
            ->select(
                'estados_examenes_estudiantes.estudiante_id',
                'preguntas.id as pregunta_id',
                'preguntas.orden_relativo as pregunta_orden',
                'preguntas.caso_id',
                'casos.titulo as caso_titulo',
                'casos.orden_relativo as caso_orden',
                'estados_examenes_estudiantes.finalizado',
                'estados_examenes_estudiantes.alertado',
                'estados_examenes_estudiantes.alertas_disponibles',
                'estados_examenes_estudiantes.posicion_finalizacion'
            )
            ->get()
            ->keyBy('estudiante_id');

        // 4) Evito N+1: traigo última pregunta por caso en una sola query
        $casoIds = $estadosActuales->pluck('caso_id')->filter()->unique()->values();
        $ultimasPorCaso = $casoIds->isEmpty()
            ? collect()
            : Pregunta::query()
                ->whereIn('caso_id', $casoIds)
                ->selectRaw('caso_id, MAX(orden_relativo) as max_orden')
                ->groupBy('caso_id')
                ->pluck('max_orden', 'caso_id');

        // 5) Armo el payload de progreso (misma forma que antes)
        $progresoEstudiantes = [];

        foreach ($estudiantes as $estudiante) {
            $estadoActualEstudiante = $estadosActuales->get($estudiante->id);
            // Clamp seguro [0,100]
            $pct = (int) round($porcentajes[$estudiante->id] ?? 0);
            $pct = max(0, min(100, $pct));

            if ($estadoActualEstudiante) {
                $ultimaOrdenCaso = $estadoActualEstudiante->caso_id
                    ? ($ultimasPorCaso[$estadoActualEstudiante->caso_id] ?? null)
                    : null;

                $progresoEstudiantes[] = [
                    'estudiante_id' => $estudiante->id,
                    'estudiante_pseudonimo' => $estudiante->pseudonimo,
                    'ultima_pregunta_respondida' => $estadoActualEstudiante->pregunta_orden,
                    'caso_titulo' => $estadoActualEstudiante->caso_titulo,
                    'caso_orden' => $estadoActualEstudiante->caso_orden,
                    'ultima_pregunta_caso' => $ultimaOrdenCaso,
                    'porcentaje' => $pct,
                    'alertado' => $estadoActualEstudiante->alertado,
                    'alertas_disponibles' => $estadoActualEstudiante->alertas_disponibles,
                    'finalizado' => $estadoActualEstudiante->finalizado,
                    'posicion_finalizacion' => $estadoActualEstudiante->posicion_finalizacion ?? null,
                ];
            } else {
                $progresoEstudiantes[] = [
                    'estudiante_id' => $estudiante->id,
                    'estudiante_pseudonimo' => $estudiante->pseudonimo,
                    'ultima_pregunta_respondida' => null,
                    'caso_titulo' => null,
                    'caso_orden' => null,
                    'ultima_pregunta_caso' => null,
                    'porcentaje' => 0,
                    'alertado' => null,
                    'alertas_disponibles' => 0,
                    'finalizado' => null,
                    'posicion_finalizacion' => null,
                ];
            }
        }

        // 6) Orden final EXACTO requerido:
        //    - NO finalizados primero por porcentaje ASC; empate por pseudónimo
        //    - Finalizados al final por posición ASC; empate por pseudónimo (no se puede dar pero para mayor robustez)
        $noFinal = array_values(array_filter($progresoEstudiantes, fn ($r) => empty($r['finalizado'])));
        $final = array_values(array_filter($progresoEstudiantes, fn ($r) => ! empty($r['finalizado'])));

        usort($noFinal, function ($a, $b) {
            $cmp = ($a['porcentaje'] <=> $b['porcentaje']); // ASC
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp($a['estudiante_pseudonimo'] ?? '', $b['estudiante_pseudonimo'] ?? '');
        });

        usort($final, function ($a, $b) {
            $posA = $a['posicion_finalizacion'] ?? PHP_INT_MAX;
            $posB = $b['posicion_finalizacion'] ?? PHP_INT_MAX;
            $cmp = $posA <=> $posB; // ASC
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp($a['estudiante_pseudonimo'] ?? '', $b['estudiante_pseudonimo'] ?? '');
        });

        $progresoEstudiantes = array_merge($noFinal, $final);

        return $this->sendResponse(HTTP::OK, ['progreso' => $progresoEstudiantes]);
    }

    public function alertarEstudiante($examenId, $estudianteId)
    {
        $estado_examen_estudiante = EstadoExamenEstudiante::where('examen_id', $examenId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        if (! $estado_examen_estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        if ($estado_examen_estudiante->alertas_disponibles > 0) {
            $estado_examen_estudiante->update(['alertas_disponibles' => $estado_examen_estudiante->alertas_disponibles - 1]);
        }

        $estado_examen_estudiante->update(['alertado' => 1]);

        $pseudonimo = ExamenEstudiantePseudonimo::with('pseudonimo')
            ->where('examen_id', $examenId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        return $this->sendResponse(HTTP::OK, [], "Estudiante {$pseudonimo->pseudonimo->nombre} alertado");
    }

    public function monitorEvaluadores($examenId)
    {
        $examen = Examen::findOrFail($examenId);

        // 1) Traigo estados + datos de usuario (una sola query)
        $evaluadores = $examen->estadosExamenesEvaluadores()
            ->join('users', 'users.id', '=', 'estados_examenes_evaluadores.evaluador_id')
            ->select(
                'users.*',
                'estados_examenes_evaluadores.finalizado',
                'estados_examenes_evaluadores.correcciones_asignadas',
                'estados_examenes_evaluadores.correcciones_realizadas'
            )
            ->orderByRaw('CASE WHEN estados_examenes_evaluadores.finalizado = 1 THEN 1 ELSE 0 END ASC') // no finalizados primero
            ->orderBy('users.apellido')
            ->orderBy('users.nombre')
            ->distinct()
            ->get();

        $progresoEvaluadores = [];

        foreach ($evaluadores as $evaluador) {
            // 2) Casos asignados (scope por examen)
            //    Usamos whereHas para ser consistentes con el modelo actual de asignaciones
            $casosAsignados = Caso::query()
                ->where('examen_id', $examenId)
                ->whereHas('evaluador', fn ($q) => $q->where('users.id', $evaluador->id))
                ->select('id', 'titulo', 'orden_relativo')
                ->orderBy('orden_relativo')
                ->get();

            // 3) % de avance del evaluador
            $porcentaje = 0;
            if ((int) $evaluador->correcciones_asignadas > 0) {
                $raw = ($evaluador->correcciones_realizadas / max(1, (int) $evaluador->correcciones_asignadas)) * 100;
                $porcentaje = (int) max(0, min(100, round($raw)));
            }

            $progresoEvaluadores[] = [
                'evaluador_id' => $evaluador->id,
                'evaluador_nombre' => $evaluador->apellido.', '.$evaluador->nombre,
                'evaluador_email' => $evaluador->email,
                'casos_asignados' => $casosAsignados,
                'porcentaje' => $porcentaje,
                'finalizado' => $evaluador->finalizado,
            ];
        }

        // 4) Orden final EXACTO requerido:
        //    - NO finalizados primero por porcentaje ASC; empate por nombre y apellido
        //    - Finalizados al final por porcentaje ASC; empate por nombre y apellido
        $noFinal = array_values(array_filter($progresoEvaluadores, fn ($r) => empty($r['finalizado'])));
        $final = array_values(array_filter($progresoEvaluadores, fn ($r) => ! empty($r['finalizado'])));

        $cmpNombre = static function ($a, $b) {
            return strnatcasecmp($a['evaluador_nombre'] ?? '', $b['evaluador_nombre'] ?? '');
        };

        usort($noFinal, function ($a, $b) use ($cmpNombre) {
            $cmp = ($a['porcentaje'] <=> $b['porcentaje']); // ASC
            if ($cmp !== 0) {
                return $cmp;
            }

            return $cmpNombre($a, $b);
        });

        usort($final, function ($a, $b) use ($cmpNombre) {
            $cmp = ($a['porcentaje'] <=> $b['porcentaje']); // ASC
            if ($cmp !== 0) {
                return $cmp;
            }

            return $cmpNombre($a, $b);
        });

        $progresoEvaluadores = array_merge($noFinal, $final);

        return $this->sendResponse(HTTP::OK, ['progreso' => $progresoEvaluadores]);
    }

    public function estadosEvaluadores(Request $request, $id)
    {
        $examenId = (int) $id;
        $userId = (int) $request->user()->id;

        $examen = Examen::find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $isAdmin = User::role(Roles::ADMINISTRADOR->value)->whereKey($userId)->exists();

        if (! $isAdmin) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'El usuario no es administrador');
        }

        $estados = EstadoExamenEvaluador::query()
            ->where('examen_id', $examenId)
            ->orderBy('evaluador_id')
            ->get();

        return $this->sendResponse(
            HTTP::OK,
            EstadoExamenEvaluadorResource::collection($estados)->resolve(),
            'Estados de evaluadores recuperados correctamente'
        );
    }

    public function estadosEstudiantes(Request $request, $id)
    {
        $examenId = (int) $id;
        $userId = (int) $request->user()->id;

        $examen = Examen::find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $isAdmin = User::role(Roles::ADMINISTRADOR->value)->whereKey($userId)->exists();

        if (! $isAdmin) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'El usuario no es administrador');
        }

        $estados = EstadoExamenEstudiante::query()
            ->where('examen_id', $examenId)
            ->orderBy('estudiante_id')
            ->get();

        return $this->sendResponse(
            HTTP::OK,
            EstadoExamenEstudianteResource::collection($estados)->resolve(),
            'Estados de evaluadores recuperados correctamente'
        );
    }
}
