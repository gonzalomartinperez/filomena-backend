<?php

namespace App\Helpers;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Pregunta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PreguntaImportHelper
{
    /**
     * Lee el Excel y devuelve filas normalizadas:
     * ['descripcion','consigna','orden_pregunta','orden_caso','_excel_row','_excel_sheet']
     */
    public function parse(UploadedFile $archivo): array
    {
        $sheets = Excel::toArray([], $archivo);
        if (! $sheets || ! is_array($sheets)) {
            throw new \RuntimeException('Archivo vacío o ilegible.');
        }

        $rows = [];
        foreach ($sheets as $sheetIndex => $sheetRows) {
            $normalized = $this->normalizeSheet($sheetRows);
            foreach ($normalized as $r) {
                $rows[] = $r + ['_excel_sheet' => $sheetIndex + 1];
            }
        }

        if (count($rows) === 0) {
            throw new \RuntimeException('No se detectaron filas de datos.');
        }

        return $rows;
    }

    /**
     * Persiste filas normalizadas. Correr dentro de una transacción.
     * Upsert por (caso_id + orden_relativo).
     */
    public function persist(array $rows, Examen $examen): array
    {
        $procesados = 0;
        $creados = 0;
        $actualizados = 0;

        // Duplicados dentro del archivo: (orden_pregunta, orden_caso)
        $seenPair = [];

        foreach ($rows as $row) {
            $procesados++;

            // Validaciones de fila (mínimas y exactas al modelo)
            $validator = Validator::make($row, [
                'descripcion' => ['required', 'string'],
                'consigna' => ['nullable', 'string'],
                'orden_pregunta' => ['required', 'integer', 'min:1'],
                'orden_caso' => ['required', 'integer', 'min:1'],
            ], [], [
                'orden_pregunta' => 'orden de la pregunta',
                'orden_caso' => 'orden del caso',
            ]);

            if ($validator->fails()) {
                $excelRow = $row['_excel_row'] ?? '?';
                $sheet = $row['_excel_sheet'] ?? '?';
                $firstErr = $validator->errors()->first();
                throw new \RuntimeException("Error en la hoja {$sheet}, fila {$excelRow}: {$firstErr}");
            }

            $desc = trim($row['descripcion']);
            $consigna = $row['consigna'] !== null ? trim($row['consigna']) : null;
            $ordPreg = (int) $row['orden_pregunta'];
            $ordCaso = (int) $row['orden_caso'];

            // Duplicado (dentro del archivo)
            $key = $ordCaso.'#'.$ordPreg;
            if (isset($seenPair[$key])) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: la combinación (orden_caso={$ordCaso}, orden_pregunta={$ordPreg}) está duplicada en el archivo.");
            }
            $seenPair[$key] = true;

            // Caso del examen según orden_relativo
            $caso = Caso::where('examen_id', $examen->id)
                ->where('orden_relativo', $ordCaso)
                ->first();

            if (! $caso) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: no se encontró el Caso con orden {$ordCaso} para el examen {$examen->id}.");
            }

            // Upsert Pregunta por (caso_id + orden_relativo)
            $preg = Pregunta::firstOrNew([
                'caso_id' => $caso->id,
                'orden_relativo' => $ordPreg,
            ]);

            $isNew = ! $preg->exists;

            // Seteo de campos EXACTOS del modelo
            $preg->descripcion = $desc;
            $preg->consigna = $consigna;
            $preg->orden_relativo = $ordPreg;
            $preg->caso_id = $caso->id;
            $preg->examen_id = $examen->id;

            $preg->save();

