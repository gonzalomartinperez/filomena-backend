<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Helpers\PreguntaImportHelper;
use App\Http\Requests\PreguntaRequests\ImportPreguntasRequest;
use App\Http\Requests\PreguntaRequests\StorePreguntaRequest;
use App\Http\Requests\PreguntaRequests\UpdatePreguntaRequest;
use App\Http\Resources\PreguntaResource;
use App\Models\Caso;
use App\Models\Examen;
use App\Models\Pregunta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreguntaController extends ApiBaseController
{
    public function __construct(
        private PreguntaImportHelper $importHelper
    ) {}

    public function index(Request $request)
    {
        $examenId = $request->query('examen');
        $casoId = $request->query('caso');

        $q = Pregunta::with('caso');

        if ($examenId) {
            $q->where('examen_id', $examenId);
        }

        if ($casoId) {
            $q->where('caso_id', $casoId);
        }

        // Orden: primero por el orden del caso, luego por el orden de la pregunta
        $q->orderBy(
            Caso::select('orden_relativo')
                ->whereColumn('casos.id', 'preguntas.caso_id')
        )->orderBy('preguntas.orden_relativo');

        $preguntas = $q->get();

        return $this->sendResponse(
            HTTP::OK,
            PreguntaResource::collection($preguntas),
            'Preguntas recuperadas exitosamente'
        );
    }

    public function show($id)
    {
        $pregunta = Pregunta::find($id);

        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        return $this->sendResponse(HTTP::OK, new PreguntaResource($pregunta), 'Pregunta recuperada exitosamente');
    }

    public function store(StorePreguntaRequest $request)
    {
        $data = $request->validated();
        $examenId = $request->query('examen');

        $pregunta = Pregunta::create([
            'consigna' => $data['consigna'],
            'descripcion' => $data['descripcion'],
            'orden_relativo' => $data['orden_relativo'],
            'caso_id' => $data['caso_id'],
            'examen_id' => $examenId,
        ]);

        return $this->sendResponse(HTTP::CREATED, new PreguntaResource($pregunta), 'Pregunta creada exitosamente');
    }

    public function update(UpdatePreguntaRequest $request, $id)
    {
        $pregunta = Pregunta::find($id);
        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $data = $request->validated();

        $pregunta->fill($data)->save();

        return $this->sendResponse(HTTP::OK, new PreguntaResource($pregunta), 'Pregunta actualizada exitosamente');
    }

    public function destroy($id)
    {
        $pregunta = Pregunta::find($id);
        if (! $pregunta) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $pregunta->delete();

        return $this->sendResponse(HTTP::OK, [], 'Pregunta eliminada exitosamente');
    }

    public function import(ImportPreguntasRequest $request)
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

            return $this->sendResponse(HTTP::OK, $resultado, 'Importación de preguntas completada');
        } catch (\Throwable $e) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }
}
