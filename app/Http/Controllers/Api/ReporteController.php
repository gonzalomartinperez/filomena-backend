<?php

namespace App\Http\Controllers\Api;

use App\Enums\Colecciones;
use App\Enums\HTTP;
use App\Enums\Reportes;
use App\Enums\Roles;
use App\Http\Resources\FileResource;
use App\Jobs\GenerateReportJob;
use App\Models\Examen;
use App\Services\PseudonimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReporteController extends ApiBaseController
{
    public function __construct(
        private readonly PseudonimoService $pseudonimoService
    ) {}

    public function pdfEstructura(Request $request, int $examenId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(Roles::ADMINISTRADOR->value)) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'Solo administradores pueden generar este PDF.');
        }

        $examen = Examen::find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado.');
        }

        $codigo = $examen->codigo ?? $examen->id;
        $fileName = "estructura-examen-{$codigo}.pdf";
        $relativePath = "reports/{$fileName}";
        $force = $request->boolean('force');

        return $this->respondExistingOrQueue(
            $examen, $fileName, $relativePath, $force,
            fn () => GenerateReportJob::dispatch(
                examId: $examen->id,
                type: Reportes::PDF_ESTRUCTURA,
                fileName: $fileName,
                relativePath: $relativePath,
                disk: 'public',
            )
        );
    }

    public function pdfCorrecciones(Request $request, int $examenId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole([Roles::ADMINISTRADOR->value, Roles::EVALUADOR->value])) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No autorizado para generar este PDF.');
        }

        $examen = Examen::with('casos')->find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado.');
        }

        $scope = 'all';
        $evaluadorId = null;

        if ($user->hasRole(Roles::EVALUADOR->value)) {
            $tieneCasos = $examen->casos->where('evaluador_id', $user->id)->isNotEmpty();
            if (! $tieneCasos) {
                return $this->sendResponse(HTTP::FORBIDDEN, [], 'No posee casos asignados en este examen.');
            }
            $scope = 'evaluator';
            $evaluadorId = $user->id;
        }

        $codigo = $examen->codigo ?? $examen->id;
        $fileName = $scope === 'all'
            ? "correcciones-examen-{$codigo}.pdf"
            : "correcciones-examen-{$codigo}-evaluador-{$evaluadorId}.pdf";
        $relativePath = "reports/{$fileName}";
        $force = $request->boolean('force');

        return $this->respondExistingOrQueue(
            $examen, $fileName, $relativePath, $force,
            fn () => GenerateReportJob::dispatch(
                examId: $examen->id,
                type: Reportes::PDF_CORRECCIONES,
                fileName: $fileName,
                relativePath: $relativePath,
                disk: 'public',
                scope: $scope,
                evaluadorId: $evaluadorId,
            )
        );
    }

    public function excelNotas(Request $request, int $examenId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(Roles::ADMINISTRADOR->value)) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'Solo administradores pueden exportar este Excel.');
        }

        $examen = Examen::find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado.');
        }

        $codigo = $examen->codigo ?? $examen->id;
        $fileName = "notas-examen-{$codigo}.xlsx";
        $relativePath = "reports/{$fileName}";
        $force = $request->boolean('force');

        return $this->respondExistingOrQueue(
            $examen, $fileName, $relativePath, $force,
            fn () => GenerateReportJob::dispatch(
                examId: $examen->id,
                type: Reportes::XLSX_NOTAS,
                fileName: $fileName,
                relativePath: $relativePath,
                disk: 'public',
            )
        );
    }

    public function excelCorreccionesDetalladas(Request $request, int $examenId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(Roles::ADMINISTRADOR->value)) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'Solo administradores pueden exportar este Excel.');
        }

        $examen = Examen::find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado.');
        }

        $codigo = $examen->codigo ?? $examen->id;
        $fileName = "correcciones-detalladas-examen-{$codigo}.xlsx";
        $relativePath = "reports/{$fileName}";
        $force = $request->boolean('force');

        return $this->respondExistingOrQueue(
            $examen, $fileName, $relativePath, $force,
            fn () => GenerateReportJob::dispatch(
                examId: $examen->id,
                type: Reportes::XLSX_CORRECCIONES_DETALLADAS,
                fileName: $fileName,
                relativePath: $relativePath,
                disk: 'public',
            )
        );
    }

    public function pdfRespuestas(Request $request, int $examenId)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(Roles::ESTUDIANTE->value)) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No autorizado para generar este PDF.');
        }

        $examen = Examen::with('estudiantes')->find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado.');
        }

        $estaInscripto = $examen->estudiantes->contains('id', $user->id);
        if (! $estaInscripto) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No estás inscripto en este examen.');
        }

        $codigo = $examen->codigo ?? $examen->id;

        $pseudonimo = $this->pseudonimoService->pseudonimoParaExamen($user, $examen);
        $fileName = "respuestas-examen-{$codigo}-estudiante-{$pseudonimo}.pdf";

        $relativePath = "reports/{$fileName}";
        $force = $request->boolean('force');

        return $this->respondExistingOrQueue(
            $examen, $fileName, $relativePath, $force,
            fn () => GenerateReportJob::dispatch(
                examId: $examen->id,
                type: Reportes::PDF_RESPUESTAS,
                fileName: $fileName,
                relativePath: $relativePath,
                disk: 'public',
                scope: 'student',
                evaluadorId: null,
                estudianteId: $user->id,
            )
        );
    }

    /**
     * Helper homogéneo:
     * - Si existe media y no se pide `force`, devuelve 200 con el file.
     * - Si `force`, elimina TODAS las medias con ese file_name y también el archivo en storage si existe.
     * - Si hay archivo en storage pero no media (y no hay force), lo adjunta y devuelve 200.
     * - Caso contrario, encola el job y devuelve 202.
     */
    private function respondExistingOrQueue(
        Examen $examen,
        string $fileName,
        string $relativePath,
        bool $force,
        callable $dispatch
    ) {
        $collection = Colecciones::REPORTES->value;

        $medias = $examen->getMedia($collection)
            ->filter(fn ($m) => $m->file_name === $fileName)
            ->values();

        if ($medias->isNotEmpty() && ! $force) {
            return $this->sendResponse(HTTP::OK, new FileResource($medias->first()), 'Reporte disponible.');
        }

        if ($force) {
            $medias->each->delete();

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        } else {
            if ($medias->isEmpty() && Storage::disk('public')->exists($relativePath)) {
                $attached = $examen->addMediaFromDisk($relativePath, 'public')
                    ->usingFileName($fileName)
                    ->toMediaCollection($collection);

                return $this->sendResponse(HTTP::OK, new FileResource($attached), 'Reporte disponible.');
            }
        }

        $dispatch();

        return $this->sendResponse(
            HTTP::ACCEPTED,
            [
                'status' => 'queued',
                'file_name' => $fileName,
                'path' => $relativePath,
                'collection' => $collection,
            ],
            'Generación de reporte en curso.'
        );
    }
}
