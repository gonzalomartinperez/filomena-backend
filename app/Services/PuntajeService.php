<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Caso;
use App\Models\Examen;
use App\Models\Item;
use App\Models\Pregunta;
use App\Models\Seccion;
use App\Models\User;

/**
 * Service de puntajes con interfaz explícita, SRP y nombres consistentes.
 *
 * Convenciones:
 *  - maxX(): tope máximo del alcance X (sumatoria de valores de ítems).
 *  - puntajeObtenidoX(): puntaje logrado por el estudiante (incluye defensas donde corresponde).
 *  - En Ítem / Pregunta no hay defensas.
 *  - En Examen, Caso, Caso+Sección y Sección (global) se incluyen defensas, siempre con tope:
 *      defensa aplicada = min(defensa registrada, máximo disponible = maxX - ítems logrados en X).
 *
 * Performance:
 *  - loadMissing(...) para evitar N+1 sin sobrecargar.
 *  - Reutiliza relaciones del estudiante: notasItems / notasDefensas.
 */
class PuntajeService
{
    /* =========================================================================
     | EXAMEN  (ítems + defensa topeada)
     * ====================================================================== */

    /** Máximo del examen (suma de todos los ítems del examen). */
    public function maxExamen(Examen $examen): float
    {
        $examen->loadMissing('casos.preguntas.items');

        return (float) $examen->casos
            ->flatMap->preguntas
            ->flatMap->items
            ->sum('valor');
    }

    /**
     * Puntaje obtenido del examen por un estudiante (ÍTEMS + DEFENSA con tope).
     * Total = items + min(defensaRegistradaEnExamen, maxExamen - items).
     */
    public function puntajeObtenidoExamen(Examen $examen, User $estudiante): float
    {
        $items = $this->puntajeObtenidoExamenSoloItems($examen, $estudiante);
        $def = $this->puntajeObtenidoDefensaExamen($examen, $estudiante);
        $max = $this->maxExamen($examen);

        $total = $items + $def;

        return $total > $max ? $max : $total;
    }

    /** Máximo de defensa aplicable en el examen (= maxExamen - items logrados), nunca negativo. */
    public function maxDefensaExamen(Examen $examen, User $estudiante): float
    {
        $max = $this->maxExamen($examen);
        $items = $this->puntajeObtenidoExamenSoloItems($examen, $estudiante);

        $restante = $max - $items;

        return $restante > 0 ? (float) $restante : 0.0;
    }

    /** Defensa efectivamente aplicada en el examen (= min(registrada, maxDefensaExamen)). */
    public function puntajeObtenidoDefensaExamen(Examen $examen, User $estudiante): float
    {
        $registrada = $this->defensaExamenRegistrada($examen, $estudiante);
        $tope = $this->maxDefensaExamen($examen, $estudiante);

        return (float) min($registrada, $tope);
    }

    /* =========================================================================
     | CASO  (ítems + defensa topeada)
     * ====================================================================== */

    /** Máximo del caso (suma de valores de sus ítems). */
    public function maxCaso(Caso $caso): float
    {
        $caso->loadMissing('preguntas.items');

        return (float) $caso->preguntas
            ->flatMap->items
            ->sum('valor');
    }

    /**
     * Puntaje obtenido en el caso (ÍTEMS + DEFENSA con tope).
     * Total = items + min(defensaRegistradaEnCaso, maxCaso - items).
     */
    public function puntajeObtenidoCaso(Caso $caso, User $estudiante): float
    {
        $items = $this->puntajeObtenidoCasoSoloItems($caso, $estudiante);
        $def = $this->puntajeObtenidoDefensaCaso($caso, $estudiante);
        $max = $this->maxCaso($caso);

        $total = $items + $def;

        return $total > $max ? $max : $total;
    }

    /** Máximo de defensa aplicable en el caso (= maxCaso - items logrados), nunca negativo. */
    public function maxDefensaCaso(Caso $caso, User $estudiante): float
    {
        $max = $this->maxCaso($caso);
        $items = $this->puntajeObtenidoCasoSoloItems($caso, $estudiante);

        $restante = $max - $items;

        return $restante > 0 ? (float) $restante : 0.0;
    }

    /** Defensa efectivamente aplicada en el caso (= min(registrada, maxDefensaCaso)). */
    public function puntajeObtenidoDefensaCaso(Caso $caso, User $estudiante): float
    {
        $registrada = $this->defensaCasoRegistrada($caso, $estudiante);
        $tope = $this->maxDefensaCaso($caso, $estudiante);

        return (float) min($registrada, $tope);
    }

