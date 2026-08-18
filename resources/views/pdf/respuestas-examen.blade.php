<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ ($examen->nombre ?? 'Examen') . ' - Respuestas del estudiante' }}</title>
    <style>
        /* =================== Base & tokens =================== */
        @page { margin: 20mm 18mm 22mm 18mm; }
        * { box-sizing: border-box; }
        html, body { color:#0f172a; font-family:"Inter","DejaVu Sans",sans-serif; font-size:10.5px; line-height:1.55; }
        h1,h2,h3{ margin:0; font-weight:700; letter-spacing:.2px; }
        .muted{ color:#475569; }

        :root{
            --ink:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --soft:#f8fafc;

            --brand:#0ea5e9;     /* sky-500 */
            --brand-ink:#0369a1; /* sky-700 */
            --brand-50:#f0f9ff;  /* sky-50 */
            --brand-100:#e0f2fe; /* sky-100 */

            --teal:#14b8a6;
            --teal-ink:#0f766e;
            --teal-50:#f0fdfa;

            --emerald:#10b981;
            --emerald-ink:#065f46;
            --emerald-50:#ecfdf5;
        }

        /* ===== Helpers (solo los usados) */
        .mb-4{ margin-bottom:16px; }
        .px-3{ padding-left:12px; padding-right:12px; }
        .py-2{ padding-top:8px; padding-bottom:8px; }
        .rounded{ border-radius:12px; }
        .border{ border:1px solid var(--border); }
        .avoid-break{ page-break-inside: avoid; }
        .content{ white-space:pre-wrap; word-break:break-word; }
        .hr-soft{ height:1px; background:var(--border); border:0; margin:16px 0; }

        /* ===== Guardia anti “página 1 en blanco” */
        body > *:first-child{ page-break-before:auto !important; break-before:auto !important; }

        /* ===== Portada (centrada como Estructura) */
        .cover{
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .cover-logo  { font-size:46px; font-weight:800; letter-spacing:1px; color:var(--brand); margin-bottom:8px; }
        .exam-title  { font-size:20px; font-weight:800; color:var(--ink); }
        .accent-line { width:60px; height:3px; background:var(--brand); margin:14px auto; border-radius:2px; }
        .stats       { font-size:12px; color:#64748b; margin:4px 0; }

        /* ===== Encabezados de contenido */
        .section-heading{
            font-size:12px; font-weight:800; color:var(--brand-ink);
            background:linear-gradient(90deg,var(--brand-50),#ffffff);
            border:1px solid var(--border);
            padding:8px 10px; border-left:4px solid var(--brand); border-radius:10px;
            margin-bottom:12px;
        }
        .case-title{
            font-size:13px; font-weight:800; color:var(--ink);
            margin:4px 0 10px;
        }

        /* ===== Card de pregunta (tricolor) */
        .question{
            background:#fff;
            border:1px solid var(--border);
            border-radius:14px;
            padding:12px 12px;
            margin-bottom:12px;
            box-shadow:0 1px 0 rgba(15,23,42,.03), 0 1px 2px rgba(15,23,42,.04);
        }
        .q-header{ display:flex; align-items:baseline; gap:8px; margin-bottom:8px; }
        .q-num{ font-weight:800; color:var(--ink); }
        .chip{
            display:inline-block; padding:2px 8px; font-size:10px; font-weight:700; border-radius:999px;
            background:var(--emerald-50); color:var(--emerald-ink); border:1px solid #d1fae5;
        }

        .q-top{
            background: var(--brand-50);
            border: 1px solid var(--brand-100);
            border-radius:10px;
            padding:10px 12px;
        }
        .q-top .q-desc{ color:#0f172a; font-style:italic; margin-top:4px; }

        .q-consigna{
            background: #f7fbff;
            border: 1px solid var(--brand-50);
            border-radius:10px;
            padding:10px 12px;
            margin-top:10px;
        }

        .q-respuesta{
            background:#fff;
            border: 1px solid var(--border);
            border-radius:10px;
            padding:10px 12px;
            margin-top:10px;
        }

        /* ===== Justificar bloques de texto largos ===== */
        .q-desc,
        .q-respuesta .content{
            text-align: justify;
            text-justify: inter-word;
            hyphens: auto;            /* cortes de palabra suaves al imprimir */
        }
        .q-consigna .content{
            text-align: justify;
            text-justify: inter-word;
            hyphens: auto;
        }

        .label{
            font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.35px; color:var(--muted);
            margin-bottom:4px;
        }

        /* ===== Etiquetas destacadas: "Consigna" y "Respuesta" ===== */
        .q-consigna .label,
        .q-consigna > .title:first-child,
        .q-consigna > strong:first-child,
        .q-respuesta .label,
        .q-respuesta > .title:first-child,
        .q-respuesta > strong:first-child,
        .consigna-label,
        .respuesta-label{
            font-weight: 700;
            letter-spacing: .02em;
        }

        .q-consigna .label,
        .q-respuesta .label,
        .consigna-label,
        .respuesta-label{
            margin-right: .25rem;
        }

        /* ===== Separación/Salto entre casos ===== */
        .case-gap{ height:8mm; }
        @media print{
            .question{
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .q-media-figure{
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
        @media print{
            p{ orphans:3; widows:3; }
        }
        @media print{
            img{ max-width:100%; height:auto; }
        }

        /* ====== Media: imágenes de la pregunta, una debajo de la otra ====== */
        .q-media-stack { margin-top: 6px; }
        .q-media-figure {
            border: 1px solid var(--soft);
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
            max-height: 240px;           /* ajustable */
            object-fit: contain;
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
    /**
     * Reporte: Respuestas de examen (PDF)
     * Entradas esperadas:
     *  - $examen   (con propiedad id y nombre)
     *  - $estudianteId (int)
     */

    use App\Models\Examen;
    use App\Models\User;
    use App\Models\RespuestaEstudiante;

    // Cargar examen con casos y preguntas necesarias (sin secciones, no se usan en este reporte)
    $examenId = $examen->id;

    $examen = Examen::query()
        ->with([
            'casos' => fn($q) => $q
                ->select('id','examen_id','titulo','orden_relativo')
                ->orderBy('orden_relativo'),
            'casos.preguntas' => fn($q) => $q
                ->select('id','caso_id','orden_relativo','descripcion','consigna')
                ->orderBy('orden_relativo'),
        ])
        ->findOrFail($examenId);

    $estudiante = User::findOrFail($estudianteId);

    $casos = $examen->casos ?? collect();

    // IDs de preguntas para mapear respuestas del estudiante
    $preguntaIds = $casos->flatMap->preguntas->pluck('id')->filter()->values();

    $respuestasPorPreguntaId = RespuestaEstudiante::query()
        ->where('estudiante_id', $estudiante->id)
        ->whereIn('pregunta_id', $preguntaIds)
        ->get()
        ->keyBy('pregunta_id');

    // Métricas de portada
    $cantidadCasos = $casos->count();
    $cantidadPreguntas = $casos->flatMap->preguntas->count();

    // Nombre de estudiante “Apellido, Nombre” con fallback a name
    $apellido = trim((string)($estudiante->apellido ?? ''));
    $nombre   = trim((string)($estudiante->nombre ?? ''));
    $nombreEstudiante = ($apellido !== '' || $nombre !== '')
        ? trim($apellido . ', ' . $nombre, ' ,')
        : (string)($estudiante->name ?? 'Estudiante');

    $fechaPortada = now()->format('d/m/Y');

    // Formatea texto multilínea con escape seguro
    $escapeMultiline = fn($texto) => nl2br(e(trim((string)$texto)));
@endphp

{{-- =================== PORTADA =================== --}}
<section class="cover">
    <h1 class="cover-logo">Filomena</h1>
    <h2 class="exam-title">{{ $examen->nombre ?? 'Examen' }} - Respuestas de examen</h2>
    <div class="accent-line"></div>
    <p class="stats">Fecha: {{ $fechaPortada }}</p>
    <p class="stats">Estudiante: {{ $nombreEstudiante }}</p>
    <p class="stats">Casos: {{ $cantidadCasos }} · Preguntas: {{ $cantidadPreguntas }}</p>
</section>

{{-- =================== CONTENIDO =================== --}}
@if($casos->isEmpty())
    <div class="soft border rounded px-3 py-2">
        <span class="muted">No hay preguntas disponibles para este examen.</span>
    </div>
@else
    <h3 class="section-heading">Respuestas por caso</h3>

    @foreach ($casos as $indiceCaso => $caso)
        @if($indiceCaso > 0)
            <hr class="hr-soft"> {{-- divisor sutil entre casos --}}
        @endif

        <h2 class="case-title">
            Caso {{ $caso->orden_relativo ?? ($indiceCaso + 1) }} — {{ $caso->titulo }}
        </h2>

        @php $preguntas = ($caso->preguntas ?? collect()); @endphp

        @if($preguntas->isEmpty())
            <div class="soft border rounded px-3 py-2 mb-4">Este caso no contiene preguntas.</div>
        @endif

        @foreach ($preguntas as $indicePregunta => $pregunta)
            @php
                $consigna      = (string) ($pregunta->consigna ?? '');
                $descripcion   = (string) ($pregunta->descripcion ?? '');
                $preguntaId    = $pregunta->id ?? null;
                $respuesta     = $preguntaId && isset($respuestasPorPreguntaId[$preguntaId])
                                    ? (string) ($respuestasPorPreguntaId[$preguntaId]->respuesta ?? '')
                                    : '';
                $esInformativa = (trim($consigna) === '');
            @endphp

            <article class="question avoid-break">
                <div class="q-header">
                    <div class="q-num">Pregunta {{ $pregunta->orden_relativo ?? ($indicePregunta + 1) }}</div>
                    @if ($esInformativa)
                        <span class="chip">Informativa</span>
                    @endif
                </div>

                {{-- Bloque 1: Pregunta + descripción (azul suave) --}}
                <div class="q-top">
                    @if(trim($descripcion) !== '')
                        <div class="q-desc">{!! $escapeMultiline($descripcion) !!}</div>
                    @else
                        <div class="q-desc muted">—</div>
                    @endif
                </div>

                @php
                    /** @var \Illuminate\Support\Collection $imagenes */
                    $imagenes = $pregunta->getMedia(\App\Enums\Colecciones::ARCHIVOS->value)
                        ->filter(fn($m) => str_starts_with($m->mime_type, 'image/'));
                @endphp

                @if($imagenes->isNotEmpty())
                    <div class="q-media-stack">
                        @foreach($imagenes as $img)
                            <figure class="q-media-figure">
                                <img
                                    class="q-media-img"
                                    src="{{ $img->getFullUrl() }}"
                                    alt="Imagen de la pregunta {{ $pregunta->orden_relativo ?? ($indicePregunta + 1) }}"
                                >
                                @if(!empty($img->name))
                                    <figcaption class="q-media-cap">{{ $img->name }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif

                {{-- Si no es informativa, mostrar consigna + respuesta --}}
                @unless($esInformativa)
                    @if(trim($consigna) !== '')
                        {{-- Bloque 2: Consigna (celeste claro) --}}
                        <div class="q-consigna">
                            <strong class="consigna-label">Consigna:</strong>
                            <div class="content">{!! $escapeMultiline($consigna) !!}</div>
                        </div>
                    @endif

                    {{-- Bloque 3: Respuesta (blanco) --}}
                    <div class="q-respuesta">
                        <strong class="respuesta-label">Respuesta:</strong>
                        <div class="content">{!! $respuesta !== '' ? $escapeMultiline($respuesta) : '—' !!}</div>
                    </div>
                @endunless
            </article>
        @endforeach

        {{-- Salto de página entre casos (evita salto extra en el último) --}}
        @if (!$loop->last)
            <div class="case-gap"></div>
        @endif
    @endforeach
@endif
</body>
</html>
