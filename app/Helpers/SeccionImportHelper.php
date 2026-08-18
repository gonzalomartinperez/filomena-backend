<?php

namespace App\Helpers;

use App\Models\Examen;
use App\Models\Seccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SeccionImportHelper
{
    /**
     * Lee el Excel y devuelve filas normalizadas:
     * ['nombre','orden_relativo','_excel_row','_excel_sheet']
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
     * Persiste filas normalizadas. Debe correrse dentro de una transacción.
     * Reglas:
     *  - Upsert por (examen_id, nombre) -> mantiene identidad por nombre.
     *  - Si no existe por nombre, intenta (examen_id, orden_relativo).
     *  - Evita colisiones de orden: si el nuevo orden está ocupado por otra sección, error.
     */
    public function persist(array $rows, Examen $examen): array
    {
        $procesados = 0;
        $creados = 0;
        $actualizados = 0;

        // duplicados en el archivo por orden y por nombre
        $seenOrders = [];
        $seenNames = [];

        foreach ($rows as $row) {
            $procesados++;

            $validator = Validator::make($row, [
                'nombre' => ['required', 'string'],
                'orden_relativo' => ['required', 'integer', 'min:1'],
            ]);
            if ($validator->fails()) {
                $excelRow = $row['_excel_row'] ?? '?';
                $sheet = $row['_excel_sheet'] ?? '?';
                $firstErr = $validator->errors()->first();
                throw new \RuntimeException("Error en la hoja {$sheet}, fila {$excelRow}: {$firstErr}");
            }

            $nombre = trim($row['nombre']);
            $orden = (int) $row['orden_relativo'];

            // Duplicados en el archivo
            $nameKey = mb_strtolower($nombre);
            if (isset($seenNames[$nameKey])) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: el nombre de sección '{$nombre}' está duplicado en el archivo.");
            }
            $seenNames[$nameKey] = true;

            if (isset($seenOrders[$orden])) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: el 'orden' {$orden} está duplicado en el archivo.");
            }
            $seenOrders[$orden] = true;

            // 1) Buscar por (examen, nombre) — identidad principal
            $seccion = Seccion::where('examen_id', $examen->id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->first();

            if (! $seccion) {
                // 2) Buscar por (examen, orden_relativo)
                $seccion = Seccion::where('examen_id', $examen->id)
                    ->where('orden_relativo', $orden)
                    ->first();
            }

            $isNew = false;

            if ($seccion) {
                // Chequeo de colisión de orden: si quiero mover a $orden, y ese orden lo tiene otro id
                $colision = Seccion::where('examen_id', $examen->id)
                    ->where('orden_relativo', $orden)
                    ->where('id', '!=', $seccion->id)
                    ->first();

                if ($colision) {
                    $excelRow = $row['_excel_row'] ?? '?';
                    throw new \RuntimeException("Error en la fila {$excelRow}: el 'orden' {$orden} ya está ocupado por la sección '{$colision->nombre}'.");
                }

                // Update
                $seccion->nombre = $nombre;
                $seccion->orden_relativo = $orden;
                $seccion->examen_id = $examen->id;
                $seccion->save();
                $actualizados++;
            } else {
                // Alta
                // Chequeo de colisión de orden por las dudas
                $colision = Seccion::where('examen_id', $examen->id)
                    ->where('orden_relativo', $orden)
                    ->first();
                if ($colision) {
                    $excelRow = $row['_excel_row'] ?? '?';
                    throw new \RuntimeException("Error en la fila {$excelRow}: el 'orden' {$orden} ya está ocupado por la sección '{$colision->nombre}'.");
                }

                $seccion = new Seccion;
                $seccion->nombre = $nombre;
                $seccion->orden_relativo = $orden;
                $seccion->examen_id = $examen->id;
                $seccion->save();
                $creados++;
                $isNew = true;
            }
        }

        return compact('procesados', 'creados', 'actualizados');
    }

    /**
     * Encabezados flexibles (en minúsculas, sin acentos/signos):
     *   - nombre: ['nombre','título','titulo','seccion','sección','nombre seccion','nombre sección']
     *   - orden:  ['orden','orden relativo','orden_relativo','posicion','posición','pos','nro','numero','número','n']
     */
    private function normalizeSheet(array $sheetRows): array
    {
        if (! is_array($sheetRows) || count($sheetRows) === 0) {
            return [];
        }

        // 1) Detectar encabezado
        [$map, $headerIdx] = $this->detectHeader($sheetRows);
        if (! $map) {
            return [];
        }

        // 2) Parsear filas de datos con cortes amigables
        $out = [];
        $seenData = false;
        $blankStreak = 0;

        for ($i = $headerIdx + 1; $i < count($sheetRows); $i++) {
            $r = $sheetRows[$i] ?? [];
            if (! is_array($r)) {
                continue;
            }

            // corte por filas vacías una vez que empezó el bloque
            if ($this->isRowEmpty($r)) {
                if ($seenData) {
                    $blankStreak++;
                    if ($blankStreak >= 1) {
                        break;
                    }
                }

                continue;
            }

            // tomar celdas por mapa (por índice)
            $nombre = $this->getCellByMap($r, $map, 'nombre');
            $orden = $this->getCellByMap($r, $map, 'orden_relativo');

            // ¿parece fila de datos?
            $looksLikeData = ($this->toString($nombre) !== null) || ($this->toString($orden) !== null);
            if (! $looksLikeData) {
                if ($seenData) {
                    break;
                }

                continue;
            }

            $seenData = true;
            $blankStreak = 0;

            $out[] = [
                'nombre' => $this->toString($nombre),
                'orden_relativo' => $this->toInt($orden),
                '_excel_row' => $i + 1,
            ];
        }

        return array_values(array_filter($out, fn ($r) => $r['nombre'] || $r['orden_relativo']
        ));
    }

    /**
     * Detecta encabezado dentro de las primeras ~120 filas, devuelve [map, idxHeader].
     */
    private function detectHeader(array $sheetRows): array
    {
        $syn = $this->headerSynonyms();

        $limit = min(120, count($sheetRows));
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

            if ($this->headerIsSufficient($map)) {
                return [$map, $i];
            }
        }

        // Fallback: aceptar al menos 1 campo clave y el otro a proximidad (col siguiente)
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

            if (isset($map['nombre']) || isset($map['orden_relativo'])) {
                return [$map, $i];
            }
        }

        return [null, null];
    }

    private function headerIsSufficient(array $map): bool
    {
        return isset($map['nombre']) && isset($map['orden_relativo']);
    }

    private function headerSynonyms(): array
    {
        $aliases = [
            'nombre' => [
                'nombre', 'titulo', 'título', 'seccion', 'sección', 'nombre seccion', 'nombre sección',
            ],
            'orden_relativo' => [
                'orden', 'orden relativo', 'orden_relativo', 'posicion', 'posición', 'pos', 'nro', 'numero', 'número', 'n',
            ],
        ];

        foreach ($aliases as $k => $vals) {
            $aliases[$k] = array_values(array_unique(array_map(fn ($v) => $this->norm($v), $vals)));
        }

        return $aliases;
    }

    // =========================
    //        Utilidades
    // =========================

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
        if ($v === null) {
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
        $s = Str::of($s)->ascii()->lower()->value();
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim($s);
    }
}