            $isNew ? $creados++ : $actualizados++;
        }

        return compact('procesados', 'creados', 'actualizados');
    }

    /**
     * Encabezados flexibles en minúsculas, sin acentos, sin signos:
     *  - descripcion:  ['descripcion','descripción','desc','enunciado','texto','pregunta','contenido']
     *  - consigna:     ['consigna','titulo','título','instruccion','instrucción','leyenda']
     *  - orden_preg.:  ['orden_pregunta','orden pregunta','orden','orden relativo','orden_relativo','nro','nro pregunta','numero','número']
     *  - orden_caso:   ['orden_caso','orden caso','caso','orden relativo caso','nro caso','numero caso','número caso']
     */
    private function normalizeSheet(array $sheetRows): array
    {
        if (! is_array($sheetRows) || count($sheetRows) === 0) {
            return [];
        }

        [$map, $headerIdx] = $this->detectHeader($sheetRows);
        if (! $map) {
            return [];
        } // sin encabezado utilizable

        // Parseo de filas a partir del header
        $out = [];
        $seenData = false;
        $blankStreak = 0;

        // NEW: último valor visto de orden_caso para soportar celdas mergeadas
        $carryOrdCaso = null;

        for ($i = $headerIdx + 1; $i < count($sheetRows); $i++) {
            $r = $sheetRows[$i] ?? [];
            if (! is_array($r)) {
                continue;
            }

            // Corte por filas vacías una vez iniciado el bloque
            if ($this->isRowEmpty($r)) {
                if ($seenData) {
                    $blankStreak++;
                    if ($blankStreak >= 1) {
                        break;
                    }
                }

                continue;
            }

            // Extraer por mapa
            $descripcionRaw = $this->getCellByMap($r, $map, 'descripcion');
            $consignaRaw = $this->getCellByMap($r, $map, 'consigna');
            $ordPregRaw = $this->getCellByMap($r, $map, 'orden_pregunta');
            $ordCasoRaw = $this->getCellByMap($r, $map, 'orden_caso');

            // Limpiezas / normalizaciones
            $descripcion = $this->toString($descripcionRaw);
            $consigna = $this->toString($consignaRaw);           // puede ser NULL y es válido
            $ordPreg = $this->toInt($ordPregRaw);

            // NEW: soportar celdas mergeadas en orden_caso → usar último valor visto si está vacío
            $ordCaso = $this->toInt($ordCasoRaw);
            if ($ordCaso === null && $carryOrdCaso !== null) {
                $ordCaso = $carryOrdCaso;
            }
            if ($ordCaso !== null) {
                $carryOrdCaso = $ordCaso; // actualizar “arrastre” para las filas siguientes del bloque
            }

            // ¿fila con datos?
            $looks = ($descripcion !== null && $descripcion !== '') ||
                    ($consigna !== null && $consigna !== '') ||
                    ($ordPreg !== null) ||
                    ($ordCaso !== null);
            if (! $looks) {
                if ($seenData) {
                    break;
                }

                continue;
            }

            $seenData = true;
            $blankStreak = 0;

            $out[] = [
                'descripcion' => $descripcion,
                'consigna' => $consigna,
                'orden_pregunta' => $ordPreg,
                'orden_caso' => $ordCaso,
                '_excel_row' => $i + 1,
            ];
        }

        return array_values(array_filter($out, fn ($r) => $r['descripcion'] || $r['consigna'] || $r['orden_pregunta'] || $r['orden_caso']
        ));
    }

    private function detectHeader(array $sheetRows): array
    {
        $syn = $this->headerSynonyms();
        $limit = min(120, count($sheetRows));

        // Intento ideal: mapear los 4
        for ($i = 0; $i < $limit; $i++) {
            $row = $sheetRows[$i] ?? [];
            if (! is_array($row) || $this->isRowEmpty($row)) {
                continue;
            }

            $map = [];
            foreach ($row as $idx => $cell) {
                $label = $this->norm($this->toString($cell) ?? '');
                if ($label === '') {
                    continue;
                }

                foreach ($syn as $field => $aliases) {
                    foreach ($aliases as $alias) {
                        if ($label === $alias || (str_contains($label, $alias) && ! isset($map[$field]))) {
                            $map[$field] = $idx;
                            break 2;
                        }
                    }
                }
            }
            if ($this->headerSufficient($map)) {
                return [$map, $i];
            }
        }

        // Fallback: aceptar mínimo descripcion + (orden_pregunta y orden_caso)
        for ($i = 0; $i < $limit; $i++) {
            $row = $sheetRows[$i] ?? [];
            if (! is_array($row) || $this->isRowEmpty($row)) {
                continue;
            }

            $map = [];
            foreach ($row as $idx => $cell) {
                $label = $this->norm($this->toString($cell) ?? '');
                if ($label === '') {
                    continue;
                }

                foreach ($syn as $field => $aliases) {
                    foreach ($aliases as $alias) {
                        if ($label === $alias || (str_contains($label, $alias) && ! isset($map[$field]))) {
                            $map[$field] = $idx;
                            break 2;
                        }
                    }
                }
            }
            if (isset($map['descripcion']) && isset($map['orden_pregunta']) && isset($map['orden_caso'])) {
                return [$map, $i];
            }
        }

        return [null, null];
    }

    private function headerSufficient(array $map): bool
    {
        // Ideal: los 4 campos; práctico: se controla con detectHeader()
        return isset($map['descripcion']) && isset($map['consigna']) && isset($map['orden_pregunta']) && isset($map['orden_caso']);
    }

    private function headerSynonyms(): array
    {
        $aliases = [
            'descripcion' => [
                'descripcion', 'descripción', 'desc', 'enunciado', 'texto', 'pregunta', 'contenido',
            ],
            'consigna' => [
                'consigna', 'titulo', 'título', 'instruccion', 'instrucción', 'leyenda',
            ],
            'orden_pregunta' => [
                'orden_pregunta', 'orden pregunta', 'orden', 'orden relativo', 'orden_relativo', 'nro', 'nro pregunta', 'numero', 'número',
            ],
            'orden_caso' => [
                'orden_caso', 'orden caso', 'caso', 'orden relativo caso', 'nro caso', 'numero caso', 'número caso',
            ],
        ];

        foreach ($aliases as $k => $vals) {
            $aliases[$k] = array_values(array_unique(array_map(fn ($v) => $this->norm($v), $vals)));
        }

        return $aliases;
    }

    // ------------------------
    //        Utilidades
    // ------------------------

    private function getCellByMap(array $row, array $map, string $key): mixed
    {
        $idx = $map[$key] ?? null;

        return is_int($idx) ? ($row[$idx] ?? null) : null;
    }

    private function isRowEmpty(array $r): bool
    {
        foreach ($r as $v) {
            if (is_string($v) && trim($v) !== '') {
                return false;
            }
            if (is_numeric($v)) {
                return false;
            }
            if (is_object($v)) {
                return false;
            }
        }

        return true;
    }

    private function toString($v): ?string
    {
        if (is_null($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function toInt($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }
        $digits = preg_replace('/\D+/', '', (string) $v);

        return $digits === '' ? null : (int) $digits;
    }

    private function norm(?string $s): string
    {
        $s = $s ?? '';
        $s = Str::of($s)->ascii()->lower()->value();     // sin acentos
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);     // sin signos

        return trim($s);
    }
}
