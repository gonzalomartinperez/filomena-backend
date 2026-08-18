<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $examen->nombre ?? ($examen['nombre'] ?? 'Examen') }} - Estructura</title>
    <style>
        /* -------------------- Variables & base -------------------- */
        @page { margin: 20mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: "Inter", DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; line-height: 1.45; }
        h1, h2, h3, h4, h5 { margin: 0; font-weight: 600; }

        :root {
            --primary: #0284c7;
            --primary-light: #e0f2fe;
            --border: #cbd5e1;
            --surface: #f8fafc;
        }

        /* -------------------- Helpers -------------------- */
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .mb-1 { margin-bottom: 6px; }
        .mb-2 { margin-bottom: 12px; }
        .mb-3 { margin-bottom: 20px; }

        /* ----- Page breaks seguros para PDF/impresión ----- */
        .page-break{
            page-break-after: always;
            break-after: page;
        }

        /* -------------------- Chips & badges -------------------- */
        .badge-section{
            display:inline-block;
            background:var(--primary-light);
            margin-top: 2px;
            margin-bottom: 4px;
            border-left:4px solid var(--primary);
            padding:1px 6px;
            font-size:9px;
            font-weight:600;
            color:#0f172a;
            line-height: 1.2;
        }
        .title-meta{ margin-top:4px; }
        .divider { border-bottom: 1px solid var(--border); margin: 8px 0; }

        /* -------------------- Tables -------------------- */
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 5px 6px; border: 1px solid var(--border); font-size: 10px; }
        th { background: var(--surface); font-weight: 600; }

        /* -------------------- Layout & sections -------------------- */
        .section-heading {
            font-size: 12px; color: #0f172a;
            background: var(--primary-light);
            padding: 6px 8px; border: 1px solid var(--border); border-radius: 8px;
            border-left: 4px solid var(--primary); margin-bottom: 6px;
        }

        .case-title { font-size: 14px; margin: 18px 0 2px; }
        .question-block { margin-bottom: 16px; }
        .question-title { font-size: 13px; margin: 8px 0 2px; color: #0f172a; }
        .question-consigna{
            font-size: 12px;
            font-weight: 700;
            padding: 4px 6px;
            background: #fff7ed;
            border-left: 4px solid #fb923c;
            color:#0f172a;
            margin: 2px 0 8px 0;
            display: inline-block;
            text-align: justify;
        }
        .question-descripcion{
            font-size: 11px;
            color:#0f172a;
            text-align: justify;
            line-height: 1.6;
            margin: 2px 0 10px 0;
            text-justify: inter-word;
            hyphens: auto;
            -ms-hyphens: auto;
            -webkit-hyphens: auto;
        }
        .section-subtitle { font-size: 11px; font-weight: 700; margin: 6px 0 2px; }

        /* ---------- Portada ---------- */
        .cover { height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .cover-logo  { font-size: 46px; font-weight: 700; letter-spacing: 1px; color: #0ea5e9; margin-bottom: 8px; }
        .exam-title  { font-size: 20px; font-weight: 700; color: #0f172a; }
        .accent-line { width: 60px; height: 3px; background: #0ea5e9; margin: 16px auto; }
        .stats       { font-size: 12px; color: #64748b; margin: 0; margin-top: 4px; }

        /* ====== Media: imágenes de la pregunta, una debajo de la otra ====== */
        .q-media-stack { margin-top: 6px; }
        .q-media-figure {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px;
            margin: 6px 0 0;
            background: #fff;
            page-break-inside: avoid;
        }
        .q-media-img {
            display: block;
            width: 100%;
            height: auto;
            max-height: 240px;           /* ajustable: 200–300px según tu gusto */
            object-fit: contain;         /* no recorta; encaja completa */
        }
        .q-media-cap {
            font-size: 9px;
            color: var(--muted);
            margin-top: 4px;
        }

    </style>
</head>
<body>
@php
/** Identidad */
$nombre = $examen->nombre ?? ($examen['nombre'] ?? '');
$fecha  = optional($examen->fecha ?? null)->format('Y-m-d');
$codigo = $examen->codigo ?? ($examen->codigo ?? null);

/** Colecciones ordenadas (estándar) */
$casos     = ($examen->casos ?? collect())->sortBy('orden_relativo')->values();
$secciones = ($examen->secciones ?? collect())->sortBy('orden_relativo')->values();

/** Stats globales (estándar) */
$stats = [
    'casos'      => $casos->count(),
    'secciones'  => $secciones->count(),
    'preguntas'  => $casos->flatMap->preguntas?->count() ?? 0,
    'items'      => $casos->flatMap->preguntas?->flatMap->items?->count() ?? 0,
];

/** Totales globales (estándar) */
$totales = [
    'examen_max' => $puntajeService->maxExamen($examen),
];

/** Auxiliar: stats de secciones */
$secciones_stats = $secciones->map(function ($sec) use ($puntajeService) {
    return [
        'id'         => $sec->id,
        'nombre'     => $sec->nombre,
        'item_count' => $sec->items?->count() ?? 0,
        'max'        => $puntajeService->maxSeccion($sec),
    ];
});

/** Escape multilínea seguro */
$escapeMultiline = fn($texto) => nl2br(e(trim((string)$texto)));
@endphp

{{-- ------------------------- PORTADA ------------------------- --}}
<section class="cover">
    <h1 class="cover-logo">Filomena</h1>
    <h2 class="exam-title">{{ $nombre ?: 'Examen' }} - Estructura</h2>
    <div class="accent-line"></div>

    <p class="stats">Fecha: {{ optional($examen->fecha ?? null)?->format('d/m/Y') ?? '—' }}</p>
    <p class="stats">Código: {{ $codigo ?? '—' }}</p>
    <p class="stats">Casos: {{ $stats['casos'] }} · Secciones: {{ $stats['secciones'] }} · Preguntas: {{ $stats['preguntas'] }} · Ítems: {{ $stats['items'] }}</p>
    <p class="stats">Puntaje máximo: {{ $totales['examen_max'] }}</p>
</section>

{{-- ------------------------- RESUMEN POR SECCIÓN ------------------------- --}}
<h3 class="section-heading">Resumen por sección</h3>
<table class="mb-2">
    <thead>
        <tr>
            <th class="text-left">Sección</th>
            <th class="text-center">Ítems</th>
            <th class="text-center">Máx.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($secciones_stats as $sec)
            <tr>
                <td class="text-left">{{ $sec['nombre'] }}</td>
                <td class="text-center">{{ $sec['item_count'] }}</td>
                <td class="text-center">{{ $sec['max'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="divider"></div>
<div class="page-break"></div>


{{-- ------------------------- DETALLE POR CASO → PREGUNTA → SECCIÓN → ÍTEMS ------------------------- --}}
@foreach ($casos as $idxCaso => $caso)
    @php
        $maxCaso        = $puntajeService->maxCaso($caso);
        $countItemsCaso = ($caso->preguntas ?? collect())->flatMap->items->count();
        $eval           = $caso->evaluador ?? null;
        $evalApe        = $eval->apellido ?? null;
        $evalNom        = $eval->nombre ?? null;
        $evalDisplay    = ($evalApe || $evalNom)
            ? trim(($evalApe ?? '') . ', ' . ($evalNom ?? ''), ', ')
            : '—';

        $preguntas = ($caso->preguntas ?? collect())->sortBy('orden_relativo')->values();
        $cantPreguntas = $caso->preguntas()->count();
    @endphp

    {{-- Caso --}}
    <h2 class="case-title">Caso {{ $idxCaso + 1 }} — {{ $caso->titulo }}</h2>
    <div class="title-meta"><span class="badge-section">{{ $countItemsCaso }} ítems · {{ $maxCaso }} pts</span></div>
    <div class="divider"></div>
    <p class="mb-1">Evaluador: {{ $evalDisplay }}</p>
    <p class="mb-4">Cantidad de preguntas: {{ $cantPreguntas }}</p>
    <div class="divider"></div>

    {{-- Preguntas del caso --}}
    @foreach ($preguntas as $idxPreg => $preg)
        @php
            $maxPregunta       = $puntajeService->maxPregunta($preg);
            $seccionesPregunta = $preg->items->map->seccion->unique('id')->sortBy('orden_relativo')->values();

            $consigna    = $preg->consigna ?? '';
            $descripcion = $preg->descripcion ?? null;
        @endphp

        <div class="question-block">
            {{-- Título de pregunta --}}
            <h3 class="question-title">Pregunta {{ $idxPreg + 1 }}</h3>
            <div class="title-meta"><span class="badge-section">{{ $preg->items->count() }} ítems · {{ $maxPregunta }} pts</span></div>

            {{-- Descripción --}}
            @if(trim((string)$descripcion) !== '')
                <p class="question-descripcion">{!! $escapeMultiline($descripcion) !!}</p>
            @endif

            {{-- Consigna --}}
            @if(trim((string)$consigna) !== '')
                <p class="question-consigna">{!! $escapeMultiline($consigna) !!}</p>
            @endif

            @php
                /** @var \Illuminate\Support\Collection $imagenes */
                $imagenes = $preg->getMedia(\App\Enums\Colecciones::ARCHIVOS->value)
                    ->filter(fn($m) => str_starts_with($m->mime_type, 'image/'));
            @endphp

            @if($imagenes->isNotEmpty())
                <div class="q-media-stack">
                    @foreach($imagenes as $img)
                        <figure class="q-media-figure">
                            <img
                                class="q-media-img"
                                src="{{ $img->getFullUrl() }}"
                                alt="Imagen de la pregunta {{ $preg->orden_relativo ?? ($idxPreg + 1) }}"
                            >
                            @if(!empty($img->name))
                                <figcaption class="q-media-cap">{{ $img->name }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endif

            <div class="divider"></div>

            {{-- Ítems por Sección --}}
            @foreach ($seccionesPregunta as $sec)
                @php
                    $itemsSeccion = $preg->items->where('seccion_id', $sec->id);
                    $maxSecPreg   = $puntajeService->maxPreguntaSeccion($preg, $sec);
                @endphp

                {{-- Sección --}}
                <h4 class="section-subtitle">{{ $sec->nombre }}</h4>
                <div class="title-meta"><span class="badge-section">{{ $itemsSeccion->count() }} ítems · {{ $maxSecPreg }} pts</span></div>

                <table class="mb-2">
                    <thead>
                        <tr>
                            <th class="text-left">Ítem</th>
                            <th class="text-center">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itemsSeccion as $it)
                            <tr>
                                <td class="text-left">{{ $it->descripcion }}</td>
                                <td class="text-center">{{ $it->valor }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
        @if(!$loop->last)
            <div class="divider"></div>
            <div class="page-break"></div>
        @endif
    @endforeach
@endforeach
</body>
</html>
