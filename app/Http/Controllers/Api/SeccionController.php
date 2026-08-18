<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Helpers\SeccionImportHelper;
use App\Http\Requests\SeccionRequests\ImportSeccionesRequest;
use App\Http\Requests\SeccionRequests\StoreSeccionRequest;
use App\Http\Requests\SeccionRequests\UpdateSeccionRequest;
use App\Http\Resources\SeccionResource;
use App\Models\Examen;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeccionController extends ApiBaseController
{
    public function __construct(
        private SeccionImportHelper $importHelper
    ) {}

    public function index(Request $request)
    {
        $examenId = $request->query('examen');

        $q = Seccion::query();

        if ($examenId !== null && $examenId !== '') {
            $q->where('examen_id', $examenId);
        }

        $q->orderBy('orden_relativo');

        $secciones = $q->get();

        return $this->sendResponse(HTTP::OK, SeccionResource::collection($secciones), 'Secciones recuperadas exitosamente');
    }

    public function show(int $id)
    {
        $seccion = Seccion::find($id);

        if (! $seccion) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        return $this->sendResponse(HTTP::OK, new SeccionResource($seccion), 'Sección recuperada exitosamente');
    }

    public function store(StoreSeccionRequest $request)
    {
        $data = $request->validated();
        $examenId = $request->query('examen');

        $seccion = Seccion::create([
            'nombre' => $data['nombre'],
            'orden_relativo' => $data['orden_relativo'],
            'examen_id' => $examenId,
        ]);

        return $this->sendResponse(HTTP::CREATED, new SeccionResource($seccion), 'Sección creada exitosamente');
    }

    public function update(UpdateSeccionRequest $request, int $id)
    {
        $seccion = Seccion::find($id);

        if (! $seccion) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $data = $request->validated();

        $seccion->fill($data)->save();

        return $this->sendResponse(HTTP::OK, new SeccionResource($seccion), 'Sección actualizada exitosamente');
    }

    public function destroy(int $id)
    {
        $seccion = Seccion::find($id);
        if (! $seccion) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $seccion->delete();

        return $this->sendResponse(HTTP::OK, [], 'Sección eliminada exitosamente');
    }

    public function import(ImportSeccionesRequest $request)
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

            return $this->sendResponse(HTTP::OK, $resultado, 'Importación de secciones completada');
        } catch (\Throwable $e) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }
}
