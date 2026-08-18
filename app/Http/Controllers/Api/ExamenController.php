<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Enums\Roles;
use App\Http\Requests\ExamenRequests\StoreExamenRequest;
use App\Http\Requests\ExamenRequests\UpdateExamenRequest;
use App\Http\Resources\ExamenResource;
use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\ExamenUserRoles;
use App\Models\Item;
use App\Models\NotaDefensa;
use App\Models\NotaItem;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Models\TablaCotejo;
use App\Models\User;
use App\Services\EstadoExamenEvaluadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ExamenController extends ApiBaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $examenes = Examen::query()
            ->whereHas('administradores', fn ($q) => $q->where('users.id', $user->id))
            ->latest('fecha')
            ->get();

        return $this->sendResponse(HTTP::OK, ExamenResource::collection($examenes), 'Exámenes recuperados exitosamente');
    }

    public function show(int $id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen recuperado exitosamente');
    }

    public function showByCode(Request $request, string $codigo)
    {
        $user = $request->user();

        $examen = Examen::where('codigo', $codigo)->first();

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $pertenece = $examen->users()->whereKey($user->id)->exists();

        if (! $pertenece) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'El usuario no pertenece a este examen');
        }

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen recuperado exitosamente');
    }

    public function store(StoreExamenRequest $request)
    {
        $data = $request->validated();
        $codigo = $data['codigo'];
        $codigoNorm = $this->normalizeCodigo($codigo);

        $existe = Examen::where('codigo', $codigoNorm)->exists();
        if ($codigoNorm !== '' && $existe) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], 'El código de examen ya está en uso');
        }

        $data['codigo'] = $codigoNorm;
        $data['creado_por'] = $request->user()->id;

        $examen = Examen::create($data);

        $adminRoleId = Role::query()
            ->where('name', Roles::ADMINISTRADOR->value)
            ->value('id');

        $examen->users()->syncWithoutDetaching([
            $request->user()->id => ['role_id' => $adminRoleId],
        ]);

        return $this->sendResponse(HTTP::CREATED, new ExamenResource($examen), 'Examen creado exitosamente');
    }

    public function update(UpdateExamenRequest $request, int $id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $data = $request->validated();

        $rawNuevoCodigo = $data['codigo'];
        $nuevoCodigoNorm = $this->normalizeCodigo($rawNuevoCodigo);
        $codigoActualNorm = $this->normalizeCodigo($examen->codigo);

        if ($nuevoCodigoNorm !== '' && $nuevoCodigoNorm !== $codigoActualNorm) {
            $existe = Examen::where('codigo', $nuevoCodigoNorm)
                ->where('id', '!=', $examen->id)
                ->exists();

            if ($existe) {
                return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], 'El código de examen ya está en uso');
            }
        }

        $data['codigo'] = $nuevoCodigoNorm !== '' ? $nuevoCodigoNorm : $codigoActualNorm;

        $examen->fill($data)->save();

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen actualizado exitosamente');
    }

    public function destroy(int $id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if ($examen->publicado && (! $examen->finalizado || ! $examen->defendido)) {
            return $this->sendResponse(
                HTTP::BAD_REQUEST,
                [],
                'No se puede borrar un examen en progreso.'
            );
        }

        $examen->delete();

        return $this->sendResponse(HTTP::OK, [], 'Examen eliminado exitosamente');
    }

    public function reestablecer($id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->publicado) {
            return $this->sendResponse(
                HTTP::BAD_REQUEST,
                [],
                'No se puede reestablecer un examen no publicado.'
            );
        }

        if ($examen->publicado && ! $examen->finalizado) {
            return $this->sendResponse(
                HTTP::BAD_REQUEST,
                [],
                'No se puede reestablecer un examen en progreso.'
            );
        }

        DB::beginTransaction();

        EstadoExamenEvaluador::where('examen_id', $id)->delete();

        $estudiantes = $examen->estudiantes()->get();

        $estudiantesIds = $estudiantes->pluck('id');

        NotaItem::whereIn('estudiante_id', $estudiantesIds)
            ->whereHas('item', function ($query) use ($id) {
                $query->where('examen_id', $id);
            })
            ->delete();

        NotaDefensa::whereIn('estudiante_id', $estudiantesIds)
            ->whereHas('caso', function ($query) use ($id) {
                $query->where('examen_id', $id);
            })
            ->delete();

        RespuestaEstudiante::whereIn('estudiante_id', $estudiantesIds)
            ->whereHas('pregunta', function ($query) use ($id) {
                $query->where('examen_id', $id);
            })
            ->delete();

        EstadoExamenEstudiante::where('examen_id', $id)->delete();

        ExamenEstudiantePseudonimo::where('examen_id', $id)->delete();

        // ID del rol ESTUDIANTE
        $studentRoleId = Role::query()
            ->where('name', Roles::ESTUDIANTE->value)
            ->value('id');

        // 1) Borrar SOLO el rol ESTUDIANTE en este examen (examen_user_roles)
        ExamenUserRoles::query()
            ->where('examen_id', $examen->id)
            ->where('role_id', $studentRoleId)
            ->whereIn('user_id', $estudiantesIds)
            ->delete();

        // 2) Detach SOLO el rol ESTUDIANTE en la relación del User (para coherencia/caché)
        User::query()
            ->whereIn('id', $estudiantesIds)
            ->each(function (User $u) use ($examen, $studentRoleId) {
                $u->rolesPorExamen()
                    ->wherePivot('examen_id', $examen->id)
                    ->wherePivot('role_id', $studentRoleId)
                    ->detach();
                $u->unsetRelation('rolesPorExamen');
            });

        $examen->update(['publicado' => 0, 'finalizado' => 0, 'defendido' => 0]);

        DB::commit();

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examenes restablecido exitosamente');
    }

    public function publicar(int $id)
    {
        $examen = Examen::find($id);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'El examen no ha sido creado, no es posible publicar un examen no creado.');
        }

        // Evaluadores del examen (vía relación)
        $evaluadores = $examen->evaluadores()->get();
        if ($evaluadores->isEmpty()) {
            return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_evaluadores'], 'No es posible publicar un examen para el cual no hay definidos evaluadores. Por favor, cree al menos uno.');
        }

        $evaluadoresIds = $examen->evaluadores()->pluck('users.id');

        $evaluadoresSinCasos = [];
        if ($evaluadoresIds->isNotEmpty()) {
            $evaluadoresMap = User::query()
                ->whereIn('id', $evaluadoresIds)
                ->get(['id', 'nombre', 'apellido'])
                ->keyBy('id');

            foreach ($evaluadoresIds as $eid) {
                $tieneCasos = Caso::query()
                    ->where('examen_id', $examen->id)
                    ->whereHas('evaluador', fn ($q) => $q->where('users.id', $eid))
                    ->exists();

                if (! $tieneCasos) {
                    $u = $evaluadoresMap->get($eid);
                    $evaluadoresSinCasos[] = $u ? ($u->nombre.' '.$u->apellido) : (string) $eid;
                }
            }

            if (! empty($evaluadoresSinCasos)) {
                return $this->sendResponse(
                    HTTP::UNPROCESSABLE_ENTITY,
                    ['error' => 'sin_evaluadores_casos_asignados'],
                    'No se puede publicar: hay evaluadores sin casos asignados ('.implode(', ', $evaluadoresSinCasos).').'
                );
            }
        }

        // Estudiantes del examen (vía relación)
        $estudiantes = $examen->estudiantes()->get();
        if ($estudiantes->isEmpty()) {
            return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_estudiantes'], 'No es posible publicar un examen para el cual no hay definidos estudiantes. Por favor, cree al menos uno.');
        }

        // Secciones del examen (vía relación)
        $secciones = $examen->secciones()->get();

        if ($secciones === 0) {
            return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_secciones'], 'No es posible publicar un examen para el cual no hay definidas secciones. Por favor, cree al menos una.');
        }

        // Casos del examen (vía relación)
        $casos = $examen->casos()->get();
        if ($casos->isEmpty()) {
            return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_casos'], 'No es posible publicar un examen para el cual no hay definidos casos. Por favor, cree al menos uno.');
        }

        // Verificación por caso: debe existir al menos una pregunta evaluable (consigna NO nula/NO vacía) y una tabla de cotejo
        foreach ($casos as $caso) {
            $preguntaEvaluable = Pregunta::query()
                ->where('caso_id', $caso->id)
                ->whereNotNull('consigna')
                ->whereRaw("TRIM(consigna) <> ''")
                ->first();

            if (! $preguntaEvaluable) {
                return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_evaluables'], 'No es posible publicar un examen en donde al menos para un caso no hay preguntas evaluables.');
            }

            $tablaCotejo = TablaCotejo::query()
                ->where('caso_id', $caso->id)
                ->first();

            if (! $tablaCotejo) {
                return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_tablas'], 'Para poder publicar el examen, cada caso debe tener una tabla de cotejo asociada. El caso '.$caso->titulo.' no tiene tabla de cotejo.');
            }
        }

        // Todas las preguntas evaluables deben tener al menos un ítem
        $preguntas = Pregunta::query()
            ->with('caso')
            ->where('examen_id', '=', $id)
            ->whereNotNull('consigna')
            ->whereRaw("TRIM(consigna) <> ''")
            ->orderBy('orden_relativo')
            ->get();

        foreach ($preguntas as $pregunta) {
            $itemPregunta = Item::query()
                ->where('pregunta_id', $pregunta->id)
                ->first();

            if (! $itemPregunta) {
                return $this->sendResponse(HTTP::BAD_REQUEST, ['error' => 'sin_items'], 'Para poder publicar el examen, cada pregunta evaluable debe tener un item asociado. La pregunta '.$pregunta->orden_relativo.' del caso '.$pregunta->caso->titulo.' no tiene.');
            }
        }

        // Estados de EVALUADORES (upsert con cálculo de asignadas/realizadas vía service)
        foreach ($evaluadores as $evaluador) {
            EstadoExamenEvaluadorService::upsert($examen, (int) $evaluador->id);
        }

        // Primera pregunta evaluable del examen (para posicionar a los estudiantes)
        $primerPreguntaExamen = Pregunta::query()
            ->where('preguntas.examen_id', $id)
            ->whereNotNull('preguntas.consigna')
            ->whereRaw("TRIM(preguntas.consigna) <> ''")
            ->join('casos', 'preguntas.caso_id', '=', 'casos.id')
            ->orderBy('casos.orden_relativo')
            ->orderBy('preguntas.orden_relativo')
            ->select('preguntas.*')
            ->first();

        // Estados de ESTUDIANTES (upsert usando la PK única).
        if ($primerPreguntaExamen) {
            foreach ($estudiantes as $estudiante) {
                EstadoExamenEstudiante::updateOrCreate(
                    [
                        'estudiante_id' => $estudiante->id,
                        'examen_id' => $id,
                    ],
                    [
                        // Solo posicionamiento inicial; flags quedan en false por defecto.
                        'caso_id' => $primerPreguntaExamen->caso_id,
                        'pregunta_id' => $primerPreguntaExamen->id,
                    ]
                );
            }
        }

        $examen->update(['publicado' => 1]);

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen publicado exitosamente');
    }

    public function finalizar(int $id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if ($examen->finalizado) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El examen ya ha finalizado, no es posible volver a finalizarlo.');
        }

        if (! $examen->publicado) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'No es posible finalizar un examen que nunca comenzó.');
        }

        $estudiantesSinFinalizar = EstadoExamenEstudiante::query()
            ->where('examen_id', $id)
            ->where('finalizado', false)
            ->get();

        $horaFinalizacion = now();

        $ultimoOrdenFinalizacion = EstadoExamenEstudiante::query()
            ->where('examen_id', $id)
            ->where('finalizado', true)
            ->max('posicion_finalizacion') ?? 0;

        foreach ($estudiantesSinFinalizar as $estudiante) {
            $estudiante->update(['finalizado' => 1]);

            if ($estudiante->inicio) {
                $ultimoOrdenFinalizacion++;
                $estudiante->update([
                    'hora_finalizacion' => $horaFinalizacion,
                    'posicion_finalizacion' => $ultimoOrdenFinalizacion,
                ]);
            }
        }

        $examen->update(['finalizado' => 1]);

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen finalizado exitosamente');
    }

    public function defender(int $id)
    {
        $examen = Examen::find($id);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->finalizado) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El examen no está finalizado.');
        }

        $hayPendientes = $examen->estadosExamenesEvaluadores()
            ->whereIn('evaluador_id', $examen->evaluadores()->select('users.id'))
            ->where('finalizado', 0)
            ->exists();

        if ($hayPendientes) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'Aún hay evaluadores sin finalizar.');
        }

        $examen->update(['defendido' => 1]);

        return $this->sendResponse(HTTP::OK, new ExamenResource($examen), 'Examen defendido exitosamente');
    }

    private function normalizeCodigo(string $codigo): string
    {
        $codigo = Str::ascii($codigo);
        $codigo = preg_replace('/\s+/', '', $codigo);
        $codigo = preg_replace('/[^A-Za-z0-9_-]/', '', $codigo);

        return strtoupper($codigo);
    }
}
