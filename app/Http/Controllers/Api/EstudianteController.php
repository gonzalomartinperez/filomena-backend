<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Resources\EstadoExamenEstudianteResource;
use App\Http\Resources\PreguntaResource;
use App\Http\Resources\PseudonimoResource;
use App\Http\Resources\RespuestaEstudianteResource;
use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Services\PseudonimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstudianteController extends ApiBaseController
{
    public function __construct(
        private readonly PseudonimoService $pseudonimoService
    ) {}

    /** Marca el inicio del examen y resuelve el puntero inicial si está vacío. */
    public function iniciar(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial($examen) + ['inicio' => true, 'hora_inicio' => now()]
        );

        if (! $estado->inicio) {
            $estado->inicio = true;
        }

        if (! $estado->hora_inicio) {
            $estado->hora_inicio = now();
        }

        if (! $estado->caso_id || ! $estado->pregunta_id) {
            $p = $this->punteroInicial($examen);
            $estado->caso_id = $p['caso_id'] ?? null;
            $estado->pregunta_id = $p['pregunta_id'] ?? null;
        }

        $estado->save();

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Examen iniciado');
    }

    /** Marca finalizado el examen, hora_finalizacion y posicion_finalizacion (orden de llegada). */
    public function finalizar(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::with(['casos'])->find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial($examen) + ['inicio' => true, 'hora_inicio' => now()]
        );

        // Si ya estaba finalizado, garantizamos idempotencia y devolvemos.
        if ($estado->finalizado) {
            if ($estado->posicion_finalizacion === null) {
                DB::transaction(function () use ($estado, $examenId) {
                    // Bloqueamos las filas relevantes para evitar colisiones de posición
                    $last = EstadoExamenEstudiante::where('examen_id', $examenId)
                        ->whereNotNull('hora_finalizacion')
                        ->whereNotNull('posicion_finalizacion')
                        ->orderByDesc('posicion_finalizacion')
                        ->lockForUpdate()
                        ->first();

                    $estado->posicion_finalizacion = (($last?->posicion_finalizacion) ?? 0) + 1;
                    $estado->save();
                });
            }

            return $this->sendResponse(
                HTTP::OK,
                new EstadoExamenEstudianteResource($estado),
                'Examen finalizado'
            );
        }

        // Finaliza y asigna posición de llegada de forma atómica.
        DB::transaction(function () use ($estado, $examenId) {
            $estado->finalizado = true;
            $estado->hora_finalizacion = now();

            if ($estado->posicion_finalizacion === null) {
                $last = EstadoExamenEstudiante::where('examen_id', $examenId)
                    ->whereNotNull('hora_finalizacion')
                    ->whereNotNull('posicion_finalizacion')
                    ->orderByDesc('posicion_finalizacion')
                    ->lockForUpdate()
                    ->first();

                $estado->posicion_finalizacion = (($last?->posicion_finalizacion) ?? 0) + 1;
            }

            $estado->save();
        });

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Examen finalizado');
    }

    /** Desactivar la alerta enviada por el administrador */
    public function desactivarAlerta(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial($examen) + ['inicio' => false]
        );

        $estado->alertado = false;
        $estado->save();

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Alerta registrada');
    }

    /** Devuelve el pseudónimo del estudiante en el examen. */
    public function pseudonimo(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $pseudonimoExamen = ExamenEstudiantePseudonimo::query()
            ->with('pseudonimo')
            ->where('examen_id', $examenId)
            ->where('estudiante_id', $user->id)
            ->first();

        if (! $pseudonimoExamen || ! $pseudonimoExamen->pseudonimo) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pseudónimo no asignado');
        }

        return $this->sendResponse(HTTP::OK, new PseudonimoResource($pseudonimoExamen->pseudonimo));
    }

    /** Obtiene el estado y garantiza puntero inicial. */
    public function estado(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial($examen)
        );

        if (! $estado->caso_id || ! $estado->pregunta_id) {
            $p = $this->punteroInicial($examen);
            $estado->caso_id = $p['caso_id'] ?? null;
            $estado->pregunta_id = $p['pregunta_id'] ?? null;
            $estado->save();
        }

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Estado de examen recuperado exitosamente');
    }

    /** Actualiza el puntero de examen validando pertenencia. */
    public function actualizarPuntero(Request $request, int $examenId)
    {
        $user = $request->user();

        $request->validate([
            'caso_id' => ['required', 'integer'],
            'pregunta_id' => ['required', 'integer'],
        ]);

        $examen = Examen::find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $pregunta = Pregunta::with('caso')->find((int) $request->pregunta_id);
        if (! $pregunta || ! $pregunta->caso || $pregunta->caso->examen_id !== $examen->id || (int) $request->caso_id !== $pregunta->caso->id) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'La pregunta/caso no pertenece al examen');
        }

        $estado = EstadoExamenEstudiante::updateOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            ['caso_id' => $pregunta->caso->id, 'pregunta_id' => $pregunta->id]
        );

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Puntero de estudiante actualizado');
    }

    /**
     * navegar(): calcula siguiente/anterior desde el puntero actual.
     * Query: direccion = 'siguiente' | 'anterior' (default: 'siguiente').
     * No muta estado; el front luego hace PATCH actualizarPuntero().
     */
    public function navegar(Request $request, int $examenId)
    {
        $user = $request->user();

        $examen = Examen::with(['casos.preguntas'])->find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $direccion = strtolower((string) $request->query('direccion', 'siguiente'));
        $dir = $direccion === 'anterior' ? -1 : 1;

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial($examen)
        );

        $orden = $this->ordenLinealPreguntas($examen, includeInformativas: true);
        if (empty($orden)) {
            return $this->sendResponse(HTTP::OK, null, 'No hay preguntas en el examen');
        }

        $ids = array_column($orden, 'pregunta_id');
        $fromId = $estado->pregunta_id ?: $ids[0];
        $idx = array_search((int) $fromId, $ids, true);
        if ($idx === false) {
            $idx = 0;
        }

        $nextIdx = $idx + $dir;
        if ($nextIdx < 0 || $nextIdx >= count($orden)) {
            return $this->sendResponse(HTTP::OK, [], 'Fin del recorrido');
        }

        $next = $orden[$nextIdx];
        $pregunta = Pregunta::find($next['pregunta_id']);

        return $this->sendResponse(HTTP::OK, new PreguntaResource($pregunta), 'Pregunta recuperada exitosamente');
    }

    /**
     * Guarda o actualiza la respuesta para una pregunta respondible.
     * Requiere consigna no vacía y pertenencia al examen.
     */
    public function responder(Request $request, int $preguntaId)
    {
        $user = $request->user();

        $pregunta = Pregunta::with('caso')->find($preguntaId);

        if (! $pregunta || ! $pregunta->caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pregunta no encontrada');
        }

        if (! is_string($pregunta->consigna) || trim($pregunta->consigna) === '') {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'La pregunta no es respondible');
        }

        $examenId = (int) $pregunta->caso->examen_id;
        $examen = Examen::find($examenId);

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        RespuestaEstudiante::updateOrCreate(
            ['estudiante_id' => $user->id, 'pregunta_id' => $preguntaId],
            ['respuesta' => $request->input('respuesta')]
        );

        $estado = EstadoExamenEstudiante::firstOrCreate(
            ['examen_id' => $examenId, 'estudiante_id' => $user->id],
            $this->punteroInicial(Examen::find($examenId))
        );

        return $this->sendResponse(HTTP::OK, new EstadoExamenEstudianteResource($estado), 'Respuesta guardada');
    }

    /** Devuelve la respuesta propia para una pregunta. */
    public function respuesta(Request $request, int $preguntaId)
    {
        $user = $request->user();

        $pregunta = Pregunta::with('caso')->find($preguntaId);
        if (! $pregunta || ! $pregunta->caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pregunta no encontrada');
        }

        $examenId = (int) $pregunta->caso->examen_id;
        $examen = Examen::find($examenId);

        if (! $examen->estudiantes()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás habilitado como ESTUDIANTE en este examen');
        }

        $resp = RespuestaEstudiante::where('estudiante_id', $user->id)
            ->where('pregunta_id', $preguntaId)
            ->first();

        if (! $resp) {
            return $this->sendResponse(HTTP::OK, [], 'Sin respuesta');
        }

        return $this->sendResponse(HTTP::OK, new RespuestaEstudianteResource($resp), 'Respuesta obtenida exitosamente');
    }

    private function punteroInicial(Examen $examen): array
    {
        $primCaso = Caso::query()->where('examen_id', $examen->id)->orderBy('orden_relativo')->first();
        if (! $primCaso) {
            return ['caso_id' => null, 'pregunta_id' => null];
        }

        $primPreg = Pregunta::query()->where('caso_id', $primCaso->id)->orderBy('orden_relativo')->first();

        return ['caso_id' => $primCaso?->id, 'pregunta_id' => $primPreg?->id];
    }

    private function ordenLinealPreguntas(Examen $examen, bool $includeInformativas = true): array
    {
        $casos = Caso::query()
            ->where('examen_id', $examen->id)
            ->orderBy('orden_relativo')
            ->get(['id', 'orden_relativo']);

        if ($casos->isEmpty()) {
            return [];
        }

        $q = Pregunta::query()
            ->whereIn('caso_id', $casos->pluck('id'))
            ->orderBy('caso_id')
            ->orderBy('orden_relativo');

        if (! $includeInformativas) {
            $q->whereNotNull('consigna')->whereRaw("TRIM(consigna) <> ''");
        }

        return $q->get(['id as pregunta_id', 'caso_id'])
            ->map(fn ($r) => ['pregunta_id' => (int) $r->pregunta_id, 'caso_id' => (int) $r->caso_id])
            ->all();
    }
}
