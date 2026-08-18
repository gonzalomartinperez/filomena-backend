<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Helpers\TablaCotejoImportHelper;
use App\Http\Requests\TablaCotejoRequests\ImportTablasCotejoRequest;
use App\Http\Requests\TablaCotejoRequests\StoreTablaCotejoRequest;
use App\Http\Requests\TablaCotejoRequests\UpdateTablaCotejoRequest;
use App\Http\Resources\TablaCotejoResource;
use App\Models\Examen;
use App\Models\TablaCotejo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;

class TablaCotejoController extends ApiBaseController
{
    public function __construct(
        private TablaCotejoImportHelper $importHelper
    ) {}

    public function index(Request $request)
    {
        $present = collect(['examen', 'caso'])->filter(fn ($p) => $request->filled($p));
        if ($present->count() !== 1) {
            return $this->sendResponse(
                HTTP::BAD_REQUEST,
                [],
                'Debe enviar exactamente un parámetro entre examen o caso'
            );
        }

        $examenId = $request->integer('examen');
        $casoId = $request->integer('caso');
        $preguntaId = $request->integer('pregunta');

        [$examenId, $casoId] = $this->inferirContexto($examenId, $casoId, $preguntaId);

        $tablas = TablaCotejo::query()
            ->when($examenId, fn ($q) => $q->where('examen_id', $examenId))
            ->when($casoId, fn ($q) => $q->where('caso_id', $casoId))
            ->when($preguntaId, function ($q) use ($preguntaId) {
                // Evitamos cache en la consulta base y en las relaciones condicionadas
                $q->withoutCache()
                    ->whereHas('items', fn ($iq) => $iq->withoutCache()->where('pregunta_id', $preguntaId))
                    ->with(['items' => fn ($iq) => $iq->withoutCache()->where('pregunta_id', $preguntaId)]);
            })
            ->get();

        return $this->sendResponse(HTTP::OK, TablaCotejoResource::collection($tablas), 'Tablas de cotejo recuperadas exitosamente');
    }

    public function show(int $id, Request $request)
    {
        $preguntaId = $request->integer('pregunta');

        $builder = TablaCotejo::query();
        if ($preguntaId) {
            $builder->withoutCache(); // para que el eager load se ejecute “fresco”
        }

        $tabla = $builder->with([
            'items' => function ($iq) use ($preguntaId) {
                $iq->withoutCache();
                if ($preguntaId) {
                    $iq->where('pregunta_id', $preguntaId);
                }
            },
        ])->find($id);

        if (! $tabla) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        return $this->sendResponse(HTTP::OK, new TablaCotejoResource($tabla));
    }

    public function store(StoreTablaCotejoRequest $request)
    {
        $data = $request->validated();

        $examenId = (int) DB::table('casos')->where('id', $data['caso_id'])->value('examen_id');

        $exists = TablaCotejo::query()
            ->where('examen_id', $examenId)
            ->where('caso_id', (int) $data['caso_id'])
            ->where('seccion_id', (int) $data['seccion_id'])
            ->exists();

        if ($exists) {
            return $this->sendResponse(
                HTTP::UNPROCESSABLE_ENTITY,
                [],
                'Ya existe una tabla de cotejo para el examen, caso y sección indicados'
            );
        }

        $tabla = LogBatch::withinBatch(fn () => DB::transaction(function () use ($data, $examenId) {
            $tabla = new TablaCotejo;
            $tabla->examen_id = $examenId;
            $tabla->caso_id = (int) $data['caso_id'];
            $tabla->seccion_id = (int) $data['seccion_id'];
            $tabla->save();

            if (! empty($data['items'])) {
                $items = collect($data['items'])->map(function (array $it) use ($tabla) {
                    return [
                        'examen_id' => (int) $tabla->examen_id,
                        'caso_id' => (int) $tabla->caso_id,
                        'seccion_id' => (int) $tabla->seccion_id,
                        'tabla_cotejo_id' => (int) $tabla->id,
                        'pregunta_id' => isset($it['pregunta_id']) ? (int) $it['pregunta_id'] : null,
                        'descripcion' => $it['descripcion'] ?? null,
                        'valor' => isset($it['valor']) ? number_format($it['valor'], 2) : 0,
                    ];
                })->all();

                if (! empty($items)) {
                    $tabla->items()->createMany($items);
                }
            }

            return $tabla->load('items');
        }));

        return $this->sendResponse(HTTP::CREATED, new TablaCotejoResource($tabla), 'Tabla de cotejo creada exitosamente');
    }

