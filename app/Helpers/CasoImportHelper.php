<?php

namespace App\Helpers;

use App\Enums\Roles;
use App\Models\Caso;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class CasoImportHelper
{
    /**
     * Lee el Excel y devuelve filas normalizadas:
     * ['titulo','orden_relativo','dni_evaluador','_excel_row','_excel_sheet']
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
     * Valida: evaluador existe, tiene rol EVALUADOR y está en el examen (pivot).
     */
    public function persist(array $rows, Examen $examen): array
    {
        $procesados = 0;
        $creados = 0;
        $actualizados = 0;

        // duplicados en el archivo por orden_relativo (dentro del mismo Excel)
        $seenOrders = [];

        foreach ($rows as $row) {
            $procesados++;

            $validator = Validator::make($row, [
                'titulo' => ['required', 'string'],
                'orden_relativo' => ['required', 'integer', 'min:1'],
                'dni_evaluador' => ['required', 'string'],
            ], [], [
                'dni_evaluador' => 'DNI del evaluador',
            ]);

            if ($validator->fails()) {
                $excelRow = $row['_excel_row'] ?? '?';
                $sheet = $row['_excel_sheet'] ?? '?';
                $firstErr = $validator->errors()->first();
                throw new \RuntimeException("Error en la hoja {$sheet}, fila {$excelRow}: {$firstErr}");
            }

            $titulo = trim($row['titulo']);
            $orden = (int) $row['orden_relativo'];
            $dniEval = $this->cleanDoc($row['dni_evaluador']);

            // Duplicado de orden dentro del archivo
            if (isset($seenOrders[$orden])) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: el 'orden' {$orden} está duplicado en el archivo.");
            }
            $seenOrders[$orden] = true;

            // Evaluador por DNI
            $evaluador = User::where('dni', $dniEval)->first();
            if (! $evaluador) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: no existe un usuario con DNI {$dniEval} para 'Evaluador'.");
            }

            // Rol EVALUADOR (spatie/permission) usando el valor del Enum
            $roleValue = Roles::EVALUADOR->value;
            if (! method_exists($evaluador, 'hasRole') || ! $evaluador->hasRole($roleValue)) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: el usuario {$evaluador->dni} no tiene rol de EVALUADOR.");
            }

            // Asociación al examen
            $belongs = $examen->evaluadores()->where('users.id', $evaluador->id)->exists();
            if (! $belongs) {
                $excelRow = $row['_excel_row'] ?? '?';
                throw new \RuntimeException("Error en la fila {$excelRow}: el evaluador (DNI {$dniEval}) no está asociado al examen.");
            }

            // Upsert por (examen_id, orden_relativo)
            $caso = Caso::firstOrNew([
                'examen_id' => $examen->id,
                'orden_relativo' => $orden,
            ]);

            $isNew = ! $caso->exists;

            $caso->titulo = $titulo;
            $caso->evaluador_id = $evaluador->id;
            $caso->examen_id = $examen->id;
            $caso->orden_relativo = $orden;

            $caso->save();

            $isNew ? $creados++ : $actualizados++;
        }

        return compact('procesados', 'creados', 'actualizados');
    }

    /**
     * Formato estándar Filomena con encabezados flexibles:
     *   - título del caso:  ['titulo','título','nombre','caso','enunciado','descripcion','descripción']
     *   - orden relativo:   ['orden','orden relativo','orden_relativo','posición','posicion','pos','n°','nro','numero','número','orden caso']
     *   - evaluador (dni):  ['dni evaluador','evaluador','evaluador (dni)','dni','documento','nro documento','número documento','doc']
     * El encabezado puede estar en cualquier fila; puede haber texto arriba/abajo.
     */
    private function normalizeSheet(array $sheetRows): array
    {
        if (! is_array($sheetRows) || count($sheetRows) === 0) {
            return [];
        }

        // 1) Detectar encabezado flexible por sinónimos
        [$map, $headerIdx] = $this->detectHeader($sheetRows);
        if (! $map) {
            return [];
        } // no hay encabezado utilizable

        // 2) Parsear filas de datos hasta el primer corte
        $out = [];
        $seenData = false;
        $blankStreak = 0;

        for ($i = $headerIdx + 1; $i < count($sheetRows); $i++) {
            $r = $sheetRows[$i] ?? [];
            if (! is_array($r)) {
                continue;
            }

            // Corte por filas vacías una vez que empezó el bloque
            if ($this->isRowEmpty($r)) {
                if ($seenData) {
                    $blankStreak++;
                    if ($blankStreak >= 1) {
                        break;
                    }
                }

                continue;
            }

            // Si aparece texto de pie/encabezado fuera de la tabla, cortamos cuando ya vimos datos
            $firstCell = $this->norm($this->toString($r[0] ?? '') ?? '');
            if ($firstCell !== '' && $seenData) {
                if (str_starts_with($firstCell, 'fecha generacion del reporte') ||
                    str_starts_with($firstCell, 'usuario:') ||
                    str_starts_with($firstCell, 'fin') ||
                    str_starts_with($firstCell, 'nota:')) {
                    break;
                }
            }

            // Tomar celdas por mapa (por índice si no están en A|B|C)
            $titulo = $this->getCellByMap($r, $map, 'titulo');
            $orden = $this->getCellByMap($r, $map, 'orden_relativo');
            $eval = $this->getCellByMap($r, $map, 'dni_evaluador');

            // ¿Parece fila de datos?
            $looksLikeData = ($titulo !== null && $titulo !== '') ||
                             ($orden !== null && $orden !== '') ||
                             ($eval !== null && $eval !== '');
            if (! $looksLikeData) {
                if ($seenData) {
                    break;
                }

                continue;
            }

            $seenData = true;
            $blankStreak = 0;

            // Normalizaciones
            $out[] = [
                'titulo' => $this->toString($titulo),
                'orden_relativo' => $this->toInt($orden),
                'dni_evaluador' => $this->cleanDoc($eval), // admite "DNI 123..." o "123.456.789"
                '_excel_row' => $i + 1, // 1-based
            ];
        }

        // Filtrar filas totalmente vacías
        return array_values(array_filter($out, fn ($r) => $r['titulo'] || $r['orden_relativo'] || $r['dni_evaluador']
        ));
    }

    /**
     * Detecta el encabezado dentro de las primeras ~120 filas
     * y devuelve [mapaCampos, idxHeader].
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

        // Fallback: si no encontramos los 3, aceptamos filas que tengan al menos 2 campos claves
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

            if (count($map) >= 2 && isset($map['titulo']) && (isset($map['orden_relativo']) || isset($map['dni_evaluador']))) {
                return [$map, $i];
            }
        }

        return [null, null];
    }

    private function headerIsSufficient(array $map): bool
    {
        // Ideal: tengamos los 3 campos
        return isset($map['titulo']) && isset($map['orden_relativo']) && isset($map['dni_evaluador']);
    }

    /**
     * Sinónimos normalizados para cada campo esperado.
     * Todo en minúsculas, sin acentos, sin signos.
     */
    private function headerSynonyms(): array
    {
        $aliases = [
            'titulo' => [
                'titulo', 'título', 'nombre', 'caso', 'enunciado', 'descripcion', 'descripción', 'titulo del caso', 'nombre del caso',
            ],
            'orden_relativo' => [
                'orden', 'orden relativo', 'orden_relativo', 'posicion', 'posición', 'pos', 'n', 'n°', 'nro', 'numero', 'número', 'orden caso', 'orden del caso',
            ],
            'dni_evaluador' => [
                'dni evaluador', 'evaluador (dni)', 'evaluador', 'dni', 'documento', 'nro documento', 'nro. documento', 'numero documento', 'número documento', 'doc',
            ],
        ];

        // Normalizar alias
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

    private function cleanDoc($v): ?string
    {
        $s = $this->toString($v);
        if ($s === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $s);

        return $digits ?: null;
    }

    private function norm(?string $s): string
    {
        $s = $s ?? '';
        $s = Str::of($s)->ascii()->lower()->value();     // sin acentos
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);     // sin signos

        return trim($s);
    }
}
