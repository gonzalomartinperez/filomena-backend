<?php

namespace App\Helpers;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Item;
use App\Models\Pregunta;
use App\Models\Seccion;
use App\Models\TablaCotejo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TablaCotejoImportHelper
{
    /**
     * Normalizado de cada fila:
     * ['item_text','puntaje','orden_seccion','orden_pregunta','orden_caso','_excel_row','_excel_sheet']
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
     * Persiste filas normalizadas. Ejecutar dentro de una transacción.
     * - Upsert TablaCotejo por (examen_id, caso_id, seccion_id)
     * - Upsert Item por (tabla_cotejo_id, pregunta_id, item_text)
     */
    public function persist(array $rows, Examen $examen): array
    {
        $procesados = 0;
        $tablas_cotejo_creadas = 0;
        $tablas_cotejo_existentes = 0;
        $items_creados = 0;
        $items_actualizados = 0;

        // Descubrimos nombres de columna reales en DB para texto/puntaje del item
        $itemTable = (new Item)->getTable();
        $hasCol = fn (string $c) => Schema::hasColumn($itemTable, $c);

        $itemTextCol = $this->firstExisting(['item', 'nombre', 'descripcion', 'descripcion_item', 'texto', 'detalle', 'titulo'], $hasCol) ?? 'item';
        $puntajeCol = $this->firstExisting(['puntaje', 'puntos', 'valor', 'peso', 'score'], $hasCol) ?? 'puntaje';

        // Caches para evitar consultas repetidas
        $casoByOrden = [];
        $seccionByOrden = [];
        $preguntaByKey = []; // "{$casoId}#{$ordPreg}" => Pregunta

        // Cache de tablas creadas en este proceso
        $tablaByKey = []; // "{$casoId}#{$seccionId}" => TablaCotejo

        foreach ($rows as $row) {
            $procesados++;

            // Validaciones mínimas y precisas
            $validator = Validator::make($row, [
                'item_text' => ['required', 'string'],
                'puntaje' => ['required', 'numeric', 'min:0'],
                'orden_seccion' => ['required', 'integer', 'min:1'],
                'orden_pregunta' => ['required', 'integer', 'min:1'],
                'orden_caso' => ['required', 'integer', 'min:1'],
            ], [], [
                'item_text' => 'item',
                'orden_seccion' => 'orden de la sección',
                'orden_pregunta' => 'orden de la pregunta',
                'orden_caso' => 'orden del caso',
            ]);

            if ($validator->fails()) {
                $excelRow = $row['_excel_row'] ?? '?';
                $sheet = $row['_excel_sheet'] ?? '?';
                $firstErr = $validator->errors()->first();
                throw new \RuntimeException("Error en la hoja {$sheet}, fila {$excelRow}: {$firstErr}");
            }

            $texto = trim($row['item_text']);
            $puntaje = (float) $row['puntaje'];
            $oSeccion = (int) $row['orden_seccion'];
            $oPreg = (int) $row['orden_pregunta'];
            $oCaso = (int) $row['orden_caso'];

            // ---- Resolución de Caso por orden ----
            if (! isset($casoByOrden[$oCaso])) {
                $casoByOrden[$oCaso] = Caso::where('examen_id', $examen->id)
                    ->where('orden_relativo', $oCaso)
                    ->first();
            }
            $caso = $casoByOrden[$oCaso];
            if (! $caso) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: no existe Caso con orden {$oCaso} para el examen {$examen->id}.");
            }

            // ---- Resolución de Seccion por orden ----
            if (! isset($seccionByOrden[$oSeccion])) {
                $seccionByOrden[$oSeccion] = Seccion::where('examen_id', $examen->id)
                    ->where('orden_relativo', $oSeccion)
                    ->first();
            }
            $seccion = $seccionByOrden[$oSeccion];
            if (! $seccion) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: no existe Sección con orden {$oSeccion} para el examen {$examen->id}.");
            }

            // ---- Resolución de Pregunta por (caso + orden) ----
            $pKey = $caso->id.'#'.$oPreg;
            if (! isset($preguntaByKey[$pKey])) {
                $preguntaByKey[$pKey] = Pregunta::where('examen_id', $examen->id)
                    ->where('caso_id', $caso->id)
                    ->where('orden_relativo', $oPreg)
                    ->first();
            }
            $pregunta = $preguntaByKey[$pKey];
            if (! $pregunta) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: no existe Pregunta con orden {$oPreg} para el caso {$oCaso}.");
            }

            // ---- Upsert TablaCotejo (examen + caso + seccion) ----
            $tKey = $caso->id.'#'.$seccion->id;
            if (! isset($tablaByKey[$tKey])) {
                $tabla = TablaCotejo::firstOrNew([
                    'examen_id' => $examen->id,
                    'caso_id' => $caso->id,
                    'seccion_id' => $seccion->id,
                ]);
                $isNewTabla = ! $tabla->exists;
                if ($isNewTabla) {
                    $tabla->examen_id = $examen->id;
                    $tabla->caso_id = $caso->id;
                    $tabla->seccion_id = $seccion->id;
                    $tabla->save();
                    $tablas_cotejo_creadas++;
                } else {
                    $tablas_cotejo_existentes++;
                }
                $tablaByKey[$tKey] = $tabla;
            }
            $tabla = $tablaByKey[$tKey];

            // ---- Upsert Item (tabla + pregunta + texto) ----
            $item = Item::firstOrNew([
                'tabla_cotejo_id' => $tabla->id,
                'pregunta_id' => $pregunta->id,
                $itemTextCol => $texto,
            ]);

            $isNewItem = ! $item->exists;

            // Seteos: puntaje + FK (sólo si existen columnas)
            if ($hasCol($puntajeCol)) {
                $item->setAttribute($puntajeCol, $puntaje);
            }

            // FK estándar (si existen)
            $this->setIfHas($item, 'examen_id', $examen->id);
            $this->setIfHas($item, 'caso_id', $caso->id);
            $this->setIfHas($item, 'seccion_id', $seccion->id);
            $this->setIfHas($item, 'pregunta_id', $pregunta->id);
            $this->setIfHas($item, 'tabla_cotejo_id', $tabla->id);

            // Texto del ítem (por si la col no fue clave)
            if ($hasCol($itemTextCol)) {
                $item->setAttribute($itemTextCol, $texto);
            }

            $item->save();

            $isNewItem ? $items_creados++ : $items_actualizados++;
        }

        return compact('procesados', 'tablas_cotejo_creadas', 'tablas_cotejo_existentes', 'items_creados', 'items_actualizados');
    }

    /**
     * Encabezados flexibles (minúsculas, sin acentos):
     *  - item_text:      ['item','ítem','criterio','descripcion','descripción','nombre','detalle','texto']
     *  - puntaje:        ['puntaje','puntos','valor','peso','score']
     *  - orden_seccion:  ['orden_seccion','orden seccion','orden sección','seccion','sección','orden relativo seccion']
     *  - orden_pregunta: ['orden_pregunta','orden pregunta','pregunta','orden relativo pregunta','nro pregunta']
     *  - orden_caso:     ['orden_caso','orden caso','caso','orden relativo caso','nro caso']
     */
    private function normalizeSheet(array $sheetRows): array
    {
        if (! is_array($sheetRows) || count($sheetRows) === 0) {
            return [];
        }

        [$map, $headerIdx] = $this->detectHeader($sheetRows);
        if (! $map) {
            return [];
        }

        $out = [];
        $seenData = false;
        $blankStreak = 0;

        // Carry-down para celdas mergeadas
        $carryCaso = null;
        $carrySeccion = null;
        $carryPreg = null;

        for ($i = $headerIdx + 1; $i < count($sheetRows); $i++) {
            $r = $sheetRows[$i] ?? [];
            if (! is_array($r)) {
                continue;
            }

            // Fin por filas vacías tras iniciar
            if ($this->isRowEmpty($r)) {
                if ($seenData) {
                    $blankStreak++;
                    if ($blankStreak >= 1) {
                        break;
                    }
                }

                continue;
            }

            // Tomar celdas
            $itemRaw = $this->getCellByMap($r, $map, 'item_text');
            $puntRaw = $this->getCellByMap($r, $map, 'puntaje');
            $oSecRaw = $this->getCellByMap($r, $map, 'orden_seccion');
            $oPreRaw = $this->getCellByMap($r, $map, 'orden_pregunta');
            $oCasRaw = $this->getCellByMap($r, $map, 'orden_caso');

            // Normalizaciones
            $itemText = $this->toString($itemRaw);
            $puntaje = $this->toFloat($puntRaw);

            $oSeccion = $this->toInt($oSecRaw);
            $oPreg = $this->toInt($oPreRaw);
            $oCaso = $this->toInt($oCasRaw);

            // Carry-down para mergeados
            if ($oSeccion === null && $carrySeccion !== null) {
                $oSeccion = $carrySeccion;
            }
            if ($oPreg === null && $carryPreg !== null) {
                $oPreg = $carryPreg;
            }
            if ($oCaso === null && $carryCaso !== null) {
                $oCaso = $carryCaso;
            }

            if ($oSeccion !== null) {
                $carrySeccion = $oSeccion;
            }
            if ($oPreg !== null) {
                $carryPreg = $oPreg;
            }
            if ($oCaso !== null) {
                $carryCaso = $oCaso;
            }

            // ¿fila con datos?
            $looks = ($itemText !== null && $itemText !== '') ||
                     ($puntaje !== null) ||
                     ($oSeccion !== null) || ($oPreg !== null) || ($oCaso !== null);

            if (! $looks) {
                if ($seenData) {
                    break;
                }

                continue;
            }

            $seenData = true;
            $blankStreak = 0;

            $out[] = [
                'item_text' => $itemText,
                'puntaje' => $puntaje,
                'orden_seccion' => $oSeccion,
                'orden_pregunta' => $oPreg,
                'orden_caso' => $oCaso,
                '_excel_row' => $i + 1,
            ];
        }

        // Filtrar basura
        return array_values(array_filter($out, fn ($r) => ($r['item_text'] !== null && $r['item_text'] !== '') ||
            $r['puntaje'] !== null ||
            $r['orden_seccion'] !== null ||
            $r['orden_pregunta'] !== null ||
            $r['orden_caso'] !== null
        ));
    }

    private function detectHeader(array $sheetRows): array
    {
        $syn = $this->headerSynonyms();
        $limit = min(120, count($sheetRows));

        // Ideal: mapear todos
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

        // Fallback: al menos item + puntaje + (algún orden)
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
            if (isset($map['item_text']) && isset($map['puntaje']) &&
                (isset($map['orden_caso']) || isset($map['orden_pregunta']) || isset($map['orden_seccion']))) {
                return [$map, $i];
            }
        }

        return [null, null];
    }

    private function headerSufficient(array $map): bool
    {
        return isset($map['item_text'], $map['puntaje'], $map['orden_seccion'], $map['orden_pregunta'], $map['orden_caso']);
    }

    private function headerSynonyms(): array
    {
        $aliases = [
            'item_text' => ['item', 'item texto', 'ítem', 'criterio', 'descripcion', 'descripción', 'nombre', 'detalle', 'texto'],
            'puntaje' => ['puntaje', 'puntos', 'valor', 'peso', 'score'],
            'orden_seccion' => ['orden_seccion', 'orden seccion', 'orden sección', 'seccion', 'sección', 'orden relativo seccion'],
            'orden_pregunta' => ['orden_pregunta', 'orden pregunta', 'pregunta', 'orden relativo pregunta', 'nro pregunta'],
            'orden_caso' => ['orden_caso', 'orden caso', 'caso', 'orden relativo caso', 'nro caso'],
        ];
        foreach ($aliases as $k => $vals) {
            $aliases[$k] = array_values(array_unique(array_map(fn ($v) => $this->norm($v), $vals)));
        }

        return $aliases;
    }

    // ------------------------------------------------
    //                 Utilidades
    // ------------------------------------------------

    private function firstExisting(array $candidates, callable $hasCol): ?string
    {
        foreach ($candidates as $c) {
            if ($hasCol($c)) {
                return $c;
            }
        }

        return null;
    }

    private function setIfHas($model, string $col, $value): void
    {
        if (Schema::hasColumn($model->getTable(), $col)) {
            $model->setAttribute($col, $value);
        }
    }

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

    private function toFloat($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        // soportar "0,5" -> 0.5 y "2,50" -> 2.5
        $s = str_replace(['.', ',', ' '], ['.', '.', ''], (string) $v);
        // si tenía separador de miles, el reemplazo podría confundir; intentamos modo europeo
        if (preg_match('/^\d+,\d+$/', (string) $v)) {
            $s = str_replace(',', '.', (string) $v);
        }
        $s = preg_replace('/[^0-9\.\-]/', '', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    private function norm(?string $s): string
    {
        $s = $s ?? '';
        $s = Str::of($s)->ascii()->lower()->value(); // sin acentos
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s); // sin signos

        return trim($s);
    }
}
