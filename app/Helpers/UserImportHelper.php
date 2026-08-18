<?php

namespace App\Helpers;

use App\Enums\Roles;
use App\Models\Examen;
use App\Models\User;
use App\Services\PseudonimoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserImportHelper
{
    public function __construct(private PseudonimoService $pseudonimoService) {}

    /**
     * Lee el archivo Excel (todas las hojas) y devuelve filas normalizadas:
     * [
     *   ['nombre','apellido','legajo','dni','email','_excel_row','_excel_sheet'],
     *   ...
     * ]
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
     * Persiste filas normalizadas. Devuelve contadores.
     * Debe ejecutarse dentro de una transacción del controller.
     */
    public function persist(array $rows, string $rol, ?Examen $examen): array
    {
        $creados = 0;
        $actualizados = 0;
        $procesados = 0;

        foreach ($rows as $row) {
            $procesados++;

            // Validación por fila
            $validator = Validator::make($row, [
                'nombre' => ['required', 'string'],
                'apellido' => ['required', 'string'],
                'dni' => ['required', 'string'],
                'email' => ['nullable', 'email'],
                'legajo' => ['nullable', 'string'],
            ], [], [
                'dni' => 'número de documento',
            ]);

            if ($validator->fails()) {
                $excelRow = $row['_excel_row'] ?? '?';
                $sheet = $row['_excel_sheet'] ?? '?';
                $firstErr = $validator->errors()->first();
                throw new \RuntimeException("Error en la hoja {$sheet}, fila {$excelRow}: {$firstErr}");
            }

            // Upsert (DNI > LEGAJO > EMAIL)
            $user = $this->findExistingUser($row);

            $cleanEmail = $this->normalizeEmail($row['email'] ?? null);
            $payload = [
                'nombre' => trim($row['nombre']),
                'apellido' => trim($row['apellido']),
                'email' => $cleanEmail,
                'legajo' => $row['legajo'] ?? null,
            ];

            if ($user) {
                // No tocar 'usuario' existente
                $user->fill(array_filter($payload, fn ($v) => ! is_null($v) && $v !== ''));
                $user->save();
                $actualizados++;
            } else {
                $new = new User;
                $new->dni = (string) $row['dni'];
                $new->nombre = $payload['nombre'];
                $new->apellido = $payload['apellido'];
                $new->email = $payload['email'] ?? null;
                $new->legajo = $payload['legajo'] ?? null;

                // Usuario único generado
                $new->usuario = $this->ensureUsername($new->nombre, $new->apellido);
                $new->password = Hash::make((string) $row['dni']);
                $new->save();

                $user = $new;
                $creados++;
            }

            // Rol global (spatie/permission) → agrega sin pisar otros
            if (method_exists($user, 'assignRole') && ! $user->hasRole($rol)) {
                $user->assignRole($rol);
            }

            // Vincular a examen en pivot examen_user_roles si corresponde
            if ($examen) {
                $user->syncRolesPorExamen($examen->id, [$rol]);

                // Si el rol importado es estudiante → asignar pseudónimo en ese examen
                if ($rol === Roles::ESTUDIANTE->value) {
                    $this->pseudonimoService->asignarPseudonimo($user, $examen);
                }
            }
        }

        return compact('procesados', 'creados', 'actualizados');
    }

    /**
     * Devuelve filas normalizadas desde una hoja cruda.
     */
    private function normalizeSheet(array $sheetRows): array
    {
        if (! is_array($sheetRows) || count($sheetRows) === 0) {
            return [];
        }

        // Detectar encabezado flexible
        $map = null;
        $headerIndex = null;

        $limit = min(120, count($sheetRows));
        for ($i = 0; $i < $limit; $i++) {
            $row = $sheetRows[$i] ?? [];
            if (! is_array($row) || $this->isRowEmpty($row)) {
                continue;
            }

            $candidateMap = $this->tryMapHeader($row);
            if ($candidateMap && $this->headerIsSufficient($candidateMap)) {
                $map = $candidateMap;
                $headerIndex = $i;
                break;
            }
        }

        if ($map === null || $headerIndex === null) {
            // Fallback: plantilla Filomena exacta
            $fallback = $this->findExactHeaderRow($sheetRows, ['nombre', 'apellido', 'legajo', 'dni', 'email']);
            if (! is_null($fallback)) {
                $map = $this->buildColumnMap($sheetRows[$fallback], [
                    'nombre' => ['nombre'],
                    'apellido' => ['apellido'],
                    'legajo' => ['legajo'],
                    'dni' => ['dni'],
                    'email' => ['email'],
                ]);
                $headerIndex = $fallback;
            } else {
                return [];
            }
        }

        // Parsear filas a partir de header
        $out = [];
        $seenData = false;  // ya vimos al menos una fila "de datos"
        $blankStreak = 0;      // rachas de filas vacías luego de empezar la tabla

        for ($i = $headerIndex + 1; $i < count($sheetRows); $i++) {
            $r = $sheetRows[$i] ?? [];
            if (! is_array($r)) {
                continue;
            }

            // 0) Corte por filas vacías consecutivas después de ver datos
            if ($this->isRowEmpty($r)) {
                if ($seenData) {
                    $blankStreak++;
                    // con una sola fila vacía después del listado ya cortamos (podés poner 2 si preferís)
                    if ($blankStreak >= 1) {
                        break;
                    }
                }

                continue;
            }

            // 1) Corte por "footers" típicos de Guaraní (fila 57: "Fecha generación del reporte", etc.)
            $firstCell = $this->norm($this->toString($r[0] ?? '')); // columna A normalizada
            if ($firstCell !== '') {
                if (
                    str_starts_with($firstCell, 'fecha generacion del reporte') ||
                    str_starts_with($firstCell, 'usuario:') ||
                    str_starts_with($firstCell, 'materia:') ||
                    str_starts_with($firstCell, 'sede:') ||
                    str_starts_with($firstCell, 'comision:') ||
                    str_starts_with($firstCell, 'comisión:') ||
                    str_starts_with($firstCell, 'año:')
                ) {
                    // Son textos de encabezado o pie que aparecen fuera del bloque de datos → cortamos
                    if ($seenData) {
                        break;
                    }     // si ya veníamos leyendo datos, terminamos
                    else {
                        continue;
                    }            // si aparece arriba de la tabla, lo ignoramos
                }
            }

            // 2) Tomamos celdas por mapa
            $legajo = $this->getCellByMap($r, $map, 'legajo');
            $email = $this->getCellByMap($r, $map, 'email');

            $nombre = $this->getCellByMap($r, $map, 'nombre');
            $apellido = $this->getCellByMap($r, $map, 'apellido');

            // Nombre completo en una sola columna (Guaraní)
            if ((! $nombre || ! $apellido) && isset($map['alumno'])) {
                $alumno = $this->getCellByMap($r, $map, 'alumno');
                [$ap, $no] = $this->splitAlumno($this->toString($alumno));
                $apellido = $apellido ?: $ap;
                $nombre = $nombre ?: $no;
            }
            foreach (['persona', 'apellido_nombre', 'nombre_apellido'] as $combo) {
                if ((! $nombre || ! $apellido) && isset($map[$combo])) {
                    $val = $this->getCellByMap($r, $map, $combo);
                    [$ap, $no] = $this->splitAlumno($this->toString($val));
                    $apellido = $apellido ?: $ap;
                    $nombre = $nombre ?: $no;
                }
            }

            // 3) DNI: priorizar "Número" si la col 'dni' no tiene dígitos (p.ej. "DNI")
            $dniFromDniCol = $this->getCellByMap($r, $map, 'dni');
            $dniClean = $this->cleanDoc($dniFromDniCol);
            if ((! $dniClean || $dniClean === '') && isset($map['numero'])) {
                $dniFromNumeroCol = $this->getCellByMap($r, $map, 'numero');
                $dniCleanAlt = $this->cleanDoc($dniFromNumeroCol);
                if ($dniCleanAlt) {
                    $dniClean = $dniCleanAlt;
                }
            }
            $dni = $dniClean;

            // 4) ¿Esta fila parece de datos?
            $looksLikeData = $dni || $nombre || $apellido || $legajo || $email;
            if (! $looksLikeData) {
                // Si ya empezamos a leer datos, una fila no-dato implica fin del bloque
                if ($seenData) {
                    break;
                }

                continue;
            }

            $seenData = true;
            $blankStreak = 0;

            // 5) Normalizaciones y push
            $out[] = [
                'nombre' => $this->toString($nombre),
                'apellido' => $this->toString($apellido),
                'legajo' => $this->toString($legajo),
                'dni' => $this->toString($dni),
                'email' => $this->normalizeEmail($email),
                '_excel_row' => $i + 1,
            ];
        }

        return array_values(array_filter($out, function ($r) {
            // conservamos solo filas con algo de contenido útil
            return $r['dni'] || $r['nombre'] || $r['apellido'] || $r['email'] || $r['legajo'];
        }));
    }

    /**
     * Intenta mapear encabezados a campos estándar usando sinónimos.
     */
    private function tryMapHeader(array $headerRow): ?array
    {
        $syn = $this->headerSynonyms();
        $map = [];

        foreach ($headerRow as $idx => $cell) {
            $label = $this->norm(mb_strtolower($this->toString($cell) ?? ''));
            if ($label === '') {
                continue;
            }

            foreach ($syn as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if ($label === $alias) {
                        $map[$field] = $idx;
                        break 2;
                    }
                    // Coincidencia contiene, ej: "Identificación (Nro. Documento)"
                    if (str_contains($label, $alias) && ! isset($map[$field])) {
                        $map[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map ?: null;
    }

    /**
     * ¿El header detectado es suficiente para extraer identidad?
     */
    private function headerIsSufficient(array $map): bool
    {
        $hasDni = isset($map['dni']) || isset($map['numero']);
        $hasNameSplit = isset($map['nombre']) && isset($map['apellido']);
        $hasAlumno = isset($map['alumno']) || isset($map['persona']) || isset($map['apellido_nombre']) || isset($map['nombre_apellido']);

        return $hasDni && ($hasNameSplit || $hasAlumno);
    }

    /**
     * Diccionario de encabezados (normalizados) para Filomena y Guaraní 2/3.
     */
    private function headerSynonyms(): array
    {
        $aliases = [
            'nombre' => ['nombre', 'nombres'],
            'apellido' => ['apellido', 'apellidos'],

            // Columna combinada
            'alumno' => ['alumno', 'apellido,nombres', 'apellido y nombre', 'nombre y apellido'],
            'persona' => ['persona'],
            'apellido_nombre' => ['apellido y nombre'],
            'nombre_apellido' => ['nombre y apellido'],

            'legajo' => ['legajo'],

            // Documento
            'dni' => [
                'dni', 'documento', 'nro documento', 'nro. documento', 'numero documento', 'número documento',
                'identificacion', 'identificación', 'tipo y nro documento', 'tipo y numero documento', 'tipo y número documento',
            ],
            'numero' => ['numero', 'número', 'nro', 'nro.', 'nro documento', 'nro. documento'],

            'email' => ['email', 'e-mail', 'e_mail', 'correo', 'correo electronico', 'correo electrónico'],
        ];

        foreach ($aliases as $k => $vals) {
            $aliases[$k] = array_values(array_unique(array_map(fn ($v) => $this->norm($v), $vals)));
        }

        return $aliases;
    }

    private function findExactHeaderRow(array $rows, array $expected): ?int
    {
        $expectedLower = array_map(fn ($k) => mb_strtolower($k), $expected);
        $limit = min(120, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $cells = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $rows[$i] ?? []);
            $cellsLower = array_map(fn ($v) => is_string($v) ? mb_strtolower($v) : $v, $cells);
            $ok = true;
            foreach ($expectedLower as $needle) {
                if (! in_array($needle, $cellsLower, true)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $i;
            }
        }

        return null;
    }

    private function buildColumnMap(array $headerRow, array $wanted): array
    {
        $map = [];
        foreach ($wanted as $key => $aliases) {
            $map[$key] = null;
            foreach ($headerRow as $idx => $cell) {
                $label = is_string($cell) ? mb_strtolower(trim($cell)) : null;
                if (! $label) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($label === mb_strtolower($alias)) {
                        $map[$key] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
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

    private function norm(?string $s): string
    {
        $s = $s ?? '';
        $s = Str::of($s)->ascii()->lower()->value();
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim($s);
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

    private function normalizeEmail($v): ?string
    {
        $s = $this->toString($v);

        return $s ? mb_strtolower($s) : null;
    }

    /**
     * "Apellido, Nombres" | "Apellido y Nombre" | "Nombre y Apellido" | "Apellido - DNI NNNN"
     */
    private function splitAlumno(?string $alumno): array
    {
        $alumno = $alumno ? trim($alumno) : '';
        if ($alumno === '') {
            return ['', ''];
        }

        if (str_contains($alumno, ',')) {
            [$ap, $no] = array_map('trim', explode(',', $alumno, 2));

            return [$ap, $no];
        }

        if (preg_match('/^(.+?)\s+y\s+(.+)$/iu', $alumno, $m)) {
            $left = trim($m[1]);
            $right = trim($m[2]);

            return [$left, $right];
        }

        if (preg_match('/^(.+?)\s*-\s*(dni|doc)[^0-9]*([0-9]+)/iu', $alumno, $m)) {
            return [trim($m[1]), ''];
        }

        return [$alumno, ''];
    }

    /**
     * Busca usuario por DNI > LEGAJO > EMAIL (incluye soft-deleted).
     */
    private function findExistingUser(array $row): ?User
    {
        $dni = $row['dni'] ?? null;
        $legajo = $row['legajo'] ?? null;
        $email = $row['email'] ?? null;

        $q = User::query()->withTrashed();
        $user = null;

        if ($dni) {
            $user = $q->where('dni', (string) $dni)->first();
        }
        if ($legajo) {
            $user = $q->where('legajo', $legajo)->first();
        }
        if ($email) {
            $user = $q->where('email', $email)->first();
        }

        // Si el usuario existe y está soft-deleted, restaurarlo antes de actualizarlo/vincularlo
        if ($user && method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
        }

        return $user;
    }

    /**
     * Genera un 'usuario' único tipo "napellido", "napellido2", …
     */
    private function ensureUsername(string $nombre, string $apellido): string
    {
        $nombre = Str::of($nombre)->ascii()->lower()->value();
        $apellido = Str::of($apellido)->ascii()->lower()->value();

        $base = preg_replace('/[^a-z0-9]/', '', mb_substr($nombre, 0, 1).$apellido);
        if (! $base) {
            $base = 'user';
        }

        $candidate = $base;
        $i = 1;
        while (User::withTrashed()->where('usuario', $candidate)->exists()) {
            $candidate = $base.$i;
            $i++;
            if ($i > 9999) {
                $candidate = $base.Str::random(4);
                if (! User::withTrashed()->where('usuario', $candidate)->exists()) {
                    break;
                }
            }
        }

        return $candidate;
    }
}