    /* =========================================================================
     | CASO + SECCIÓN  (ítems + defensa topeada por cada caso-sección)
     * ====================================================================== */

    /** Máximo del caso acotado a una sección. */
    public function maxCasoSeccion(Caso $caso, Seccion $seccion): float
    {
        $caso->loadMissing('preguntas.items');

        return (float) $caso->preguntas
            ->flatMap->items
            ->where('seccion_id', $seccion->id)
            ->sum('valor');
    }

    /**
     * Puntaje obtenido en el caso para una sección (ÍTEMS + DEFENSA con tope por caso-sección).
     * Total = items + min(defensaRegistradaEnCasoSeccion, maxCasoSeccion - items).
     */
    public function puntajeObtenidoCasoSeccion(Caso $caso, Seccion $seccion, User $estudiante): float
    {
        $items = $this->puntajeObtenidoCasoSeccionSoloItems($caso, $seccion, $estudiante);
        $def = $this->puntajeObtenidoDefensaCasoSeccion($caso, $seccion, $estudiante);
        $max = $this->maxCasoSeccion($caso, $seccion);

        $total = $items + $def;

        return $total > $max ? $max : $total;
    }

    /** Máximo de defensa aplicable en el caso-sección (= maxCasoSeccion - items logrados), nunca negativo. */
    public function maxDefensaCasoSeccion(Caso $caso, Seccion $seccion, User $estudiante): float
    {
        $max = $this->maxCasoSeccion($caso, $seccion);
        $items = $this->puntajeObtenidoCasoSeccionSoloItems($caso, $seccion, $estudiante);

        $restante = $max - $items;

        return $restante > 0 ? (float) $restante : 0.0;
    }

    /** Defensa aplicada en el caso-sección (= min(registrada, maxDefensaCasoSeccion)). */
    public function puntajeObtenidoDefensaCasoSeccion(Caso $caso, Seccion $seccion, User $estudiante): float
    {
        $registrada = $this->defensaCasoSeccionRegistrada($caso, $seccion, $estudiante);
        $tope = $this->maxDefensaCasoSeccion($caso, $seccion, $estudiante);

        return (float) min($registrada, $tope);
    }

    /* =========================================================================
     | PREGUNTA  (solo ítems)
     * ====================================================================== */

    public function maxPregunta(Pregunta $pregunta): float
    {
        $pregunta->loadMissing('items');

        return (float) $pregunta->items->sum('valor');
    }

    public function puntajeObtenidoPregunta(Pregunta $pregunta, User $estudiante): float
    {
        $pregunta->loadMissing('items');

        $sum = 0.0;
        foreach ($pregunta->items as $item) {
            $sum += $this->puntajeObtenidoItem($item, $estudiante);
        }

        return $sum;
    }

    public function maxPreguntaSeccion(Pregunta $pregunta, Seccion $seccion): float
    {
        $pregunta->loadMissing('items');

        return (float) $pregunta->items
            ->where('seccion_id', $seccion->id)
            ->sum('valor');
    }

    public function puntajeObtenidoPreguntaSeccion(Pregunta $pregunta, Seccion $seccion, User $estudiante): float
    {
        $pregunta->loadMissing('items');

        $sum = 0.0;
        foreach ($pregunta->items as $item) {
            if ((int) $item->seccion_id !== (int) $seccion->id) {
                continue;
            }
            $sum += $this->puntajeObtenidoItem($item, $estudiante);
        }

        return $sum;
    }

    /* =========================================================================
     | ÍTEM  (solo ítems)
     * ====================================================================== */

    public function maxItem(Item $item): float
    {
        return (float) $item->valor;
    }

    public function puntajeObtenidoItem(Item $item, User $estudiante): float
    {
        $estudiante->loadMissing('notasItems');

        $nota = $estudiante->notasItems->firstWhere('item_id', $item->id);
        $aprobado = $nota && (bool) $nota->aprobado;

        return $aprobado ? (float) $item->valor : 0.0;
    }

    /* =========================================================================
     | SECCIÓN (GLOBAL, suma por todos los casos que contengan esa sección)
     |        (ítems + defensas aplicadas por cada caso-sección)
     * ====================================================================== */

    /** Máximo global de una sección (suma del valor de todos los ítems de esa sección en todo el sistema). */
    public function maxSeccion(Seccion $seccion): float
    {
        return (float) Item::query()
            ->where('seccion_id', $seccion->id)
            ->sum('valor');
    }

