<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Helpers\CasoImportHelper;
use App\Http\Requests\CasoRequests\ImportCasosRequest;
use App\Http\Requests\CasoRequests\StoreCasoRequest;
use App\Http\Requests\CasoRequests\UpdateCasoRequest;
use App\Http\Resources\CasoResource;
use App\Models\Caso;
use App\Models\Examen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasoController extends ApiBaseController
{
    public function __construct(
        private CasoImportHelper $importHelper
    ) {}

    public function index(Request $request)
    {
        $examenId = $request->query('examen');
        $evaluadorId = $request->query('evaluador');

        $q = Caso::query()->with('evaluador');

        if ($examenId !== null && $examenId !== '') {
            $q->where('examen_id', $examenId);
        }

        if ($evaluadorId !== null && $evaluadorId !== '') {
            $q->where('evaluador_id', $evaluadorId);
        }

        $q->orderBy('orden_relativo');

        $casos = $q->get();

        return $this->sendResponse(HTTP::OK, CasoResource::collection($casos), 'Casos recuperados exitosamente');
    }

    public function show($id)
    {
        $caso = Caso::find($id);

        if (! $caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        return $this->sendResponse(HTTP::OK, new CasoResource($caso), 'Caso recuperado exitosamente');
    }

    public function store(StoreCasoRequest $request)
    {
        $data = $request->validated();
        $examenId = $request->query('examen');

        $caso = Caso::create([
            'titulo' => $data['titulo'],
            'orden_relativo' => $data['orden_relativo'],
            'examen_id' => $examenId,
            'evaluador_id' => $data['evaluador_id'],
        ]);

        return $this->sendResponse(HTTP::CREATED, new CasoResource($caso), 'Casos creado exitosamente');
    }

    public function update(UpdateCasoRequest $request, $id)
    {
        $caso = Caso::find($id);
        if (! $caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $data = $request->validated();

        $caso->fill($data)->save();

        return $this->sendResponse(HTTP::OK, new CasoResource($caso), 'Caso actualizado exitosamente');
    }

    public function destroy($id)
    {
        $caso = Caso::find($id);
        if (! $caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $caso->delete();

        return $this->sendResponse(HTTP::OK, [], 'Caso eliminado exitosamente');
    }

    public function import(ImportCasosRequest $request)
    {
        $archivo = $request->file('archivo');
        $examenId = (int) $request->query('examen');

        try {
            // 1) Estandarizacion del archivo
            $rows = $this->importHelper->parse($archivo);

            // 2) Examen validado
            $examen = Examen::find($examenId);

            // 3) Transacción: todo o nada
            $resultado = DB::transaction(function () use ($rows, $examen) {
                return $this->importHelper->persist($rows, $examen);
            });

            return $this->sendResponse(HTTP::OK, $resultado, 'Importación de casos completada');
        } catch (\Throwable $e) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }
}
