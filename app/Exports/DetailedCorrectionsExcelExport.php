<?php

namespace App\Exports;

use App\Models\Examen;
use App\Models\Item;
use App\Models\User;
use App\Services\PseudonimoService;
use App\Services\PuntajeService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetailedCorrectionsExcelExport implements FromCollection, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithProperties, WithStrictNullComparison, WithStyles
{
    /** Filas fijas (alineadas al Excel de Notas) */
    private const ROW_TITLE = 1; // "Filomena - Correcciones detalladas"

    private const ROW_EXAM = 2; // "Examen: ..."

    private const ROW_DATE = 3; // "Fecha: ..."

    private const ROW_META = 4; // "Estudiantes | Casos | Secciones"

    private const ROW_HEADER = 6; // Encabezados de tabla

    private const ROW_DATA_START = 7; // Primer renglón de datos

    /** Suma del valor de ítems con tasa < 10% (para puntaje sugerido) */
    private float $sumItemsSub10 = 0.0;

    /** Paleta */
    private const COLOR_ACCENT_DARK = '095d7e'; // título principal

    private const COLOR_SOFT_BLUE = 'ccecee'; // estructura (SECCIÓN/ÍTEM)

    private const COLOR_SOFT_GREEN = 'e2fcd6'; // puntaje del ítem

    private const COLOR_SOFT_MINT = 'f1f9ff'; // estudiantes

    private const COLOR_SOFT_AQUA = 'eaf7f6'; // "Caso:" (fila especial) y header de tasa

    private const COLOR_ZEBRA_A = 'FFFFFF';

    private const COLOR_ZEBRA_B = 'f7fafc';

    private const COLOR_GREEN_RATE = 'd3f9d8';

    private const COLOR_RED_RATE = 'fee2e2';

    /** Formatos numéricos */
    private const PCT_FMT = NumberFormat::FORMAT_PERCENTAGE_00;

    private const DEC_FMT = NumberFormat::FORMAT_NUMBER_00;

    /** Columnas base (1-based) */
    private const COL_SECTION = 1; // A

    private const COL_ITEM = 2; // B

    private const COL_ITEM_SCORE = 3; // C

    private const COL_FIRST_STU = 4; // D

    private Examen $examen;

    private PuntajeService $puntajeService;

    private PseudonimoService $pseudonimoService;

    /** @var Collection<User> */
    private Collection $estudiantes;

    /** Filas para FromCollection (cada fila es un array plano) */
    private array $rows = [];

    /**
     * @param  Examen  $examen  Examen completo
     * @param  PuntajeService  $puntajeService  Servicio de métricas (puntajes por ítem/estudiante)
     * @param  PseudonimoService  $pseudonimoService  Pseudónimos por estudiante/examen
     */
    public function __construct(
        Examen $examen,
        PuntajeService $puntajeService,
        PseudonimoService $pseudonimoService
    ) {
        // Carga diferida de relaciones necesarias (dinámico ante cualquier volumen)
        $this->examen = $examen->loadMissing('casos.preguntas.items.seccion', 'estudiantes');
        $this->puntajeService = $puntajeService;
        $this->pseudonimoService = $pseudonimoService;
        $this->estudiantes = $this->examen->estudiantes->values();

        // Preconstruimos todas las filas de datos para simplificar AfterSheet.
        $this->buildRows();
    }

    /** Propiedades del archivo (metadatos del XLSX) */
    public function properties(): array
    {
        return [
            'creator' => 'Filomena',
            'title' => "Correcciones detalladas - {$this->examen->nombre}",
        ];
    }

    /** La tabla arranca en la fila 6 (A6) */
    public function startCell(): string
    {
        return 'A'.self::ROW_HEADER;
    }

    /**
     * Encabezados en una sola fila (van en mayúsculas).
     * A: SECCIÓN · B: ÍTEM · C: PUNTAJE · D..: Estudiantes · Última: TASA DE RESPUESTA
     */
    public function headings(): array
    {
        $headers = ['SECCIÓN', 'ÍTEM', 'PUNTAJE'];

        // Estudiantes: "Apellido, Nombre | Pseudónimo: X"
        foreach ($this->estudiantes as $estudiante) {
            $pseudo = $this->pseudonimoService->pseudonimoParaExamen($estudiante, $this->examen) ?: '—';
            $apellido = (string) ($estudiante->apellido ?? '');
            $nombre = (string) ($estudiante->nombre ?? '');
            $headers[] = "{$apellido}, {$nombre} ({$pseudo})";
        }

        // Columna final (tasa de respuesta por ítem)
        $headers[] = 'TASA DE RESPUESTA';

        // Guardamos la última columna para usar en AfterSheet (estilos/rangos).
        $this->lastColIndex = \count($headers);

        return $headers;
    }

    /** Dataset plano para Maatwebsite (cada fila corresponde a una "línea visual" en la planilla) */
    public function collection(): Collection
    {
        return collect($this->rows);
    }

    /** Estilos generales (no dependientes de cantidades) */
    public function styles(Worksheet $sheet): array
    {
        // Texto en A y B alineado a la izquierda; ítems en cursiva.
        $sheet->getStyle('A:B')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(false);

        $sheet->getStyle('B')->getFont()->setItalic(true);

        // Tipografías de metadata (coincidente con el Excel de Notas)
        $sheet->getStyle('A'.self::ROW_TITLE)->getFont()
            ->setName('Segoe UI')->setSize(18)->setBold(true)
            ->getColor()->setRGB(self::COLOR_ACCENT_DARK);

        $sheet->getStyle('A'.self::ROW_EXAM)->getFont()
            ->setName('Segoe UI')->setSize(13)->setBold(true);

        $sheet->getStyle('A'.self::ROW_DATE)->getFont()
            ->setName('Segoe UI')->setSize(11)->setItalic(true);

        $sheet->getStyle('A'.self::ROW_META)->getFont()
            ->setName('Segoe UI')->setSize(11);

        // No devolvemos estilos por array (usamos AfterSheet para todo lo dinámico)
        return [];
    }

    /**
     * Construye filas (orden visual):
     * - Fila de Caso: B = "Caso: <Título>" (centrado), A y B pintadas; sin merge.
     * - Primera fila de cada Sección: A con el nombre (negrita), el resto vacío; estilos en AfterSheet.
     * - C = puntaje máximo del ítem; D.. = puntaje por estudiante (0 | valor); última = tasa de respuesta (aprobación).
     */
    private function buildRows(): void
    {
        $studentCount = $this->estudiantes->count();

        foreach ($this->examen->casos as $caso) {
            // Fila "CASO: <Título>" (se estiliza en AfterSheet)
            $caseRow = array_fill(0, max(self::COL_FIRST_STU, $studentCount + self::COL_FIRST_STU), null);
            $caseRow[self::COL_ITEM - 1] = 'CASO: '.(string) $caso->titulo;
            $this->rows[] = $caseRow;

            // Ítems del caso agrupados por sección
            $items = $caso->preguntas->flatMap->items->values();
            $bySection = $items->groupBy('seccion_id');

            foreach ($bySection as $group) {
                /** @var Item $first */
                $first = $group->first();
                $sectionName = (string) ($first->seccion->nombre ?? '—');
                $firstInSection = true;

                foreach ($group as $item) {
                    // Buffer de fila con tamaño suficiente (C + estudiantes + tasa)
                    $rowLen = self::COL_FIRST_STU + $studentCount; // +1 extra lo usaremos para la tasa
                    $row = array_fill(0, $rowLen, null);

                    // A: nombre de sección SOLO en el primer ítem de la sección
                    $row[self::COL_SECTION - 1] = $firstInSection ? $sectionName : '';

                    // B: descripción del ítem (tal cual BD)
                    $row[self::COL_ITEM - 1] = (string) $item->descripcion;

                    // C: puntaje máximo del ítem
                    $itemMax = (float) $this->puntajeService->maxItem($item);
                    $row[self::COL_ITEM_SCORE - 1] = $itemMax;

                    // D..: puntaje por estudiante (0 | itemMax)
                    $positive = 0;
                    $col = self::COL_FIRST_STU - 1;

                    foreach ($this->estudiantes as $estudiante) {
                        /** @var User $estudiante */
                        $obt = (float) $this->puntajeService->puntajeObtenidoItem($item, $estudiante);
                        $row[$col++] = $obt;
                        if ($obt > 0) {
                            $positive++;
                        }
                    }

                    // Última: tasa de respuesta (aprobación) = positivos / total estudiantes
                    $rate = $studentCount > 0 ? $positive / $studentCount : 0.0;
                    $row[$col] = $rate; // col apunta a la siguiente (inmediatamente tras el último estudiante)

                    if ($rate < 0.10) {
                        $this->sumItemsSub10 += (float) $this->puntajeService->maxItem($item);
                    }

                    $this->rows[] = $row;
                    $firstInSection = false;
                }
            }
        }
    }

    /** Eventos dinámicos de hoja (estilos, bordes, colores, congelados, etc.) */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // Dimensiones calculadas
                $lastRow = $s->getHighestRow();
                $lastCol = $s->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);

                // ── Metadata (A1..A4) ─────────────────────────────────────
                $s->mergeCells('A'.self::ROW_TITLE.':'.$lastCol.self::ROW_TITLE);
                $s->mergeCells('A'.self::ROW_EXAM.':'.$lastCol.self::ROW_EXAM);
                $s->mergeCells('A'.self::ROW_DATE.':'.$lastCol.self::ROW_DATE);
                $s->mergeCells('A'.self::ROW_META.':'.$lastCol.self::ROW_META);

                $s->setCellValue('A'.self::ROW_TITLE, 'Filomena - Correcciones detalladas');
                $s->setCellValue('A'.self::ROW_EXAM, 'Examen: '.$this->examen->nombre.' ('.$this->examen->codigo.')');
                $s->setCellValue('A'.self::ROW_DATE, 'Fecha: '.optional($this->examen->fecha)->format('d/m/Y'));

                $estudiantesCount = $this->examen->estudiantes->count();
                $casosCount = $this->examen->casos->count();
                $seccionesCount = $this->examen->secciones()->count();
                $max = $this->puntajeService->maxExamen($this->examen);
                $sugerido = max(0, $max - $this->sumItemsSub10);

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

                // ── Header (fila 6): negrita + negro + centrado ──────────
                $hdrRow = self::ROW_HEADER;
                $hdrRange = 'A'.$hdrRow.':'.$lastCol.$hdrRow;

                $s->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setRGB('000000');
                $s->getStyle($hdrRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(false)
                    ->setShrinkToFit(true);

                // Colores por rol en header
                // A–B (estructura)
                $s->getStyle('A'.$hdrRow.':B'.$hdrRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_BLUE);

                // C (puntaje ítem)
                $s->getStyle('C'.$hdrRow.':C'.$hdrRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_GREEN);

                // D.. estudiantes (si existen)
                if ($lastColIdx > self::COL_FIRST_STU) {
                    $stuEndCol = Coordinate::stringFromColumnIndex($lastColIdx - 1);
                    $s->getStyle(Coordinate::stringFromColumnIndex(self::COL_FIRST_STU).$hdrRow.':'.$stuEndCol.$hdrRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_MINT);
                }

                // ----- Formato condicional para "TASA DE RESPUESTA" -----
                $rateCol = Coordinate::stringFromColumnIndex($lastColIdx);
                $s->getStyle($rateCol.$hdrRow.':'.$rateCol.$hdrRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_GREEN);

                // Saltamos el header: si la cabecera está en ROW_DATA_START, los datos comienzan en +1.
                $firstDataRow = self::ROW_DATA_START + 1;
                $range = $rateCol.$firstDataRow.':'.$rateCol.$lastRow;

                // <20% => rojo suave
                $condLow = new Conditional;
                $condLow->setConditionType(Conditional::CONDITION_CELLIS);
                $condLow->setOperatorType(Conditional::OPERATOR_LESSTHAN);
                $condLow->addCondition('0.2'); // 20% como fracción
                $condLow->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
                $condLow->getStyle()->getFill()->getStartColor()->setARGB('FF'.strtoupper(self::COLOR_RED_RATE));

                // >90% => verde suave
                $condHigh = new Conditional;
                $condHigh->setConditionType(Conditional::CONDITION_CELLIS);
                $condHigh->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
                $condHigh->addCondition('0.9'); // 90% como fracción
                $condHigh->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
                $condHigh->getStyle()->getFill()->getStartColor()->setARGB('FF'.strtoupper(self::COLOR_GREEN_RATE));

                // Conservar otros condicionales si los hubiera
                $existing = $s->getStyle($range)->getConditionalStyles();
                $existing[] = $condLow;
                $existing[] = $condHigh;
                $s->getStyle($range)->setConditionalStyles($existing);

                // Quitar italic en B6 (header) y B7 (primer dato/Caso:)
                $s->getStyle('B'.self::ROW_HEADER)->getFont()->setItalic(false);
                $s->getStyle('B'.self::ROW_DATA_START)->getFont()->setItalic(false);

                // ── Congelado: A..C + header + primera fila de datos (Caso:) ─
                $s->freezePaneByColumnAndRow(self::COL_FIRST_STU, self::ROW_HEADER + 1);
                // Nota: no existe "freeze dinámico por caso" durante el scroll en Excel.

                // ── Formatos / bordes ─────────────────────────────────────
                // Números (C y estudiantes) → decimales
                $stuStart = Coordinate::stringFromColumnIndex(self::COL_ITEM_SCORE);
                $stuEnd = Coordinate::stringFromColumnIndex(max(self::COL_FIRST_STU - 1, $lastColIdx - 1));
                $s->getStyle("{$stuStart}".self::ROW_DATA_START.":{$stuEnd}{$lastRow}")
                    ->getNumberFormat()->setFormatCode(self::DEC_FMT);

                // Tasa % (última columna)
                $s->getStyle($rateCol.self::ROW_DATA_START.':'.$rateCol.$lastRow)
                    ->getNumberFormat()->setFormatCode(self::PCT_FMT);

                // Alineación centrada para las columnas numéricas
                $s->getStyle('C'.self::ROW_DATA_START.':'.$lastCol.$lastRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);

                // Bordes finos en toda la tabla (header + datos)
                $tableRange = 'A'.self::ROW_HEADER.':'.$lastCol.$lastRow;
                $s->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Zebra + filas especiales (CASO / Primera de Sección) ──
                for ($r = self::ROW_DATA_START; $r <= $lastRow; $r++) {
                    $cellA = (string) $s->getCell('A'.$r)->getValue();
                    $cellB = (string) $s->getCell('B'.$r)->getValue();

                    // Fila de CASO (B comienza con "CASO:")
                    if (\str_starts_with($cellB, 'CASO:')) {
                        // Pintar A..B con aqua, poner B en negrita centrado
                        $s->getStyle("A{$r}:B{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_AQUA);

                        $s->getStyle("B{$r}")->getFont()->setBold(true);
                        $s->getStyle("B{$r}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);

                        // No zebra en la fila de caso
                        continue;
                    }

                    // Primera fila visible de cada SECCIÓN:
                    // A tiene nombre · B tiene ítem · la fila anterior no tenía A (prevA === '')
                    $prevA = $r > self::ROW_DATA_START ? (string) $s->getCell('A'.($r - 1))->getValue() : '';
                    $isSectionFirstRow = ($cellA !== '') && ($cellB !== '') && ($prevA === '');

                    if ($isSectionFirstRow) {
                        // Sección en negrita
                        $s->getStyle("A{$r}")->getFont()->setBold(true);

                        // *** Solo columna A (NO pintar B) en el primer ítem de la sección ***
                        $s->getStyle("A{$r}:A{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_SOFT_GREEN);

                        // El resto de la fila (C..última) queda con zebra (más abajo)
                        // y la columna B queda sin color (requisito del cliente).
                    }

                    // Zebra general (C..última) para TODAS las filas de datos que no son "Caso:"
                    $fill = (($r - self::ROW_DATA_START) % 2 === 0) ? self::COLOR_ZEBRA_A : self::COLOR_ZEBRA_B;
                    $s->getStyle("C{$r}:{$lastCol}{$r}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
                }

                // ── AutoSize + shrinkToFit en headers ────────────────────
                foreach (range(1, $lastColIdx) as $i) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $s->getColumnDimension($col)->setAutoSize(true);
                    $s->getStyle($col.self::ROW_HEADER)->getAlignment()->setShrinkToFit(true);
                }

                // Línea vertical suave después de C (separar valor de estudiantes)
                $s->getStyle('C'.self::ROW_HEADER.':C'.$lastRow)->getBorders()->getRight()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}
