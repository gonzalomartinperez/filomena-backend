<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Requests\PseudonimosRequests\StorePseudonimoRequest;
use App\Http\Requests\PseudonimosRequests\UpdatePseudonimoRequest;
use App\Http\Resources\PseudonimoResource;
use App\Models\Pseudonimo;
use Illuminate\Http\Request;

class PseudonimoController extends ApiBaseController
{
    public function index(Request $request)
    {
        $examenId = $request->query('examen');

        /* Siempre traemos TODOS los pseudónimos.
        Calculamos "en_uso" como COUNT de asignaciones:
        - Si se pasa ?examen=ID → cuenta solo las del examen dado.
        - Si no se pasa → cuenta global (en cualquier examen).
        - Además, ignoramos usuarios soft-deleted.
        */
        $pseudonimos = Pseudonimo::query()
            ->withCount([
                'estudiantes as en_uso' => function ($q) use ($examenId) {
                    if ($examenId !== null && $examenId !== '') {
                        $q->where('examen_estudiante_pseudonimo.examen_id', (int) $examenId);
                    }
                    $q->whereNull('users.deleted_at');
                },
            ])
            ->orderByRaw('CAST(pseudonimos.nombre AS UNSIGNED) ASC')
            ->orderBy('pseudonimos.nombre', 'asc')
            ->get();

        return $this->sendResponse(HTTP::OK, PseudonimoResource::collection($pseudonimos), 'Pseudónimos recuperados exitosamente');
    }

    public function store(StorePseudonimoRequest $request)
    {
        $data = $request->validated();

        $pseudonimo = Pseudonimo::create($data);

        return $this->sendResponse(HTTP::CREATED, new PseudonimoResource($pseudonimo), 'Pseudónimo creado exitosamente');
    }

    public function show(int $id)
    {
        $pseudonimo = Pseudonimo::find($id);

        if (! $pseudonimo) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        return $this->sendResponse(HTTP::OK, new PseudonimoResource($pseudonimo), 'Pseudónimo recuperado exitosamente');
    }

    public function update(UpdatePseudonimoRequest $request, int $id)
    {
        $pseudonimo = Pseudonimo::find($id);
        if (! $pseudonimo) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $data = $request->validated();

        $pseudonimo->fill($data)->save();

        return $this->sendResponse(HTTP::OK, new PseudonimoResource($pseudonimo), 'Pseudónimo actualizado exitosamente');
    }

    public function destroy(int $id)
    {
        $pseudonimo = Pseudonimo::find($id);
        if (! $pseudonimo) {
            return $this->sendResponse(HTTP::NOT_FOUND, []);
        }

        $pseudonimo->delete();

        return $this->sendResponse(HTTP::OK, [], 'Pseudónimo eliminado exitosamente');
    }
}
