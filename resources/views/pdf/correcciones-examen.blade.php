<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $examen->nombre ?? 'Examen' }} - Correcciones</title>

    <style>
        /* ---------- Base ---------- */
        @page { margin: 25mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: "Inter", DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; }
        h1, h2, h3, h4 { margin: 0; font-weight: 600; }

        /* ---------- Helpers ---------- */
        .w-60 { width: 60%; }
        .w-20 { width: 20%; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 12px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 12px; }
        .page-break { page-break-after: always; }
        .break-word { word-break: break-word; }

        /* ---------- Cards / grids ---------- */
        .grid2 { display: grid; grid-template-columns: 1fr 160px; gap: 20px; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; }

        /* ---------- Progress bar ---------- */
        .bar { height: 6px; border-radius: 4px; background: #e2e8f0; }
        .bar-inner { height: 6px; border-radius: 4px; background: #0ea5e9; }

        /* ---------- Badges + divisores (alineado con estructura) ---------- */
        .badge-section{
            display:inline-block;
            background:#e0f2fe;
            border-left:4px solid #0284c7;
            padding:1px 6px;
            font-size:10px;
            font-weight:600;
            color:#0f172a;
            line-height:1.2;
        }
        .title-meta{ margin-top:4px; }
        .divider { border-bottom: 1px solid #cbd5e1; margin: 8px 0; }

        /* ---------- Títulos ---------- */
        .case-title     { font-size: 16px; margin: 18px 0 2px; }
        .question-title { font-size: 14px; margin: 14px 0 2px; }
        .section-title  { font-size: 12px; margin: 8px 0 2px; font-weight: 700; }

        /* ---------- Tablas ---------- */
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 10px; }
        th { background: #f1f5f9; text-align: left; }
        .items-table thead { display: table-row-group; }

        /* ---------- Fila aprobada / reprobada ---------- */
        .row-pass td { background: #ecfdf5; }     /* verde claro */
        .row-fail td { background: #fef2f2; }     /* rojo claro  */
        .row-pass td:first-child { border-left: 4px solid #16a34a; }
        .row-fail td:first-child { border-left: 4px solid #dc2626; }

        /* ---------- Íconos aprobado/reprobado ---------- */
        .ic { font-weight: 700; font-size: 12px; display:inline-block; }
        .ic-pass { color:#16a34a; }  /* verde */
        .ic-fail { color:#dc2626; }  /* rojo  */
        .tag-scope { font-size: 10px; color:#475569; }

        /* ---------- Portada ---------- */
        .cover { height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .cover-logo  { font-size: 46px; font-weight: 700; letter-spacing: 1px; color: #0ea5e9; margin: 0 0 8px; }
        .exam-title  { font-size: 22px; font-weight: 600; color: #1e293b; margin: 0; }
        .accent-line { width: 60px; height: 3px; background: #0ea5e9; margin: 16px auto; }
        .stats       { font-size: 12px; color: #64748b; margin: 0; margin-top: 4px; }

        /* ---------- Consigna & Respuesta ---------- */
        .consigna {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 6px;
            background: #fff7ed;        /* sutil resaltado */
            border-left: 4px solid #fb923c;
            display: inline-block;
            text-align: justify;
        }
        .respuesta-estudiante {
            text-align: justify;         /* alinear respuesta del estudiante */
        }
    </style>
</head>
<body>
@php
/** Colecciones ordenadas (estándar) */
$casosBase = ($casos ?? $examen->casos ?? collect());
$casos     = $casosBase->sortBy('orden_relativo')->values();
$secciones = ($examen->secciones ?? collect())->sortBy('orden_relativo')->values();
$estudiantes   = $examen->estudiantes ?? collect();

/** Stats globales (estándar) */
$stats = [
    'casos'      => $casos->count(),
    'secciones'  => $secciones->count(),
    'preguntas'  => $casos->flatMap->preguntas?->count() ?? 0,
    'items'      => $casos->flatMap->preguntas?->flatMap->items?->count() ?? 0,
];

/** Totales globales / scope (estándar) */
$totales = [
    'examen_max' => $puntajeService->maxExamen($examen),
    'scope_max'  => isset($casosAsignados) && $casosAsignados ? $casosAsignados->sum(fn($c) => $puntajeService->maxCaso($c)) : $puntajeService->maxExamen($examen),
];

/** Evaluador */
$isEvaluator = (($scope ?? 'all') === 'evaluator');

$evaluador = null;
if ($isEvaluator && isset($casos) && $casos->count() > 0) {
    $evaluador = optional($casos->first())->evaluador;
}

/** Escape multilínea seguro */
$escapeMultiline = fn($texto) => nl2br(e(trim((string)$texto)));
@endphp

{{-- ---------- PORTADA ---------- --}}
<section class="cover">
    @if ($isEvaluator)
        <h1 class="cover-logo">Filomena</h1>
        <h2 class="exam-title">{{ $examen->nombre ?? 'Examen' }} - Correcciones evaluador</h2>
    @else
        <h1 class="cover-logo">Filomena</h1>
        <h2 class="exam-title">{{ $examen->nombre ?? 'Examen' }} - Correcciones</h2>
    @endif

    <div class="accent-line"></div>

    {{-- Fecha en formato día/mes/año para ambos casos --}}
    <p class="stats">Fecha: {{ optional($examen->fecha ?? null)?->format('d/m/Y') ?? '—' }}</p>

    {{-- Si es evaluador, mostrar "Evaluador: Apellido, Nombre" (si existe); caso contrario, omitir --}}
    @if ($isEvaluator && $evaluador)
        <p class="stats">Evaluador: {{ ($evaluador->apellido ?? '—') }}, {{ ($evaluador->nombre ?? '—') }}</p>
    @endif

    <p class="stats">Código: {{ $examen->codigo ?? ($examen->codigo ?? '—') }}</p>

    {{-- Línea de volumen: administrador vs. evaluador --}}
    @if ($isEvaluator)
        <p class="stats">
            Casos corregidos: {{ $stats['casos'] }} · Secciones corregidas: {{ $stats['secciones'] }} · Preguntas corregidas: {{ $stats['preguntas'] }} · Ítems corregidos: {{ $stats['items'] }}
        </p>
    @else
    <p class="stats">
        Casos: {{ $stats['casos'] }} · Secciones: {{ $stats['secciones'] }} · Preguntas: {{ $stats['preguntas'] }} · Ítems: {{ $stats['items'] }}
    </p>
    @endif

    {{-- Puntaje máximo: examen completo (admin) o solo casos asignados (evaluador) --}}
    <p class="stats">
        Puntaje máximo:
        @if ($isEvaluator)
            {{ $totales['scope_max'] }}
        @else
            {{ $totales['examen_max'] }}
        @endif
    </p>
</section>

{{-- ---------- CUERPO: Por estudiante ---------- --}}
@foreach ($estudiantes as $idxEstudiante => $estudiante)
    @php
        // Puntaje del estudiante en el scope
        $pseudonimo = $pseudonimoService->pseudonimoParaExamen($estudiante, $examen);
        $puntajeObtenido = $isEvaluator
            ? $casos->sum(fn($c) => $puntajeService->puntajeObtenidoCasoSoloItems($c, $estudiante))
            : $puntajeService->puntajeObtenidoExamenSoloItems($examen, $estudiante);

        $scopeMax = $isEvaluator ? $totales['scope_max'] : $totales['examen_max'];
        $porcentaje = $scopeMax > 0 ? round(($puntajeObtenido / $scopeMax) * 100, 2) : 0;
        $scopeLabel = $isEvaluator ? 'Puntaje de los casos' : 'Puntaje máximo';
    @endphp

    <section class="page-break">
        {{-- ------ Cabecera estudiante ------ --}}
        <div class="card grid2">
            <div>
            @if ($isEvaluator)
                <h2 class="mb-1"> Pseudónimo: {{ $pseudonimo ?? '—' }}</h2>
            @else
                <h2 class="mb-1">{{ ($estudiante->apellido ?? '—') }}, {{ ($estudiante->nombre ?? '—') }}</h2>
                <small>
                Pseudónimo: {{ $pseudonimo ?? '—' }}<br>
                </small>
            @endif
            </div>
            <div class="text-center">
                <h3 class="mb-1">{{ $puntajeObtenido }} / {{ $scopeMax }} pts</h3>
                <div class="bar mb-1"><div class="bar-inner" style="width: {{ $pctAluno = $porcentaje }}%;"></div></div>
                <small>{{ $porcentaje }} % · <span class="tag-scope">{{ $scopeLabel }}</span></small>
            </div>
        </div>

        <div class="divider"></div>

        {{-- ------ Casos del estudiante ------ --}}
        @foreach ($casos as $idxCaso => $caso)
            @php
                $maxCaso = $puntajeService->maxCaso($caso);
                $obtCaso = $puntajeService->puntajeObtenidoCasoSoloItems($caso, $estudiante);
                $eval    = $caso->evaluador ?? null;
                $evalStr = ($eval?->apellido || $eval?->nombre)
                    ? trim(($eval->apellido ?? '').', '.($eval->nombre ?? ''), ', ')
                    : '—';
            @endphp

            <h2 class="case-title">Caso {{ $idxCaso + 1 }} — {{ $caso->titulo ?? 'Sin título' }}</h2>
            <div class="title-meta"><span class="badge-section">{{ $obtCaso }} / {{ $maxCaso }} pts</span></div>


            @if (!$isEvaluator)
                <p class="mb-2">Evaluador: {{ $evalStr }}</p>
            @endif

            <div class="divider"></div>

            {{-- ------ Preguntas del caso ------ --}}
            @foreach (($caso->preguntas ?? collect())->sortBy('orden_relativo')->values() as $idxPreg => $preg)
                @php
                    $maxPreg     = $puntajeService->maxPregunta($preg);
                    $obtPreg     = $puntajeService->puntajeObtenidoPregunta($preg, $estudiante);
                    $secciones   = $preg->items->map->seccion->unique('id')->sortBy('orden_relativo')->values();

                    $consigna      = (string) ($preg->consigna ?? '');
                    $esInformativa = trim($consigna) === '';

                    // Solo buscamos respuesta si NO es informativa
                    $respuesta = null;
                    if (! $esInformativa) {
                        $respuesta = \App\Models\RespuestaEstudiante::where('estudiante_id', $estudiante->id)
                                    ->where('pregunta_id', $preg->id)
                                    ->value('respuesta');
                    }
                @endphp

                @continue($esInformativa)   {{-- ocultar preguntas informativas del reporte --}}

                <h3 class="question-title">Pregunta {{ $idxPreg + 1 }}</h3>
                <div class="title-meta"><span class="badge-section">{{ $obtPreg }} / {{ $maxPreg }} pts</span></div>

                @if (trim($consigna) !== '')
                    <p class="consigna mb-2">{!! $escapeMultiline($consigna) !!}</p>
                @endif

                <div class="mt-2"><strong>Respuesta del estudiante:</strong></div>
                @if (trim((string)($respuesta ?? '')) !== '')
                    <div class="respuesta-estudiante">{!! $escapeMultiline($respuesta) !!}</div>
                @else
                    <div class="respuesta-estudiante">Sin respuesta</div>
                @endif

                <div class="divider"></div>

                {{-- Ítems agrupados por sección --}}
                @foreach ($secciones as $sec)
                    @php
                        $itemsSec   = $preg->items->where('seccion_id', $sec->id);
                        $maxSP      = $puntajeService->maxPreguntaSeccion($preg, $sec);
                        $obtSP      = $puntajeService->puntajeObtenidoPreguntaSeccion($preg, $sec, $estudiante);
                        $notasKeyed = $estudiante->notasItems->keyBy('item_id');
                    @endphp

                    <h4 class="section-title">{{ $sec->nombre }}</h4>
                    <div class="title-meta"><span class="badge-section">{{ $obtSP }} / {{ $maxSP }} pts</span></div>

                    <table class="items-table mb-2">
                        <thead>
                            <tr>
                                <th class="w-60">Ítem</th>
                                <th class="w-20 text-center">Valor</th>
                                <th class="w-20 text-center">Corrección</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($itemsSec as $it)
                                @php
                                    $nota  = $notasKeyed->get($it->id);
                                    $pass  = (bool) ($nota->aprobado ?? false);
                                @endphp
                                <tr class="{{ $pass ? 'row-pass' : 'row-fail' }}">
                                    <td>{{ $it->descripcion }}</td>
                                    <td class="text-center">{{ $it->valor }}</td>
                                    <td class="text-center">
                                        @if ($pass)
                                            <span class="ic ic-pass">✔</span>
                                        @else
                                            <span class="ic ic-fail">✖</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach

                    <div class="divider"></div>
            @endforeach
        @endforeach
    </section>
@endforeach
</body>
</html>