    /**
     * Puntaje global de una sección por estudiante.
     * Suma, para cada caso que contenga la sección, el puntaje obtenido en ese caso-sección
     * (ítems + defensa aplicada topeada).
     */
    public function puntajeObtenidoSeccion(Seccion $seccion, User $estudiante): float
    {
        $casos = $this->casosItemsSeccion($seccion);

        $total = 0.0;
        foreach ($casos as $caso) {
            $total += $this->puntajeObtenidoCasoSeccion($caso, $seccion, $estudiante);
        }
        // Por construcción, cada caso-sección aplica su propio tope; la suma no supera maxSeccion.
        // (Opcional) reforzar tope global:
        $max = $this->maxSeccion($seccion);

        return $total > $max ? $max : $total;
    }

    /** Máximo de defensa global disponible para una sección, sumando cada caso-sección. */
    public function maxDefensaSeccion(Seccion $seccion, User $estudiante): float
    {
        $casos = $this->casosItemsSeccion($seccion);

        $sum = 0.0;
        foreach ($casos as $caso) {
            $sum += $this->maxDefensaCasoSeccion($caso, $seccion, $estudiante);
        }

        return $sum;
    }

    /**
     * Defensa aplicada global en la sección (suma de min(registrada, tope) por cada caso-sección).
     */
    public function puntajeObtenidoDefensaSeccion(Seccion $seccion, User $estudiante): float
    {
        $casos = $this->casosItemsSeccion($seccion);

        $sum = 0.0;
        foreach ($casos as $caso) {
            $sum += $this->puntajeObtenidoDefensaCasoSeccion($caso, $seccion, $estudiante);
        }

        return $sum;
    }

    /* =========================================================================
     | Helpers de puntajes obtenidos a partir de ítems (sin defensas)
     * ====================================================================== */

    /** Ítems logrados en el examen (sin defensas). */
    public function puntajeObtenidoExamenSoloItems(Examen $examen, User $estudiante): float
    {
        $examen->loadMissing('casos.preguntas.items');

        $sum = 0.0;
        foreach ($examen->casos as $caso) {
            $sum += $this->puntajeObtenidoCasoSoloItems($caso, $estudiante);
        }

        return $sum;
    }

    /** Ítems logrados en el caso (sin defensas). */
    public function puntajeObtenidoCasoSoloItems(Caso $caso, User $estudiante): float
    {
        $caso->loadMissing('preguntas.items');

        $sum = 0.0;
        foreach ($caso->preguntas as $pregunta) {
            $sum += $this->puntajeObtenidoPregunta($pregunta, $estudiante);
        }

        return $sum;
    }

    /** Ítems logrados en un caso-sección (sin defensas). */
    private function puntajeObtenidoCasoSeccionSoloItems(Caso $caso, Seccion $seccion, User $estudiante): float
    {
        $caso->loadMissing('preguntas.items');

        $sum = 0.0;
        foreach ($caso->preguntas as $pregunta) {
            $sum += $this->puntajeObtenidoPreguntaSeccion($pregunta, $seccion, $estudiante);
        }

        return $sum;
    }

    /** Suma de defensas registradas del estudiante en el examen (casos pertenecientes a ese examen). */
    private function defensaExamenRegistrada(Examen $examen, User $estudiante): float
    {
        $estudiante->loadMissing('notasDefensas');
        $examen->loadMissing('casos');

        $casoIds = $examen->casos->pluck('id')->all();

        return (float) $estudiante->notasDefensas
            ->whereIn('caso_id', $casoIds)
            ->sum('nota');
    }

    /** Suma de defensas registradas del estudiante en un caso (todas sus secciones). */
    private function defensaCasoRegistrada(Caso $caso, User $estudiante): float
    {
        $estudiante->loadMissing('notasDefensas');

        return (float) $estudiante->notasDefensas
            ->where('caso_id', $caso->id)
            ->sum('nota');
    }

    /** Defensa registrada del estudiante para un caso-sección (single o suma si hubiera varias filas). */
    private function defensaCasoSeccionRegistrada(Caso $caso, Seccion $seccion, User $estudiante): float
    {
        $estudiante->loadMissing('notasDefensas');

        return (float) $estudiante->notasDefensas
            ->where('caso_id', $caso->id)
            ->where('seccion_id', $seccion->id)
            ->sum('nota');
    }

    /** Casos que tienen ítems de la sección (con preguntas.items cargadas). */
    private function casosItemsSeccion(Seccion $seccion)
    {
        return Caso::query()
            ->whereHas('preguntas.items', fn ($q) => $q->where('seccion_id', $seccion->id))
            ->with(['preguntas.items'])
            ->get();
    }
}
