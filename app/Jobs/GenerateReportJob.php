<?php

namespace App\Jobs;

use App\Enums\Colecciones;
use App\Enums\Reportes;
use App\Exports\DetailedCorrectionsExcelExport;
use App\Exports\ExamGradesExcelExport;
use App\Models\Examen;
use App\Services\PseudonimoService;
use App\Services\PuntajeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class GenerateReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public int $timeout = 300;

    public function __construct(
        public int $examId,
        public Reportes $type,
        public string $fileName,
        public string $relativePath,
        public string $disk = 'public',
        public string $scope = 'all',
        public ?int $evaluadorId = null,
        public ?int $estudianteId = null,
    ) {}

    public function uniqueId(): string
    {
        return implode(':', [
            'report',
            $this->type->value,
            $this->examId,
            $this->scope,
            $this->evaluadorId ?? 'null',
            $this->estudianteId ?? 'null',
            $this->disk,
            $this->relativePath,
        ]);
    }

    public function handle(PuntajeService $puntajeService, PseudonimoService $pseudonimoService): void
    {
        $t0 = microtime(true);

        Log::info('[Report][Job] start', [
            'examId' => $this->examId,
            'type' => $this->type->value,
            'scope' => $this->scope,
            'evaluadorId' => $this->evaluadorId,
            'estudianteId' => $this->estudianteId,
            'disk' => $this->disk,
            'path' => $this->relativePath,
        ]);

        $examen = Examen::findOrFail($this->examId);

        $this->ensureDirectory();

        switch ($this->type) {
            case Reportes::PDF_ESTRUCTURA:
                $this->makePdf(
                    view: 'pdf.estructura-examen',
                    data: [
                        'examen' => $examen,
                        'puntajeService' => $puntajeService,
                    ],
                    margins: [20, 18, 22, 18],
                    footerView: 'pdf.footer-examen',
                );
                break;

            case Reportes::PDF_CORRECCIONES:
                $this->makePdf(
                    view: 'pdf.correcciones-examen',
                    data: [
                        'examen' => $examen,
                        'puntajeService' => $puntajeService,
                        'pseudonimoService' => $pseudonimoService,
                        'scope' => $this->scope,
                        'evaluadorId' => $this->evaluadorId,
                    ],
                    margins: [20, 18, 22, 18],
                    footerView: 'pdf.footer-examen',
                );
                break;

            case Reportes::PDF_RESPUESTAS:
                if (! $this->estudianteId) {
                    throw new \InvalidArgumentException('estudianteId es requerido para PDF_RESPUESTAS');
                }

                $this->makePdf(
                    view: 'pdf.respuestas-examen',
                    data: [
                        'examen' => $examen,
                        'estudianteId' => $this->estudianteId,
                        'scope' => 'student',
                    ],
                    margins: [20, 18, 22, 18],
                    footerView: 'pdf.footer-examen',
                );
                break;

            case Reportes::XLSX_NOTAS:
                Excel::store(new ExamGradesExcelExport($examen, $puntajeService, $pseudonimoService), $this->relativePath, $this->disk);
                break;

            case Reportes::XLSX_CORRECCIONES_DETALLADAS:
                Excel::store(new DetailedCorrectionsExcelExport($examen, $puntajeService, $pseudonimoService), $this->relativePath, $this->disk);
                break;
        }

        // Adjuntar a Media Library y limpiar
        $media = $examen->addMediaFromDisk($this->relativePath, $this->disk)
            ->usingFileName($this->fileName)
            ->withCustomProperties([
                'tipo' => $this->type->value,
                'scope' => $this->scope,
                'evaluador_id' => $this->evaluadorId,
                'estudiante_id' => $this->estudianteId,
                'source_path' => $this->relativePath,
                'storage_disk' => $this->disk,
            ])
            ->toMediaCollection(Colecciones::REPORTES->value);

        Storage::disk($this->disk)->delete($this->relativePath);

        Log::info('[Report][Job] done', [
            'examId' => $this->examId,
            'type' => $this->type->value,
            'media_id' => $media->id,
            'elapsed' => round((microtime(true) - $t0) * 1000).'ms',
        ]);
    }

    private function makePdf(
        string $view,
        array $data,
        array $margins = [20, 18, 22, 18], // mm
        ?string $footerView = null,
        string $format = 'a4'
    ): void {
        $builder = Pdf::view($view, $data)
            ->format($format)
            ->disk($this->disk)
            ->margins(...$margins)
            ->withBrowsershot(function (Browsershot $b) {
                $b->noSandbox()
                    ->showBackground()
                    ->timeout(120)
                    ->addChromiumArguments([
                        'disable-dev-shm-usage',
                        'disable-setuid-sandbox',
                        'no-sandbox',
                    ]);
            });

        if (! empty($footerView)) {
            $builder->footerView($footerView);
        }

        $builder->save($this->relativePath);
    }

    private function ensureDirectory(): void
    {
        $dir = \dirname($this->relativePath);
        if ($dir !== '.' && ! Storage::disk($this->disk)->exists($dir)) {
            Storage::disk($this->disk)->makeDirectory($dir);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Report][Job] failed', [
            'examId' => $this->examId,
            'type' => $this->type->value,
            'scope' => $this->scope,
            'evaluadorId' => $this->evaluadorId,
            'estudianteId' => $this->estudianteId,
            'path' => $this->relativePath,
            'error' => $e->getMessage(),
        ]);
    }
}
