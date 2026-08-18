<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Requests\EvaluadorRequests\StoreNotaPreguntaRequest;
use App\Http\Resources\EstadoExamenEvaluadorResource;
use App\Http\Resources\NotaItemResource;
use App\Http\Resources\RespuestaEstudianteResource;
use App\Models\EstadoExamenEvaluador;
use App\Models\Examen;
use App\Models\ExamenEstudiantePseudonimo;
use App\Models\NotaItem;
use App\Models\Pregunta;
use App\Models\RespuestaEstudiante;
use App\Services\EstadoExamenEvaluadorService;
use App\Services\PseudonimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;

class EvaluadorController extends ApiBaseController
{
    public function __construct(
        private readonly PseudonimoService $pseudonimoService,
        private readonly EstadoExamenEvaluadorService $estadoExamenEvaluadorService,
    ) {}

    public function respuestasEstudiantes(Request $request)
    {
        $preguntaId = (int) $request->query('pregunta');
        if (! $preguntaId) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El parámetro pregunta es obligatorio');
        }
        $pseudonimo = (string) $request->query('pseudonimo', '');
        if ($pseudonimo === '') {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El parámetro pseudonimo es obligatorio');
        }

        $pregunta = Pregunta::with('caso.examen')->find($preguntaId);
        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pregunta no encontrada');
        }

        $examen = $pregunta->caso->examen;

        if (! $examen->evaluadores()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, null, 'No estás habilitado como EVALUADOR en este examen');
        }

        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado');
        }

        $data = RespuestaEstudianteResource::collection(
            RespuestaEstudiante::query()
                ->where('estudiante_id', $estudiante->id)
                ->where('pregunta_id', $pregunta->id)
                ->get()
        )->resolve();

        return $this->sendResponse(HTTP::OK, $data, 'Respuestas de estudiantes recuperadas correctamente');
    }

    public function correcciones(Request $request)
    {
        $preguntaId = (int) $request->query('pregunta');
        if (! $preguntaId) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El parámetro pregunta es obligatorio');
        }

        $pseudonimo = (string) $request->query('pseudonimo', '');
        if ($pseudonimo === '') {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'El parámetro pseudonimo es obligatorio');
        }

        $pregunta = Pregunta::with('caso.examen', 'items')->find($preguntaId);
        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pregunta no encontrada');
        }

        $examen = $pregunta->caso->examen;

        if (! $examen->evaluadores()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, null, 'No estás habilitado como EVALUADOR en este examen');
        }

        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado');
        }

        $itemsIds = $pregunta->items()->pluck('items.id');

        $data = NotaItemResource::collection(
            NotaItem::query()
                ->whereIn('item_id', $itemsIds)
                ->where('estudiante_id', $estudiante->id)
                ->get()
        )->resolve();

        return $this->sendResponse(HTTP::OK, $data, 'Correcciones recuperadas correctamente');
    }

    public function cargarCorreccionesPregunta(StoreNotaPreguntaRequest $request)
    {
        $preguntaId = (int) $request->query('pregunta');
        $pregunta = Pregunta::with('caso.examen')->find($preguntaId);
        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Pregunta no encontrada');
        }

        $examen = $pregunta->caso->examen;

        if (! $examen->evaluadores()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, null, 'No estás habilitado como EVALUADOR en este examen');
        }

        $pseudonimo = (string) $request->query('pseudonimo');
        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado');
        }

        LogBatch::withinBatch(fn () => DB::transaction(function () use ($request, $estudiante) {
            $evaluadorId = (int) $request->user()->id;
            $items = (array) $request->validated('items');

            foreach ($items as $item) {
                NotaItem::updateOrCreate(
                    [
                        'estudiante_id' => $estudiante->id,
                        'item_id' => Arr::get($item, 'item_id'),
                    ],
                    [
                        'aprobado' => (bool) Arr::get($item, 'aprobado'),
                        'evaluador_id' => $evaluadorId,
                    ],
                );
            }
        }));

        $evaluadorId = (int) $request->user()->id;
        $this->estadoExamenEvaluadorService->upsert($examen, $evaluadorId);

        $totalItems = $pregunta->items()->count();
        $itemsCorreg = NotaItem::where('estudiante_id', $estudiante->id)
            ->whereIn('item_id', $pregunta->items()->pluck('items.id'))
            ->count();
        $completa = $totalItems === $itemsCorreg;

        return $this->sendResponse(
            $completa ? HTTP::ACCEPTED : HTTP::CREATED,
            [],
            $completa ? 'Pregunta corregida para el estudiante' : 'Corrección parcial guardada exitosamente'
        );
    }

    public function estadoCorreccionExamen(Request $request)
    {
        $examenId = (int) $request->query('examen');
        $evaluadorId = (int) ($request->query('evaluador') ?: $request->user()->id);

        // 1) Carga selectiva + filtro de casos por evaluador (menos datos y mejor cache hit)
        $examen = Examen::query()
            ->select('id')
            ->with([
                'casos' => function ($q) use ($evaluadorId) {
                    $q->select('id', 'examen_id', 'evaluador_id')
                        ->where('evaluador_id', $evaluadorId)
                        ->with(['preguntas' => function ($p) {
                            $p->select('id', 'caso_id', 'consigna')
                                ->whereNotNull('consigna')
                                ->whereRaw("TRIM(consigna) <> ''")
                                ->with(['items' => function ($i) {
                                    $i->select('id', 'pregunta_id'); // solo lo usado
                                }]);
                        }]);
                },
                'estudiantes' => function ($q) {
                    $q->select('users.id');
                },
            ])
            ->find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->evaluadores()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, null, 'No estás habilitado como EVALUADOR en este examen');
        }

        $totalEstudiantes = $examen->estudiantes->count();

        // 2) Mapas en memoria (eficientes)
        $preguntaItems = [];  // pregunta_id => [item_id,...]
        $casoItems = [];  // caso_id => [item_id,...]
        $preguntasPorCaso = [];  // caso_id => [pregunta_id,...]
        $preguntaToCaso = [];  // pregunta_id => caso_id
        $itemsIdsAll = [];

        foreach ($examen->casos as $caso) {
            $cid = (int) $caso->id;
            $preguntasPorCaso[$cid] = [];
            $casoItems[$cid] = [];

            foreach ($caso->preguntas as $pregunta) {
                $pid = (int) $pregunta->id;
                $preguntaToCaso[$pid] = (int) $pregunta->caso_id;
                $preguntasPorCaso[$cid][] = $pid;

                $itemIds = $pregunta->items->pluck('id')->map(fn ($x) => (int) $x)->all();
                $preguntaItems[$pid] = $itemIds;

                if (! empty($itemIds)) {
                    $casoItems[$cid] = array_merge($casoItems[$cid], $itemIds);
                    $itemsIdsAll = array_merge($itemsIdsAll, $itemIds);
                }
            }
        }

        $itemsIdsAll = array_values(array_unique($itemsIdsAll));

        $estadoEvaluador = EstadoExamenEvaluador::select('id', 'finalizado', 'created_at', 'updated_at')
            ->where('examen_id', $examen->id)
            ->where('evaluador_id', $evaluadorId)
            ->first();

        if (empty($itemsIdsAll)) {
            $data = [
                'id' => (int) ($estadoEvaluador->id ?? 0),
                'examen_id' => (int) $examen->id,
                'evaluador_id' => (int) $evaluadorId,
                'finalizado' => (bool) ($estadoEvaluador->finalizado ?? false),
                'correcciones_asignadas' => 0,
                'correcciones_realizadas' => 0,
                'created_at' => $estadoEvaluador?->created_at,
                'updated_at' => $estadoEvaluador?->updated_at,
                'casos' => [],
                'preguntas' => [],
                'estudiantes' => [],
            ];

            return $this->sendResponse(
                HTTP::OK,
                new EstadoExamenEvaluadorResource($data),
                'Estado de corrección de evaluador recuperado exitosamente'
            );
        }

        // 3) Conteo por ítem (1 query cacheable en Eloquent)
        $conteoPorItem = NotaItem::query()
            ->select('item_id', DB::raw('COUNT(DISTINCT estudiante_id) as total'))
            ->whereIn('item_id', $itemsIdsAll)
            ->groupBy('item_id')
            ->pluck('total', 'item_id');

        $itemsCompletosSet = array_flip(
            collect($conteoPorItem)->filter(fn ($c) => (int) $c === $totalEstudiantes)->keys()->all()
        );

        // 4) Preguntas
        $preguntasData = [];
        $preguntasFinalizadasSet = [];

        foreach ($preguntaItems as $preguntaId => $itemIds) {
            $itemsTot = count($itemIds);
            $itemsCorr = 0;

            foreach ($itemIds as $iid) {
                if (isset($itemsCompletosSet[$iid])) {
                    $itemsCorr++;
                }
            }

            $finalizada = ($itemsCorr === $itemsTot);

            $preguntasData[] = [
                'id' => (int) $preguntaId,
                'caso_id' => (int) ($preguntaToCaso[$preguntaId] ?? 0),
                'finalizada' => $finalizada,
                'items_totales' => $itemsTot,
                'items_corregidos' => $itemsCorr,
            ];

            if ($finalizada) {
                $preguntasFinalizadasSet[(int) $preguntaId] = true;
            }
        }

        // 5) Casos
        $casosData = [];
        foreach ($preguntasPorCaso as $casoId => $preguntasIds) {
            $pregTot = count($preguntasIds);
            $pregCorr = 0;
            foreach ($preguntasIds as $pid) {
                if (isset($preguntasFinalizadasSet[(int) $pid])) {
                    $pregCorr++;
                }
            }

            $casosData[] = [
                'id' => (int) $casoId,
                'finalizado' => ($pregCorr === $pregTot),
                'preguntas_totales' => $pregTot,
                'preguntas_corregidas' => $pregCorr,
            ];
        }

        // 6) Ítems corregidos por estudiante (1 query cacheable; solo columnas necesarias)
        $notasPorEstudiante = NotaItem::query()
            ->select('estudiante_id', 'item_id')
            ->whereIn('item_id', $itemsIdsAll)
            ->distinct()
            ->get()
            ->groupBy('estudiante_id')
            ->map(fn ($g) => $g->pluck('item_id')->all());

        // 7) Pseudónimos por estudiante del examen (1 query cacheable)
        $pseudonimos = ExamenEstudiantePseudonimo::query()
            ->select('estudiante_id', 'pseudonimo_id')
            ->where('examen_id', $examen->id)
            ->with(['pseudonimo:id,nombre'])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->estudiante_id => (string) optional($row->pseudonimo)->nombre]);

        // 8) Estudiantes
        $estudiantesData = [];
        foreach ($examen->estudiantes as $estudiante) {
            $eid = (int) $estudiante->id;
            $itemsEstudianteSet = array_flip($notasPorEstudiante->get($eid, []));

            $casosFin = [];
            foreach ($casoItems as $casoId => $itemsCaso) {
                $done = true;
                foreach ($itemsCaso as $iid) {
                    if (! isset($itemsEstudianteSet[$iid])) {
                        $done = false;
                        break;
                    }
                }
                if ($done) {
                    $casosFin[] = (int) $casoId;
                }
            }

            $pregFin = [];
            foreach ($preguntaItems as $pregId => $itemsPreg) {
                $done = true;
                foreach ($itemsPreg as $iid) {
                    if (! isset($itemsEstudianteSet[$iid])) {
                        $done = false;
                        break;
                    }
                }
                if ($done) {
                    $pregFin[] = (int) $pregId;
                }
            }

            $estudiantesData[] = [
                'id' => $eid,
                'pseudonimo' => $pseudonimos->get($eid),
                'casos_finalizados' => $casosFin,
                'preguntas_finalizadas' => $pregFin,
            ];
        }

        // 9) Totales
        $asignadas = array_sum(array_column($casosData, 'preguntas_totales'));
        $realizadas = array_sum(array_column($casosData, 'preguntas_corregidas'));

        $data = [
            'id' => (int) ($estadoEvaluador->id ?? 0),
            'examen_id' => (int) $examen->id,
            'evaluador_id' => (int) $evaluadorId,
            'finalizado' => (bool) ($estadoEvaluador->finalizado ?? false),
            'correcciones_asignadas' => (int) $asignadas,
            'correcciones_realizadas' => (int) $realizadas,
            'created_at' => $estadoEvaluador?->created_at,
            'updated_at' => $estadoEvaluador?->updated_at,
            'casos' => $casosData,
            'preguntas' => $preguntasData,
            'estudiantes' => $estudiantesData,
        ];

        return $this->sendResponse(
            HTTP::OK,
            new EstadoExamenEvaluadorResource($data),
            'Estado de corrección de evaluador recuperado exitosamente'
        );
    }

    public function finalizarCorreccionExamen(Request $request)
    {
        $examenId = (int) $request->query('examen');
        $evaluadorId = (int) ($request->query('evaluador') ?: $request->user()->id);

        $examen = Examen::with('casos')->find($examenId);

        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        if (! $examen->evaluadores()->whereKey($request->user()->id)->exists()) {
            return $this->sendResponse(HTTP::FORBIDDEN, null, 'No estás habilitado como EVALUADOR en este examen');
        }

        $estado = EstadoExamenEvaluador::firstOrCreate(
            ['examen_id' => $examen->id, 'evaluador_id' => $evaluadorId],
            ['finalizado' => false, 'correcciones_asignadas' => 0, 'correcciones_realizadas' => 0],
        );

        $asignadas = (int) ($estado->correcciones_asignadas ?? 0);
        $realizadas = (int) ($estado->correcciones_realizadas ?? 0);
        if ($asignadas !== $realizadas) {
            return $this->sendResponse(
                HTTP::UNPROCESSABLE_ENTITY,
                (new EstadoExamenEvaluadorResource($estado))->resolve(),
                'Aún hay correcciones pendientes de realizar.'
            );
        }

        $estado->finalizado = true;
        $estado->save();

        return $this->sendResponse(
            HTTP::OK,
            (new EstadoExamenEvaluadorResource($estado))->resolve(),
            'Correcciones finalizadas exitosamente'
        );
    }
}
