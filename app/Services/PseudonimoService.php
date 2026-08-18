<?php

namespace App\Services;

use App\Models\Examen;
use App\Models\Pseudonimo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PseudonimoService
{
    /**
     * Pseudónimo (string) que usa un estudiante en un examen.
     */
    public function pseudonimoParaExamen(User $estudiante, Examen $examen): ?string
    {
        return $estudiante->pseudonimos()
            ->wherePivot('examen_id', $examen->id)
            ->limit(1)
            ->value('pseudonimos.nombre');
    }

    /**
     * Estudiante asociado a un pseudónimo dentro de un examen.
     */
    public function getEstudianteByPseudonimo(Examen $examen, string $pseudonimo): ?User
    {
        return User::whereHas('pseudonimos', function ($q) use ($examen, $pseudonimo) {
            $q->where('pseudonimos.nombre', $pseudonimo)
                ->where('examen_estudiante_pseudonimo.examen_id', $examen->id);
        })->first();
    }

    /**
     * Asigna el próximo pseudónimo libre del examen al estudiante.
     * - Evita cache usando Query Builder puro.
     * - Usa lockForUpdate para evitar carreras.
     * - Reintenta si cae en 23000 (carrera extrema).
     */
    public function asignarPseudonimo(User $user, Examen $examen): ?Pseudonimo
    {
        return DB::transaction(function () use ($user, $examen) {
            // Si ya tiene, devolvémoslo.
            if ($actual = $user->pseudonimos()->wherePivot('examen_id', $examen->id)->first()) {
                return $actual;
            }

            $buscarLibre = function () use ($examen) {
                // Elegimos un pseudónimo no usado en ese examen
                return DB::table('pseudonimos')
                    ->leftJoin('examen_estudiante_pseudonimo as eep', function ($join) use ($examen) {
                        $join->on('eep.pseudonimo_id', '=', 'pseudonimos.id')
                            ->where('eep.examen_id', '=', $examen->id);
                    })
                    ->whereNull('eep.id')
                    ->orderBy('pseudonimos.id')
                    ->lockForUpdate()          // fila bloqueada hasta fin de TX
                    ->value('pseudonimos.id'); // solo el id
            };

            $id = $buscarLibre();
            if (! $id) {
                return null; // o lanzar excepción si preferís
            }

            // Reintento simple ante 23000 por carrera extrema.
            for ($i = 0; $i < 3; $i++) {
                try {
                    $user->pseudonimos()->attach($id, ['examen_id' => $examen->id]);

                    return Pseudonimo::find($id);
                } catch (QueryException $e) {
                    // 23000 = violación de índice único (alguien lo tomó en paralelo)
                    if ($e->getCode() !== '23000' || $i === 2) {
                        throw $e;
                    }
                    $id = $buscarLibre();
                    if (! $id) {
                        return null;
                    }
                }
            }

            // No debería llegar acá
            return null;
        });
    }

    /**
     * Libera cualquier pseudónimo del estudiante en el examen.
     */
    public function liberarPseudonimo(User $user, Examen $examen): void
    {
        DB::transaction(function () use ($user, $examen) {
            $user->pseudonimos()
                ->wherePivot('examen_id', $examen->id)
                ->detach();
        });
    }
}