    public function update(UpdateTablaCotejoRequest $request, int $id)
    {
        $tabla = TablaCotejo::query()->with('items')->find($id);
        if (! $tabla) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $data = $request->validated();

        if (! empty($data['items'])) {
            $idsEnviados = collect($data['items'])->pluck('id')->filter()->map(fn ($v) => (int) $v)->values();
            if ($idsEnviados->isNotEmpty()) {
                $idsValidos = $tabla->items()->whereIn('id', $idsEnviados)->pluck('id')->values();
                $idsInvalidos = $idsEnviados->diff($idsValidos);
                if ($idsInvalidos->isNotEmpty()) {
                    return $this->sendResponse(
                        HTTP::UNPROCESSABLE_ENTITY,
                        ['ids' => $idsInvalidos->all()],
                        'Algunos ítems no pertenecen a esta tabla de cotejo'
                    );
                }
            }
        }

        LogBatch::withinBatch(fn () => DB::transaction(function () use ($tabla, $data) {
            $nuevoCasoId = (int) $data['caso_id'];
            $nuevaSeccionId = (int) $data['seccion_id'];
            $nuevoExamenId = (int) DB::table('casos')->where('id', $nuevoCasoId)->value('examen_id');

            $tabla->caso_id = $nuevoCasoId;
            $tabla->seccion_id = $nuevaSeccionId;
            $tabla->examen_id = $nuevoExamenId;
            $tabla->save();

            if (! empty($data['items'])) {
                $idsMantener = [];

                foreach ($data['items'] as $it) {
                    $payload = [
                        'examen_id' => $nuevoExamenId,
                        'caso_id' => $nuevoCasoId,
                        'seccion_id' => $nuevaSeccionId,
                        'tabla_cotejo_id' => $tabla->id,
                        'pregunta_id' => isset($it['pregunta_id']) ? (int) $it['pregunta_id'] : null,
                        'descripcion' => $it['descripcion'] ?? null,
                        'valor' => isset($it['valor']) ? number_format($it['valor'], 2) : 0,
                    ];

                    if (! empty($it['id'])) {
                        $itemId = (int) $it['id'];
                        $tabla->items()->where('id', $itemId)->update($payload);
                        $idsMantener[] = $itemId;
                    } else {
                        $new = $tabla->items()->create($payload);
                        $idsMantener[] = (int) $new->id;
                    }
                }

                $tabla->items()->whereNotIn('id', $idsMantener)->delete();
            }
        }));

        $tabla->refresh()->load('items');

        return $this->sendResponse(HTTP::OK, new TablaCotejoResource($tabla), 'Tabla de cotejo actualizada exitosamente');
    }

    public function destroy(int $id)
    {
        $tabla = TablaCotejo::find($id);
        if (! $tabla) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        LogBatch::withinBatch(fn () => DB::transaction(fn () => $tabla->delete()));

        return $this->sendResponse(HTTP::OK, [], 'Tabla de cotejo eliminada exitosamente');
    }

    public function puntajes(int $id)
    {
        $tabla = TablaCotejo::select('id', 'caso_id', 'seccion_id')->find($id);
        if (! $tabla) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $totalCaso = (float) DB::table('items')
            ->where('tabla_cotejo_id', $tabla->id)
            ->where('caso_id', $tabla->caso_id)
            ->sum('valor');

        $totalSeccion = (float) DB::table('items')
            ->where('tabla_cotejo_id', $tabla->id)
            ->where('seccion_id', $tabla->seccion_id)
            ->sum('valor');

        $preguntas = DB::table('preguntas')
            ->where('caso_id', $tabla->caso_id)
            ->select('id', 'orden_relativo')
            ->orderBy('orden_relativo')
            ->get();

        $sumasPorPregunta = DB::table('items')
            ->select('pregunta_id', DB::raw('COALESCE(SUM(valor),0) as total'))
            ->where('tabla_cotejo_id', $tabla->id)
            ->groupBy('pregunta_id')
            ->pluck('total', 'pregunta_id');

        $detallePreguntas = $preguntas->map(function ($p) use ($sumasPorPregunta) {
            $t = ($sumasPorPregunta[$p->id] ?? 0);

            return [
                'pregunta_id' => (int) $p->id,
                'orden_relativo' => (int) $p->orden_relativo,
                'total' => round($t, 2),
            ];
        })->values();

        $payload = [
            'tabla_cotejo_id' => $tabla->id,
            'caso_id' => $tabla->caso_id,
            'seccion_id' => $tabla->seccion_id,
            'totales' => [
                'caso' => $totalCaso,
                'seccion' => $totalSeccion,
            ],
            'preguntas' => $detallePreguntas,
        ];

        return $this->sendResponse(HTTP::OK, $payload, 'Puntajes de tabla de cotejo obtenidos exitosamente');
    }

    private function inferirContexto(?int $examenId, ?int $casoId, ?int $preguntaId): array
    {
        if ($preguntaId) {
            $p = DB::table('preguntas')->select('caso_id', 'examen_id')->where('id', $preguntaId)->first();
            if ($p) {
                $casoId = $casoId ?: (int) $p->caso_id;
                $examenId = $examenId ?: (int) $p->examen_id;
            }
        }

        if ($casoId) {
            $examenId = (int) DB::table('casos')->where('id', $casoId)->value('examen_id');
        }

        return [$examenId, $casoId];
    }

    public function import(ImportTablasCotejoRequest $request)
    {
        $archivo = $request->file('archivo');
        $examenId = (int) $request->query('examen');

        try {
            // 1) Estandarización del archivo
            $rows = $this->importHelper->parse($archivo);

            // 2) Examen validado
            $examen = Examen::find($examenId);

            // 3) Transacción: todo o nada
            $resultado = DB::transaction(function () use ($rows, $examen) {
                return $this->importHelper->persist($rows, $examen);
            });

            return $this->sendResponse(HTTP::OK, $resultado, 'Importación de tablas de cotejo completada');
        } catch (\Throwable $e) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }
}
