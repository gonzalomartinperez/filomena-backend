<?php

namespace App\Http\Requests\DefensaRequests;

use App\Http\Requests\ApiBaseRequest;
use App\Models\Caso;
use App\Models\Item;
use App\Models\Seccion;
use App\Models\User;
use App\Services\PuntajeService;
use Illuminate\Validation\ValidationException;

class StoreNotaDefensaRequest extends ApiBaseRequest
{
    /**
     * Reglas de forma del payload (sin I/O ni reglas de negocio con DB).
     */
    public function rules(): array
    {
        return [
            'defensas' => ['required', 'array', 'min:1'],
            'defensas.*.seccion_id' => ['required', 'integer', 'min:1'],
            'defensas.*.nota' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Valida reglas de negocio con contexto ya resuelto por el controller.
     * - Que la sección pertenezca al caso (al menos un ítem del caso en esa sección)
     * - Que la nota no supere el tope de defensa para el caso-sección-estudiante
     *
     * @throws ValidationException
     */
    public function validateBusinessWithContext(Caso $caso, User $estudiante): void
    {
        $data = $this->validated(); // ya pasó rules() de forma
        $rows = (array) ($data['defensas'] ?? []);

        if (empty($rows)) {
            // No hay nada que validar a nivel negocio
            return;
        }

        // 1) Secciones válidas para el caso (tienen al menos un ítem del caso)
        $idsSolicitados = collect($rows)
            ->pluck('seccion_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $seccionIdsValidos = Item::query()
            ->where('caso_id', $caso->id)
            ->whereIn('seccion_id', $idsSolicitados)
            ->distinct()
            ->pluck('seccion_id')
            ->all();

        $validas = array_flip($seccionIdsValidos);

        // 2) Tope por caso-sección-estudiante
        /** @var PuntajeService $puntajes */
        $puntajes = app(PuntajeService::class);

        $errores = [];

        foreach ($rows as $i => $row) {
            $sid = (int) ($row['seccion_id'] ?? 0);
            $nota = (float) ($row['nota'] ?? 0);

            // a) La sección debe pertenecer al caso (tener al menos un ítem)
            if (! $sid || ! isset($validas[$sid])) {
                $errores["defensas.$i.seccion_id"] = [
                    'La sección no pertenece al caso o no tiene ítems.',
                ];

                // Si la sección no es válida, no intentamos calcular tope
                continue;
            }

            $seccion = Seccion::query()->find($sid);
            if (! $seccion) {
                // La existencia de entidades la valida el controller,
                // pero si llegamos aquí y no está, protegemos igualmente.
                $errores["defensas.$i.seccion_id"] = [
                    'La sección indicada no existe.',
                ];

                continue;
            }

            // b) Tope de defensa para ese caso–sección–estudiante
            try {
                $max = (float) $puntajes->maxDefensaCasoSeccion($caso, $seccion, $estudiante);
            } catch (\Throwable $e) {
                // Si no podemos calcular, protegemos por el lado seguro
                $max = 0.0;
            }

            if ($nota > $max) {
                $errores["defensas.$i.nota"] = [
                    "La nota excede el máximo permitido para la sección ({$max}).",
                ];
            }
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }
}
