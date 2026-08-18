<?php

namespace App\Exports;

use App\Models\Examen;
use App\Models\Seccion;
use App\Models\User;
use App\Services\PseudonimoService;
use App\Services\PuntajeService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExamGradesExcelExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithProperties, WithStrictNullComparison, WithStyles
{
    /** Filas fijas del layout superior */
    private const ROW_TITLE = 1;

    private const ROW_EXAM = 2;

    private const ROW_DATE = 3;

    private const ROW_META = 4;

    private const ROW_HEADER_TOP = 6;

    private const ROW_HEADER_SUB = 7;

    private const ROW_DATA_START = 8;

    /** Paleta (medical 8) */
    private const COLOR_ACCENT_DARK = '095d7e';

    private const COLOR_TEAL = '14967f';

    private const COLOR_SOFT_GREEN = 'e2fcd6';

    private const COLOR_SOFT_BLUE = 'ccecee';

    private const COLOR_SOFT_MINT = 'f1f9ff';

    private const COLOR_STRIP_A = 'FFFFFF';

    private const COLOR_STRIP_B = 'f7fafc';

    /** Formatos */
    private const PCT_FMT = NumberFormat::FORMAT_PERCENTAGE_00;

    private const NUM_FMT = NumberFormat::FORMAT_NUMBER_00;

    /** Celdas de parámetros editables (coordenadas dinámicas) */
    private ?string $cellTotalBruto = null;

    private ?string $cellTotalEvaluado = null;

    private ?string $cellUmbralAprob = null;

    /** Punto de inicio de la tabla (bajo headers dobles) */
    public function startCell(): string
    {
        return 'A'.self::ROW_HEADER_TOP;
    }

    // ── Dependencias y contexto ────────────────────────────────────────────────
    private Examen $examen;

    private PuntajeService $puntajeService;

    private PseudonimoService $pseudonimoService;

    /** @var Collection<User> */
    private Collection $estudiantes;

    /** @var Collection<Seccion> Secciones presentes (TC) */
    private Collection $sections;

    /**
     * Plan de columnas. Estructura:
     * [
     *   ['title' => 'CASO: X', 'kind' => 'case', 'cols' => [ ['label'=>'Anamnesis','type'=>'raw',meta..], ['label'=>'TOTAL','type'=>'total_case',..], ['label'=>'%','type'=>'pct_case',..] ]],
     *   ['title' => 'SECCIONES', 'kind' => 'sections', 'cols' => [ ['label'=>'Sección (Total)','type'=>'sum_section',..], ['label'=>'Sección (%)','type'=>'pct_section',..] ]],
     *   ['title' => 'TOTALES', 'kind' => 'totals', 'cols' => [ ['label'=>'TOTAL','type'=>'total_raw'], ['label'=>'% TOTAL','type'=>'pct_total'], ['label'=>'% CORREGIDO','type'=>'pct_corregido'], ['label'=>'ESTADO','type'=>'estado'], ['label'=>'NOTA','type'=>'nota'] ]]
     * ]
     *
     * @var array<int,array<string,mixed>>
     */
    private array $columnPlan = [];

    /** Letras de columnas útiles en TOTALES */
    private array $colLetters = []; // total, pct_total, pct_corregido, estado, nota

    /** Rango de header de TOTALES (para estilos) */
    private string $totalsHeaderRange = 'G6:J7';

    /** Columnas por tipo (para formato y stats) */
    private array $percentColLetters = [];

    private array $numberColLetters = [];

    private array $excludeStatLetters = [];

    /** Denominadores (máximos) */
    private array $maxSeccionGlobalTC = [];

    private array $maxCaseTotalTC = [];

    public function __construct(
        Examen $examen,
        PuntajeService $puntajeService,
        PseudonimoService $pseudonimoService
    ) {
        $this->examen = $examen->loadMissing('casos.preguntas.items.seccion', 'estudiantes');
        $this->puntajeService = $puntajeService;
        $this->pseudonimoService = $pseudonimoService;

        $this->estudiantes = $this->examen->estudiantes;

        // 1) Secciones presentes en tablas de cotejo (para armar columnas)
        $sec = collect();
        foreach ($this->examen->casos as $caso) {
            foreach ($caso->preguntas as $preg) {
                foreach ($preg->items as $it) {
                    if ($it->tabla_cotejo_id && $it->seccion) {
                        $sec->push($it->seccion);
                    }
                }
            }
        }
        $this->sections = $sec->unique('id')->sortBy('orden_relativo')->values();

        // 2) Denominadores (máximos por sección global y por caso)
        foreach ($this->sections as $s) {
            $this->maxSeccionGlobalTC[$s->id] = (float) $this->puntajeService->maxSeccion($s);
        }
        foreach ($this->examen->casos as $c) {
            $this->maxCaseTotalTC[$c->id] = (float) $this->puntajeService->maxCaso($c);
        }

        // 3) Estructura de columnas
        $this->buildColumnPlan();
    }

    /**
     * Arma el layout dinámico:
     * CASO–SECCIÓN → CASO: TOTAL/% → SECCIONES: (Total/%) → TOTALES.
     */
    private function buildColumnPlan(): void
    {
        $plan = [];
        $colIdx = 3; // C

        // Bloques por CASO (secciones con max>0, TOTAL del caso con defensas, % del caso contra max items-only)
        foreach ($this->examen->casos as $caso) {
            $cols = [];

            $seccionesCaso = [];
            foreach ($this->sections as $s) {
                $m = $this->puntajeService->maxCasoSeccion($caso, $s);
                if ($m > 0) {
                    $seccionesCaso[] = $s;
                }
            }

            foreach ($seccionesCaso as $s) {
                $letter = Coordinate::stringFromColumnIndex($colIdx);
                $cols[] = ['label' => $s->nombre, 'type' => 'raw', 'meta' => ['case_id' => $caso->id, 'seccion_id' => $s->id]];
                $this->numberColLetters[] = $letter;
                $colIdx++;
            }

            $totalColLetter = Coordinate::stringFromColumnIndex($colIdx);
            $cols[] = ['label' => 'TOTAL', 'type' => 'total_case', 'meta' => ['case_id' => $caso->id, 'col_letter' => $totalColLetter]];
            $this->numberColLetters[] = $totalColLetter;
            $colIdx++;

            $pcColLetter = Coordinate::stringFromColumnIndex($colIdx);
            $cols[] = ['label' => '%', 'type' => 'pct_case', 'meta' => ['case_id' => $caso->id, 'den_col' => $totalColLetter]];
            $this->percentColLetters[] = $pcColLetter;
            $colIdx++;

            $plan[] = ['title' => 'CASO: '.$caso->titulo, 'kind' => 'case', 'cols' => $cols];
        }

        // Bloque SECCIONES (agregado global por examen)
        $cols = [];
        foreach ($this->sections as $s) {
            $br = Coordinate::stringFromColumnIndex($colIdx);
            $cols[] = ['label' => $s->nombre.' (TOTAL)', 'type' => 'sum_section', 'meta' => ['seccion_id' => $s->id, 'col_letter' => $br, 'title' => $s->nombre]];
            $this->numberColLetters[] = $br;
            $colIdx++;

            $pc = Coordinate::stringFromColumnIndex($colIdx);
            $cols[] = ['label' => $s->nombre.' (%)', 'type' => 'pct_section', 'meta' => ['seccion_id' => $s->id, 'den_col' => $br, 'title' => $s->nombre]];
            $this->percentColLetters[] = $pc;
            $colIdx++;
        }
        $plan[] = ['title' => 'SECCIONES', 'kind' => 'sections', 'cols' => $cols];

        // Bloque TOTALES
        $start = Coordinate::stringFromColumnIndex($colIdx);
        $cols = [
            ['label' => 'TOTAL',       'type' => 'total_raw'],
            ['label' => '% TOTAL',     'type' => 'pct_total'],
            ['label' => '% CORREGIDO', 'type' => 'pct_corregido'],
            ['label' => 'ESTADO',      'type' => 'estado'],
            ['label' => 'NOTA',        'type' => 'nota'],
        ];
        $plan[] = ['title' => 'TOTALES', 'kind' => 'totals', 'cols' => $cols];

        $this->colLetters['total'] = Coordinate::stringFromColumnIndex($colIdx);
        $this->numberColLetters[] = $this->colLetters['total'];
        $colIdx++;
        $this->colLetters['pct_total'] = Coordinate::stringFromColumnIndex($colIdx);
        $this->percentColLetters[] = $this->colLetters['pct_total'];
        $colIdx++;
        $this->colLetters['pct_corregido'] = Coordinate::stringFromColumnIndex($colIdx);
        $this->percentColLetters[] = $this->colLetters['pct_corregido'];
        $colIdx++;
        $this->colLetters['estado'] = Coordinate::stringFromColumnIndex($colIdx);
        $this->excludeStatLetters[] = $this->colLetters['estado'];
        $colIdx++;
        $this->colLetters['nota'] = Coordinate::stringFromColumnIndex($colIdx);
        $this->numberColLetters[] = $this->colLetters['nota'];

        $end = Coordinate::stringFromColumnIndex($colIdx);
        $this->totalsHeaderRange = "{$start}".self::ROW_HEADER_TOP.":{$end}".self::ROW_HEADER_SUB;

        $this->columnPlan = $plan;
    }

    /** Puntaje sugerido = maxPuntaje - suma(valor ítems con acierto < 10%) */
    private function computePuntajeSugerido(): float
    {
        $this->examen->loadMissing('casos.preguntas.items', 'estudiantes');

        $max = $this->puntajeService->maxExamen($this->examen);
        $students = $this->examen->estudiantes;
        $n = max(1, $students->count());
        $sub10 = 0.0;

        foreach ($this->examen->casos as $caso) {
            foreach ($caso->preguntas as $preg) {
                foreach ($preg->items as $item) {
                    $pos = 0;
                    foreach ($students as $alumno) {
                        if ($this->puntajeService->puntajeObtenidoItem($item, $alumno) > 0) {
                            $pos++;
                        }
                    }
                    $rate = $pos / $n;
                    if ($rate < 0.10) {
                        $sub10 += (float) $this->puntajeService->maxItem($item);
                    }
                }
            }
        }

        return max(0.0, $max - $sub10);
    }

    /** Propiedades del documento */
    public function properties(): array
    {
        return ['creator' => 'Filomena', 'title' => "Notas V2 - {$this->examen->nombre}"];
    }

    /** Filas = estudiantes del examen */
    public function collection(): Collection
    {
        return $this->estudiantes;
    }

    /** Encabezados en dos filas (bloques y subetiquetas) */
    public function headings(): array
    {
        $row1 = ['Nombre y apellido', 'Pseudónimo'];
        $row2 = ['', ''];
        foreach ($this->columnPlan as $block) {
            $row1[] = $block['title'];
            for ($i = 1; $i < count($block['cols']); $i++) {
                $row1[] = '';
            }
            foreach ($block['cols'] as $col) {
                $row2[] = $col['label'];
            }
        }

        return [$row1, $row2];
    }

    /**
     * Mapea un estudiante → fila de datos.
     * - Cols por caso/sección: solo ítems TC.
     * - TOTAL de caso: ítems + defensa (service).
     * - Acumula por sección para el bloque SECCIONES.
     */
    public function map($estudiante): array
    {
        $fila = [
            "{$estudiante->apellido}, {$estudiante->nombre}",
            $this->pseudonimoService->pseudonimoParaExamen($estudiante, $this->examen) ?: '—',
        ];

        $sectionAcc = [];
        foreach ($this->sections as $s) {
            $sectionAcc[$s->id] = 0.0;
        }

        $totalConDefensas = 0.0;

        foreach ($this->columnPlan as $block) {
            if ($block['kind'] === 'case') {
                // Secciones del caso
                foreach ($block['cols'] as $col) {
                    if (($col['type'] ?? '') === 'raw') {
                        $caseId = $col['meta']['case_id'];
                        $secId = $col['meta']['seccion_id'];
                        $caso = $this->examen->casos->firstWhere('id', $caseId);
                        $seccion = $this->sections->firstWhere('id', $secId);
                        $v = $this->puntajeService->puntajeObtenidoCasoSeccion($caso, $seccion, $estudiante);
                        $v = $v ?: 0;
                        $fila[] = $v;
                        $sectionAcc[$secId] += $v;
                    }
                }

                // TOTAL del caso
                $caseId = $block['cols'][0]['meta']['case_id'] ?? null;
                $caso = $this->examen->casos->firstWhere('id', $caseId);
                $sumCaseConDef = $this->puntajeService->puntajeObtenidoCaso($caso, $estudiante);
                $fila[] = $sumCaseConDef;
                $fila[] = 0; // % (se setea en AfterSheet)
                $totalConDefensas += $sumCaseConDef;

            } elseif ($block['kind'] === 'sections') {
                foreach ($block['cols'] as $col) {
                    if ($col['type'] === 'sum_section') {
                        $fila[] = $sectionAcc[$col['meta']['seccion_id']] ?? 0;
                    } elseif ($col['type'] === 'pct_section') {
                        $fila[] = 0; // fórmula en AfterSheet
                    }
                }
            } elseif ($block['kind'] === 'totals') {
                $fila[] = $totalConDefensas; // TOTAL global del estudiante
                $fila[] = 0;                 // % TOTAL
                $fila[] = 0;                 // % CORREGIDO
                $fila[] = '';                // ESTADO
                $fila[] = 0;                 // NOTA
            }
        }

        // Ceros explícitos
        foreach ($fila as &$v) {
            if ($v === null || $v === '') {
                $v = 0;
            }
        } unset($v);

        return $fila;
    }

    /** Estilos base (metadata, headers y alineaciones) */
    public function styles(Worksheet $sheet): array
    {
        // Metadata: jerarquía tipográfica
        $sheet->mergeCells('A'.self::ROW_TITLE.':'.$sheet->getHighestColumn().self::ROW_TITLE);
        $sheet->mergeCells('A'.self::ROW_EXAM.':'.$sheet->getHighestColumn().self::ROW_EXAM);
        $sheet->mergeCells('A'.self::ROW_DATE.':'.$sheet->getHighestColumn().self::ROW_DATE);
        $sheet->mergeCells('A'.self::ROW_META.':'.$sheet->getHighestColumn().self::ROW_META);

        $sheet->getStyle('A'.self::ROW_TITLE)->getFont()->setName('Segoe UI')->setSize(18)->setBold(true)
            ->getColor()->setRGB(self::COLOR_ACCENT_DARK);
        $sheet->getStyle('A'.self::ROW_EXAM)->getFont()->setName('Segoe UI')->setSize(13)->setBold(true);
        $sheet->getStyle('A'.self::ROW_DATE)->getFont()->setName('Segoe UI')->setSize(11)->setItalic(true);
        $sheet->getStyle('A'.self::ROW_META)->getFont()->setName('Segoe UI')->setSize(11);

        // Headers dobles
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle('A'.self::ROW_HEADER_TOP.':'.$lastCol.self::ROW_HEADER_SUB)->getFont()->setBold(true);
        $sheet->getStyle('A'.self::ROW_HEADER_TOP.':'.$lastCol.self::ROW_HEADER_SUB)
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // A (Nombre y apellido) a izquierda; desde B.. centrado
        $sheet->getStyle('A'.self::ROW_HEADER_TOP.':A'.self::ROW_HEADER_SUB)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B'.self::ROW_HEADER_TOP.':'.$lastCol.self::ROW_HEADER_SUB)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function columnFormats(): array
    {
        return [];
    }

    /** Post-procesado: merges, colores, fórmulas y estadísticas */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function ($event) {
                $s = $event->sheet->getDelegate();
                $lastCol = $s->getHighestColumn();

                // Columna inmediatamente después del contenido actual
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);
                $labelColIndex = $lastColIndex + 1;   // columna de textos ("TOTAL BRUTO", etc.)
                $valueColIndex = $lastColIndex + 2;   // columna de valores

                $labelCol = Coordinate::stringFromColumnIndex($labelColIndex);
                $valueCol = Coordinate::stringFromColumnIndex($valueColIndex);

                // Filas donde irán los parámetros (aprovechamos las filas de header superior)
                $rowTotalBruto = self::ROW_EXAM;       // 2
                $rowTotalEvaluado = self::ROW_EXAM + 1;   // 3
                $rowUmbralAprob = self::ROW_EXAM + 2;   // 4

                // Guardamos las coordenadas para poder usarlas en fórmulas luego
                $this->cellTotalBruto = $valueCol.$rowTotalBruto;
                $this->cellTotalEvaluado = $valueCol.$rowTotalEvaluado;
                $this->cellUmbralAprob = $valueCol.$rowUmbralAprob;

                // ── Metadata y parámetros editables ───────────────────────────
                $s->setCellValue('A'.self::ROW_TITLE, 'Filomena - Notas');
                $s->setCellValue('A'.self::ROW_EXAM, 'Examen: '.$this->examen->nombre.' ('.$this->examen->codigo.')');
                $s->setCellValue('A'.self::ROW_DATE, 'Fecha: '.optional($this->examen->fecha)->format('d/m/Y'));

                $estudiantesCount = $this->examen->estudiantes->count();
                $casosCount = $this->examen->casos->count();
                $seccionesCount = $this->examen->secciones()->count();
                $max = $this->puntajeService->maxExamen($this->examen);
                $sugerido = $this->computePuntajeSugerido();

                $metaLine1 = 'Estudiantes: '.$estudiantesCount.'  |  Casos: '.$casosCount.'  |  Secciones: '.$seccionesCount;
                $metaLine2 = 'Puntaje máximo: '.$max.'  |  Puntaje sugerido: '.$sugerido.' (max - ítems <10%)';

                $metaText = $metaLine1."\n".$metaLine2;

                $s->setCellValue('A'.self::ROW_META, $metaText);
                $s->getStyle('A'.self::ROW_META)->getAlignment()->setWrapText(true);

                // Altura explícita (autofit no funciona con celdas mergeadas)
                $lines = substr_count($metaText, "\n") + 1;
                $base = $s->getDefaultRowDimension()->getRowHeight();
                $base = ($base > 0) ? $base : 15; // default Excel
                $pad = 2; // pequeño margen entre líneas
                $s->getRowDimension(self::ROW_META)->setRowHeight($lines * ($base + $pad));

                $totalBruto = array_sum($this->maxSeccionGlobalTC);

                // Asegurarnos de que las coordenadas dinámicas estén calculadas
                $labelCol = $labelCol ?? Coordinate::stringFromColumnIndex(
                    Coordinate::columnIndexFromString($lastCol) + 1
                );
                $valueCol = $valueCol ?? Coordinate::stringFromColumnIndex(
                    Coordinate::columnIndexFromString($lastCol) + 2
                );

                $rowTotalBruto = self::ROW_EXAM;       // 2
                $rowTotalEvaluado = self::ROW_EXAM + 1;   // 3
                $rowUmbralAprob = self::ROW_EXAM + 2;   // 4

                // Por si el callback se ejecuta en otro orden, garantizamos las propiedades
                $this->cellTotalBruto ??= $valueCol.$rowTotalBruto;
                $this->cellTotalEvaluado ??= $valueCol.$rowTotalEvaluado;
                $this->cellUmbralAprob ??= $valueCol.$rowUmbralAprob;

                // Textos
                $s->setCellValue("{$labelCol}{$rowTotalBruto}", 'TOTAL BRUTO');
                $s->setCellValue("{$labelCol}{$rowTotalEvaluado}", 'TOTAL EVALUADO');
                $s->setCellValue("{$labelCol}{$rowUmbralAprob}", 'UMBRAL APROB.');

                // Ajustar automáticamente el ancho de la columna de etiquetas
                $s->getColumnDimension($labelCol)->setAutoSize(true);

                // Valores
                $s->setCellValue($this->cellTotalBruto, $totalBruto);
                $s->setCellValue($this->cellTotalEvaluado, $totalBruto);
                $s->setCellValue($this->cellUmbralAprob, 0.60);

                // Formato del umbral como porcentaje
                $s->getStyle($this->cellUmbralAprob)
                    ->getNumberFormat()->setFormatCode(self::PCT_FMT);

                // Centrar los valores (TOTAL BRUTO / TOTAL EVALUADO / UMBRAL)
                $valuesRange = $this->cellTotalBruto.':'.$this->cellUmbralAprob;
                $s->getStyle($valuesRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Estilos de texto y fondo, dinámicos
                $s->getStyle("{$labelCol}{$rowTotalBruto}:{$labelCol}{$rowUmbralAprob}")
                    ->getFont()->setBold(true);

                $s->getStyle($this->cellTotalBruto.':'.$this->cellTotalEvaluado)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_MINT);

                // ── Encabezados por bloque (merge + fondo) y separadores ─────
                $colIdx = 3;
                foreach ($this->columnPlan as $block) {
                    $start = Coordinate::stringFromColumnIndex($colIdx);
                    $end = Coordinate::stringFromColumnIndex($colIdx + count($block['cols']) - 1);

                    $s->mergeCells("{$start}".self::ROW_HEADER_TOP.":{$end}".self::ROW_HEADER_TOP);

                    $fill = ($block['kind'] === 'case') ? self::COLOR_SOFT_GREEN :
                            (($block['kind'] === 'sections') ? self::COLOR_SOFT_BLUE : self::COLOR_SOFT_MINT);
                    $s->getStyle("{$start}".self::ROW_HEADER_TOP.":{$end}".self::ROW_HEADER_SUB)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
                    $s->getStyle("{$start}".self::ROW_HEADER_TOP.":{$end}".self::ROW_HEADER_SUB)
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    if (in_array($block['kind'], ['case', 'sections'], true)) {
                        // Separador vertical para delimitar CASOS y SECCIONES
                        $s->getStyle("{$end}".self::ROW_HEADER_TOP.":{$end}".($s->getHighestRow()))
                            ->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM)
                            ->getColor()->setRGB(self::COLOR_TEAL);
                    }
                    $colIdx += count($block['cols']);
                }

                // A/B con fondo suave
                $s->mergeCells('A'.self::ROW_HEADER_TOP.':A'.self::ROW_HEADER_SUB);
                $s->mergeCells('B'.self::ROW_HEADER_TOP.':B'.self::ROW_HEADER_SUB);
                $s->getStyle('A'.self::ROW_HEADER_TOP.':B'.self::ROW_HEADER_SUB)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_MINT);

                // Congelar y alineaciones de datos
                $s->freezePane('C'.self::ROW_DATA_START);
                $lastRow = $s->getHighestRow();
                $s->getStyle('B'.self::ROW_DATA_START.':B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle('C'.self::ROW_DATA_START.':'.$lastCol.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Zebra para filas de estudiantes
                for ($r = self::ROW_DATA_START; $r <= $lastRow; $r++) {
                    $color = (($r - self::ROW_DATA_START) % 2 === 0) ? self::COLOR_STRIP_A : self::COLOR_STRIP_B;
                    $s->getStyle('A'.$r.':'.$lastCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                }

                // ── Fórmulas por fila: %Caso / %Sección / %Total / %Corregido / Estado / Nota ─────
                $rowFirst = self::ROW_DATA_START;

                // % por caso
                $cIdx = 3;
                foreach ($this->columnPlan as $block) {
                    if ($block['kind'] === 'case') {
                        $caseId = null;
                        $totCol = null;
                        $pctCol = null;
                        $local = 0;
                        foreach ($block['cols'] as $cdef) {
                            $colLetter = Coordinate::stringFromColumnIndex($cIdx + $local);
                            if (($cdef['type'] ?? '') === 'total_case') {
                                $totCol = $colLetter;
                                $caseId = $cdef['meta']['case_id'];
                            }
                            if (($cdef['type'] ?? '') === 'pct_case') {
                                $pctCol = $colLetter;
                            }
                            $local++;
                        }
                        $den = $caseId ? ($this->maxCaseTotalTC[$caseId] ?? 0) : 0; // items-only
                        for ($r = $rowFirst; $r <= $lastRow; $r++) {
                            $s->setCellValue("{$pctCol}{$r}", $den > 0 ? "={$totCol}{$r}/{$den}" : 0);
                        }
                    }
                    $cIdx += count($block['cols']);
                }

                // % por sección
                $cIdx = 3;
                foreach ($this->columnPlan as $block) {
                    if ($block['kind'] === 'sections') {
                        $local = 0;
                        foreach ($block['cols'] as $cdef) {
                            if (($cdef['type'] ?? '') === 'pct_section') {
                                $secId = $cdef['meta']['seccion_id'];
                                $den = $this->maxSeccionGlobalTC[$secId] ?? 0;
                                $pctCol = Coordinate::stringFromColumnIndex($cIdx + $local);
                                $totCol = Coordinate::stringFromColumnIndex($cIdx + $local - 1);
                                for ($r = $rowFirst; $r <= $lastRow; $r++) {
                                    $s->setCellValue("{$pctCol}{$r}", $den > 0 ? "={$totCol}{$r}/{$den}" : 0);
                                }
                            }
                            $local++;
                        }
                    }
                    $cIdx += count($block['cols']);
                }

                // % TOTAL / % CORREGIDO / ESTADO / NOTA
                $colTotal = $this->colLetters['total'];
                $colPT = $this->colLetters['pct_total'];
                $colPC = $this->colLetters['pct_corregido'];
                $colEst = $this->colLetters['estado'];
                $colNota = $this->colLetters['nota'];

                $cellTotalBruto = $this->cellTotalBruto;
                $cellTotalEvaluado = $this->cellTotalEvaluado;
                $cellUmbralAprob = $this->cellUmbralAprob;

                for ($r = $rowFirst; $r <= $lastRow; $r++) {
                    // % TOTAL = TOTAL / TOTAL_BRUTO
                    $s->setCellValue(
                        "{$colPT}{$r}",
                        '=IF('.$cellTotalBruto."=0,0,{$colTotal}{$r}/".$cellTotalBruto.')'
                    );

                    // % CORREGIDO = TOTAL / TOTAL_EVALUADO
                    $s->setCellValue(
                        "{$colPC}{$r}",
                        '=IF('.$cellTotalEvaluado."=0,0,{$colTotal}{$r}/".$cellTotalEvaluado.')'
                    );

                    // ESTADO segun UMBRAL
                    $s->setCellValue(
                        "{$colEst}{$r}",
                        "=IF({$colPC}{$r}>=".$cellUmbralAprob.',"APRUEBA","REMEDIA")'
                    );

                    // NOTA = % CORREGIDO * 4
                    $s->setCellValue("{$colNota}{$r}", "={$colPC}{$r}*4");
                }

                // Formato numérico/porcentaje (incluye estadísticas al pie)
                $rowSummaryEnd = $lastRow + 5;
                foreach ($this->percentColLetters as $L) {
                    $s->getStyle("{$L}{$rowFirst}:{$L}{$rowSummaryEnd}")->getNumberFormat()->setFormatCode(self::PCT_FMT);
                    $s->getStyle("{$L}{$rowFirst}:{$L}{$rowSummaryEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach ($this->numberColLetters as $L) {
                    $s->getStyle("{$L}{$rowFirst}:{$L}{$rowSummaryEnd}")->getNumberFormat()->setFormatCode(self::NUM_FMT);
                    $s->getStyle("{$L}{$rowFirst}:{$L}{$rowSummaryEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ── Estadísticos al pie (con separación visual y rótulos claros) ─────
                $rowMax = $lastRow + 1;
                $rowMin = $lastRow + 2;
                $rowAvg = $lastRow + 3;
                $rowStd = $lastRow + 4;
                $rowCut = $lastRow + 5;

                // Línea de separación entre datos y métricas
                $s->getStyle('A'.$rowMax.':'.$lastCol.$rowMax)->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COLOR_TEAL);

                // Etiquetas (incluye fórmula explícita del corte)
                $s->setCellValue("A{$rowMax}", 'Máximo (max)');
                $s->setCellValue("A{$rowMin}", 'Mínimo (min)');
                $s->setCellValue("A{$rowAvg}", 'Media (avg)');
                $s->setCellValue("A{$rowStd}", 'Desvío estándar (dst)');
                $s->setCellValue("A{$rowCut}", 'Corte (max(0.6*max; media-2*dst))');
                $s->getStyle("A{$rowMax}:A{$rowCut}")->getFont()->setBold(true);

                // Fondo suave para todo el bloque de métricas
                $s->getStyle('A'.$rowMax.':'.$lastCol.$rowCut)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_MINT);

                // Centrado de métricas
                $s->getStyle('C'.$rowMax.':'.$lastCol.$rowCut)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Cálculos de métricas por columna (omitiendo ESTADO)
                $cStartIdx = 3;
                $cEndIdx = Coordinate::columnIndexFromString($lastCol);
                for ($c = $cStartIdx; $c <= $cEndIdx; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    if (in_array($col, $this->excludeStatLetters, true)) {
                        continue;
                    }

                    $range = "{$col}{$rowFirst}:{$col}{$lastRow}";
                    $s->setCellValue("{$col}{$rowMax}", "=MAX({$range})");
                    $s->setCellValue("{$col}{$rowMin}", "=MIN({$range})");
                    $s->setCellValue("{$col}{$rowAvg}", "=AVERAGE({$range})");
                    $s->setCellValue("{$col}{$rowStd}", "=STDEV.S({$range})");
                    // CORTE = max(0.6*max, media-2*dst)
                    $s->setCellValue("{$col}{$rowCut}", "=MAX({$col}{$rowMax}*0.6, {$col}{$rowAvg}-(2*{$col}{$rowStd}))");
                }

                // Resaltar header de TOTALES
                $s->getStyle($this->totalsHeaderRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_MINT);

                // Auto size
                foreach (range('A', $lastCol) as $L) {
                    $s->getColumnDimension($L)->setAutoSize(true);
                }

                // Ancho fijo para la columna "Pseudónimo"
                $s->getColumnDimension('B')->setAutoSize(false);
                $s->getColumnDimension('B')->setWidth(20);

            },
        ];
    }
}
