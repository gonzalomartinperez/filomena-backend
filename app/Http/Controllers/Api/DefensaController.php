<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Requests\DefensaRequests\StoreNotaDefensaRequest;
use App\Http\Resources\EstadoExamenEstudianteResource;
use App\Http\Resources\NotaDefensaResource;
use App\Models\Caso;
use App\Models\EstadoExamenEstudiante;
use App\Models\Examen;
use App\Models\NotaDefensa;
use App\Services\PseudonimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;

class DefensaController extends ApiBaseController
{
    public function __construct(
        private readonly PseudonimoService $pseudonimoService
    ) {}

    /**
     * Devuelve las notas de defensa por sección para el estudiante y caso indicados.
     */
    public function index(Request $request)
    {
        $casoId = (int) $request->query('caso', 0);
        $pseudonimo = (string) $request->query('pseudonimo', '');

        if ($casoId <= 0 || $pseudonimo === '') {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], 'Parámetros "caso" y "pseudonimo" son requeridos');
        }

        $caso = Caso::query()->with('examen')->find($casoId);
        if (! $caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Caso no encontrado');
        }

        $examen = $caso->examen ?? Examen::query()->find($caso->examen_id);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado para el caso indicado');
        }

        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado para el pseudónimo indicado');
        }

        $notas = NotaDefensa::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('caso_id', $caso->id)
            ->orderBy('seccion_id')
            ->get();

        return $this->sendResponse(
            HTTP::OK,
            NotaDefensaResource::collection($notas),
            'Notas de defensa recuperadas correctamente'
        );
    }

    public function upsert(StoreNotaDefensaRequest $request)
    {
        $casoId = (int) ($request->query('caso') ?? 0);
        $pseudonimo = (string) ($request->query('pseudonimo') ?? '');

        if ($casoId <= 0 || $pseudonimo === '') {
            return $this->sendResponse(
                HTTP::UNPROCESSABLE_ENTITY,
                [],
                'Los parámetros caso y pseudónimo son requeridos.'
            );
        }

        $caso = Caso::query()->with('examen')->find($casoId);
        if (! $caso) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Caso no encontrado');
        }

        $examen = $caso->examen ?? Examen::query()->find($caso->examen_id);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado para el pseudónimo indicado');
        }

        // Validación de negocio con contexto (secciones válidas + tope de defensa)
        // Si algo falla, lanza ValidationException con los campos de 'defensas.*'
        $request->validateBusinessWithContext($caso, $estudiante);

        // 3) Persistencia: sin cambios en la lógica (upsert por estudiante/caso/sección)
        $payload = $request->validated();
        $items = (array) ($payload['defensas'] ?? []);

        if (empty($items)) {
            return $this->sendResponse(
                HTTP::UNPROCESSABLE_ENTITY,
                [],
                'No se recibieron defensas para procesar'
            );
        }

        $notas = [];
        LogBatch::withinBatch(fn () => DB::transaction(function () use ($items, $estudiante, $caso, &$notas) {
            foreach ($items as $row) {
                $sid = (int) ($row['seccion_id']);
                $nota = (float) ($row['nota']);

                $nd = NotaDefensa::query()->firstOrNew([
                    'estudiante_id' => $estudiante->id,
                    'caso_id' => $caso->id,
                    'seccion_id' => $sid,
                ]);

                $nd->nota = $nota;
                $nd->save();

                $notas[] = $nd;
            }
        }));

        return $this->sendResponse(
            HTTP::OK,
            NotaDefensaResource::collection(collect($notas)),
            'Defensas actualizadas correctamente'
        );
    }

    public function marcarEstudianteDefendido(Request $request)
    {
        $examenId = (int) $request->query('examen') ?? 0;

        $pseudonimo = (string) $request->query('pseudonimo') ?? '';

        if ($examenId <= 0 || $pseudonimo === '') {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], 'Parámetros "examen" y "pseudonimo" son requeridos');
        }

        $examen = Examen::query()->find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $estudiante = $this->pseudonimoService->getEstudianteByPseudonimo($examen, $pseudonimo);
        if (! $estudiante) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Estudiante no encontrado para el pseudónimo indicado');
        }

        $estado = EstadoExamenEstudiante::query()->firstOrCreate(
            ['examen_id' => $examen->id, 'estudiante_id' => $estudiante->id],
            ['defendido' => true]
        );

        if (! $estado->defendido) {
            $estado->defendido = true;
            $estado->save();
        }

        return $this->sendResponse(
            HTTP::OK,
            new EstadoExamenEstudianteResource($estado),
            'Estudiante marcado como defendido'
        );
    }
}
